<?php

namespace ch\fugu\oledata\driver\wordpress;

use ch\fugu\oledata\driver\AbstractSqlOleDriver;
use Exception;
use WordPressOleExportUtil;
use DateTimeZone;
use WP_User;
use wpdb;

/**
 * Ole abstract driver for WordPress
 * @author fugu GmbH  (www.fugu.ch)
 */
abstract class AbstractWordPressOleDriver extends AbstractSqlOleDriver {

    const PAGE_SIZE = 50;

    /**
     * @var wpdb $_wpdp
     */
    protected $_wpdb;

    /**
     * @var string
     */
    protected $_tablePrefix;

    /**
     * @var DateTimeZone
     */
    protected $_dateTimeZone;

    public function getDisplayName(){
        return get_class($this);
    }

    public function getActivePluginName(){
        return '';
    }

    public function getEventPostTypes(){
        return [];
    }

    public function init($config=null){
        parent::init($config);
        global $wpdb;
        $this->_wpdb = $wpdb;
        /**
         * @var WP_User $user
         */
        $user = wp_get_current_user();
        $this->_tablePrefix = $this->_wpdb->get_blog_prefix($user->get_site_id());
        $this->_oleExport->setSourceVersion(WordPressOleExportUtil::getOption('source_version'));
        try {
           $this->_dateTimeZone = new DateTimeZone(get_option('timezone_string'));
        } catch(Exception $e) {
        }
    }

    protected function _getCheckSourceIds(){
        return !empty($_POST['checksourceids']) ? sanitize_text_field ($_POST['checksourceids']) : null;
    }

    protected function _fetchData($sql){
        return $this->_wpdb->get_results($sql);
    }

    /**
     * @param $text
     * @return string
     */
    protected function _stripWPTags($text) {
        if(!empty($text)) {
            $text = str_replace('<!-- wp:paragraph -->', '', $text);
            $text = str_replace('<!-- /wp:paragraph -->', '', $text);
            $text = preg_replace('/\[([^\]]*)\].*\[\/\\1\]/', '', $text);
            $text = preg_replace('/\[[^\]]*\]/', '', $text);
        }
        return $text;
    }
}

