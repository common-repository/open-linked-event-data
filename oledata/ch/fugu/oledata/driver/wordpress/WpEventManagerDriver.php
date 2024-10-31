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
 * Ole driver for wp-eventmanager.com
 * @author fugu GmbH (www.fugu.ch)
 */
class WpEventManagerDriver extends AbstractWordPressOleDriver {

    public function getDisplayName(){
        return 'WP Event Manager';
    }

    public function getActivePluginName(){
        return 'wp-event-manager/wp-event-manager.php';
    }

    public function getEventPostTypes(){
        return ['event_listing'];
    }

    /**
     * @throws Exception
     */
    public function execute() {

        //General sql constraint
        $sqlWhere = 'post_type=\'event_listing\' AND post_status IN (\'publish\')';

        if (WordPressOleExportUtil::getOption('post_checkbox') === 1) {
            $sqlWhere .= ' AND ID IN (SELECT post_id FROM '.$this->_tablePrefix.'postmeta WHERE meta_key = "_oleexport_post_enabled" AND meta_value = "1")';
        }

        //Handle and exit ?
        if($this->_handleCheckSourceIds(
            //Select shows
            'SELECT id FROM '.$this->_tablePrefix.'post WHERE id IN (#IDS#) AND '.$sqlWhere.')',
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
            $rows = get_posts(array('post_type' => 'event_listing', 'numberposts' => -1, 'include' => $ids));
            foreach($rows as $row) {
                $event = new OleExportEvent('posts-' . $row->ID);
                $show = new OleExportShow('events-1');
                $eventStartDate = get_post_meta($row->ID, '_event_start_date', 1);
                if (!empty($eventStartDate)) {
                    $startDate = new DateTime($eventStartDate, $this->_dateTimeZone);
                    $show->setValue('date_start', $startDate->format('c'));
                }

                $eventEndDate = get_post_meta($row->ID, '_event_end_date', 1);
                if (!empty($eventEndDate)) {
                    $endDate = new DateTime($eventEndDate, $this->_dateTimeZone);
                    $show->setValue('date_end', $endDate->format('c'));
                }

                $event->addShow($show);

                $event->setValue('name', $row->post_title);
                $event->setValue('lead', $this->_stripWPTags($row->post_excerpt));
                $event->setValue('description', $this->_stripWPTags($row->post_content));

                $location = new OleExportLocation();
                $event->setLocation($location);

                $venueId = get_post_meta($row->ID, '_event_venue_ids', 1);
                $venue = get_posts(array('post_type' => 'event_venue', 'numberposts' => -1, 'include' => $venueId));
                if (!empty($venue)) {
                    $location->setValue('name', $venue[0]->post_title);
                }

                $code = get_post_meta($row->ID, '_event_pincode', 1);
                if (!empty($code)) {
                    $location->setValue('code', $code);
                }

                $locality = get_post_meta($row->ID, '_event_location', 1);
                if (!empty($locality)) {
                    $location->setValue('locality', $locality);
                }

                $categories = [];
                $eventCategories = wp_get_post_terms($row->ID, 'event_listing_category');
                if (!empty($eventCategories)) {
                    $categories = $eventCategories;
                }

                $eventTypes = wp_get_post_terms($row->ID, 'event_listing_type');
                if (!empty($eventTypes)) {
                    $categories = array_merge($categories, $eventTypes);
                }

                foreach($categories as $category) {
                    $event->addCategory($category->name);
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
