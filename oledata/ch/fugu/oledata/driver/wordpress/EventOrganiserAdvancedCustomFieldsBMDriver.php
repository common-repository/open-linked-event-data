<?php

namespace ch\fugu\oledata\driver\wordpress;

use ch\fugu\oledata\OleExportEvent;
use ch\fugu\oledata\OleExportImage;
use ch\fugu\oledata\OleExportLocation;
use ch\fugu\oledata\OleExportShow;
use Exception;

/**
 * Ole driver for Event Organiser (wp-event-organiser.com) extends by Advance Custom Fields (advancedcustomfields.com)
 * @author fugu GmbH (www.fugu.ch)
 */
class EventOrganiserAdvancedCustomFieldsBMDriver extends EventOrganiserDriver {

    public function getDisplayName(){
        return 'Event Organiser BM';
    }

    public function getActivePluginName(){
        return 'event-organiser/event-organiser.php'.(function_exists('get_field')?'':'--not-available');
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

        $locationField = get_field('locationpage', $row->ID);
        if (!empty($locationField)) {
            $location = new OleExportLocation();
            $event->setLocation($location);
            $locationId = $locationField[0]->ID;
            $location->setValue('name', $locationField[0]->post_title);
            $location->setValue('street', get_field('strasse', $locationId));
            $location->setValue('code', get_field('postleitzahl', $locationId));
            $location->setValue('locality', get_field('ort', $locationId));
        }

        $event->addTargetGroup('Erwachsene');

        $categories = [];
        $eventType = get_field('eventart', $row->ID);
        if (!empty($eventType)) {
            $categories = $eventType;
        }
        $musicGenres = get_field('musikgenre', $row->ID);
        if (!empty($musicGenres)) {
            $categories = array_merge($categories, $musicGenres);
        }
        foreach($categories as $category) {
            $event->addCategory($category);
        }

        $event->setValue('url',get_permalink($row->ID));

        $ticketField = get_field('tickets', $row->ID);
        if (!empty($ticketField)) {
            $event->setValue('ticket_url', $ticketField[0]['ticketlink']);
        }

        $imageUrl = get_the_post_thumbnail_url($row->ID);
        if ($imageUrl) {
            $file = new OleExportImage();
            $file->setValue('src', $imageUrl);
            $event->addFile($file);
        }

        $event->flushOpen();
        $event->flushClose();
    }
}
