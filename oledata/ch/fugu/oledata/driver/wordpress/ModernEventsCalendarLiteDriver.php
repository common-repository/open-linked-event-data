<?php

namespace ch\fugu\oledata\driver\wordpress;

use ch\fugu\oledata\OleExportEvent;
use ch\fugu\oledata\OleExportImage;
use ch\fugu\oledata\OleExportLocation;
use ch\fugu\oledata\OleExportShow;
use Exception;
use WordPressOleExportUtil;
use DateTime;
use MEC;

/**
 * Ole driver for Modern Events Calender (lite) (webnus.net/modern-events-calendar/)
 * @author fugu GmbH (www.fugu.ch)
 */
class ModernEventsCalendarLiteDriver extends AbstractWordPressOleDriver {

    public function getDisplayName(){
        return 'Modern Events Calender (lite)';
    }

    public function getActivePluginName(){
        return 'modern-events-calendar-lite/modern-events-calendar-lite.php';
    }

    public function getEventPostTypes(){
        return ['mec-events'];
    }

    /**
     * @throws Exception
     */
    public function execute() {
        //General sql constraint
        $sqlWhere = 'post_type=\'mec-events\' AND post_status IN (\'publish\') AND ID IN (SELECT post_id FROM '.$this->_tablePrefix.'mec_events)';

        if (WordPressOleExportUtil::getOption('post_checkbox') === 1) {
            $sqlWhere .= ' AND ID IN (SELECT post_id FROM '.$this->_tablePrefix.'postmeta WHERE meta_key = "_oleexport_post_enabled" AND meta_value = "1")';
        }

        //Handle and exit ?
        if($this->_handleCheckSourceIds(
        //Select shows
            'SELECT event_id FROM '.$this->_tablePrefix.'mec_events WHERE event_id IN (#IDS#) AND post_id IN (SELECT ID FROM '.$this->_tablePrefix.'posts WHERE '.$sqlWhere.')',
            //Define prefix for shows
            'events-'
        )){
            return;
        }

        //Changed since available, set to supported that the client may clean up after update with checksourceids
        $changedSinceTime = $this->_getChangedSince();
        if(!empty($changedSinceTime)){
            $this->_oleExport->setChangedSinceSupported(true);
            $sqlWhere .= ' AND post_modified_gmt>=\'' . gmdate('Y-m-d H:i:s', $changedSinceTime) . '\'';
        }

        //Select shows (all posts which exists in eo_events)
        $sql = 'SELECT ID FROM '.$this->_tablePrefix.'posts WHERE '.$sqlWhere;

        $paging = $this->_calculatePaging($sql,self::PAGE_SIZE);
        $pageNumber = $this->_getPageNumber($paging->pages);
        $this->_oleExport->setPagingMeta($pageNumber,$paging->pages);
        $this->_oleExport->flushOpen();

        $ids = [];
        foreach($this->fetchData($sql,'ID', false, $pageNumber, $paging->pageSize) as $row){
            $ids[] = $row->ID;
        }
        if($ids) {
            foreach(get_posts(array('post_type' => 'mec-events', 'numberposts' => -1, 'include' => $ids)) as $post) {
                $this->_executeRow(get_post($post));
            }
        }
        $this->_oleExport->flushClose();
    }

    /**
     * @param $row
     * @throws Exception
     */
    protected function _executeRow($row){
        $render = MEC::getInstance('app.libraries.render');
        $data = $render->data($row->ID);
//        error_log(print_r($data, true));

        $event = new OleExportEvent('posts-' . $row->ID);

        $timeStart = !empty($data->time['end']) ? $data->time['start'] : '';
        $timeEnd = $data->time['end'];

        foreach ($this->fetchData('SELECT * FROM ' . $this->_tablePrefix . 'mec_dates WHERE post_id=' . $row->ID) as $date) {
            $startDate = new DateTime($date->dstart.' '.$timeStart, $this->_dateTimeZone);
            $endDate = new DateTime($date->dend.' '.$timeEnd, $this->_dateTimeZone);

            $show = new OleExportShow('events-' . $date->id);
            $show->setValue('date_start', $startDate->format('c'));
            $show->setValue('date_end', $endDate->format('c'));
            $event->addShow($show);
        }

        $event->setValue('name', $row->post_title);
        $event->setValue('lead', $this->_stripWPTags($row->post_excerpt));
        $event->setValue('description', $this->_stripWPTags($row->post_content));

        if ($data->locations) {
            foreach($data->locations as $mecLocation) {
                $location = new OleExportLocation();
                $location->setValue('name', $mecLocation['name']);
                $location->setValue('locality', $mecLocation['address']);
                $event->setLocation($location);
            }
        }

        if($data->categories) {
            foreach ($data->categories as $category) {
                $event->addCategory($category['name']);
            }
        }

        $event->setValue('url', get_permalink($row->ID));

        $imageUrl = get_the_post_thumbnail_url($row->ID);
        if ($imageUrl) {
            $file = new OleExportImage();
            $file->setValue('src', $imageUrl);
            $event->addFile($file);
        }

        $this->_finalizeRow($event);

        $event->flushOpen();
        $event->flushClose();
    }
}
