<?php

namespace ch\fugu\oledata;

/**
 * Class OleExport
 * @author fugu GmbH (www.fugu.ch)
 */
class OleExport {

    /**
     * @var int
     */
    protected $_pageNbr;
    /**
     * @var int
     */
    protected $_nbrOfPages;

    /**
     * @var OleExportMeta
     */
    protected $_metaElement;

    public function __construct() {
        $this->_metaElement = new OleExportMeta();
    }

    /**
     * Autoload driver
     */
    public static function autoload() {
        if(!self::$_autoloading) {
            self::$_autoloading = true;
            spl_autoload_register(function ($class) {
                //echo 'class: '.$class."\n";
                if(strpos($class,'ch\\fugu\\oledata\\')===0) {
                    $file = __DIR__ . '/' . str_replace('\\', '/', str_replace('ch\\fugu\\oledata\\','',$class)) . '.php';
                    //echo $file."\n";
                    if (file_exists($file)) {
                        //echo 'load: '.$file."\n";
                        require($file);
                        return true;
                    }
                }
                return false;
            });
        }
    }
    protected static $_autoloading = false;

    /**
     * @param $path
     * @return array|bool
     */
    public static function getConfig($path){
        $config = @parse_ini_file($path, true);
        $configEnv = empty($config['environment']) ? '' : $config['environment'];
        $dbConfig = empty($config['db'.($configEnv?'_'.$configEnv:'')]) ? [] : $config['db'.($configEnv?'_'.$configEnv:'')];
        $dbConfig = array_merge(array('username'=>null,'password'=>null,'name'=>null, 'hostname'=>'localhost', 'port'=>3306, 'charset'=>'utf8'),$dbConfig);
        $config['db'] = $dbConfig;
        return $config;
    }

    /**
     * Sets if the script supports
     * @param bool $flag
     */
    public function setChangedSinceSupported($flag){
        $this->_metaElement->setValue('changedsince_supported',$flag?'true':'false');
    }

    /**
     * Send checksourceids xml response
     * @param $deleteIds
     */
    public function sendCheckedSourceIds($deleteIds){
        $this->_flushHeader();
        echo '<ole>' . "\n";
        echo '<checksourceids>' . "\n";
        echo '<delete><![CDATA[';
        echo implode(',', array_unique($deleteIds));
        echo ']]></delete>' . "\n";
        echo '</checksourceids>' . "\n";
        echo '</ole>' . "\n";
    }

    /**
     * @param int $pageNbr
     * @param int $nbrOfPages
     */
    public function setPagingMeta($pageNbr,$nbrOfPages){
        $this->_pageNbr = $pageNbr;
        $this->_nbrOfPages = $nbrOfPages;
        $this->_metaElement->setValue('max_pages',$this->_nbrOfPages);
        if ($this->_pageNbr < $this->_nbrOfPages) {
            $this->_metaElement->setValue('next_url',$this->getScriptUrl(array('page'=>($this->_pageNbr+1))));
        }
    }

    /**
     * @param string $sourceVersion
     */
    public function setSourceVersion($sourceVersion){
        if(!empty($sourceVersion)) {
            $this->_metaElement->setValue('source_version', $sourceVersion);
        }
    }

    /**
     * Flush http and xml header
     */
    protected function _flushHeader(){
        if(!headers_sent()) {
            header('Cache-Control: no-cache, must-revalidate');
            header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
            header('Content-Type: text/xml; charset=utf-8');
        }
        echo '<?xml version="1.0" encoding="utf-8"?>'."\n";
        echo '<!--' . "\n". 'This XML interface is licensed under a Creative Commons 4.0 BY-SA license, https://www.hinto.ch/olelicense.html' . "\n" . '-->' . "\n";
    }

    public function flushOpen(){
        $this->_flushHeader();
        echo '<ole>'."\n";
        $this->_metaElement->flushOpen();
        $this->_metaElement->flushClose();
        echo '<events>'."\n";
    }

    public function flushClose(){
        echo '</events>'."\n";
        echo '</ole>'."\n";
    }

    /**
     * Returns script/server url (used for paging)
     * @return string
     */
    public function getServerUrl(){
        $url = '';
        if(!empty($_SERVER['HTTP_HOST'])) {
            $scheme = 'http';
            $port = empty($_SERVER['SERVER_PORT']) ? 80 : intval($_SERVER['SERVER_PORT']);
            if((isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === true)) ||
                (isset($_SERVER['HTTP_SCHEME']) && ($_SERVER['HTTP_SCHEME'] === 'https')) ||
                (isset($_SERVER['SERVER_PORT']) && ($_SERVER['SERVER_PORT'] == 443))) {
                    $scheme = 'https';
            }
            if(($scheme==='http' && $port===80) || ($scheme==='https' && $port===443)){
                $port = null;
            }
            $url = $scheme.'://' . $_SERVER['HTTP_HOST'] . ($port ? ':'.$port : '');
        }
        return $url;
    }

    /**
     * Creates next url
     * @param array|null $newQuery
     * @return string
     */
    public function getScriptUrl($newQuery=null){
        $url = $this->getServerUrl();
        if($url){
            $purl = parse_url(!empty($_SERVER['REQUEST_URI'])?$_SERVER['REQUEST_URI']:'');
            if(!empty($purl['path'])){
                $url .= $purl['path'];
            }
            $query = [];
            if(!empty($purl['query'])){
                parse_str($purl['query'],$query);
            }
            if($newQuery){
                if(empty($query)){
                    $query = $newQuery;
                }
                else {
                    $query = $this->_mergeAttributes($query, $newQuery);
                }
            }
            if($query) {
                $url .= '?' . http_build_query($query);
            }
        }
        return $url;
    }

    /**
     * Merges arrays
     * @param array $a1
     * @param array|null $a2
     * @return array
     */
    protected function _mergeAttributes($a1,$a2=null){
        if(empty($a1) || empty($a2)){
            return $a1;
        }
        foreach($a2 as $k=>$v){
            if(!isset($ar[$k])){
                $a1[$k] = $v;
            }
        }
        return $a1;
    }
}
class OleExportData {

