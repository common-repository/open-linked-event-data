<?php

namespace ch\fugu\oledata\driver;

use Exception;
use PDO;
use stdClass;

/**
 * Ole abstract driver for SQL databases (PDO, sql queries)
 * @author fugu GmbH (www.fugu.ch)
 */
abstract class AbstractSqlOleDriver extends AbstractOleDriver {

    /**
     * @var PDO
     */
    protected $_db;

    /**
     * @var stdClass
     */
    protected $_dbConfig;

    /**
     * @param []|null $config
     * @param []|null $dbConfig (username, password, name, hostname, port, charset)
     */
    public function init($config=null){
        parent::init($config);
        $this->_setDbConfig($config);
    }

    /**
     * @param $dbConfig
     */
    protected function _setDbConfig($config){
        if(isset($config['db'])) {
            $dbConfig = $config['db'];
            if (!isset($dbConfig['hostname'])) {
                $dbConfig['hostname'] = 'localhost';
            }
            if (!isset($dbConfig['port'])) {
                $dbConfig['port'] = 3306;
            }
            if (!isset($dbConfig['charset'])) {
                $dbConfig['charset'] = 'utf8';
            }
            $this->_dbConfig = (object)$dbConfig;
        }
    }

    /**
     * @return PDO
     * @throws Exception
     */
    public function getDb(){
        if(is_null($this->_db)){
            $driverOptions = array();
            if (isset($dbConfig->persistent) && $this->_dbConfig->persistent) {
                $driverOptions[PDO::ATTR_PERSISTENT] = true;
            }
            if (isset($dbConfig->timeout) && $this->_dbConfig->timeout > 0) {
                $driverOptions[PDO::ATTR_TIMEOUT] = $this->_dbConfig->timeout;
            }

            $options = $this->_getConnectionOptions();
            if (!is_null($options)) {
                $driverOptions += $options;
            }
            $connectionString = $this->getConnectionString();

            try {
                $this->_db = new PDO($connectionString, $this->_dbConfig->username, $this->_dbConfig->password, $driverOptions);
            } catch (Exception $e) {
                throw new Exception($e->getMessage(), $e->getCode());
            }
        }
        return $this->_db;
    }

    /**
     * Gets connection options
     *
     * @see Fuman_Db_Abstract::getConnectionString()
     * @return string
     */
    public function getConnectionString() {
        $s = 'mysql:host=' . $this->_dbConfig->hostname;
        if (isset($this->_dbConfig->port) && $this->_dbConfig->port) {
            $s .= ';port=' . $this->_dbConfig->port;
        }
        $s .= ';dbname=' . $this->_dbConfig->name;
        return $s;
    }

    /**
     * Returns connection options
     * @return array
     */
    protected function _getConnectionOptions() {
        $options = array();
        if (!empty($this->_dbConfig->charset)) {
            $options[PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES \'' . $this->_dbConfig->charset . '\'';
        }
        return $options;
    }

    /**
     * @param string $sql
     * @param string|null $orderColumn
     * @param bool $orderAsc
     * @param int $pageNumber
     * @param int $pageSize
     * @return object[]
     * @throws Exception
     */
    public function fetchData($sql, $orderColumn=null, $orderAsc=true, $pageNumber=1, $pageSize=0){
        return $this->_fetchData($this->_getFetchSql($sql,$orderColumn,$orderAsc,$pageNumber,$pageSize));
    }

    /**
     * @param $sql
     * @param null $orderColumn
     * @param bool $orderAsc
     * @param int $pageNumber
     * @param int $pageSize
     * @return string
     */
    protected function _getFetchSql($sql, $orderColumn=null, $orderAsc=true, $pageNumber=1, $pageSize=0){
        if(!empty($orderColumn)){
            $sql .= ' ORDER BY '.$orderColumn.' '.($orderAsc?'ASC':'DESC');
        }
        if($pageSize>0){
            $sql .= ' LIMIT '.((max($pageNumber,1)-1)*$pageSize).', '.$pageSize;
        }
        return $sql;
    }

    /**
     * @param string $sql
     * @return array
     * @throws Exception
     */
    protected function _fetchData($sql){
        $rows = [];
        $stmt = $this->getDb()->query($sql);
        if($stmt) {
            $rows = $stmt->fetchAll(PDO::FETCH_OBJ);
            $stmt->closeCursor();
        }
        return $rows;
    }

    /**
     * @param string $sql
     * @param int $pageSize
     * @return object ($obj->count, $obj->pages, $obj->pageSize)
     * @throws Exception
     */
    protected function _calculatePaging($sql, $pageSize=10){
        $rows = $this->fetchData('SELECT count(*) AS counter FROM ('.$sql.') x');
        $obj = (object)array('count'=>0,'pages'=>0,'pageSize'=>$pageSize);
        if(!empty($rows[0]->counter)){
            $counter = $rows[0]->counter;
            $obj->count = $counter;
            $obj->pages = $this->_calculateNumberOfPages($counter,$pageSize);
        }
        return $obj;
    }

    protected $_checkSourceIds = null;
    protected $_checkSourceDeleteIds = [];

    protected function _getCheckSourceIds(){
        return !empty($_POST['checksourceids']) ? filter_var ($_POST['checksourceids'],FILTER_SANITIZE_STRING) : null;
    }

    /**
     * @param string $sql (#IDS# will be replaced by appropriated list of ids; SELECT event_id FROM vqng_eo_events WHERE event_id IN (#IDS#))
     * @param string $idShowPrefix
     * @param bool $flush
     * @throws Exception
     * @return bool
     */
    protected function _handleCheckSourceIds($sql,$idShowPrefix='',$flush=true){
        if(is_null($this->_checkSourceIds)) {
            $checkSourceIds = $this->_getCheckSourceIds();
            if (!empty($checkSourceIds)) {
                $this->_checkSourceIds = explode(',', $checkSourceIds);
            }
        }

        if($this->_checkSourceIds){
            $showIds = array();
            foreach ($this->_checkSourceIds as $sourceId) {
                $id = $sourceId;
                if (!empty($idShowPrefix)) {
                    $id = preg_replace('/' . preg_quote($idShowPrefix) . '/i', '', $id);
                }

                if (is_numeric($id)) {
                    $showIds[] = intval($id);
                    while(($idx = array_search($sourceId, $this->_checkSourceIds)) !== false){
                        unset($this->_checkSourceIds[$idx]);
                    }
                }
            }

            if (count($showIds) > 0) {
                //Get existing shows
                $sql = str_replace('#IDS#', implode(',', $showIds), $sql);
                $ids = null;
                foreach ($this->fetchData($sql) as $row) {
                    $values = array_values((array)$row);
                    if(count($values)===1){
                        $ids[] = $values[0];
                    }
                }
                if (is_array($ids)) {
                    $diff = array_diff($showIds, $ids);
                    foreach ($diff as $id) {
                        $this->_checkSourceDeleteIds[] = $idShowPrefix . $id;
                    }
                }
            }

            if($flush){
                $this->_oleExport->sendCheckedSourceIds(array_merge($this->_checkSourceIds,$this->_checkSourceDeleteIds));
            }

            return true;
        }
        return false;
    }
}

