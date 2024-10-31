=== Ole Export ===

Contributors: fuguwp
Tags: event manager, calendar, Event, events, event management
Requires at least: 5.2
Tested up to: 6.0
Requires PHP: 7.2
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

This plugin was build to easy add support of Open Linked Event Data to you WordPress installation (XML feed).

Supported event plugins are:
* Event Organiser
* The Events Calendar
* Events Manager
* Modern Events Calender Lite
* WP Event Manager
* Any custom written driver

== Custom Driver ==

Place your custom OLE driver into wp-content/oleexport_customerdriver/MyCustomDriver which subclasses AbstractWordPressOleDriver.

class MyCustomDriver extends ch\fugu\oledata\driver\wordpress\AbstractWordPressOleDriver {

}
