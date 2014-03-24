<?php
/*
Plugin Name: Spiffy Calendar
Plugin URI: http://www.stofko.ca
Description: This plugin allows you to display a calendar of all your events and appointments as a page on your site.
Version: 1.1.7
Author: Bev Stofko

Credits:
- Derived from Calendar plugin version 1.3.1 by Kieran O'Shea http://www.kieranoshea.com
*/

/* Copyright 2012 Bev Stofko (email : bev@stofko.ca)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.		See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA		02110-1301		USA
*/

$plugin_dir = basename(dirname(__FILE__));

// Define the tables used by Spiffy Calendar
global $wpdb;
define('WP_SPIFFYCAL_TABLE', $wpdb->prefix . 'spiffy_calendar');
define('WP_SPIFFYCAL_CATEGORIES_TABLE', $wpdb->prefix . 'spiffy_calendar_categories');

if (!class_exists("Spiffy_Calendar")) {
Class Spiffy_Calendar
{
	private $gmt_offset = null;
	private $spiffy_options = 'spiffy_calendar_options';
	private $event_menu_page;
	private $calendar_config_page;
	private $categories = array();

	function __construct()
	{
		// Admin stuff
		add_action('init', array($this, 'calendar_init_action'));
		register_activation_hook( __FILE__, array($this, 'activate') );
		add_action('admin_menu', array($this, 'calendar_admin_menu'));	
		add_action('admin_enqueue_scripts', array($this, 'calendar_admin_scripts'));

		// Enable the ability for the calendar to be loaded from pages
		add_shortcode('spiffy-calendar', array(&$this, 'calendar_insert'));	
		add_shortcode('spiffy-minical', array(&$this, 'minical_insert'));	
		add_shortcode('spiffy-upcoming-list', array($this, 'upcoming_insert'));
		add_shortcode('spiffy-todays-list', array($this, 'todays_insert'));

		// Add the function that puts style information in the header
		add_action('wp_enqueue_scripts', array($this, 'calendar_styles'));

		// Add the function that deals with deleted users
		add_action('delete_user', array($this, 'deal_with_deleted_user'));

		// Add the widgets
		add_action('widgets_init', array($this, 'widget_init_calendar_today'));
		add_action('widgets_init', array($this, 'widget_init_calendar_upcoming'));
		add_action('widgets_init', array($this, 'widget_init_events_calendar'));
	}

	function activate()
	{
		// Checks to make sure Calendar is installed, if not it adds the default
		// database tables and populates them with test data. 

		// Lets see if this is first run and create us a table if it is!
		global $wpdb;

		// Assume this is not a new install until we prove otherwise
		$new_install = true;

		$wp_spiffycal_exists = false;

		// Determine if the calendar exists
		if( mysql_num_rows( mysql_query("SHOW TABLES LIKE '".WP_SPIFFYCAL_TABLE."'"))) {
			$new_install = false;
		}

		// Now we've determined what the current install is or isn't 
		// we perform operations according to the findings
		if ( $new_install == true ) {
			$sql = "CREATE TABLE " . WP_SPIFFYCAL_TABLE . " (
				event_id INT(11) NOT NULL AUTO_INCREMENT ,
				event_begin DATE NOT NULL ,
				event_end DATE NOT NULL ,
				event_title VARCHAR(30) NOT NULL ,
				event_desc TEXT NOT NULL ,
				event_time TIME ,
				event_end_time TIME ,
				event_recur CHAR(1) ,
				event_repeats INT(3) ,
				event_author BIGINT(20) UNSIGNED ,
				event_category BIGINT(20) UNSIGNED NOT NULL DEFAULT 1 ,
				event_link TEXT ,
				event_image BIGINT(20) UNSIGNED ,
				PRIMARY KEY (event_id)
			)";
			$wpdb->get_results($sql);

			$sql = "CREATE TABLE " . WP_SPIFFYCAL_CATEGORIES_TABLE . " ( 
				category_id INT(11) NOT NULL AUTO_INCREMENT, 
				category_name VARCHAR(30) NOT NULL , 
				category_colour VARCHAR(30) NOT NULL , 
				PRIMARY KEY (category_id) 
			 )";
			$wpdb->get_results($sql);

			$sql = "INSERT INTO " . WP_SPIFFYCAL_CATEGORIES_TABLE . " SET category_id=1, category_name='General', category_colour='#000000'";
			$wpdb->get_results($sql);

			$this->default_styles();
		} 
	}
	
	function calendar_init_action() {
		// Localization
		load_plugin_textdomain('spiffy-calendar', false, basename( dirname( __FILE__ ) ) . '/languages' );
	}

	function get_options() {
		
		/*
		** Merge default options with the saved values
		*/
		$use_options = array(	'calendar_style' => '',
						'can_manage_events' => 'edit_posts',
						'display_author' => 'false',
						'display_detailed' => 'false',
						'display_jump' => 'false',
						'display_todays' => 'true',
						'display_upcoming' => 'true',
						'display_upcoming_days' => 7,
						'enable_categories' => 'false',
						'enable_new_window' => 'false',
					);
		$saved_options = get_option($this->spiffy_options);
		if (!empty($saved_options)) {
			foreach ($saved_options as $key => $option)
				$use_options[$key] = $option;
		}

		if ($use_options['calendar_style'] == '' ) {
			$use_options['calendar_style'] = $this->default_styles();
		}

		if (!file_exists( plugin_dir_path(__FILE__). 'spiffycal.css')) {
			$this->write_styles($use_options['calendar_style']);
		} 

		return $use_options;
	}

	function default_styles() {
		// load default styles
		$defaults = plugin_dir_path(__FILE__). 'styles/default.css';
		$f = fopen($defaults,'r');
		$file = fread($f, filesize($defaults));
		fclose($f);

		// update current style file
		$this->write_styles($file);

		return $file;
	}

	// Function to deal with events posted by a user when that user is deleted
	function deal_with_deleted_user($id)
	{
		global $wpdb;

		// Do the query
		$wpdb->get_results("UPDATE ".WP_SPIFFYCAL_TABLE." SET event_author=".$wpdb->get_var("SELECT MIN(ID) FROM ".$wpdb->prefix."users",0,0)." WHERE 					event_author=".mysql_real_escape_string($id));
	}

	// Function to provide time with WordPress offset, localy replaces time()
	function ctwo()
	{
		if ($this->gmt_offset == null) $this->gmt_offset = get_option('gmt_offset');
		return (time()+(3600*($this->gmt_offset)));
	}

	// Function to add the calendar style into the header
	function calendar_styles() {
		wp_enqueue_style ('spiffycal-styles', plugins_url('spiffycal.css', __FILE__));

	}

	// Function to deal with adding the calendar menus
	function calendar_admin_menu() {
		global $wpdb;

		// Set admin as the only one who can use Calendar for security
		$allowed_group = 'manage_options';

		// Use the database to *potentially* override the above if allowed
		$options = $this->get_options();
		$allowed_group = $options['can_manage_events'];

		// Add the admin panel pages for Calendar. Use permissions pulled from above
		 if (function_exists('add_menu_page')) {
			 add_menu_page(__('Spiffy Calendar','spiffy-calendar'), __('Spiffy Calendar','spiffy-calendar'), $allowed_group, 'spiffy-calendar', 
					array($this, 'manage_events'));
		 }
		 if (function_exists('add_submenu_page')) {
			$this->event_menu_page = add_submenu_page('spiffy-calendar', __('Manage Events','spiffy-calendar'), 
							__('Manage Events','spiffy-calendar'), $allowed_group, 'spiffy-calendar', array($this, 'manage_events'));
			add_action( 'admin_head-'. $this->event_menu_page, array($this, 'manage_events_header'));

			// Note only admin can change calendar options
			add_submenu_page('spiffy-calendar', __('Manage Categories','spiffy-calendar'), __('Manage Categories','spiffy-calendar'), 
						'manage_options', 'spiffy-calendar-categories', array($this, 'manage_categories'));
			$this->calendar_config_page = add_submenu_page('spiffy-calendar', __('Calendar Config','spiffy-calendar'), 
						__('Calendar Options','spiffy-calendar'), 
						'manage_options', 'spiffy-calendar-config', array($this, 'edit_calendar_config'));
			add_action( 'admin_head-'. $this->calendar_config_page, array($this, 'calendar_config_header'));
		}
	}

	// Function to add the javascript to the admin pages
	function calendar_admin_scripts($hook)
	{ 
		if( $hook == $this->event_menu_page ) {
			wp_enqueue_style ( 'spiffycal-styles', plugins_url('calendrical/calendrical.css', __FILE__));
			wp_enqueue_script( 'spiffy_calendar_script', plugins_url('calendrical/jquery.calendrical.js', __FILE__), array('jquery') );
		} 
	}

	// Admin header on events manager page
	function manage_events_header() {
		echo '<script type="text/javascript">
//<![CDATA[
jQuery(document).ready(function($) {
    //jQuery("#event_begin, #event_time," + "#event_end, #event_end_time").calendricalDateTimeRange();
    jQuery("#event_begin, #event_end").calendricalDateRange();
});
//]]>
</script>
';
	}

	// Admin header on calendar config page
	function calendar_config_header() {
		echo '<script type="text/javascript">
function toggleVisibility(id) {
   var e = document.getElementById(id);
   if(e.style.display == "block")
      e.style.display = "none";
   else
      e.style.display = "block";
}
</script>
';
	}

	// Calendar shortcode
	function calendar_insert($attr)
	{
		/*
		** Standard shortcode defaults that we support here	
		*/
		global $post;
		extract(shortcode_atts(array(
				'cat_list'	=> '',
		  ), $attr));

		if ($cat_list != '') { 
			$cal_output = $this->calendar($cat_list);
		} else {
			$cal_output = $this->calendar();
		}
		return $cal_output;
	}

	// Mini calendar shortcode
	function minical_insert($attr) {
		/*
		** Standard shortcode defaults that we support here	
		*/
		global $post;
		extract(shortcode_atts(array(
				'cat_list'	=> '',
		  ), $attr));

		if ($cat_list != '') {
			$cal_output = $this->minical($cat_list);
		} else {
			$cal_output = $this->minical();
		}
		return $cal_output;
	}

	// Upcoming events shortcode
	function upcoming_insert($attr) {
		/*
		** Standard shortcode defaults that we support here	
		*/
		global $post;
		extract(shortcode_atts(array(
				'cat_list'	=> '',
		  ), $attr));

		if ($cat_list != '') {
			$cal_output = '<div class="page-upcoming-events">'.$this->upcoming_events($cat_list).'</div>';
		} else {
			$cal_output = '<div class="page-upcoming-events">'.$this->upcoming_events().'</div>';
		}
		return $cal_output;
	}

	// Today's events shortcode
	function todays_insert($attr) {
		/*
		** Standard shortcode defaults that we support here	
		*/
		global $post;
		extract(shortcode_atts(array(
				'cat_list'	=> '',
		  ), $attr));

		if ($cat_list != '') {
			$cal_output = '<div class="page-todays-events">'.$this->todays_events($cat_list).'</div>';
		} else {
			$cal_output = '<div class="page-todays-events">'.$this->todays_events().'</div>';
		}
		return $cal_output;
	}

	// Used on the manage events admin page to display a list of events
	function wp_events_display_list()
	{

		global $wpdb;
	
		$events = $wpdb->get_results("SELECT * FROM " . WP_SPIFFYCAL_TABLE . " ORDER BY event_begin DESC");
	
		if ( !empty($events) ) {
		?>
<table class="widefat page fixed" width="100%" cellpadding="3" cellspacing="3">
<thead>
<tr>
	<th class="manage-column" scope="col"><?php _e('ID','spiffy-calendar') ?></th>
	<th class="manage-column" scope="col" style="width:150px;"><?php _e('Title','spiffy-calendar') ?></th>
	<th class="manage-column" scope="col"><?php _e('Start Date','spiffy-calendar') ?></th>
	<th class="manage-column" scope="col"><?php _e('End Date','spiffy-calendar') ?></th>
	<th class="manage-column" scope="col"><?php _e('Start Time','spiffy-calendar') ?></th>
	<th class="manage-column" scope="col"><?php _e('End Time','spiffy-calendar') ?></th>
	<th class="manage-column" scope="col"><?php _e('Recurs','spiffy-calendar') ?></th>
	<th class="manage-column" scope="col"><?php _e('Repeats','spiffy-calendar') ?></th>
	<th class="manage-column" scope="col"><?php _e('Image','spiffy-calendar') ?></th>
	<th class="manage-column" scope="col"><?php _e('Author','spiffy-calendar') ?></th>
	<th class="manage-column" scope="col"><?php _e('Category','spiffy-calendar') ?></th>
	<th class="manage-column" scope="col" style="width:2em;"><?php _e('Edit','spiffy-calendar') ?></th>
	<th class="manage-column" scope="col" style="width:5em;"><?php _e('Delete','spiffy-calendar') ?></th>
</tr>
</thead>
		<?php
		$class = '';
		foreach ( $events as $event ) {
			$class = ($class == 'alternate') ? '' : 'alternate';
			?>
<tr style="vertical-align:top;" class="<?php echo $class; ?>">
	<th scope="row"><?php echo $event->event_id; ?></th>
	<td><?php echo stripslashes($event->event_title); ?></td>
	<td><?php echo $event->event_begin; ?></td>
	<td><?php echo $event->event_end; ?></td>
	<td><?php if ($event->event_time == '00:00:00') { echo __('N/A','spiffy-calendar'); } else { echo date("h:i a",strtotime($event->event_time)); } ?></td>
	<td><?php if ($event->event_end_time == '00:00:00') { echo __('N/A','spiffy-calendar'); } else { echo date("h:i a",strtotime($event->event_end_time)); } ?></td>
	<td>
			<?php 
			// Interpret the DB values into something human readable
			if ($event->event_recur == 'S') { echo __('Never','spiffy-calendar'); } 
			else if ($event->event_recur == 'W') { echo __('Weekly','spiffy-calendar'); }
			else if ($event->event_recur == 'M') { echo __('Monthly (date)','spiffy-calendar'); }
			else if ($event->event_recur == 'U') { echo __('Monthly (day)','spiffy-calendar'); }
			else if ($event->event_recur == 'Y') { echo __('Yearly','spiffy-calendar'); }
			?>
	</td>
	<td>
			<?php
			// Interpret the DB values into something human readable
			if ($event->event_recur == 'S') { echo __('N/A','spiffy-calendar'); }
			else if ($event->event_repeats == 0) { echo __('Forever','spiffy-calendar'); }
			else if ($event->event_repeats > 0) { echo $event->event_repeats.' '.__('Times','spiffy-calendar'); }
			?>
	</td>
	<td>
			<?php
			if ($event->event_image > 0) {
				$image = wp_get_attachment_image_src( $event->event_image, 'THUMBNAIL');
				echo '<img src="' . $image[0] . '" width="76px" />';
			}
			?>
	</td>
	<td><?php $e = get_userdata($event->event_author); echo $e->display_name; ?></td>
			<?php
			$sql = "SELECT * FROM " . WP_SPIFFYCAL_CATEGORIES_TABLE . " WHERE category_id=".mysql_real_escape_string($event->event_category);
			$this_cat = $wpdb->get_row($sql);
			?>
	<td style="background-color:<?php echo $this_cat->category_colour;?>;"><?php echo stripslashes($this_cat->category_name); ?>
	</td>
			<?php unset($this_cat); ?>
	<td><a href="<?php echo bloginfo('wpurl') ?>/wp-admin/admin.php?page=spiffy-calendar&amp;action=edit&amp;event_id=<?php echo $event->event_id;?>" class='edit'><?php echo __('Edit','spiffy-calendar'); ?></a></td>
	<td><form name="deleventform" method="post" action="<?php echo admin_url('admin.php?page=spiffy-calendar'); ?>">

		<input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce('spiffy-event-delete-nonce'); ?>" />
		<input type="hidden" name="action" value="delete" />
		<input type="hidden" name="event_id" value="<?php echo $event->event_id; ?>" />
		<input type="submit" name="delete" class="button bold" value="<?php _e('Delete','spiffy-calendar'); ?> &raquo;" onclick="return confirm('<?php echo __('Are you sure you want to delete the event titled &quot;','spiffy-calendar').$event->event_title.'&quot;?'; ?>')" />

	</form>
	</td>
</tr>
			<?php
		}
		?>
</table>
		<?php
		} else {
			echo __("<p>There are no events in the database!</p>",'spiffy-calendar');
		}
	}


	// The event edit form for the manage events admin page
	function wp_events_edit_form($mode='add', $event_id=false)
	{
		global $wpdb,$users_entries;
		$data = false;
	
		if ( $event_id !== false ) {
			if ( intval($event_id) != $event_id ) {
				echo "<div class=\"error\"><p>".__('Bad event ID','spiffy-calendar')."</p></div>";
				return;
			} else {
				$data = $wpdb->get_results("SELECT * FROM " . WP_SPIFFYCAL_TABLE . " WHERE event_id='" . 
								mysql_real_escape_string($event_id) . "' LIMIT 1");
				if ( empty($data) ) {
					echo "<div class=\"error\"><p>".__("An event with that ID couldn't be found",'spiffy-calendar')."</p></div>";
					return;
				}
				$data = $data[0];
			}
			// Recover users entries if they exist; in other words if editing an event went wrong
			if (!empty($users_entries)) {
				$data = $users_entries;
			}
		} else {
			// Deal with possibility that form was submitted but not saved due to error - recover user's entries here
			$data = $users_entries;
		}
	
		?>

<div id="pop_up_cal" style="position:absolute;margin-left:70px;margin-top:-50px;visibility:hidden;background-color:white;layer-background-color:white;z-index:1;"></div>
	<form name="quoteform" id="quoteform" class="wrap" enctype="multipart/form-data" method="post" action="<?php echo admin_url('admin.php?page=spiffy-calendar');?> ">
		<input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce('spiffy-event-edit-nonce'); ?>" />		
		<input type="hidden" name="action" value="<?php echo $mode; ?>">
		<input type="hidden" name="event_id" value="<?php echo $event_id; ?>">
	
		<div id="linkadvanceddiv" class="postbox">
			<div style="float: left; width: 98%; clear: both;" class="inside">
				<table cellpadding="5" cellspacing="5">
				<tr>				
				<td><legend><?php _e('Event Title','spiffy-calendar'); ?></legend></td>
				<td><input type="text" name="event_title" class="input" size="40" maxlength="30"
					value="<?php if ( !empty($data) ) echo htmlspecialchars(stripslashes($data->event_title)); ?>" /></td>
				</tr>
				<tr>
				<td style="vertical-align:top;"><legend><?php _e('Event Description','spiffy-calendar'); ?></legend></td>
				<td>
<textarea name="event_desc" class="input" rows="5" cols="50"><?php if ( !empty($data) ) echo (stripslashes($data->event_desc)); ?>
</textarea></td>
				</tr>
				<tr>
				<td><legend><?php _e('Event Category','spiffy-calendar'); ?></legend></td>
				<td>	 <select name="event_category">

		 <?php
		// Grab all the categories and list them
		$sql = "SELECT * FROM " . WP_SPIFFYCAL_CATEGORIES_TABLE;
		$cats = $wpdb->get_results($sql);
		foreach($cats as $cat) {
			 echo '<option value="'.$cat->category_id.'"';
			 if (!empty($data)) {
				if ($data->event_category == $cat->category_id) {
					echo 'selected="selected"';
				}
			 }
			 echo '>'.stripslashes($cat->category_name).'</option>';
	 	}
		?>
		
					</select>
				</td>
				</tr>
				<tr>
				<td><legend><?php _e('Event Link (Optional)','spiffy-calendar'); ?></legend></td>
				<td><input type="text" name="event_link" class="input" size="40" value="<?php if ( !empty($data) ) echo htmlspecialchars(stripslashes($data->event_link)); ?>" /></td>
				</tr>
				<tr>
				<td><legend><?php _e('Start Date','spiffy-calendar'); ?></legend></td>
				<td>
					<input type="text" id="event_begin" name="event_begin" class="input" size="12"
					value="<?php 
						if ( !empty($data) ) {
							echo $data->event_begin;
						} else {
							echo date("Y-m-d",$this->ctwo());
						} 
					?>" />
				</td>
				</tr>
				<tr>
				<td><legend><?php _e('Start Time (hh:mm)','spiffy-calendar'); ?></legend></td>
				<td>	<input type="text" id="event_time" name="event_time" class="input" size=12
					value="<?php 
					if ( !empty($data) ) {
						if ($data->event_time == "00:00:00") {
							echo '';
						} else {
							echo date("h:i a",strtotime($data->event_time));
						}
					} else {
						//echo date("a:i a",$this->ctwo()); //defaulting to current time is not helpful
					}
					?>" /> <?php _e('Optional, set blank if not required.','spiffy-calendar'); ?>
				</td>
				</tr>
				<tr>
				<td><legend><?php _e('End Date','spiffy-calendar'); ?></legend></td>
				<td><input type="text" id="event_end" name="event_end" class="input" size="12"
					value="<?php 
						if ( !empty($data) ) {
							echo $data->event_end;
						} else {
							echo date("Y-m-d",$this->ctwo());
						} 
					?>" />
				</td>
				</tr>
				<tr>
				<td><legend><?php _e('End Time (hh:mm)','spiffy-calendar'); ?></legend></td>
				<td>	<input type="text" id="event_end_time" name="event_end_time" class="input" size=12
					value="<?php 
					if ( !empty($data) ) {
						if ($data->event_end_time == "00:00:00") {
							echo '';
						} else {
							echo date("h:i a",strtotime($data->event_end_time));
						}
					} 
					?>" /> <?php _e('Optional, set blank if not required.','spiffy-calendar'); ?>
				</td>
				</tr>
				<tr>
				<td style="vertical-align:top;"><legend><?php _e('Recurring Events','spiffy-calendar'); ?></legend></td>
				<td>	<?php
					if (isset($data)) {
						if ($data->event_repeats != NULL) {
							$repeats = $data->event_repeats;
						} else {
							$repeats = 0;
						}
					} else {
						$repeats = 0;
					}

					$selected_s = '';
					$selected_w = '';
					$selected_m = '';
					$selected_y = '';
					$selected_u = '';
					if (isset($data)) {
						if ($data->event_recur == "S") {
							$selected_s = 'selected="selected"';
						} else if ($data->event_recur == "W") {
							$selected_w = 'selected="selected"';
						} else if ($data->event_recur == "M") {
							$selected_m = 'selected="selected"';
						} else if ($data->event_recur == "Y") {
							$selected_y = 'selected="selected"';
						} else if ($data->event_recur == "U") {
							$selected_u = 'selected="selected"';
						}
					}
					_e('Repeats for','spiffy-calendar'); 
					?> 
					<input type="text" name="event_repeats" class="input" size="1" value="<?php echo $repeats; ?>" /> 
					<select name="event_recur" class="input">
						<option class="input" <?php echo $selected_s; ?> value="S"><?php _e('None') ?></option>
						<option class="input" <?php echo $selected_w; ?> value="W"><?php _e('Weeks') ?></option>
						<option class="input" <?php echo $selected_m; ?> value="M"><?php _e('Months (date)') ?></option>
						<option class="input" <?php echo $selected_u; ?> value="U"><?php _e('Months (day)') ?></option>
						<option class="input" <?php echo $selected_y; ?> value="Y"><?php _e('Years') ?></option>
					</select><br />
					<?php _e('Entering 0 means forever. Where the recurrance interval is left at none, the event will not reoccur.','spiffy-calendar'); ?>
				</td>
				</tr>
				<tr>
				<td><legend><?php _e('Image','spiffy-calendar'); ?></legend></td>
				<td>
					<input type="file" name="image_upload" size="80" class="input" />
					<?php
						if ( !empty($data) ) {
							if ($data->event_image > 0) {
								?>
					<input type="hidden" name="event_image" size="80" class="input" value="<?php echo $data->event_image; ?>" />
								<?php
								$image = wp_get_attachment_image_src( $data->event_image, 'THUMBNAIL');
								echo '<img src="' . $image[0] . '" />';
							}
						}
					?>
				</td>
				</tr>
				</table>
			</div>
			<div style="clear:both; height:1px;">&nbsp;</div>
		</div>
		<input type="submit" name="save" class="button bold" value="<?php _e('Save','spiffy-calendar'); ?> &raquo;" />
	</form>
	<?php
	}

	// The actual function called to render the manage events page and 
	// to deal with posts
	function manage_events() {
		global $current_user, $wpdb, $users_entries;
		?>
<style type="text/css">
<!--
.error {
	background: lightcoral;
	border: 1px solid #e64f69;
	margin: 1em 5% 10px;
	padding: 0 1em 0 1em;
}

.center { 
	text-align: center;	
}
.right { text-align: right;	
}
.left { 
	text-align: left;		
}
.top { 
	vertical-align: top;	
}
.bold { 
	font-weight: bold; 
}
.private { 
	color: #e64f69;		
}
//-->
</style>

	<?php

	// First some quick cleaning up 
	$edit = $create = $save = $delete = false;

	// Make sure we are collecting the variables we need to select years and months
	$action = !empty($_REQUEST['action']) ? $_REQUEST['action'] : '';
	$event_id = !empty($_REQUEST['event_id']) ? $_REQUEST['event_id'] : '';
	$title = !empty($_REQUEST['event_title']) ? $_REQUEST['event_title'] : '';
	$desc = !empty($_REQUEST['event_desc']) ? $_REQUEST['event_desc'] : '';
	$begin = !empty($_REQUEST['event_begin']) ? $_REQUEST['event_begin'] : '';
	$end = !empty($_REQUEST['event_end']) ? $_REQUEST['event_end'] : '';
	$time = !empty($_REQUEST['event_time']) ? trim($_REQUEST['event_time']) : '';
	$end_time = !empty($_REQUEST['event_end_time']) ? trim($_REQUEST['event_end_time']) : '';
	$recur = !empty($_REQUEST['event_recur']) ? $_REQUEST['event_recur'] : '';
	$repeats = !empty($_REQUEST['event_repeats']) ? $_REQUEST['event_repeats'] : '';
	$event_image = !empty($_REQUEST['event_image']) ? $_REQUEST['event_image'] : '';
	$category = !empty($_REQUEST['event_category']) ? $_REQUEST['event_category'] : '';
	$linky = !empty($_REQUEST['event_link']) ? $_REQUEST['event_link'] : '';

	if ( ($action == 'edit_save') && (empty($event_id)) ) {
		// Missing event id for update?
		?>
	
<div class="error"><p><strong><?php _e('Failure','spiffy-calendar'); ?>:</strong> <?php _e("You can't update an event if you haven't submitted an event id",'spiffy-calendar'); ?></p></div>

		<?php		
	} else if ( ($action == 'add') || ($action == 'edit_save') )	{
		$nonce=$_REQUEST['_wpnonce'];
		if (! wp_verify_nonce($nonce,'spiffy-event-edit-nonce') ) die("Security check failed");

		// Deal with adding/updating an event 

		// Perform some validation on the submitted dates - this checks for valid years and months
		$begin = date( 'Y-m-d',strtotime($begin) );
		$end = ($end == '')? $begin : date( 'Y-m-d',strtotime($end) );
		$date_format_one = '/^([0-9]{4})-([0][1-9])-([0-3][0-9])$/';
		$date_format_two = '/^([0-9]{4})-([1][0-2])-([0-3][0-9])$/';
		if ((preg_match($date_format_one,$begin) || preg_match($date_format_two,$begin)) && 
				(preg_match($date_format_one,$end) || preg_match($date_format_two,$end))) {

			// We know we have a valid year and month and valid integers for days so now we do a final check on the date
			$begin_split = explode('-',$begin);
			$begin_y = $begin_split[0]; 
			$begin_m = $begin_split[1];
			$begin_d = $begin_split[2];
			$end_split = explode('-',$end);
			$end_y = $end_split[0];
			$end_m = $end_split[1];
			$end_d = $end_split[2];
			if (checkdate($begin_m,$begin_d,$begin_y) && checkdate($end_m,$end_d,$end_y)) {
				// Ok, now we know we have valid dates, we want to make sure that they are either equal or that 
				// the end date is later than the start date
				if (strtotime($end) >= strtotime($begin)) {
					$start_date_ok = 1;
					$end_date_ok = 1;
		 		} else {
					?>

<div class="error"><p><strong><?php _e('Error','spiffy-calendar'); ?>:</strong> <?php _e('Your event end date must be either after or the same as your event begin date','spiffy-calendar'); ?></p></div>

					 <?php
		 		}
			 } else {
				?>

<div class="error"><p><strong><?php _e('Error','spiffy-calendar'); ?>:</strong> <?php _e('Your date formatting is correct but one or more of your dates is invalid. Check for number of days in month and leap year related errors.','spiffy-calendar'); ?></p></div>

				<?php
			}
		} else {
			?>

<div class="error"><p><strong><?php _e('Error','spiffy-calendar'); ?>:</strong> <?php _e('Both start and end dates must be entered and be in the format YYYY-MM-DD','spiffy-calendar'); ?></p></div>

			<?php
		}

		// We check for a valid time, or an empty one
		$time = ($time == '')?'00:00:00':date( 'H:i:00',strtotime($time) );
		$time_format_one = '/^([0-1][0-9]):([0-5][0-9]):([0-5][0-9])$/';
		$time_format_two = '/^([2][0-3]):([0-5][0-9]):([0-5][0-9])$/';
		if (preg_match($time_format_one,$time) || preg_match($time_format_two,$time)) {
			$time_ok = 1;
		} else { 
			?>

<div class="error"><p><strong><?php _e('Error','spiffy-calendar'); ?>:</strong> <?php _e('The time field must either be blank or be entered in the format hh:mm am/pm','spiffy-calendar'); ?></p></div>

			<?php
		}

		// We check for a valid end time, or an empty one
		$end_time = ($end_time == '')?'00:00:00':date( 'H:i:00',strtotime($end_time) );
		$time_format_one = '/^([0-1][0-9]):([0-5][0-9]):([0-5][0-9])$/';
		$time_format_two = '/^([2][0-3]):([0-5][0-9]):([0-5][0-9])$/';
		if (preg_match($time_format_one,$end_time) || preg_match($time_format_two,$end_time)) {
			$end_time_ok = 1;
		} else { 
			?>

<div class="error"><p><strong><?php _e('Error','spiffy-calendar'); ?>:</strong> <?php _e('The end time field must either be blank or be entered in the format hh:mm am/pm','spiffy-calendar'); ?></p></div>

			<?php
		}

		// We check to make sure the URL is alright
		if (preg_match('/^(http)(s?)(:)\/\//',$linky) || $linky == '') {
			$url_ok = 1;
		} else {
			?>

<div class="error"><p><strong><?php _e('Error','spiffy-calendar'); ?>:</strong> <?php _e('The URL entered must either be prefixed with http:// or be completely blank','spiffy-calendar'); ?></p></div>

			<?php
		}
		// The title must be at least one character in length and no more than 30
		if (preg_match('/^.{1,30}$/',$title)) {
			$title_ok =1;
		} else {
			?>

<div class="error"><p><strong><?php _e('Error','spiffy-calendar'); ?>:</strong> <?php _e('The event title must be between 1 and 30 characters in length','spiffy-calendar'); ?></p></div>

			<?php
		}
		// We run some checks on recurrance
		$repeats = (int)$repeats;
		if (($repeats == 0 && $recur == 'S') || (($repeats >= 0) && ($recur == 'W' || $recur == 'M' || $recur == 'Y' || $recur == 'U'))) {
			$recurring_ok = 1;
		} else {
			?>

<div class="error"><p><strong><?php _e('Error','spiffy-calendar'); ?>:</strong> <?php _e('The repetition value must be 0 unless a type of recurrance is selected in which case the repetition value must be 0 or higher','spiffy-calendar'); ?></p></div>

			<?php
		}

		// Check for image upload
		foreach ( $_FILES as $image ) {
			// if a file was uploaded
			if ($image['size']) {
				// is it an image?
				if (preg_match('/(jpg|jpeg|png|gif)$/i', $image['type'])) {
					$override = array('test_form' => false);
					$uploaded_file = wp_handle_upload($image, $override);

					// Add to the Media library if successful
					if(isset($uploaded_file['file'])) {

	                        	$file_name_and_location = $uploaded_file['file'];
      	                  	$file_title_for_media_library = "Event Image";

	                        	// Set up options array to add this file as an attachment
      	                  	$attachment = array(
            	                  	'post_mime_type' => $uploaded_file['type'],
                  	            	'post_title' => addslashes($file_title_for_media_library),
                        	      	'post_content' => '',
                              		'post_status' => 'inherit'
	                        	);

	                        	$attach_id = wp_insert_attachment( $attachment, $file_name_and_location );
      	                  	require_once(ABSPATH . "wp-admin" . '/includes/image.php');
            	            	$attach_data = wp_generate_attachment_metadata( $attach_id, $file_name_and_location );
                  	      	wp_update_attachment_metadata($attach_id,  $attach_data);

						// Delete previous background image, if any
						if ($event_image != '') {
							wp_delete_attachment( $event_image);
						}

						$event_image = $attach_id;
					} 
				}
			}
		}

		// Done checks - attempt to insert or update
		if (isset($start_date_ok) && isset($end_date_ok) && isset($time_ok) && isset($end_time_ok) && isset($url_ok) && isset($title_ok) && isset($recurring_ok)) {

			// Inspection passed, now add/insert
			$fields = array(
					'event_title' => $title, 
					'event_desc' => $desc, 
					'event_begin' => $begin, 
					'event_end' => $end, 
					'event_time' => $time, 
					'event_end_time' => $end_time, 
					'event_recur' => $recur, 
					'event_repeats' => $repeats, 
					'event_image' => $event_image, 
					'event_author' => $current_user->ID, 
					'event_category' => $category,
				 	'event_link' => mysql_real_escape_string($linky)
					);

			if ($action == 'add') {
				$result = $wpdb->insert(WP_SPIFFYCAL_TABLE, $fields);
			} else {
				$result = $wpdb->update(WP_SPIFFYCAL_TABLE, $fields, array( 'event_id' => $event_id ));
			}
	
			if ($result === false) { 
				if ($action == 'add') {
					?>

<div class="error"><p><strong><?php _e('Error','spiffy-calendar'); ?>:</strong> <?php _e('The event could not be added to the database. This may indicate a problem with your database or the way in which it is configured.','spiffy-calendar'); ?></p></div>

					<?php
				} else {
					?>

<div class="error"><p><strong><?php _e('Error','spiffy-calendar'); ?>:</strong> <?php _e('The event could not be updated. This may indicate a problem with your database or the way in which it is configured.','spiffy-calendar'); ?></p></div>

					<?php
				}
			} else if ($action == 'add') {
				?>

<div class="updated"><p><?php _e('Event added. It will now show in your calendar.','spiffy-calendar'); ?></p></div>

				<?php
			} else if ($action == 'edit_save') {
				?>

<div class="updated"><p><?php _e('Event updated successfully.','spiffy-calendar'); ?></p></div>

				<?php
			}

		} else {
			// The form is going to be rejected due to field validation issues, so we preserve the users entries here
			$users_entries->event_title = $title;
			$users_entries->event_desc = $desc;
			$users_entries->event_begin = $begin;
			$users_entries->event_end = $end;
			$users_entries->event_time = $time;
			$users_entries->event_end_time = $end_time;
			$users_entries->event_recur = $recur;
			$users_entries->event_repeats = $repeats;
			$users_entries->event_image = $event_image;
			$users_entries->event_category = $category;
			$users_entries->event_link = $linky;
		}
	}

	// Deal with deleting an event from the database
	elseif ( $action == 'delete' ) {
		$nonce=$_REQUEST['_wpnonce'];
		if (! wp_verify_nonce($nonce,'spiffy-event-delete-nonce') ) die("Security check failed");

		if ( empty($event_id) )	{
			?>

<div class="error"><p><strong><?php _e('Error','spiffy-calendar'); ?>:</strong> <?php _e("You can't delete an event if you haven't submitted an event id",'spiffy-calendar'); ?></p></div>

			<?php			
		} else {
			// First delete the image, if any
			$sql = "SELECT event_image FROM " . WP_SPIFFYCAL_TABLE . " WHERE event_id='" . mysql_real_escape_string($event_id) . "'";
			$result = $wpdb->get_results($sql);

			// Delete previous background image, if any
			if ( !empty($result) ) {
				wp_delete_attachment( $result[0]->event_image);
			}

			$sql = "DELETE FROM " . WP_SPIFFYCAL_TABLE . " WHERE event_id='" . mysql_real_escape_string($event_id) . "'";
			$wpdb->get_results($sql);
		
			$sql = "SELECT event_id FROM " . WP_SPIFFYCAL_TABLE . " WHERE event_id='" . mysql_real_escape_string($event_id) . "'";
			$result = $wpdb->get_results($sql);
		
			if ( empty($result) || empty($result[0]->event_id) ) {
				?>
				<div class="updated"><p><?php _e('Event deleted successfully','spiffy-calendar'); ?></p></div>
				<?php
			} else {
				?>
				<div class="error"><p><strong><?php _e('Error','spiffy-calendar'); ?>:</strong> <?php _e('Despite issuing a request to delete, the event still remains in the database. Please investigate.','spiffy-calendar'); ?></p></div>
				<?php
			}		
		}
	}

	// Now follows a little bit of code that pulls in the main 
	// components of this page; the edit form and the list of events
	?>

<div class="wrap">
	<?php
	if ( $action == 'edit' || ($action == 'edit_save' && isset($error_with_saving))) {
		?>
		<h2><?php _e('Edit Event','spiffy-calendar'); ?></h2>
		<?php
		if ( empty($event_id) ) {
			echo "<div class=\"error\"><p>".__("You must provide an event id in order to edit it",'spiffy-calendar')."</p></div>";
		} else {
			$this->wp_events_edit_form('edit_save', $event_id);
		}	
	} else {
		?>
		<h2><?php _e('Add Event','spiffy-calendar'); ?></h2>
		<?php $this->wp_events_edit_form(); ?>
	
		<h2><?php _e('Manage Events','spiffy-calendar'); ?></h2>
		<?php
		$this->wp_events_display_list();
	}
	?>
</div>

	<?php
 
	}

	// Display the admin configuration page
	function edit_calendar_config()
	{
		global $wpdb;

		$options = $this->get_options();

		if ( isset( $_POST['spiffy_edit_style'] ) ) {
			$nonce = $_REQUEST['_wpnonce'];
			if (! wp_verify_nonce($nonce,'spiffy-calendar-nonce') ) die("Security check failed");

			if ($_POST['permissions'] == 'subscriber') { $options['can_manage_events'] = 'read'; }
			else if ($_POST['permissions'] == 'contributor') { $options['can_manage_events'] = 'edit_posts'; }
			else if ($_POST['permissions'] == 'author') { $options['can_manage_events'] = 'publish_posts'; }
			else if ($_POST['permissions'] == 'editor') { $options['can_manage_events'] = 'moderate_comments'; }
			else if ($_POST['permissions'] == 'admin') { $options['can_manage_events'] = 'manage_options'; }
			else { $options['can_manage_events'] = 'manage_options'; }

			$update_styles = false;
			if ( $options['calendar_style'] != $_POST['style'] ) {
				$options['calendar_style'] = $_POST['style'];
				$this->write_styles ($options['calendar_style']);
			}

			$options['display_upcoming_days'] = $_POST['display_upcoming_days'];

			if ($_POST['display_author'] == 'on') {
				$options['display_author'] = 'true';
			} else {
				$options['display_author'] = 'false';
			}

			if ($_POST['display_detailed'] == 'on') {
				$options['display_detailed'] = 'true';
			} else {
				$options['display_detailed'] = 'false';
			}

			if ($_POST['display_jump'] == 'on') {
				$options['display_jump'] = 'true';
			} else {
				$options['display_jump'] = 'false';
			}

			if ($_POST['display_todays'] == 'on') {
				$options['display_todays'] = 'true';
			} else {
				$options['display_todays'] = 'false';
			}

			if ($_POST['display_upcoming'] == 'on') {
				$options['display_upcoming'] = 'true';
			} else {
				$options['display_upcoming'] = 'false';
			}

			if ($_POST['enable_categories'] == 'on') {
				$options['enable_categories'] = 'true';
			} else {
				$options['enable_categories'] = 'false';
			}

			if ($_POST['enable_new_window'] == 'on') {
				$options['enable_new_window'] = 'true';
			} else {
				$options['enable_new_window'] = 'false';
			}

			// Check to see if we are replacing the original style
			if (isset($_POST['reset_styles'])) {
				if ($_POST['reset_styles'] == 'on') {
					$options['calendar_style'] = $this->default_styles();
				}
			}
			update_option($this->spiffy_options, $options);

			echo "<div class=\"updated\"><p><strong>".__('Settings saved','spiffy-calendar').".</strong></p></div>";
		}

		// Pull the values out of the database that we need for the form
		$allowed_group = $options['can_manage_events'];
		$calendar_style = $options['calendar_style'];

		if ($options['display_author'] == 'true') {
			$yes_disp_author = 'selected="selected"';
			$no_disp_author = '';
		} else {
			$yes_disp_author = '';
			$no_disp_author = 'selected="selected"';
		}

		if ($options['display_detailed'] == 'true') {
			$yes_disp_detailed = 'selected="selected"';
			$no_disp_detailed = '';
		} else {
			$yes_disp_detailed = '';
			$no_disp_detailed = 'selected="selected"';
		}

		if ($options['display_jump'] == 'true') {
			$yes_disp_jump = 'selected="selected"';
			$no_disp_jump = '';
		} else {
			$yes_disp_jump = '';
			$no_disp_jump = 'selected="selected"';
		}

		if ($options['display_todays'] == 'true') {
			$yes_disp_todays = 'selected="selected"';
			$no_disp_todays = '';
		} else {
			$yes_disp_todays = '';
			$no_disp_todays = 'selected="selected"';
		}

		if ($options['display_upcoming'] == 'true') {
			$yes_disp_upcoming = 'selected="selected"';
			$no_disp_upcoming = '';
		} else {
			$yes_disp_upcoming = '';
			$no_disp_upcoming = 'selected="selected"';
		}

		$upcoming_days = $options['display_upcoming_days'];

		if ($options['enable_categories'] == 'true') {
			$yes_enable_categories = 'selected="selected"';
			$no_enable_categories = '';
		} else {
			$yes_enable_categories = '';
			$no_enable_categories = 'selected="selected"';
		}

		if ($options['enable_new_window'] == 'true') {
			$yes_enable_new_window = 'selected="selected"';
			$no_enable_new_window = '';
		} else {
			$yes_enable_new_window = '';
			$no_enable_new_window = 'selected="selected"';
		}

		$subscriber_selected = '';
		$contributor_selected = '';
		$author_selected = '';
		$editor_selected = '';
		$admin_selected = '';
		if ($allowed_group == 'read') { $subscriber_selected='selected="selected"';}
		else if ($allowed_group == 'edit_posts') { $contributor_selected='selected="selected"';}
		else if ($allowed_group == 'publish_posts') { $author_selected='selected="selected"';}
		else if ($allowed_group == 'moderate_comments') { $editor_selected='selected="selected"';}
		else if ($allowed_group == 'manage_options') { $admin_selected='selected="selected"';}

		// Now we render the form
		?>
<!-- Spiffy Calendar -->
<style type="text/css">
<!--
.error {
	background: lightcoral;
	border: 1px solid #e64f69;
	margin: 1em 5% 10px;
	padding: 0 1em 0 1em;
}

.center { 
	text-align: center; 
}
.right { 
	text-align: right; 
}
.left { 
	text-align: left; 
}
.top { 
	vertical-align: top; 
}
.bold { 
	font-weight: bold; 
}
.private { 
	color: #e64f69; 
}
//-->					
</style>

<div class="wrap">
	<h2><?php _e('Spiffy Calendar Options','spiffy-calendar'); ?></h2>
	<form name="quoteform" id="quoteform" class="wrap" class="wrap" method="post" action="<?php echo admin_url('admin.php?page=spiffy-calendar-config'); ?>">
		<div><input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce('spiffy-calendar-nonce'); ?>" /></div>
		<div><input type="hidden" value="true" name="spiffy_edit_style" />
		<div id="linkadvanceddiv" class="postbox">
			<div style="float: left; width: 98%; clear: both;" class="inside">
				<table cellpadding="5" cellspacing="5">
				<tr>
				<td><legend><?php _e('Choose the lowest user group that may manage events','spiffy-calendar'); ?></legend></td>
				<td><select name="permissions">
					<option value="subscriber"<?php echo $subscriber_selected ?>><?php _e('Subscriber','spiffy-calendar')?></option>
					<option value="contributor" <?php echo $contributor_selected ?>><?php _e('Contributor','spiffy-calendar')?></option>
					<option value="author" <?php echo $author_selected ?>><?php _e('Author','spiffy-calendar')?></option>
					<option value="editor" <?php echo $editor_selected ?>><?php _e('Editor','spiffy-calendar')?></option>
					<option value="admin" <?php echo $admin_selected ?>><?php _e('Administrator','spiffy-calendar')?></option>
				  </select>
				</td>
				</tr>
				<tr>
				<td><legend><?php _e('Do you want to display the author name on events?','spiffy-calendar'); ?></legend></td>
				<td><select name="display_author">
					<option value="on" <?php echo $yes_disp_author ?>><?php _e('Yes','spiffy-calendar') ?></option>
					<option value="off" <?php echo $no_disp_author ?>><?php _e('No','spiffy-calendar') ?></option>
				  </select>
				</td>
				</tr>
				<tr style="vertical-align:top;">
				<td><legend><?php _e('Do you want to enable detailed event display?','spiffy-calendar'); ?></legend></td>
				<td><select name="display_detailed">
					<option value="on" <?php echo $yes_disp_detailed ?>><?php _e('Yes','spiffy-calendar') ?></option>
					<option value="off" <?php echo $no_disp_detailed ?>><?php _e('No','spiffy-calendar') ?></option>
				  </select>
				  <a style="cursor:pointer;" title="Click for help" onclick="toggleVisibility('detailed_display_tip');">?</a>
				  <div id="detailed_display_tip" style="max-width:500px; display:none; margin-left:20px;"><small><em><?php _e('When this option is enabled the time and image will be listed with the event title. Note that time and image are always displayed in the popup window.'); ?></em></small></div>
				</td>
				</tr>
				<tr>
				<td><legend><?php _e('Display a jumpbox for changing month and year quickly?','spiffy-calendar'); ?></legend></td>
				<td><select name="display_jump">
					 <option value="on" <?php echo $yes_disp_jump ?>><?php _e('Yes','spiffy-calendar') ?></option>
					 <option value="off" <?php echo $no_disp_jump ?>><?php _e('No','spiffy-calendar') ?></option>
				  </select>
				</td>
				</tr>
				<tr>
				<td><legend><?php _e('Display todays events?','spiffy-calendar'); ?></legend></td>
				<td><select name="display_todays">
					<option value="on" <?php echo $yes_disp_todays ?>><?php _e('Yes','spiffy-calendar') ?></option>
					<option value="off" <?php echo $no_disp_todays ?>><?php _e('No','spiffy-calendar') ?></option>
				  </select>
				</td>
				</tr>
				<tr>
				<td><legend><?php _e('Display upcoming events?','spiffy-calendar'); ?></legend></td>
				<td><select name="display_upcoming">
					<option value="on" <?php echo $yes_disp_upcoming ?>><?php _e('Yes','spiffy-calendar') ?></option>
					<option value="off" <?php echo $no_disp_upcoming ?>><?php _e('No','spiffy-calendar') ?></option>
				  </select>
	<?php _e('for','spiffy-calendar'); ?> <input type="text" name="display_upcoming_days" value="<?php echo $upcoming_days ?>" size="1" maxlength="3" /> <?php _e('days into the future','spiffy-calendar'); ?>
				</td>
				</tr>
				<tr>
				<td><legend><?php _e('Enable event categories?','spiffy-calendar'); ?></legend></td>
				<td><select name="enable_categories">
					<option value="on" <?php echo $yes_enable_categories ?>><?php _e('Yes','spiffy-calendar') ?></option>
					<option value="off" <?php echo $no_enable_categories ?>><?php _e('No','spiffy-calendar') ?></option>
				  </select>
				</td>
				</tr>
				<tr>
				<td><legend><?php _e('Open event links in new window?','spiffy-calendar'); ?></legend></td>
				<td><select name="enable_new_window">
					<option value="on" <?php echo $yes_enable_new_window ?>><?php _e('Yes','spiffy-calendar') ?></option>
					<option value="off" <?php echo $no_enable_new_window ?>><?php _e('No','spiffy-calendar') ?></option>
				  </select>
				</td>
				</tr>
				<tr>
				<td style="vertical-align:top;"><legend><?php _e('Configure the stylesheet for Calendar','spiffy-calendar'); ?></legend></td>
				<td><textarea name="style" rows="10" cols="60" tabindex="2"><?php echo $calendar_style; ?></textarea><br />
				<input type="checkbox" name="reset_styles" /> <?php _e('Tick this box if you wish to reset the Calendar style to default','spiffy-calendar'); ?></td>
				</tr>
				</table>
			</div>
			<div style="clear:both; height:1px;">&nbsp;</div>
		</div>
		<input type="submit" name="save" class="button bold" value="<?php _e('Save','spiffy-calendar'); ?> &raquo;" />
	</form>
</div>
		<?php
	}

	// Function to handle the management of categories
	function manage_categories()
	{
		global $wpdb;

		?>
		<style type="text/css">
		<!--
		 .error {
				 background: lightcoral;
				 border: 1px solid #e64f69;
				 margin: 1em 5% 10px;
				 padding: 0 1em 0 1em;
		 }

		.center {
				text-align: center;
		}
		.right {
				text-align: right;
		}
		.left {
				text-align: left;
		}
		.top {
				vertical-align: top;
		}
		.bold {
				font-weight: bold;
		}
		.private {
		color: #e64f69;
		}
		//-->
				 
		</style>

		<?php
		// We do some checking to see what we're doing
		if (isset($_POST['mode']) && $_POST['mode'] == 'add') {

			$nonce = $_REQUEST['_wpnonce'];
			if (! wp_verify_nonce($nonce,'spiffy-add-category-nonce') ) die("Security check failed");

			// Proceed with the save		
			$sql = "INSERT INTO " . WP_SPIFFYCAL_CATEGORIES_TABLE . " SET category_name='".mysql_real_escape_string($_POST['category_name'])."', category_colour='".mysql_real_escape_string($_POST['category_colour'])."'";
			$wpdb->get_results($sql);
			echo "<div class=\"updated\"><p><strong>".__('Category added successfully','spiffy-calendar')."</strong></p></div>";

		} else if (isset($_POST['mode']) && isset($_POST['category_id']) && $_POST['mode'] == 'delete') {

			$nonce = $_REQUEST['_wpnonce'];
			if (! wp_verify_nonce($nonce,'spiffy-delete-category-nonce') ) die("Security check failed");

			$sql = "DELETE FROM " . WP_SPIFFYCAL_CATEGORIES_TABLE . " WHERE category_id=".$_POST['category_id'];
			$wpdb->get_results($sql);
			$sql = "UPDATE " . WP_SPIFFYCAL_TABLE . " SET event_category=1 WHERE event_category=".$_POST['category_id'];
			$wpdb->get_results($sql);
			echo "<div class=\"updated\"><p><strong>".__('Category deleted successfully','spiffy-calendar')."</strong></p></div>";

		} else if (isset($_GET['mode']) && isset($_GET['category_id']) && $_GET['mode'] == 'edit' && !isset($_POST['mode'])) {

			$sql = "SELECT * FROM " . WP_SPIFFYCAL_CATEGORIES_TABLE . " WHERE category_id=".intval(mysql_real_escape_string($_GET['category_id']));
			$cur_cat = $wpdb->get_row($sql);
			?>
	<div class="wrap">
		<h2><?php _e('Edit Category','spiffy-calendar'); ?></h2>
		<form name="catform" id="catform" class="wrap" method="post" action="<?php echo admin_url('admin.php?page=spiffy-calendar-categories'); ?>">
			<input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce('spiffy-edit-category-nonce'); ?>" />
			<input type="hidden" name="mode" value="edit" />
			<input type="hidden" name="category_id" value="<?php echo $cur_cat->category_id; ?>" />
			<div id="linkadvanceddiv" class="postbox">
				<div style="float: left; width: 98%; clear: both;" class="inside">
					<table cellpadding="5" cellspacing="5">
					<tr>
					<td><legend><?php _e('Category Name','spiffy-calendar'); ?>:</legend></td>
					<td><input type="text" name="category_name" class="input" size="30" maxlength="30" value="<?php echo stripslashes($cur_cat->category_name); ?>" /></td>
					</tr>
					<tr>
					<td><legend><?php _e('Category Colour (Hex format)','spiffy-calendar'); ?>:</legend></td>
					<td><input type="text" name="category_colour" class="input" size="10" maxlength="7" value="<?php echo $cur_cat->category_colour ?>" /></td>
					</tr>
					</table>
				</div>
				<div style="clear:both; height:1px;">&nbsp;</div>
			</div>
			<input type="submit" name="save" class="button bold" value="<?php _e('Save','spiffy-calendar'); ?> &raquo;" />
		</form>
	</div>
		<?php
		} else if (isset($_POST['mode']) && isset($_POST['category_id']) && isset($_POST['category_name']) && isset($_POST['category_colour']) && $_POST['mode'] == 'edit') {

			$nonce = $_REQUEST['_wpnonce'];
			if (! wp_verify_nonce($nonce,'spiffy-edit-category-nonce') ) die("Security check failed");

			// Proceed with the save
			$sql = "UPDATE " . WP_SPIFFYCAL_CATEGORIES_TABLE . " SET category_name='".mysql_real_escape_string($_POST['category_name'])."', category_colour='".mysql_real_escape_string($_POST['category_colour'])."' WHERE category_id=".mysql_real_escape_string($_POST['category_id']);
			$wpdb->get_results($sql);
			echo "<div class=\"updated\"><p><strong>".__('Category edited successfully','spiffy-calendar')."</strong></p></div>";
		}

		$get_mode = 0;
		$post_mode = 0;
		if (isset($_GET['mode'])) {
			if ($_GET['mode'] == 'edit') {
				$get_mode = 1;
			}
		}
		if (isset($_POST['mode'])) {
			if ($_POST['mode'] == 'edit') {
				$post_mode = 1;
			}
		}
		if ($get_mode != 1 || $post_mode == 1) {
			?>

	<div class="wrap">
		<h2><?php _e('Add Category','spiffy-calendar'); ?></h2>
		<form name="catform" id="catform" class="wrap" method="post" action="<?php echo admin_url('admin.php?page=spiffy-calendar-categories'); ?>">
			<input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce('spiffy-add-category-nonce'); ?>" />
			<input type="hidden" name="mode" value="add" />
			<input type="hidden" name="category_id" value="">
			<div id="linkadvanceddiv" class="postbox">
				<div style="float: left; width: 98%; clear: both;" class="inside">
					<table cellspacing="5" cellpadding="5">
					<tr>
					<td><legend><?php _e('Category Name','spiffy-calendar'); ?>:</legend></td>
					<td><input type="text" name="category_name" class="input" size="30" maxlength="30" value="" /></td>
					</tr>
					<tr>
					<td><legend><?php _e('Category Colour (Hex format)','spiffy-calendar'); ?>:</legend></td>
					<td><input type="text" name="category_colour" class="input" size="10" maxlength="7" value="" /></td>
					</tr>
					</table>
				</div>
				<div style="clear:both; height:1px;">&nbsp;</div>
			</div>
			<input type="submit" name="save" class="button bold" value="<?php _e('Save','spiffy-calendar'); ?> &raquo;" />
		</form>
		<h2><?php _e('Manage Categories','spiffy-calendar'); ?></h2>
			<?php
				
			// We pull the categories from the database	
			$categories = $wpdb->get_results("SELECT * FROM " . WP_SPIFFYCAL_CATEGORIES_TABLE . " ORDER BY category_id ASC");

			if ( !empty($categories) ) {
				 ?>
		<table class="widefat page fixed" width="50%" cellpadding="3" cellspacing="3">
		<thead> 
		<tr>
			 <th class="manage-column" scope="col"><?php _e('ID','spiffy-calendar') ?></th>
			 <th class="manage-column" scope="col"><?php _e('Category Name','spiffy-calendar') ?></th>
			 <th class="manage-column" scope="col"><?php _e('Category Colour','spiffy-calendar') ?></th>
			 <th class="manage-column" scope="col"><?php _e('Edit','spiffy-calendar') ?></th>
			 <th class="manage-column" scope="col"><?php _e('Delete','spiffy-calendar') ?></th>
		</tr>
		</thead>
				<?php
				$class = '';
				foreach ( $categories as $category ) {
					 $class = ($class == 'alternate') ? '' : 'alternate';
					 ?>
		 <tr class="<?php echo $class; ?>">
			 <th scope="row"><?php echo $category->category_id; ?></th>
			 <td><?php echo stripslashes($category->category_name); ?></td>
			 <td style="background-color:<?php echo $category->category_colour; ?>;">&nbsp;</td>
			 <td><a href="<?php echo bloginfo('wpurl')		?>/wp-admin/admin.php?page=spiffy-calendar-categories&amp;mode=edit&amp;category_id=<?php echo $category->category_id;?>" class='edit'><?php echo __('Edit','spiffy-calendar'); ?></a>			</td>
					 <?php
					if ($category->category_id == 1) {
						echo '<td>'.__('N/A','spiffy-calendar').'</td>';
 					} else {
 						?>
			 <td><form name="catform" id="catform" class="wrap" method="post" action="<?php echo admin_url('admin.php?page=spiffy-calendar-categories'); ?>">

				<input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce('spiffy-delete-category-nonce'); ?>" />
				<input type="hidden" name="mode" value="delete" />
				<input type="hidden" name="category_id" value="<?php echo $category->category_id; ?>" />
				<input type="submit" name="delete" class="button bold" value="<?php _e('Delete','spiffy-calendar'); ?> &raquo;" onclick="return confirm('<?php echo __('Are you sure you want to delete the category named &quot;','spiffy-calendar').$category->category_name.'&quot;?'; ?>')" />

			</form></td>
						<?php
 					}
					?>
		</tr>
					<?php
				}
				?>
		</table>
				<?php
			} else {
				 echo '<p>'.__('There are no categories in the database - something has gone wrong!','spiffy-calendar').'</p>';
		 	}
		 	?>
		</div>

			<?php
		} 
	}

	// Function to indicate the number of the day passed, eg. 1st or 2nd Sunday
	function np_of_day($date)
	{
		$instance = 0;
		$dom = date('j',strtotime($date));
		if (($dom-7) <= 0) { $instance = 1; }
		else if (($dom-7) > 0 && ($dom-7) <= 7) { $instance = 2; }
		else if (($dom-7) > 7 && ($dom-7) <= 14) { $instance = 3; }
		else if (($dom-7) > 14 && ($dom-7) <= 21) { $instance = 4; }
		else if (($dom-7) > 21 && ($dom-7) < 28) { $instance = 5; }
		return $instance;
	}

	// Function to provide date of the nth day passed (eg. 2nd Sunday)
	function dt_of_sun($date,$instance,$day)
	{
		$plan = array();
		$plan['Mon'] = 1;
		$plan['Tue'] = 2;
		$plan['Wed'] = 3;
		$plan['Thu'] = 4;
		$plan['Fri'] = 5;
		$plan['Sat'] = 6;
		$plan['Sun'] = 7;
		$proper_date = date('Y-m-d',strtotime($date));
		$begin_month = substr($proper_date,0,8).'01'; 
		$offset = $plan[date('D',strtotime($begin_month))]; 
		$result_day = 0;
		$recon = 0;
		if (($day-($offset)) < 0) { $recon = 7; }
		if ($instance == 1) { $result_day = $day-($offset-1)+$recon; }
		else if ($instance == 2) { $result_day = $day-($offset-1)+$recon+7; }
		else if ($instance == 3) { $result_day = $day-($offset-1)+$recon+14; }
		else if ($instance == 4) { $result_day = $day-($offset-1)+$recon+21; }
		else if ($instance == 5) { $result_day = $day-($offset-1)+$recon+28; }
		return substr($proper_date,0,8).$result_day;
	}

	// Function to return a prefix which will allow the correct 
	// placement of arguments into the query string.
	function permalink_prefix()
	{
		// Get the permalink structure from WordPress
		if (is_home()) { 
				$p_link = get_bloginfo('url'); 
				if ($p_link[strlen($p_link)-1] != '/') { $p_link = $p_link.'/'; }
		} else { 
				$p_link = get_permalink(); 
		}

		// Based on the structure, append the appropriate ending
		if (!(strstr($p_link,'?'))) { $link_part = $p_link.'?'; } else { $link_part = $p_link.'&'; }

		return $link_part;
	}

	// Configure the "Next" link in the calendar
	function next_link($cur_year,$cur_month,$minical = false)
	{
		$mod_rewrite_months = array(1=>'jan','feb','mar','apr','may','jun','jul','aug','sept','oct','nov','dec');
		$next_year = $cur_year + 1;

		if ($cur_month == 12) {
			if ($minical) { $rlink = ''; } else { $rlink = __('Next','spiffy-calendar'); }
			return '<a href="' . $this->permalink_prefix() . 'month=jan&amp;yr=' . $next_year . '">'.$rlink.' &raquo;</a>';
		} else {
			$next_month = $cur_month + 1;
			$month = $mod_rewrite_months[$next_month];
			if ($minical) { $rlink = ''; } else { $rlink = __('Next','spiffy-calendar'); }
			return '<a href="' . $this->permalink_prefix() . 'month='.$month.'&amp;yr=' . $cur_year . '">'.$rlink.' &raquo;</a>';
		}
	}

	// Configure the "Previous" link in the calendar
	function prev_link($cur_year,$cur_month,$minical = false)
	{
		$mod_rewrite_months = array(1=>'jan','feb','mar','apr','may','jun','jul','aug','sept','oct','nov','dec');
		$last_year = $cur_year - 1;

		if ($cur_month == 1) {
			if ($minical) { $llink = ''; } else { $llink = __('Prev','spiffy-calendar'); }
			return '<a href="' . $this->permalink_prefix() . 'month=dec&amp;yr='. $last_year .'">&laquo; '.$llink.'</a>';
		} else {
			$next_month = $cur_month - 1;
			$month = $mod_rewrite_months[$next_month];
			if ($minical) { $llink = ''; } else { $llink = __('Prev','spiffy-calendar'); }
			return '<a href="' . $this->permalink_prefix() . 'month='.$month.'&amp;yr=' . $cur_year . '">&laquo; '.$llink.'</a>';
		}
	}

	// Print upcoming events
	function upcoming_events($cat_list = '')
	{
		global $wpdb;

		$options = $this->get_options();

		// Find out if we should be displaying upcoming events
		$display = $options['display_upcoming'];
		
		if ($display == 'true') {
			// Get number of days we should go into the future 
			$future_days = $options['display_upcoming_days'];
			$day_count = 1;
						
			$output = '';
			while ($day_count < $future_days+1)	{
				list($y,$m,$d) = explode("-",date("Y-m-d",mktime($day_count*24,0,0,date("m",$this->ctwo()),date("d",$this->ctwo()),date("Y",$this->ctwo()))));
				$events = $this->grab_events($y,$m,$d,'upcoming',$cat_list);
				usort($events, array($this, 'time_cmp'));
				if (count($events) != 0) {
					$output .= '<li>';
					$output .= date_i18n(get_option('date_format'),
								mktime($day_count*24,0,0,date("m",$this->ctwo()),date("d",$this->ctwo()),date("Y",$this->ctwo())));
					$output .= '<ul>';
				} 
				foreach($events as $event) {
					$output .= '<li>'.$this->draw_event($event).'</li>';
				}
				if (count($events) != 0) {
					$output .= '</ul></li>';
				}
				$day_count = $day_count+1;
			}

			if ($output != '') {
				$visual = '<ul>';
				$visual .= $output;
				$visual .= '</ul>';
				return $visual;
			} 
		}
	}

	// Print todays events
	function todays_events($cat_list = '')
	{
		global $wpdb;

		$options = $this->get_options();

		// Find out if we should be displaying todays events
		$display = $options['display_todays'];

		if ($display == 'true') {
			$output = '<ul>';
			$events = $this->grab_events(date("Y",$this->ctwo()),date("m",$this->ctwo()),date("d",$this->ctwo()),'todays',$cat_list);
			usort($events, array($this, 'time_cmp'));
			foreach($events as $event) {
				$output .= '<li>'.$this->draw_event($event).'</li>';
			}
			$output .= '</ul>';
			if (count($events) != 0) {
				return $output;
			} 
		}
	}

	// Function to compare time in event objects
	function time_cmp($a, $b)
	{
		if ($a->event_time == $b->event_time) {
				return 0;
		}
		return ($a->event_time < $b->event_time) ? -1 : 1;
	}

	// Used to draw multiple events
	function draw_events($events)
	{
		// We need to sort arrays of objects by time
		usort($events, array($this, 'time_cmp'));
		$output = '';
		// Now process the events
		foreach($events as $event) {
			$output .= $this->draw_event($event).'
';
		}
		return $output;
	}

	// The widget to show the mini calendar
	function widget_init_events_calendar() 
	{ 
		// Check for required function s
		if (!function_exists('wp_register_sidebar_widget'))
			return;

		wp_register_sidebar_widget('spiffy_events_calendar',__('Mini Calendar','spiffy-calendar'),
			array($this, 'widget_events_calendar'),
			array('description'=>'A calendar of your events'));
		wp_register_widget_control('spiffy_events_calendar','spiffy_events_calendar',
			array($this, 'widget_events_calendar_control'));
	}

	function widget_events_calendar($args) {
		extract($args);
		$the_title = stripslashes(get_option('spiffy_calendar_widget_title'));
		$the_cats = stripslashes(get_option('spiffy_calendar_widget_cats'));
		$widget_title = empty($the_title) ? __('Calendar','spiffy-calendar') : $the_title;
		$the_events = $this->minical($the_cats);
		if ($the_events != '') {
			echo $before_widget;
			echo $before_title . $widget_title . $after_title;
			echo '<br />'.$the_events;
			echo $after_widget;
		}
	}

	function widget_events_calendar_control() {
		$widget_title = stripslashes(get_option('spiffy_calendar_widget_title'));
		$widget_cats = stripslashes(get_option('spiffy_calendar_widget_cats'));
		if (isset($_POST['events_calendar_widget_title']) || isset($_POST['events_calendar_widget_cats'])) {
			update_option('spiffy_calendar_widget_title',strip_tags($_POST['events_calendar_widget_title']));
			update_option('spiffy_calendar_widget_cats',strip_tags($_POST['events_calendar_widget_cats']));
		}
		?>
		<p>
<label for="events_calendar_widget_title"><?php _e('Title','spiffy-calendar'); ?>:<br />
<input class="widefat" type="text" id="events_calendar_widget_title" name="events_calendar_widget_title" value="<?php echo $widget_title; ?>"/></label>
<label for="events_calendar_widget_cats"><?php _e('Comma separated category id list','spiffy-calendar'); ?>:<br />
<input class="widefat" type="text" id="events_calendar_widget_cats" name="events_calendar_widget_cats" value="<?php echo $widget_cats; ?>"/></label>
		</p>
		<?php
	}

	// The widget to show todays events in the sidebar
	function widget_init_calendar_today() 
	{
		// Check for required function s
		if (!function_exists('wp_register_sidebar_widget'))
			return;

		wp_register_sidebar_widget('spiffy_todays_events_calendar',__('Today\'s Events','spiffy-calendar'), 
						array($this, 'widget_calendar_today'),
						array('description'=>'A list of your events today'));
		wp_register_widget_control('spiffy_todays_events_calendar','spiffy_todays_events_calendar', array($this, 'widget_calendar_today_control'));
	}

	function widget_calendar_today($args) {
		extract($args);
		$the_title = stripslashes(get_option('spiffy_calendar_today_widget_title'));
		$the_cats = stripslashes(get_option('spiffy_calendar_today_widget_cats'));
		$widget_title = empty($the_title) ? __('Today\'s Events','spiffy-calendar') : $the_title;
		$the_events = $this->todays_events($the_cats);
		if ($the_events != '') {
			echo $before_widget;
			echo $before_title . $widget_title . $after_title;
			echo $the_events;
			echo $after_widget;
		}
	}

	function widget_calendar_today_control() {
		$widget_title = stripslashes(get_option('spiffy_calendar_today_widget_title'));
		$widget_cats = stripslashes(get_option('spiffy_calendar_today_widget_cats'));
		if (isset($_POST['calendar_today_widget_title']) || isset($_POST['calendar_today_widget_cats'])) {
			update_option('spiffy_calendar_today_widget_title',strip_tags($_POST['calendar_today_widget_title']));
			update_option('spiffy_calendar_today_widget_cats',strip_tags($_POST['calendar_today_widget_cats']));
		}
		?>
		<p>
<label for="calendar_today_widget_title"><?php _e('Title','spiffy-calendar'); ?>:<br />
<input class="widefat" type="text" id="calendar_today_widget_title" name="calendar_today_widget_title" value="<?php echo $widget_title; ?>"/></label>
<label for="calendar_today_widget_cats"><?php _e('Comma separated category id list','spiffy-calendar'); ?>:<br />
<input class="widefat" type="text" id="calendar_today_widget_cats" name="calendar_today_widget_cats" value="<?php echo $widget_cats; ?>"/></label>
		</p>
		<?php
	}

	// The widget to show todays events in the sidebar				
	function widget_init_calendar_upcoming() {
		// Check for required function s
		if (!function_exists('wp_register_sidebar_widget'))
				return;

		wp_register_sidebar_widget( 'spiffy_upcoming_events_calendar', __('Upcoming Events','spiffy-calendar'), 
							array($this, 'widget_calendar_upcoming'), array('description'=>'A list of your upcoming events'));
		wp_register_widget_control('spiffy_upcoming_events_calendar','spiffy_upcoming_events_calendar',
							array($this, 'widget_calendar_upcoming_control'));
	}

	function widget_calendar_upcoming($args) {
		extract($args);
		$the_title = stripslashes(get_option('spiffy_calendar_upcoming_widget_title'));
		$the_cats = stripslashes(get_option('spiffy_calendar_upcoming_widget_cats'));
		$widget_title = empty($the_title) ? __('Upcoming Events','spiffy-calendar') : $the_title;
		$the_events = $this->upcoming_events($the_cats);
		if ($the_events != '') {
			echo $before_widget;
			echo $before_title . $widget_title . $after_title;
			echo $the_events;
			echo $after_widget;
		}
	}

	function widget_calendar_upcoming_control() {
		$widget_title = stripslashes(get_option('spiffy_calendar_upcoming_widget_title'));
		$widget_cats = stripslashes(get_option('spiffy_calendar_upcoming_widget_cats'));
		if (isset($_POST['calendar_upcoming_widget_title']) || isset($_POST['calendar_upcoming_widget_cats'])) {
			update_option('spiffy_calendar_upcoming_widget_title',strip_tags($_POST['calendar_upcoming_widget_title']));
			update_option('spiffy_calendar_upcoming_widget_cats',strip_tags($_POST['calendar_upcoming_widget_cats']));
		}
		?>
		<p>
<label for="calendar_upcoming_widget_title"><?php _e('Title','spiffy-calendar'); ?>:<br />
<input class="widefat" type="text" id="calendar_upcoming_widget_title" name="calendar_upcoming_widget_title" value="<?php echo $widget_title; ?>"/></label>
<label for="calendar_upcoming_widget_cats"><?php _e('Comma separated category id list','spiffy-calendar'); ?>:<br />
<input class="widefat" type="text" id="calendar_upcoming_widget_cats" name="calendar_upcoming_widget_cats" value="<?php echo $widget_cats; ?>"/></label>
		</p>
		<?php
	}

	// Read the categories into memory once when drawing events
	function get_all_categories() 
	{
		global $wpdb;

		if (count($this->categories) > 0) return;
		$sql = "SELECT * FROM " . WP_SPIFFYCAL_CATEGORIES_TABLE;
		$this->categories = $wpdb->get_results($sql);
	}

	// Used to draw an event to the screen
	function draw_event($event)
	{
		global $wpdb;

		$options = $this->get_options();
		$this->get_all_categories();

		$style = '';
		if ($options['enable_categories'] == 'true') {
			foreach ($this->categories as $cat_details) {
				if ($cat_details->category_id == $event->event_category) {
					$style = 'style="color:'.$cat_details->category_colour.';"';
					break;
				}
			}
		}

		// Get time formatted
		if ($event->event_time != "00:00:00") {
			$time = date(get_option('time_format'), strtotime($event->event_time));
		} else {
			$time = "";
		}
		if ($event->event_end_time != "00:00:00") {
			$end_time = date(get_option('time_format'), strtotime($event->event_end_time));
		} else {
			$end_time = "";
		}

		$popup_details = '<div class="event-title" '.$style.'>'.stripslashes($event->event_title).'</div><br />';
		$popup_details .= '<div class="event-title-break"></div><br />';
		if ($event->event_time != "00:00:00") {
			$popup_details .= '<strong>'.__('Time','spiffy-calendar').':</strong> ' . $time;
			if ($event->event_end_time != "00:00:00") {
				$popup_details .= ' - ' . $end_time;
			}
			$popup_details .= '<br />';
		}
		if ($event->event_image > 0) {
			$image = wp_get_attachment_image_src( $event->event_image, 'THUMBNAIL');
			$popup_details .= '<img src="' . $image[0] . '" />';
		}
		if ($options['display_author'] == 'true') {
			$e = get_userdata(stripslashes($event->event_author));
			$popup_details .= '<strong>'.__('Posted by', 'spiffy-calendar').':</strong> '.$e->display_name.'<br />';
		}
		if ($options['display_author'] == 'true' || $event->event_time != "00:00:00") {
			$popup_details .= '<div class="event-content-break"></div><br />';
		}
		if ($event->event_link != '') { 
			$linky = stripslashes($event->event_link); 
			if ($options['enable_new_window'] == 'true') {
				$target = ' target="_blank"';
			} else {
				$target = '';
			}
			$show = '';

		} else { 
			$linky = '#'; 
			$target = '';
			$show = ' onclick="if (navigator.userAgent.match(/iPad|iPhone|iPod/i) != null ) jQuery(this).children(\'.popup\').toggle();return false;"';
		}

		$details = '<div class="calnk"><a href="'.$linky.'" '.$style.$target.$show.'><b>' . stripslashes($event->event_title) . '</b>';
		if ($options['display_detailed'] == 'true') {
			if ($time != '') {
				$details .= '<span class="calnk-time"><br />' . $time;
				if ($event->event_end_time != "00:00:00") {
					$details .= ' - ' . $end_time;
				}
				$details .= '</span>';
			}
			if ($event->event_image > 0) {
				$details .= '<br /><img class="calnk-icon" src="' . $image[0] . '" />';
			}
		}
		$details .= '<div class="popup" '.$style.'>' . $popup_details . '' . stripslashes($event->event_desc) . '</div></a></div>';

		return $details;
	}

	// Grab all events for the requested date from calendar
	function grab_events($y,$m,$d,$typing,$cat_list = '')
	{
		global $wpdb;

		$arr_events = array();

		// Get the date format right
		$date = $y . '-' . $m . '-' . $d;

		// Format the category list
		if ($cat_list == '') { $cat_sql = ''; }
		else { $cat_sql = 'AND event_category in ('.$cat_list.')'; }
				 
		// The collated SQL code
		$sql = "SELECT a.*,'Normal' AS type FROM " . WP_SPIFFYCAL_TABLE . " AS a WHERE a.event_begin <= '$date' AND a.event_end >= '$date' AND a.event_recur = 'S' ".$cat_sql." 
UNION ALL 
SELECT b.*,'Yearly' AS type FROM " . WP_SPIFFYCAL_TABLE . " AS b WHERE b.event_recur = 'Y' AND EXTRACT(YEAR FROM '$date') >= EXTRACT(YEAR FROM b.event_begin) AND b.event_repeats = 0 ".$cat_sql." 
UNION ALL 
SELECT c.*,'Yearly' AS type FROM " . WP_SPIFFYCAL_TABLE . " AS c WHERE c.event_recur = 'Y' AND EXTRACT(YEAR FROM '$date') >= EXTRACT(YEAR FROM c.event_begin) AND c.event_repeats != 0 AND (EXTRACT(YEAR FROM '$date')-EXTRACT(YEAR FROM c.event_begin)) <= c.event_repeats ".$cat_sql." 
UNION ALL 
SELECT d.*,'Monthly' AS type FROM " . WP_SPIFFYCAL_TABLE . " AS d WHERE d.event_recur = 'M' AND EXTRACT(YEAR FROM '$date') >= EXTRACT(YEAR FROM d.event_begin) AND d.event_repeats = 0 ".$cat_sql." 
UNION ALL
SELECT e.*,'Monthly' AS type FROM " . WP_SPIFFYCAL_TABLE . " AS e WHERE e.event_recur = 'M' AND EXTRACT(YEAR FROM '$date') >= EXTRACT(YEAR FROM e.event_begin) AND e.event_repeats != 0 AND (PERIOD_DIFF(EXTRACT(YEAR_MONTH FROM '$date'),EXTRACT(YEAR_MONTH FROM e.event_begin))) <= e.event_repeats ".$cat_sql." 
UNION ALL
SELECT f.*,'MonthSun' AS type FROM " . WP_SPIFFYCAL_TABLE . " AS f WHERE f.event_recur = 'U' AND EXTRACT(YEAR FROM '$date') >= EXTRACT(YEAR FROM f.event_begin) AND f.event_repeats = 0 ".$cat_sql." 
UNION ALL
SELECT g.*,'MonthSun' AS type FROM " . WP_SPIFFYCAL_TABLE . " AS g WHERE g.event_recur = 'U' AND EXTRACT(YEAR FROM '$date') >= EXTRACT(YEAR FROM g.event_begin) AND g.event_repeats != 0 AND (PERIOD_DIFF(EXTRACT(YEAR_MONTH FROM '$date'),EXTRACT(YEAR_MONTH FROM g.event_begin))) <= g.event_repeats ".$cat_sql." 
UNION ALL
SELECT h.*,'Weekly' AS type FROM " . WP_SPIFFYCAL_TABLE . " AS h WHERE h.event_recur = 'W' AND '$date' >= h.event_begin AND h.event_repeats = 0 ".$cat_sql." 
UNION ALL
SELECT i.*,'Weekly' AS type FROM " . WP_SPIFFYCAL_TABLE . " AS i WHERE i.event_recur = 'W' AND '$date' >= i.event_begin AND i.event_repeats != 0 AND (i.event_repeats*7) >= (TO_DAYS('$date') - TO_DAYS(i.event_end)) ".$cat_sql." 
ORDER BY event_id";

		// Run the collated code
		$events =$wpdb->get_results($sql);
		if (!empty($events)) {
			foreach($events as $event) {
				if ($event->type == 'Normal') {
					array_push($arr_events, $event);
 				} else if ($event->type == 'Yearly') {
					// This is going to get complex so lets setup what we would place in for
					// an event so we can drop it in with ease

					// Technically we don't care about the years, but we need to find out if the
					// event spans the turn of a year so we can deal with it appropriately.
					$year_begin = date('Y',strtotime($event->event_begin));
					$year_end = date('Y',strtotime($event->event_end));

					if ($year_begin == $year_end) {
						if (date('m-d',strtotime($event->event_begin)) <= date('m-d',strtotime($date)) &&
							 date('m-d',strtotime($event->event_end)) >= date('m-d',strtotime($date))) {
							array_push($arr_events, $event);
	 					}
					} else if ($year_begin < $year_end) {
						if (date('m-d',strtotime($event->event_begin)) <= date('m-d',strtotime($date)) ||
							 date('m-d',strtotime($event->event_end)) >= date('m-d',strtotime($date))) {
							array_push($arr_events, $event);
	 					}
					}
 				} else if ($event->type == 'Monthly') {
					// This is going to get complex so lets setup what we would place in for
					// an event so we can drop it in with ease

					// Technically we don't care about the years or months, but we need to find out if the
					// event spans the turn of a year or month so we can deal with it appropriately.
					$month_begin = date('m',strtotime($event->event_begin));
					$month_end = date('m',strtotime($event->event_end));

					if (($month_begin == $month_end) && (strtotime($event->event_begin) <= strtotime($date))) {
						if (date('d',strtotime($event->event_begin)) <= date('d',strtotime($date)) &&
							date('d',strtotime($event->event_end)) >= date('d',strtotime($date))) {
							array_push($arr_events, $event);
	 					}
				 	} else if (($month_begin < $month_end) && (strtotime($event->event_begin) <= strtotime($date))) {
						if ( ($event->event_begin <= date('Y-m-d',strtotime($date))) 
							&& (date('d',strtotime($event->event_begin)) <= date('d',strtotime($date)) 
							|| date('d',strtotime($event->event_end)) >= date('d',strtotime($date))) ) {
							array_push($arr_events, $event);
	 					}
				 	}
 				} else if ($event->type == 'MonthSun') {
					// This used to be complex but writing the $this->dt_of_sun() function helped loads!

					// Technically we don't care about the years or months, but we need to find out if the
					// event spans the turn of a year or month so we can deal with it appropriately.
					$month_begin = date('m',strtotime($event->event_begin));
					$month_end = date('m',strtotime($event->event_end));

					// Setup some variables and get some values
					$dow = date('w',strtotime($event->event_begin));
					if ($dow == 0) { $dow = 7; }
					$start_ent_this = $this->dt_of_sun($date,$this->np_of_day($event->event_begin),$dow);
					$start_ent_prev = $this->dt_of_sun(date('Y-m-d',strtotime($date.'-1 month')),$this->np_of_day($event->event_begin),$dow);
					$len_ent = strtotime($event->event_end)-strtotime($event->event_begin);

					// The grunt work
					if (($month_begin == $month_end) && (strtotime($event->event_begin) <= strtotime($date))) {
						// The checks
						if (strtotime($event->event_begin) <= strtotime($date) 
							&& strtotime($event->event_end) >= strtotime($date)) {
							// Handle the first occurance
							array_push($arr_events, $event);
	 					}
						else if (strtotime($start_ent_this) <= strtotime($date) 
							&& strtotime($date) <= strtotime($start_ent_this)+$len_ent) {
							// Now remaining items 
							array_push($arr_events, $event);
	 					}
				 	} else if (($month_begin < $month_end) && (strtotime($event->event_begin) <= strtotime($date))) {
						// The checks
						if (strtotime($event->event_begin) <= strtotime($date) 
							&& strtotime($event->event_end) >= strtotime($date)) {
							// Handle the first occurance
							array_push($arr_events, $event);
	 					} else if (strtotime($start_ent_prev) <= strtotime($date) 
							&& strtotime($date) <= strtotime($start_ent_prev)+$len_ent) {
							 // Remaining items from prev month
							array_push($arr_events, $event);
	 					} else if (strtotime($start_ent_this) <= strtotime($date) 
							&& strtotime($date) <= strtotime($start_ent_this)+$len_ent) {
							// Remaining items starting this month
							array_push($arr_events, $event);
	 					}
				 	}
 				} else if ($event->type == 'Weekly') {
					// This is going to get complex so lets setup what we would place in for
					// an event so we can drop it in with ease

					// Now we are going to check to see what day the original event
					// fell on and see if the current date is both after it and on
					// the correct day. If it is, display the event!
					$day_start_event = date('D',strtotime($event->event_begin));
					$day_end_event = date('D',strtotime($event->event_end));
					$current_day = date('D',strtotime($date));

					$plan = array();
					$plan['Mon'] = 1;
					$plan['Tue'] = 2;
					$plan['Wed'] = 3;
					$plan['Thu'] = 4;
					$plan['Fri'] = 5;
					$plan['Sat'] = 6;
					$plan['Sun'] = 7;

					if ($plan[$day_start_event] > $plan[$day_end_event]) {
						if (($plan[$day_start_event] <= $plan[$current_day]) || ($plan[$current_day] <= $plan[$day_end_event])) {
							array_push($arr_events, $event);
	 					}
				 	} else if (($plan[$day_start_event] < $plan[$day_end_event]) || ($plan[$day_start_event]== $plan[$day_end_event])) {
						if (($plan[$day_start_event] <= $plan[$current_day]) && ($plan[$current_day] <= $plan[$day_end_event])) {
							array_push($arr_events, $event);
	 					}
				 	}
 				}
			 }
		}

		return $arr_events;
	}

	// Setup comparison functions for building the calendar later
	function calendar_month_comparison($month)
	{
		$current_month = strtolower(date("M", $this->ctwo()));
		if (isset($_GET['yr']) && isset($_GET['month'])) {
			if ($month == $_GET['month'])	{
				return ' selected="selected"';
			}
		} elseif ($month == $current_month) {
			return ' selected="selected"';
		}
	}

	function calendar_year_comparison($year)
	{
		$current_year = strtolower(date("Y", $this->ctwo()));
		if (isset($_GET['yr']) && isset($_GET['month'])) {
			if ($year == $_GET['yr']) {
				return ' selected="selected"';
			}
		} else if ($year == $current_year) {
			return ' selected="selected"';
		}
	}

	// Actually do the printing of the calendar
	// Compared to searching for and displaying events
	// this bit is really rather easy!
	function calendar($cat_list = '')
	{
		global $wpdb;

		$options = $this->get_options();
		$this->get_all_categories();

		// Deal with the week not starting on a monday
		if (get_option('start_of_week') == 0) {
			$name_days = array(1=>__('Sunday','spiffy-calendar'),
					__('Monday','spiffy-calendar'),__('Tuesday','spiffy-calendar'),__('Wednesday','spiffy-calendar'),
					__('Thursday','spiffy-calendar'),__('Friday','spiffy-calendar'),__('Saturday','spiffy-calendar'));
		} else {
			// Choose Monday if anything other than Sunday is set
			$name_days = array(1=>__('Monday','spiffy-calendar'),
					__('Tuesday','spiffy-calendar'),__('Wednesday','spiffy-calendar'),__('Thursday','spiffy-calendar')
					,__('Friday','spiffy-calendar'),__('Saturday','spiffy-calendar'),__('Sunday','spiffy-calendar'));
		}

		// Carry on with the script
		$name_months = array(1=>__('January','spiffy-calendar'),__('February','spiffy-calendar'),__('March','spiffy-calendar'),
					__('April','spiffy-calendar'),__('May','spiffy-calendar'),__('June','spiffy-calendar'),__('July','spiffy-calendar'),
					__('August','spiffy-calendar'),__('September','spiffy-calendar'),__('October','spiffy-calendar'),
					__('November','spiffy-calendar'),__('December','spiffy-calendar'));

		// If we don't pass arguments we want a calendar that is relevant to today
		if (empty($_GET['month']) || empty($_GET['yr'])) {
			$c_year = date("Y",$this->ctwo());
			$c_month = date("m",$this->ctwo());
			$c_day = date("d",$this->ctwo());
		}

		// Years get funny if we exceed 3000, so we use this check
		if (isset($_GET['yr'])) {				
			if ($_GET['yr'] <= 3000 && $_GET['yr'] >= 0 && (int)$_GET['yr'] != 0) {
				// This is just plain nasty and all because of permalinks
				// which are no longer used, this will be cleaned up soon
				if ($_GET['month'] == 'jan' || $_GET['month'] == 'feb' || $_GET['month'] == 'mar' || $_GET['month'] == 'apr' || $_GET['month'] == 'may' || $_GET['month'] == 'jun' || $_GET['month'] == 'jul' || $_GET['month'] == 'aug' || $_GET['month'] == 'sept' || $_GET['month'] == 'oct' || $_GET['month'] == 'nov' || $_GET['month'] == 'dec') {

					// Again nasty code to map permalinks into something
					// databases can understand. This will be cleaned up
					$c_year = mysql_real_escape_string($_GET['yr']);
					if ($_GET['month'] == 'jan') { $t_month = 1; }
					else if ($_GET['month'] == 'feb') { $t_month = 2; }
					else if ($_GET['month'] == 'mar') { $t_month = 3; }
					else if ($_GET['month'] == 'apr') { $t_month = 4; }
					else if ($_GET['month'] == 'may') { $t_month = 5; }
					else if ($_GET['month'] == 'jun') { $t_month = 6; }
					else if ($_GET['month'] == 'jul') { $t_month = 7; }
					else if ($_GET['month'] == 'aug') { $t_month = 8; }
					else if ($_GET['month'] == 'sept') { $t_month = 9; }
					else if ($_GET['month'] == 'oct') { $t_month = 10; }
					else if ($_GET['month'] == 'nov') { $t_month = 11; }
					else if ($_GET['month'] == 'dec') { $t_month = 12; }
					$c_month = $t_month;
					$c_day = date("d",$this->ctwo());
				} else {
					// No valid month causes the calendar to default to today
					$c_year = date("Y",$this->ctwo());
					$c_month = date("m",$this->ctwo());
					$c_day = date("d",$this->ctwo());
				}
			}
		} else {
			// No valid year causes the calendar to default to today
			$c_year = date("Y",$this->ctwo());
			$c_month = date("m",$this->ctwo());
			$c_day = date("d",$this->ctwo());
		}

		// Fix the days of the week if week start is not on a monday
		if (get_option('start_of_week') == 0) {
			$first_weekday = date("w",mktime(0,0,0,$c_month,1,$c_year));
			$first_weekday = ($first_weekday==0?1:$first_weekday+1);
		} else {
			// Otherwise assume the week starts on a Monday. Anything other 
			// than Sunday or Monday is just plain odd
			$first_weekday = date("w",mktime(0,0,0,$c_month,1,$c_year));
			$first_weekday = ($first_weekday==0?7:$first_weekday);
		}

		$days_in_month = date("t", mktime (0,0,0,$c_month,1,$c_year));

		// Start the table and add the header and naviagtion
		$calendar_body = '';
		$calendar_body .= '
<table cellspacing="1" cellpadding="0" class="calendar-table">';

		// We want to know if we should display the date switcher
		$date_switcher = $options['display_jump'];

		if ($date_switcher == 'true') {
			$calendar_body .= '
<tr>
	<td colspan="7" class="calendar-date-switcher">
		<form method="get" action="'.htmlspecialchars($_SERVER['REQUEST_URI']).'">';

			if (isset($_SERVER['QUERY_STRING'])) { 
				$qsa = array();
				parse_str($_SERVER['QUERY_STRING'], $qsa);
				foreach ($qsa as $name => $argument) {
					if ($name != 'month' && $name != 'yr') {
						$calendar_body .= '<input type="hidden" name="'.strip_tags($name).'" value="'.strip_tags($argument).'" />';
					}
				}
			}

			// We build the months in the switcher
			$calendar_body .= '
				'.__('Month','spiffy-calendar').': <select name="month" style="width:100px;">
				<option value="jan"'.$this->calendar_month_comparison('jan').'>'.__('January','spiffy-calendar').'</option>
				<option value="feb"'.$this->calendar_month_comparison('feb').'>'.__('February','spiffy-calendar').'</option>
				<option value="mar"'.$this->calendar_month_comparison('mar').'>'.__('March','spiffy-calendar').'</option>
				<option value="apr"'.$this->calendar_month_comparison('apr').'>'.__('April','spiffy-calendar').'</option>
				<option value="may"'.$this->calendar_month_comparison('may').'>'.__('May','spiffy-calendar').'</option>
				<option value="jun"'.$this->calendar_month_comparison('jun').'>'.__('June','spiffy-calendar').'</option>
				<option value="jul"'.$this->calendar_month_comparison('jul').'>'.__('July','spiffy-calendar').'</option> 
				<option value="aug"'.$this->calendar_month_comparison('aug').'>'.__('August','spiffy-calendar').'</option> 
				<option value="sept"'.$this->calendar_month_comparison('sept').'>'.__('September','spiffy-calendar').'</option> 
				<option value="oct"'.$this->calendar_month_comparison('oct').'>'.__('October','spiffy-calendar').'</option> 
				<option value="nov"'.$this->calendar_month_comparison('nov').'>'.__('November','spiffy-calendar').'</option> 
				<option value="dec"'.$this->calendar_month_comparison('dec').'>'.__('December','spiffy-calendar').'</option> 
				</select>
				'.__('Year','spiffy-calendar').': <select name="yr" style="width:60px;">';

			// The year builder is string mania. If you can make sense of this, you know your PHP!
			$past = 1;
			$future = 30;
			$fut = 1;
			$f = '';
			$p = '';
			while ($past > 0) {
				$p .= '					<option value="';
				$p .= date("Y",$this->ctwo())-$past;
				$p .= '"'.$this->calendar_year_comparison(date("Y",$this->ctwo())-$past).'>';
				$p .= date("Y",$this->ctwo())-$past.'</option>';
				$past = $past - 1;
			}
			while ($fut < $future) {
				$f .= '					<option value="';
				$f .= date("Y",$this->ctwo())+$fut;
				$f .= '"'.$this->calendar_year_comparison(date("Y",$this->ctwo())+$fut).'>';
				$f .= date("Y",$this->ctwo())+$fut.'</option>';
				$fut = $fut + 1;
			} 
			$calendar_body .= $p;
			$calendar_body .= '
				<option value="'.date("Y",$this->ctwo()).'"'.$this->calendar_year_comparison(date("Y",$this->ctwo())).'>'.date("Y",$this->ctwo()).'</option>';
			$calendar_body .= $f;
			$calendar_body .= '</select>
			<input type="submit" value="'.__('Go','spiffy-calendar').'" />
		</form>
	</td>
</tr>';
		}

		// The header of the calendar table and the links. Note calls to link function s
		$calendar_body .= '
<tr>
	<td colspan="7" class="calendar-heading">
			<table border="0" cellpadding="0" cellspacing="0" width="100%">
				<tr>
					<td class="calendar-prev">' . $this->prev_link($c_year,$c_month) . '</td>
					<td class="calendar-month">'.$name_months[(int)$c_month].' '.$c_year.'</td>
					<td class="calendar-next">' . $this->next_link($c_year,$c_month) . '</td>
				</tr>
			</table>
	</td>
</tr>';

		// Print the headings of the days of the week
		$calendar_body .= '<tr>';
		for ($i=1; $i<=7; $i++) {
			// Colours need to be different if the starting day of the week is different
			if (get_option('start_of_week') == 0) {
				$calendar_body .= '	<td class="'.($i<7&$i>1?'normal-day-heading':'weekend-heading').'">'.$name_days[$i].'</td>';
			} else {
				$calendar_body .= '	<td class="'.($i<6?'normal-day-heading':'weekend-heading').'">'.$name_days[$i].'</td>';
			}
		}
		$calendar_body .= '</tr>';
		$go = FALSE;
		for ($i=1; $i<=$days_in_month;) {
			$calendar_body .= '<tr>';
			for ($ii=1; $ii<=7; $ii++) {
				if ($ii==$first_weekday && $i==1) {
					$go = TRUE;
				} elseif ($i > $days_in_month ) {
					$go = FALSE;
				}
				if ($go) {
					// Colours again, this time for the day numbers
					if (get_option('start_of_week') == 0) {
						// This bit of code is for styles believe it or not.
						$grabbed_events = $this->grab_events($c_year,$c_month,$i,'spiffy-calendar',$cat_list);
						$no_events_class = '';
						if (!count($grabbed_events)) {
							$no_events_class = ' no-events';
						}
						$calendar_body .= '	<td class="'.(date("Ymd", mktime (0,0,0,$c_month,$i,$c_year))==date("Ymd",$this->ctwo())?'current-day':'day-with-date').$no_events_class.'"><div '.($ii<7&$ii>1?'':'class="weekend"').'>'.$i++.'</div><div class="event">' . $this->draw_events($grabbed_events) . '</div></td>';
					} else {
						$grabbed_events = $this->grab_events($c_year,$c_month,$i,'spiffy-calendar',$cat_list);
						$no_events_class = '';
						if (!count($grabbed_events)) {
							$no_events_class = ' no-events';
						}
						$calendar_body .= '	<td class="'.(date("Ymd", mktime (0,0,0,$c_month,$i,$c_year))==date("Ymd",$this->ctwo())?'current-day':'day-with-date').$no_events_class.'"><div '.($ii<6?'':'class="weekend"').'>'.$i++.'</div><div class="event">' . $this->draw_events($grabbed_events) . '</div></td>';
					}
				} else {
					$calendar_body .= '	<td class="day-without-date">&nbsp;</td>';
				}
			}
			$calendar_body .= '</tr>';
		}
		$calendar_body .= '</table>';

		if ($options['enable_categories'] == 'true') {
			$calendar_body .= '<table class="cat-key">
<tr><td colspan="2" class="cat-key-cell"><strong>'.__('Category Key','spiffy-calendar').'</strong></td></tr>';
			foreach($this->categories as $cat_detail) {
				$calendar_body .= '<tr><td style="background-color:'.$cat_detail->category_colour.'; width:20px; height:20px;" class="cat-key-cell"></td>
<td class="cat-key-cell">&nbsp;'.$cat_detail->category_name.'</td></tr>';
			}
			$calendar_body .= '</table>';
		}

		// Phew! After that bit of string building, spit it all out.
		// The actual printing is done by the calling function .
		return $calendar_body;
	}

	// Used to create a hover with all a day's events for minical
	function minical_draw_events($events,$day_of_week = '')
	{
		// We need to sort arrays of objects by time
		usort($events, array($this, 'time_cmp'));
		// Only show anything if there are events
		$output = '';
		if (count($events)) {
			// Setup the wrapper
			$output = '<div class="calnk"><a href="#" onclick="if (navigator.userAgent.match(/iPad|iPhone|iPod/i) != null ) jQuery(this).children(\'.popup\').toggle();return false;" style="background-color:#F6F79B;">'.$day_of_week.'<div class="popup">';
			// Now process the events
			foreach($events as $event) {
				if ($event->event_time == '00:00:00') { 
					$the_time = 'all day'; 
				} else if ($event->event_end_time == '00:00:00') { 
					$the_time = 'at ' . date(get_option('time_format'), strtotime($event->event_time)); 
				} else {
					$the_time = 'from ' . date(get_option('time_format'), strtotime($event->event_time)); 
					$the_time .= ' to ' . date(get_option('time_format'), strtotime($event->event_end_time));
				} 
				$output .= '<strong>'.$event->event_title.'</strong> '.$the_time.'<br />';
			}
			// The tail
			$output .= '</div></a></div>';
		} else {
			$output .= $day_of_week;
		}
		return $output;
	}

	function minical($cat_list = '') {
		
		global $wpdb;

		// Deal with the week not starting on a monday, choose Monday if anything other than Sunday is set				
		if (get_option('start_of_week') == 0) {
			$name_days = array(1=>__('Su','spiffy-calendar'),__('Mo','spiffy-calendar'),__('Tu','spiffy-calendar'),
						__('We','spiffy-calendar'),__('Th','spiffy-calendar'),__('Fr','spiffy-calendar'),__('Sa','spiffy-calendar'));
		} else {
			$name_days = array(1=>__('Mo','spiffy-calendar'),__('Tu','spiffy-calendar'),__('We','spiffy-calendar'),
						__('Th','spiffy-calendar'),__('Fr','spiffy-calendar'),__('Sa','spiffy-calendar'),__('Su','spiffy-calendar'));
		}

		// Carry on with the script	 
		$name_months = array(1=>__('January','spiffy-calendar'),__('February','spiffy-calendar'),__('March','spiffy-calendar'),
						__('April','spiffy-calendar'),__('May','spiffy-calendar'),__('June','spiffy-calendar'),
						__('July','spiffy-calendar'),__('August','spiffy-calendar'),__('September','spiffy-calendar'),
						__('October','spiffy-calendar'),__('November','spiffy-calendar'),__('December','spiffy-calendar'));

		// If we don't pass arguments we want a calendar that is relevant to today				
		if (empty($_GET['month']) || empty($_GET['yr'])) {
			$c_year = date("Y",$this->ctwo());
			$c_month = date("m",$this->ctwo());
			$c_day = date("d",$this->ctwo());
		}

		// Years get funny if we exceed 3000, so we use this check						
		if (isset($_GET['yr'])) {
			if ($_GET['yr'] <= 3000 && $_GET['yr'] >= 0 && (int)$_GET['yr'] != 0) {
				// This is just plain nasty and all because of permalinks
				// which are no longer used, this will be cleaned up soon
				if ($_GET['month'] == 'jan' || $_GET['month'] == 'feb' || $_GET['month'] == 'mar' || $_GET['month'] == 'apr' || 
					$_GET['month'] == 'may' || $_GET['month'] == 'jun' || $_GET['month'] == 'jul' || $_GET['month'] == 'aug' || 
					$_GET['month'] == 'sept' || $_GET['month'] == 'oct' || $_GET['month'] == 'nov' || $_GET['month'] == 'dec') {

					// Again nasty code to map permalinks into something 
					// databases can understand. This will be cleaned up
					$c_year = mysql_real_escape_string($_GET['yr']);
					if ($_GET['month'] == 'jan') { $t_month = 1; }
					else if ($_GET['month'] == 'feb') { $t_month = 2; }
					else if ($_GET['month'] == 'mar') { $t_month = 3; }
					else if ($_GET['month'] == 'apr') { $t_month = 4; }
					else if ($_GET['month'] == 'may') { $t_month = 5; }
					else if ($_GET['month'] == 'jun') { $t_month = 6; }
					else if ($_GET['month'] == 'jul') { $t_month = 7; }
					else if ($_GET['month'] == 'aug') { $t_month = 8; }
					else if ($_GET['month'] == 'sept') { $t_month = 9; }
					else if ($_GET['month'] == 'oct') { $t_month = 10; }
					else if ($_GET['month'] == 'nov') { $t_month = 11; }
					else if ($_GET['month'] == 'dec') { $t_month = 12; }
					$c_month = $t_month;
					$c_day = date("d",$this->ctwo());
				} else {
					// No valid month causes the calendar to default to today
					$c_year = date("Y",$this->ctwo());
					$c_month = date("m",$this->ctwo());
					$c_day = date("d",$this->ctwo());
				}
			}
		} else {
			// No valid year causes the calendar to default to today	
			$c_year = date("Y",$this->ctwo());
			$c_month = date("m",$this->ctwo());
			$c_day = date("d",$this->ctwo());
		}

		// Fix the days of the week if week start is not on a monday				
		if (get_option('start_of_week') == 0) {
			$first_weekday = date("w",mktime(0,0,0,$c_month,1,$c_year));
			$first_weekday = ($first_weekday==0?1:$first_weekday+1);
		} else {
			// Otherwise assume the week starts on a Monday. Anything other 
			// than Sunday or Monday is just plain odd	
			$first_weekday = date("w",mktime(0,0,0,$c_month,1,$c_year));
			$first_weekday = ($first_weekday==0?7:$first_weekday);
		}

		$days_in_month = date("t", mktime (0,0,0,$c_month,1,$c_year));

		// Start the table and add the header and naviagtion					
		$calendar_body = '';
		$calendar_body .= '<div style="width:200px;"><table cellspacing="1" cellpadding="0" class="calendar-table">';

		// The header of the calendar table and the links. Note calls to link function s
		$calendar_body .= '<tr>
 <td colspan="7" class="calendar-heading" style="height:0;">
	<table border="0" cellpadding="0" cellspacing="0" width="100%">
		<tr>
			<td class="calendar-prev">' . $this->prev_link($c_year,$c_month,true) . '</td>
			<td class="calendar-month">'.$name_months[(int)$c_month].' '.$c_year.'</td>
			<td class="calendar-next">' . $this->next_link($c_year,$c_month,true) . '</td>
		</tr>
	</table>
 </td>
</tr>';

		// Print the headings of the days of the week
		$calendar_body .= '<tr>';
		for ($i=1; $i<=7; $i++) {
			// Colours need to be different if the starting day of the week is different
			if (get_option('start_of_week') == 0) {
				$calendar_body .= '	<td class="'.($i<7&$i>1?'normal-day-heading':'weekend-heading').'" style="height:0;">'.$name_days[$i].'</td>';
			} else {
				$calendar_body .= '	<td class="'.($i<6?'normal-day-heading':'weekend-heading').'" style="height:0;">'.$name_days[$i].'</td>';
			}
		}
		$calendar_body .= '</tr>';
		$go = FALSE;
		for ($i=1; $i<=$days_in_month;) {
			$calendar_body .= '<tr>';
			for ($ii=1; $ii<=7; $ii++) {
				if ($ii==$first_weekday && $i==1) {
					$go = TRUE;
				} elseif ($i > $days_in_month ) {
					$go = FALSE;
				}
				if ($go) {
					// Colours again, this time for the day numbers				 
					if (get_option('start_of_week') == 0) {
						// This bit of code is for styles believe it or not.
						$grabbed_events = $this->grab_events($c_year,$c_month,$i,'spiffy-calendar',$cat_list);
						$no_events_class = '';
						if (!count($grabbed_events)) {
							$no_events_class = ' no-events';
						}
						$calendar_body .= '	<td class="'.(date("Ymd", mktime (0,0,0,$c_month,$i,$c_year))==date("Ymd",$this->ctwo())?'current-day':'day-with-date').$no_events_class.'" style="height:0;"><div '.($ii<7&$ii>1?'':'class="weekend"').'>'.$this->minical_draw_events($grabbed_events,$i++).'</div></td>';
					} else {
						$grabbed_events = $this->grab_events($c_year,$c_month,$i,'spiffy-calendar',$cat_list);
						$no_events_class = '';
						if (!count($grabbed_events)) {
							$no_events_class = ' no-events';
						}
						$calendar_body .= '	<td class="'.(date("Ymd", mktime (0,0,0,$c_month,$i,$c_year))==date("Ymd",$this->ctwo())?'current-day':'day-with-date').$no_events_class.'" style="height:0;"><div '.($ii<6?'':'class="weekend"').'>'.$this->minical_draw_events($grabbed_events,$i++).'</div></td>';
					}
				} else {
					$calendar_body .= '	<td class="day-without-date" style="height:0;">&nbsp;</td>';
				}
			}
			$calendar_body .= '</tr>';
		}
		$calendar_body .= '</table>';

		// Closing div
		$calendar_body .= '</div>';

		// Phew! After that bit of string building, spit it all out.
		// The actual printing is done by the calling function .
		return $calendar_body;
	}

	// Output new styles to the css file
	function write_styles($new_styles) {
		$stylefile = plugin_dir_path(__FILE__). 'spiffycal.css';
		$f = fopen( $stylefile, 'w+' );
		if ($f) {
			fwrite( $f, $new_styles, 20000 ); // number of bytes to write, max.
			fclose( $f );
			return true;
		} else {
			return false;
		}
	}

} // end of class definition

} // end of "if !class exists"

if (class_exists("Spiffy_Calendar")) {
	$spiffy_calendar = new Spiffy_Calendar();
}

?>
