=== Spiffy Calendar ===
Author: Bev Stofko
Contributors: BStofko
Donate link: http://stofko.ca/spiffy-calendar
Requires at least: 3.4
Tested up to: 3.5.1
Stable tag: 1.2.0
License: GPLv2
Tags:  calendar,widget,image,event,events,upcoming,time,schedule

A full featured, simple to use Spiffy Calendar plugin for WordPress that allows you to manage and display your events and appointments.

== Description ==

A full featured, simple to use Spiffy Calendar plugin for WordPress that allows you to manage and display your events and appointments.

[Demo](http://stofko.ca/spiffy-calendar/)

= Features =

*	Post/page displays include:
	* Standard monthly calendar grid 
	* Mini-calendar view for compact displays
	* Lists of today's events
	* Lists of upcoming events
*	Widgets include:
	* Widget to show today's events
	* Widget to show upcoming events 
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
*	Easy to use events manager in admin dashboard
	* Comprehensive options panel for admin
	* Optional drop down boxes to quickly change month and year
	* User groups other than admin can be permitted to manage events
	* Categories system can be switched on or off
	* Pop up javascript calendars help the choosing of dates
	* Events can be links pointing to a location of your choice

== Installation ==

The installation is extremely simple and straightforward. It only takes a second.

Installing:

1. Install the plugin from the Wordpress repository in the usual way.

2. Activate the plugin on your WordPress plugins page

3. Configure Calendar using the following pages in the admin panel:

   Spiffy Calendar -> Manage Events

   Spiffy Calendar -> Manage Categories

   Spiffy Calendar -> Calendar Options

4. Edit or create a page on your blog which includes the shortcode [spiffy-calendar] and visit the page you have edited or created. You should see your calendar in action.

5. Shortcodes available are:

* 	[spiffy-calendar] for the monthly calendar
*	[spiffy-minical] for the mini version of the monthly calendar
*	[spiffy-upcoming-list] for the upcoming events list
*	[spiffy-todays-list] for the list of today's events

All of the shortcodes accept a comma separated list of category IDs, such as cat_list='1,4'.

== Frequently Asked Questions ==

*	Nothing yet

== Screenshots ==

1. Full calendar with default options

2. Full calendar with detailed display 

3. Calendar options

4. Event manager

== Changelog ==

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