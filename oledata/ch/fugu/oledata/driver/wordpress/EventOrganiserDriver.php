<?php

namespace ch\fugu\oledata\driver\wordpress;

use ch\fugu\oledata\OleExportEvent;
use ch\fugu\oledata\OleExportImage;
use ch\fugu\oledata\OleExportLocation;
use ch\fugu\oledata\OleExportShow;
use Exception;
use WordPressOleExportUtil;

/**
 * Ole driver for Event Organiser (wp-event-organiser.com)
 * @author fugu GmbH (www.fugu.ch)
 */
class EventOrganiserDriver extends AbstractWordPressOleDriver {

    public function getDisplayName(){
        return 'Event Organiser';
    }

    public function getActivePluginName(){
        return 'event-organiser/event-organiser.php';
    }

    public function getEventPostTypes(){
        return ['event'];
    }

    /**
     * @throws Exception
     */
    public function execute() {

        //General sql constraint
        $sqlWhere = 'post_type=\'event\' AND post_status IN (\'publish\') AND ID IN (SELECT post_id FROM '.$this->_tablePrefix.'eo_events)';

        if (WordPressOleExportUtil::getOption('post_checkbox') === 1) {
            $sqlWhere .= ' AND ID IN (SELECT post_id FROM '.$this->_tablePrefix.'postmeta WHERE meta_key = "_oleexport_post_enabled" AND meta_value = "1")';
        }

        //Handle and exit ?
        if($this->_handleCheckSourceIds(
            //Select shows
            'SELECT event_id FROM '.$this->_tablePrefix.'eo_events WHERE event_id IN (#IDS#) AND post_id IN (SELECT ID FROM '.$this->_tablePrefix.'posts WHERE '.$sqlWhere.')',
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
            foreach (eo_get_events(array('include' => $ids)) as $row) {
                $this->_executeRow(get_post($row));
            }
        }
        $this->_oleExport->flushClose();
    }

    /**
     * @param $row
     * @throws Exception
     */
    protected function _executeRow($row){

        $event = new OleExportEvent('posts-' . $row->ID);

        foreach(eo_get_the_occurrences_of($row->ID) as $occurenceId=>$occurenceRow){
            $show = new OleExportShow('events-' . $occurenceId);
            $show->setValue('date_start', $occurenceRow['start']->format('c'));
            $show->setValue('date_end', $occurenceRow['end']->format('c'));
            $event->addShow($show);
        }

        $event->setValue('name', $row->post_title);
        $event->setValue('lead', $this->_stripWPTags($row->post_excerpt));
        $event->setValue('description', $this->_stripWPTags($row->post_content));

        $venueId = eo_get_venue($row->ID);
        if($venueId) {
            $location = new OleExportLocation();
            $event->setLocation($location);
            $location->setValue('name', eo_get_venue_name($venueId));
            foreach ($this->fetchData('SELECT * FROM ' . $this->_tablePrefix . 'eo_venuemeta WHERE eo_venue_id=' . $venueId) as $venueRow) {
                if ($venueRow->meta_key === '_address') {
                    $location->setValue('street', $venueRow->meta_value);
                }
                if ($venueRow->meta_key === '_postcode') {
                    $location->setValue('code', $venueRow->meta_value);
                }
                if ($venueRow->meta_key === '_city') {
                    $location->setValue('locality', $venueRow->meta_value);
                }
            }
        }

        $categories = get_the_terms($row->ID, 'event-category');
        if($categories) {
            foreach ($categories as $category) {
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
