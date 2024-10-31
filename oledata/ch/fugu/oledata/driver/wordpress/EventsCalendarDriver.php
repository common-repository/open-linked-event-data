<?php

namespace ch\fugu\oledata\driver\wordpress;

use ch\fugu\oledata\OleExportEvent;
use ch\fugu\oledata\OleExportImage;
use ch\fugu\oledata\OleExportLocation;
use ch\fugu\oledata\OleExportShow;
use DateTime;
use Exception;
use WordPressOleExportUtil;

/**
 * Ole driver for theeventscalendar.com
 * @author fugu GmbH (www.fugu.ch)
 */
class EventsCalendarDriver extends AbstractWordPressOleDriver {

    public function getDisplayName(){
        return 'The Events Calendar';
    }

    public function getActivePluginName(){
        return 'the-events-calendar/the-events-calendar.php';
    }

    public function getEventPostTypes(){
        return ['tribe_events'];
    }

    /**
     * @throws Exception
     */
    public function execute() {

        //General sql constraint
        $sqlWhere = 'post_type=\'tribe_events\' AND post_status IN (\'publish\')';

        if (WordPressOleExportUtil::getOption('post_checkbox') === 1) {
            $sqlWhere .= ' AND ID IN (SELECT post_id FROM '.$this->_tablePrefix.'postmeta WHERE meta_key = "_oleexport_post_enabled" AND meta_value = "1")';
        }

        //Handle and exit ?
        if($this->_handleCheckSourceIds(
            //Select shows
            'SELECT meta_id FROM '.$this->_tablePrefix.'postmeta WHERE meta_id IN (#IDS#) AND post_id IN (SELECT ID FROM '.$this->_tablePrefix.'posts WHERE '.$sqlWhere.')',
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

        //Select shows (all posts which exists)
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
            //$rows = tribe_get_events(array('include' => $ids, 'posts_per_page' => -1));
            //foreach($rows as $row) {
            foreach($ids as $postId){
                $row = get_post($postId);
                $event = new OleExportEvent('posts-' . $row->ID);

                $metaEventStart = $this->_fetchData('SELECT * FROM '.$this->_tablePrefix.'postmeta WHERE meta_key=\'_EventStartDate\' AND post_id='.$row->ID);
                if (!empty($metaEventStart[0]->meta_value)) {
                    $show = new OleExportShow('events-'.$metaEventStart[0]->meta_id);
                    $startDate = new DateTime($metaEventStart[0]->meta_value, $this->_dateTimeZone);
                    $show->setValue('date_start', $startDate->format('c'));
                    $eventEndDate = get_post_meta($row->ID, '_EventEndDate', 1);
                    if (!empty($eventEndDate)) {
                        $endDate = new DateTime($eventEndDate, $this->_dateTimeZone);
                        $show->setValue('date_end', $endDate->format('c'));
                    }
                    $event->addShow($show);
                }

                $event->setValue('name', $row->post_title);
                $event->setValue('lead', $this->_stripWPTags($row->post_excerpt));
                $event->setValue('description', $this->_stripWPTags($row->post_content));

                $location = new OleExportLocation();
                $event->setLocation($location);
                $location->setValue('name', tribe_get_venue($row->ID));
                $location->setValue('street', tribe_get_address($row->ID));
                $location->setValue('code', tribe_get_zip($row->ID));
                $location->setValue('locality', tribe_get_city($row->ID));

                $eventCategories = wp_get_post_terms($row->ID, 'tribe_events_cat');
                if (!empty($eventCategories)) {
                    foreach($eventCategories as $category) {
                        $event->addCategory($category->name);
                    }
                }

                $event->setValue('url',get_permalink($row->ID));

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
        $this->_oleExport->flushClose();
    }
}
