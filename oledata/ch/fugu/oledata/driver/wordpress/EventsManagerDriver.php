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
 * Ole driver for wp-events-plugin.com
 * @author fugu GmbH (www.fugu.ch)
 */
class EventsManagerDriver extends AbstractWordPressOleDriver {

    public function getDisplayName(){
        return 'Events Manager';
    }

    public function getActivePluginName(){
        return 'events-manager/events-manager.php';
    }

    public function getEventPostTypes(){
        return ['event', 'event-recurring'];
    }

    /**
     * @throws Exception
     */
    public function execute() {

        //General sql constraint
        $sqlWhere = '(post_type=\'event\' OR post_type=\'event-recurring\') AND post_status IN (\'publish\') AND ID IN (SELECT post_id FROM '.$this->_tablePrefix.'em_events WHERE recurrence_id IS NULL)';
        $recurrenceSqlWhere = 'post_id IN (SELECT ID FROM '.$this->_tablePrefix.'posts WHERE post_status IN (\'publish\'))';

        if (WordPressOleExportUtil::getOption('post_checkbox') === 1) {
            $sqlWhere .= ' AND ID IN (SELECT post_id FROM '.$this->_tablePrefix.'postmeta WHERE meta_key = "_oleexport_post_enabled" AND meta_value = "1")';
        }

        //Handle and exit ?
        $this->_handleCheckSourceIds(
        //Select shows
            'SELECT post_id FROM '.$this->_tablePrefix.'em_events WHERE event_id IN (#IDS#) AND '.$recurrenceSqlWhere,
            //Define prefix for shows
            'revents-',
            false
        );
        if($this->_handleCheckSourceIds(
            //Select shows
            'SELECT event_id FROM '.$this->_tablePrefix.'em_events WHERE event_id IN (#IDS#) AND post_id IN (SELECT ID FROM '.$this->_tablePrefix.'posts WHERE '.$sqlWhere.')',
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

        //Select shows (all posts which exists in em_events)
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
            foreach($ids as $id) {
                $emEvent = em_get_event($id, 'post_id');
                $event = new OleExportEvent('posts-' . $id);

                $recurringLinkId = 0;
                if ($emEvent->is_recurring()) {
                    foreach($this->_fetchData('SELECT * FROM '.$this->_tablePrefix.'em_events WHERE recurrence_id = '.$emEvent->id.' AND '.$recurrenceSqlWhere.' ORDER BY event_start_date ASC') as $occurenceRow) {
                        $startDate = new DateTime($occurenceRow->event_start_date.' '.$occurenceRow->event_start_time, $this->_dateTimeZone);
                        $endDate = new DateTime($occurenceRow->event_end_date.' '.$occurenceRow->event_end_time, $this->_dateTimeZone);

                        $show = new OleExportShow('revents-' . $occurenceRow->post_id);
                        $show->setValue('date_start', $startDate->format('c'));
                        $show->setValue('date_end', $endDate->format('c'));
                        $event->addShow($show);

                        if ($recurringLinkId === 0 && $startDate->getTimestamp() > time()) {
                            $recurringLinkId = $occurenceRow->post_id;
                        }
                    }
                } else {
                    $show = new OleExportShow('events-'.$id);
                    $show->setValue('date_start', $emEvent->get_datetime()->format('c'));
                    $show->setValue('date_end', $emEvent->get_datetime('end')->format('c'));
                    $event->addShow($show);
                }

                $event->setValue('name', $emEvent->name);
                $event->setValue('lead', $this->_stripWPTags($emEvent->post_excerpt));
                $event->setValue('description', $this->_stripWPTags($emEvent->post_content));

                $emLocation = $emEvent->get_location();
                if($emLocation) {
                    $location = new OleExportLocation();
                    $event->setLocation($location);
                    $location->setValue('name', $emLocation->name);
                    $location->setValue('street', $emLocation->location_address);
                    $location->setValue('code', $emLocation->location_postcode);
                    $location->setValue('locality', $emLocation->location_town);
                }

                foreach ($emEvent->get_categories() as $category) {
                    $event->addCategory($category->name);
                }

                if (!$emEvent->is_recurring() || $recurringLinkId === 0) {
                    $event->setValue('url',get_permalink($id));
                } else {
                    $event->setValue('url',get_permalink($recurringLinkId));
                }

                $imageUrl = get_the_post_thumbnail_url($id);
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
