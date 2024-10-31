<?php

use ch\fugu\oledata\driver\AbstractOleDriver;
use ch\fugu\oledata\OleExport;

/*
Plugin Name: OLE Export (Open Linked Event Data)
Plugin URI: https://www.hinto.ch
Description: OLE Export adds Open Linked Event Data Network support (<a href="https://www.hinto.ch/olelicense.html" target="_blank">https://www.hinto.ch/olelicense.html</a>) to multiple event management plugins. Check plugin settings!
Version: 0.97
Author: fugu GmbH (<a href="https://www.fugu.ch" target="_blank">www.fugu.ch</a>)
TextDomain: oleexport
License: GPLv3

Based on https://de-ch.wordpress.org/plugins/jsonfeed/

*/
class WordPressOleExport {

    public function __construct() {
        self::autoload();

        require(dirname(__FILE__) . '/oledata/ch/fugu/oledata/OleExport.php');
        OleExport::autoload();

        require(dirname(__FILE__) . '/admin/WordPressOleExportUtil.php');

        if (is_admin()) {
            require(dirname(__FILE__) . '/admin/WordPressOleExportAdmin.php');
            new WordPressOleExportAdmin();
        }

        add_action( 'plugins_loaded', array($this, 'init'));
        //add_action( 'wp_head', array($this, 'oleexport_link') );
    }

    /**
     * Autoload driver
     */
    public static function autoload() {

        if(!self::$_autoloading) {
            self::$_autoloading = true;
            spl_autoload_register(function ($class) {
                //echo 'class: '.$class."\n";
                    $file = __DIR__ . '/../../oleexport_customdriver/' . $class . '.php';
                    if (file_exists($file)) {
                        //echo 'load: '.$file."\n";
                        require($file);
                        return true;
                    }

                return false;
            });
        }
    }
    protected static $_autoloading = false;

    public function init() {
        load_plugin_textdomain('oleexport', FALSE, basename( dirname( __FILE__ ) ) . '/languages/');

        $feedActive = (bool)WordPressOleExportUtil::getOption('active');
        if ($feedActive || get_current_user_id() > 0) {
            add_action( 'init', array($this, 'oleexport_setup_feed'));
        }
    }

    public function oleexport_link() {
        //echo '<!--oleexport_link-->'."\n";
    }

    public function oleexport_setup_feed() {
        add_feed('ole', array($this, 'do_oleexport'));
    }

    public function do_oleexport( ) {
        $driverClassName = null;

        $driverOption = WordPressOleExportUtil::getOption('driver');
        if ($driverOption) {
            $driverClassName = $driverOption;
        }

        if($driverClassName){
            $driverClassName = str_replace('wordpress_','ch\\fugu\\oledata\\driver\\wordpress\\',$driverClassName);

            /**
             * @var AbstractOleDriver $driver
             */
            $driver = new $driverClassName();
            $driver->init();
            $driver->execute();
        }
    }

    public function oleexport_content_type( $content_type, $type ) {
        if ( 'ole' === $type ) {
            return 'text/xml';
        }
        return $content_type;
    }
}

new WordPressOleExport();

/*


function oleexport_links_extra( $args = array() ) {
    echo '<!--oleexport_links_extra-->'."\n";
}
add_filter( 'wp_head', 'oleexport_links_extra' );
*/