    public static $sanitizeValues = true;

    protected $_elementName;
    protected $_elementValue;
    protected $_attributes = [];
    protected $_values = [];
    /**
     * @var OleExportData[][]
     */
    protected $_children = [];

    public function __construct($_elementName,$elementValue=null){
        $this->_elementName = $_elementName;
        $this->_elementValue = $elementValue;
    }

    /**
     * Gets xml element name
     * @return string
     */
    public function getElementName(){
        return $this->_elementName;
    }

    /**
     * Sets xml element attribute
     *
     * @param string $name
     * @param string $value
     */
    public function setAttribute($name,$value){
        if(is_null($value)){
            unset($this->_attributes[$name]);
        }
        else {
            $this->_attributes[$name] = $value;
        }
    }

    /**
     * Sets child element
     *
     * @param string $name
     * @param string $value
     */
    public function setValue($name,$value){
        if(is_null($value)){
            unset($this->_values[$name]);
        }
        else {
            $this->_values[$name] = $value;
        }
    }

    /**
     * Adds a child element
     *
     * @param OleExportData $element
     * @param bool $allowMultiple
     */
    public function addChild(OleExportData $element,$allowMultiple=true){
        $elementName = $element->getElementName();
        if($allowMultiple) {
            $this->_children[$elementName][] = $element;
        }
        else {
            $this->_children[$element->getElementName()] = array($element);
        }
    }

    public function flushOpen(){
        echo '<'.$this->_elementName;
        foreach($this->_attributes as $name=>$value){
            echo ' '.$name.'="'.$value.'"';
        }
        echo '>';
        if(!empty($this->_elementValue)){
            echo '<![CDATA[';
            echo (self::$sanitizeValues?OleExportData::sanitizeValue($this->_elementValue):$this->_elementValue);
            echo ']]>';
        }
        else {
            echo "\n";
        }
        foreach($this->_values as $name=>$value){
            echo '<'.$name.'><![CDATA[';
            echo (self::$sanitizeValues?OleExportData::sanitizeValue($value):$value);
            echo ']]></'.$name.'>' . "\n";
        }
        foreach($this->_children as $elementName=>$childElements){
            foreach($childElements as $childElement) {
                $childElement->flushOpen();
                $childElement->flushClose();
            }
        }
    }

    public function flushClose(){
        echo '</'.$this->_elementName.'>'."\n";
        flush();
    }

    /**
     * Removes invalid utf8 chars
     *
     * @param $value
     * @return string|string[]|null
     */
    public static function sanitizeValue($value){
        //$value = preg_replace('/[\x00-\x09]?[\x0B-\x0C]?[\x0E-\x1F]?[\x7F]?/u','',$value);
        $value = preg_replace('/[\x00-\x09]?/u','',$value);
        return $value;
    }
}
class OleExportMeta extends OleExportData {
    public function __construct(){
        parent::__construct('meta');
    }
}
class OleExportEvent extends OleExportData {

    /**
     * @var OleExportData
     */
    protected $_containers;

    public function __construct($sourceId){
        parent::__construct('event');
        $this->setAttribute('source_id',$sourceId);
    }

    public function setLocation(OleExportLocation $location){
        $this->addChild($location,false);
    }

    public function addShow(OleExportShow $show){
        $this->_addContainer('shows',$show);
    }

    public function addFile(OleExportFile $file){
        $this->_addContainer('files',$file);
    }

    public function addCategory($category){
        $this->_addContainer('categories',new OleExportData('category',$category));
    }

    public function addTargetGroup($targetGroup){
        $this->_addContainer('targetgroups',new OleExportData('targetgroup',$targetGroup));
    }

    public function addLink($url){
        $this->_addContainer('links',new OleExportData('url',$url));
    }

    protected function _addContainer($containerName,$element){
        if(!isset($this->_containers[$containerName])) {
            $container = new OleExportData($containerName);
            $this->addChild($container);
            $this->_containers[$containerName] = $container;
        }
        $this->_containers[$containerName]->addChild($element);
    }

}
class OleExportLocation extends OleExportData {
    public function __construct(){
        parent::__construct('location');
    }
}
class OleExportShow extends OleExportData {

    public function __construct($sourceId){
        parent::__construct('show');
        $this->setAttribute('source_id',$sourceId);
    }

}
class OleExportFile extends OleExportData {
    public function __construct($type=null){
        parent::__construct(empty($type)?'file':$type);
    }
}
class OleExportImage extends OleExportFile {
    public function __construct(){
        parent::__construct('image');
    }
}
