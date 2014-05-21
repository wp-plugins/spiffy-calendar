=== Spiffy Calendar ===
Contributors: SunnyThemes
Donate link: http://www.sunnythemes.com/plugins/spiffy-calendar/
Requires at least: 3.4
Tested up to: 3.9
Stable tag:trunk
License: GPLv2
Tags:  calendar,widget,image,event,events,upcoming,time,schedule

A full featured, simple to use Spiffy Calendar plugin for WordPress that allows you to manage and display your events and appointments.

== Description ==

A full featured, simple to use Spiffy Calendar plugin for WordPress that allows you to manage and display your events and appointments.

[Demo](http://www.sunnythemes.com/plugins/spiffy-calendar/)

= Features =

*	Post/page displays include:
	* Standard monthly calendar grid 
	* Mini-calendar view for compact displays
	* Lists of today's events
	* Lists of upcoming events
*	Widgets include:
	* Show today's events
	* Show upcoming events 
	* Mini Calendar
*	Other features:
	* Displays may be filtered by category list
	* Mouse-over details for each event
	* Events can display their author (optional)
	* Edit the CSS styles or just use the defaults
* 	Choose which of the following fields you want to enter and display for each event:
	* title, 
	* description, 
	* event category, 
	* link, 
	* event start/end date
	* event start/end time
	* event recurrence details
	* event image
*	Schedule a wide variety of recurring events.
	* Events can repeat on a weekly, monthly (set numerical day), monthly (set textual day) or yearly basis
	* Repeats can occur indefinitely or a limited number of times
	* Events can span more than one day
*	Hide all events for specific days:
	* Hide repeating event for a single day such as a holiday
	* Hide full days of events that span more than one day
	* Substitute new title to replace hidden events
	* Select override based on category
*	Easy to use events manager in admin dashboard
	* Comprehensive options panel for admin
	* Optional drop down boxes to quickly change month and year
	* User groups other than admin can be permitted to manage events
	* Categories system can be switched on or off
	* Pop up javascript calendars help the choosing of dates
	* Events can be links pointing to a location of your choice
	
= Languages =

* Spanish (Courtesy of Andrew Kurtis, WebHostingHub)

== Installation ==

1. Install the plugin from the Wordpress repository in the usual way.

2. Activate the plugin on your WordPress plugins page

3. Configure Calendar using the following pages in the admin panel:

   Spiffy Calendar -> Manage Events

   Spiffy Calendar -> Manage Categories

   Spiffy Calendar -> Calendar Options

4. Edit or create a page on your blog which includes one of the shortcodes:

[spiffy-calendar] for the monthly calendar

[spiffy-minical] for the mini version of the monthly calendar

[spiffy-upcoming-list] for the upcoming events list

[spiffy-todays-list] for the list of today's events

Add one of the spiffy widgets to your theme widget areas.

All of the shortcodes and widgets accept a comma separated list of category IDs, such as cat_list='1,4'.

== Frequently Asked Questions ==

*	Nothing yet

== Screenshots ==

1. Full calendar with default options

2. Full calendar with detailed display 

3. Calendar options

4. Event manager

== Changelog ==

= Version 1.2.0 =

* Override selected parts of recurring event without changing original event definition, courtesy of Douglas Forester. MAKE SURE YOU DEACTIVATE AND REACTIVE THE PLUGIN. This will happen automatically if you use the WP updater, but if you just copy the files via FTP you must do this manually to ensure the database is updated.

= Version 1.1.8 =

* CSS improvements. The default CSS has been updated to work better with most themes.
* Additional language strings

= Version 1.1.7 =

* Add language support
* Spanish translation (Courtesy of Andrew Kurtis, WebHostingHub)

= Version 1.1.6 July 18, 2013 =

* Fix typo in event end time test

= Version 1.1.5 March 21, 2013 =

* Fix title and category selection on mini calendar widget

= Version 1.1.4 March 20, 2013 = 

* Fix change made in version 1.1.3 

= Version 1.1.3 March 18, 2013 =

* Add popup window closure for better functionality on touch devices

= Version 1.1.2 February 17, 2013 =

* Allow 3 digits for upcoming days configuration
* Fix minicalendar widget (has been missing since day 1)

= Version 1.1.1 February 7, 2013 =

* Fix default CSS to confine table styles to Spiffy Calendar tables

= Version 1.1.0 January 22, 2013 =

* NEW FEATURE: Provide option to open event links in new window
* Fix typo in minical html

= Version 1.0.3 January 15, 2013 =

* Fix end time on mini-calendar hover

= Version 1.0.2a December 17, 2012 =

* Make sure CSS file is recreated after plugin upgrade

= Version 1.0.1 November 2012 =

* Corrected missed removal of some options when plugin is deleted, and renamed to avoid conflicts

= Version 1.0.0 November 19, 2012 =

* Initial version

= Notes =

This plugin was derived from Calendar plugin version 1.3.1 by Kieran O'Shea http://www.kieranoshea.com.

Reasons for creating a new version:

* Support images and event end times
* Update to modern WP methods
* Wrap functions in class to avoid conflicts
* Use shortcodes
* Reduce database queries - there is still room for improvement here
* Clean up tables and options on uninstall

== Upgrade Notice ==

= 1.0.0 =

* Initial release