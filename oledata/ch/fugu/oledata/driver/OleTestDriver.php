<?php

namespace ch\fugu\oledata\driver;

use ch\fugu\oledata\OleExportEvent;
use ch\fugu\oledata\OleExportImage;
use ch\fugu\oledata\OleExportLocation;
use ch\fugu\oledata\OleExportShow;
use stdClass;

/**
 * Ole test/sample driver
 * @author fugu GmbH  (www.fugu.ch)
 */
class OleTestDriver extends AbstractOleDriver {
    public function execute(){

        $pageSize = 10;

        //Test data
        $items = $this->_getTestItems();

        //Handle checksourceids request
        $checkSourceIds = $this->_getCheckSourceIds();
        if (!empty($checkSourceIds)) {
            $showSourceIds = [];
            foreach($items as $item){
                foreach($item->shows as $showItem) {
                    $showSourceIds[] = 'show-' . $showItem->id;
                }
            }
            $checkSourceIds = explode(',', $checkSourceIds);
            $deleteIds = array_diff($checkSourceIds, $showSourceIds);
            $this->_oleExport->sendCheckedSourceIds($deleteIds);
            return;
        }

        //Request with changedsince param
        $changedSinceTime = $this->_getChangedSince();
        if(!empty($changedSinceTime)) {
            $this->_oleExport->setChangedSinceSupported(true);
            $filteredItems = [];
            foreach($items as $item){
                if($item->changedTime>=$changedSinceTime){
                    $filteredItems[] = $item;
                }
            }
            $items = $filteredItems;
        }

        //Paging
        $numberOfPages = $this->_calculateNumberOfPages(count($items),$pageSize);
        $pageNumber = $this->_getPageNumber($numberOfPages);
        $this->_oleExport->setPagingMeta($pageNumber,$numberOfPages);
        $items = array_slice($items,$pageSize*($pageNumber-1),$pageSize);

        $this->_oleExport->flushOpen();
        foreach($items as $item) {
            $event = new OleExportEvent('event-' . $item->id);

            $event->setValue('name', $item->title);
            $event->setValue('lead', $item->lead);
            $event->setValue('description', $item->description);

            foreach($item->shows as $showItem){
                $show = new OleExportShow('show-'.$showItem->id);
                $show->setValue('date_start', date('c',$showItem->date_start));
                $show->setValue('date_end', date('c',$showItem->date_end));
                $event->addShow($show);
            }

            $location = new OleExportLocation();
            $event->setLocation($location);
            $location->setValue('name', $item->location->name);
            $location->setValue('street', $item->location->street);
            $location->setValue('code', $item->location->code);
            $location->setValue('locality', $item->location->locality);

            foreach($item->categories as $category){
                $event->addCategory($category);
            }

            $event->setValue('url',$item->url);
            $event->setValue('ticket_url',$item->ticket_url);

            $file = new OleExportImage();
            $file->setValue('src', $item->image_src);
            $event->addFile($file);

            $this->_finalizeRow($event);

            $event->flushOpen();
            $event->flushClose();
        }
        $this->_oleExport->flushClose();
    }

    /**
     * @return array
     */
    protected function _getTestItems(){
        //Test data
        $items = array();
        for($i=1;$i<=33;$i++){
            $item = new stdClass();
            $item->id = $i;
            $item->changedTime = strtotime('TODAY '.($i-5).' DAYS');
            foreach(['title','lead','description'] as $k){
                $item->{$k} = $k.' '.$i;
            }
            $item->description .= ' changedTime: '.date('c',$item->changedTime);

            $showItems = [];
            for($j=1;$j<=3;$j++){
                $showItem = new stdClass();
                $showItem->id = 10*$i+$j;
                $showItem->date_start = strtotime('TODAY + '.$i.' DAYS '.(16+($j*2)).':00');
                $showItem->date_end = strtotime('TODAY + '.$i.' DAYS '.(16+($j*2)).':45');
                $showItems[] = $showItem;
            }
            $item->shows = $showItems;

            $item->categories = ['Pop','Rock'];
            $item->url = 'https://www.fugu.ch/myevent_'.$i.'.html';
            $item->ticket_url = 'https://www.fugu.ch/order_ticket_myevent_'.$i.'.html';
            $item->image_src = 'https://images.unsplash.com/photo-1501281668745-f7f57925c3b4?ixid=MXwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHw%3D&ixlib=rb-1.2.1&auto=format&fit=crop&w=1650&q=80';

            $location = new stdClass();
            $location->name = 'MyLocation';
            $location->street = 'MyStreet 9';
            $location->code = 3000;
            $location->locality = 'Bern';
            $item->location = $location;

            $items[] = $item;
        }
        return $items;
    }
}
