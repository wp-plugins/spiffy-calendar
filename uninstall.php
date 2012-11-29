<?php

if ( !defined( 'ABSPATH' ) && !defined( 'WP_UNINSTALL_PLUGIN' ) ) {
exit();
} else {
global $wpdb;

// drop tables
define('WP_SPIFFYCAL_TABLE', $wpdb->prefix . 'spiffy_calendar');
define('WP_SPIFFYCAL_CATEGORIES_TABLE', $wpdb->prefix . 'spiffy_calendar_categories');
$wpdb->query("DROP TABLE IF EXISTS ".WP_SPIFFYCAL_TABLE);
$wpdb->query("DROP TABLE IF EXISTS ".WP_SPIFFYCAL_CATEGORIES_TABLE);

// delete options
delete_option('spiffy_calendar_options');
delete_option('spiffy_calendar_widget_title');
delete_option('spiffy_calendar_widget_cats');
delete_option('spiffy_calendar_today_widget_title');
delete_option('spiffy_calendar_today_widget_cats');
delete_option('spiffy_calendar_upcoming_widget_title');
delete_option('spiffy_calendar_upcoming_widget_cats');
}
?>