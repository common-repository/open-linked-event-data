<?php

namespace ch\fugu\oledata\driver;

use ch\fugu\oledata\OleExport;
use ch\fugu\oledata\OleExportEvent;
use Exception;

/**
 * Ole abstract driver
 * @author fugu GmbH (www.fugu.ch)
 */
abstract class AbstractOleDriver {

    /**
     * @var []
     */
    protected $_config;

    /**
     * @var OleExport $_oleExport
     */
    protected $_oleExport;

    protected $_doDump = false;

    /**
     * @param []|null $config
     * @param []|null $dbConfig (username, password, name, hostname, port, charset)
     */
    public function init($config=null){
        $this->_config = $config;
        $this->_oleExport = new OleExport();
    }

    public function echoDump($txt){
        if($this->_doDump){
            echo "\n\n".$txt."\n\n";
        }
    }

    /**
     * Gets page GET-param
     *
     * @param null|int $maxPageNumber
     * @return int
     */
    protected function _getPageNumber($maxPageNumber=null){
        $pageNumber = empty($_GET['page']) ? 1 : intval($_GET['page']);
        if(!empty($pageNumber)) {
            $pageNumber = min($maxPageNumber, $pageNumber);
        }
        return max(1, $pageNumber);
    }

    /**
     * @param $nbrOfItems
     * @param int $pageSize
     * @return int
     */
    protected function _calculateNumberOfPages($nbrOfItems, $pageSize=10) {
        if (!empty($pageSize) && $pageSize > 0) {
            $numberOfPages = $nbrOfItems / $pageSize;
            $numberOfPages = ($nbrOfItems % $pageSize === 0) ? (int)$numberOfPages : (int)$numberOfPages + 1;
            return $numberOfPages;
        }
        return 1;
    }


    /**
     * Gets changedsince GET-param
     *
     * @return false|int|null
     */
    protected function _getChangedSince(){
        if(!empty($_GET['changedsince'])){
            return strtotime($_GET['changedsince']);
        }
        return null;
    }

    protected $_checkSourceIds = null;
    protected $_checkSourceDeleteIds = [];

    /**
     * Gets checksourceids POST-param
     *
     * @return string|null
     */
    protected function _getCheckSourceIds(){
        return !empty($_POST['checksourceids']) ? filter_var ($_POST['checksourceids'],FILTER_SANITIZE_STRING) : null;
    }

    /**
     * @throws Exception
     */
    public abstract function execute();

    /**
     * Called in execute for every row
     * @param OleExportEvent $event
     */
    protected function _finalizeRow(OleExportEvent $event){

    }
}

