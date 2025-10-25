<?php
/**
* Plugin Name: WP Event Manager
* Plugin URI: https://www.wp-eventmanager.com/
* Description: Lightweight, scalable and full-featured event listings & management plugin for managing event listings from the Frontend and Backend.
* Author: WP Event Manager
* Author URI: https://www.wp-eventmanager.com
* Text Domain: wp-event-manager
* Domain Path: /languages
* Version: 3.2.2
* Since: 1.0.0
* Requires WordPress Version at least: 6.8.2
* Copyright: 2019 WP Event Manager
* License: GNU General Public License v3.0
* License URI: http://www.gnu.org/licenses/gpl-3.0.html
*
**/

// Exit if accessed directly
if(!defined('ABSPATH')) {
	exit;
}

// Include WPEM Plugin Updater Class
if ( !class_exists( 'WPEM_Updater' ) ) {
	include( 'autoupdater/wpem-updater.php' );
}

/**
 * A class that defines the main features of the WP event manager plugin.
 */
class WP_Event_Manager extends WPEM_Updater {

	/**
	 * The single instance of the class.
	 *
	 * @var self
	 * @since  2.5
	 */
	private static $_instance = null;

	/**
	 * version of plugin.
	 *
	 * @var plugin version
	 * @since  3.1.33
	 */
	private static $wpem_verion = '3.2.2';

	public $forms;
	public $post_types;
	public function __construct() {
		// Define constants
		define('EVENT_MANAGER_VERSION', self::$wpem_verion);
		define('EVENT_MANAGER_PLUGIN_DIR', untrailingslashit(plugin_dir_path(__FILE__)));
		define('EVENT_MANAGER_PLUGIN_URL', untrailingslashit(plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__))));

		// Here are all the files for the admin side of WP Event Manager.	
		include('includes/wp-event-manager-install.php');
		include('includes/wp-event-manager-post-types.php');
		include('includes/wp-event-manager-ajax.php');
		include('includes/wp-event-manager-geocode.php');
		include('includes/wp-event-manager-filters.php');
		include('includes/wp-event-manager-cache-helper.php');
		include('includes/wp-event-manager-date-time.php');
		include('includes/wp-event-manager-helpers.php');
		include('includes/wp-event-manager-stubs.php');

		// Here is the list of all the shortcodes for WP Event Manager.
		include('shortcodes/wp-event-manager-shortcodes.php');

		// Forms of WP Event manager.
		include('forms/wp-event-manager-forms.php');	

		if(is_admin()) {
			include('admin/wp-event-manager-admin.php');
		}
		
		// In the case of third party support, use this. 
		include('external/external.php');

		// Init classes
		$this->forms      = WP_Event_Manager_Forms::instance();
		$this->post_types = WP_Event_Manager_Post_Types::instance();

		// Activation hooks provide ways to perform actions when plugins are activated.
		register_activation_hook(basename(dirname(__FILE__)) . '/' . basename(__FILE__), array($this, 'activate'));
		
		// Hide dashboard pages from non DJ user
		add_action('event_manager_dj_dashboard_before', array($this, 'wpem_restrict_non_dj_access_to_dashboard'));
		add_action('event_manager_local_dashboard_before',array($this, 'wpem_restrict_non_dj_access_to_dashboard'));
		add_action('event_manager_event_dashboard_before',array($this, 'wpem_restrict_non_dj_access_to_dashboard'));
        
		// Restrict to add local, DJ, event form for non DJ user.
		add_action('wp_event_manager_local_submit_before', array($this, 'wpem_restrict_non_dj_access_to_dashboard'));
		add_action('wp_event_manager_dj_submit_before', array($this, 'wpem_restrict_non_dj_access_to_dashboard'));
		add_action('wp_event_manager_event_submit_before', array($this, 'wpem_restrict_non_dj_access_to_dashboard'));

		// Switch theme
		add_action('after_switch_theme', array('WP_Event_Manager_Ajax', 'add_endpoint'), 10);
		add_action('after_switch_theme', array($this->post_types, 'register_post_types'), 11);
		add_action('after_switch_theme', 'flush_rewrite_rules', 15);

		add_action('after_setup_theme', array($this, 'load_plugin_textdomain'));
		add_action('after_setup_theme', array($this, 'include_template_functions'), 11);

		add_action('widgets_init', array($this, 'widgets_init'));
		add_action('wp_enqueue_scripts', array($this, 'frontend_scripts'));

		add_action('admin_init', array($this, 'updater'));
		add_action('wp_logout', array($this, 'cleanup_event_posting_cookies'));
		
		// Defaults for core actions
		add_action('event_manager_notify_new_user', 'wp_event_manager_notify_new_user', 10, 2);

		if(is_admin()){
			// Call updater for WPEM addons update
			$this->init_updates( __FILE__ );
		}
		
		// Duplicate the_content filter for Wp event Manager plugin
		global $wp_embed;
		add_filter('wpem_the_content', array($wp_embed, 'run_shortcode'), 8);
		add_filter('wpem_the_content', array($wp_embed, 'autoembed'    ), 8);
		add_filter('wpem_the_content', 'wptexturize'       );
		add_filter('wpem_the_content', 'convert_chars'     );
		add_filter('wpem_the_content', 'wpautop'           );
		add_filter('wpem_the_content', 'shortcode_unautop' );
		add_filter('wpem_the_content', 'do_shortcode'      );
		add_filter('wpem_the_content', 'wpem_embed_oembed_html'      );
		// Schedule cron events
		self::check_schedule_crons();
	}

	/**
	 * Main WP Event Manager Instance.
	 *
	 * Ensures only one instance of WP Event Manager is loaded or can be loaded.
	 *
	 * @since  2.5
	 * @static
	 * @see WP_Event_Manager()
	 * @return self Main instance.
	 */
	public static function instance() {
		if(is_null(self::$_instance)) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Provide ways to perform actions when plugins are activated.
	 * 
	 * @since 1.0.0
	 */
	public function activate() {

		WP_Event_Manager_Ajax::add_endpoint();
		unregister_post_type('event_listing');
		add_filter('pre_option_event_manager_enable_categories', '__return_true');
		add_filter('pre_option_event_manager_enable_event_types', '__return_true');
		$this->post_types->register_post_types();
		remove_filter('pre_option_event_manager_enable_categories', '__return_true');
		remove_filter('pre_option_event_manager_enable_event_types', '__return_true');
		WP_Event_Manager_Install::install();
		// Show notice after activating plugin
		update_option('event_manager_rating_showcase_admin_notices_dismiss','0');
		// 3.1.37.1 change field option name
		if(!empty(get_option('event_manager_form_fields', true))) {
			$all_fields = get_option('event_manager_form_fields', true);

			if(isset($all_fields) && !empty($all_fields) && is_array($all_fields)) {
				
				// 3.1.37.1 change field option name
				if(isset($all_fields['event']['event_registration_email']))
					unset($all_fields['event']['event_registration_email']);
				
				update_option('event_manager_submit_event_form_fields', array('event' =>$all_fields['event']));
			}			
		}
		flush_rewrite_rules();
	}

	/**
	 * Handle Updates.
	 * @since 1.0.0
	 */
	public function updater() {
		if(version_compare((string)EVENT_MANAGER_VERSION, (string)get_option('wp_event_manager_version'), '>')) {
			WP_Event_Manager_Install::update();
			flush_rewrite_rules();
		}
	}

	/**
	 * Loads a plugin's translated strings.
	 * @since 1.0.0
	 */
	public function load_plugin_textdomain() {
		$domain = 'wp-event-manager';       
		$locale = apply_filters('plugin_locale', get_locale(), $domain);
		load_textdomain($domain, WP_LANG_DIR . "/wp-event-manager/".$domain."-" .$locale. ".mo");
		load_plugin_textdomain($domain, false, dirname(plugin_basename(__FILE__)) . '/languages/');
	}

	/**
	 * Load the functions files for WP Event Manager.
	 * @since 1.0.0
	 */
	public function include_template_functions() {
		include('wp-event-manager-functions.php');
		include('wp-event-manager-template.php');
	}

	/**
	 * Manage WordPress widgets.
	 * @since 1.0.0
	 */
	public function widgets_init() {
		include_once('widgets/wp-event-manager-widgets.php');
	}

	/**
	 * Format array for the datepicker.
	 *
	 * WordPress stores the locale information in an array with a alphanumeric index, and
	 * the datepicker wants a numerical index. This function replaces the index with a number
	 */
	public function strip_array_indices($ArrayToStrip) {
		foreach($ArrayToStrip as $objArrayItem) {
			$NewArray[] =  $objArrayItem;
		}
		return($NewArray);
	}

	/**
	 * Register and enqueue scripts and css.
	 */
	public function frontend_scripts() {
		$ajax_url         = WP_Event_Manager_Ajax::get_endpoint();
		$ajax_filter_deps = array('jquery', 'jquery-deserialize');

		$chosen_shortcodes   = array('submit_event_form', 'event_dashboard', 'events');
		$chosen_used_on_page = has_wpem_shortcode(null, $chosen_shortcodes);

		// jQuery Chosen
		if(apply_filters('event_manager_chosen_enabled', $chosen_used_on_page)) {
			wp_enqueue_script('wpem-dompurify', EVENT_MANAGER_PLUGIN_URL . '/assets/js/dom-purify/dompurify.min.js', [], '3.0.5', true);
			wp_register_script('chosen', EVENT_MANAGER_PLUGIN_URL . '/assets/js/jquery-chosen/chosen.jquery.min.js', array('jquery'), '1.1.0', true);
			wp_register_script('wp-event-manager-term-multiselect', EVENT_MANAGER_PLUGIN_URL . '/assets/js/term-multiselect.min.js', array('jquery', 'chosen'), EVENT_MANAGER_VERSION, true);
			wp_register_script('wp-event-manager-multiselect', EVENT_MANAGER_PLUGIN_URL . '/assets/js/multiselect.min.js', array('jquery', 'chosen'), EVENT_MANAGER_VERSION, true);
			wp_enqueue_style('chosen', EVENT_MANAGER_PLUGIN_URL . '/assets/css/chosen.css');
			$ajax_filter_deps[] = 'chosen';
		}

		// Leaflet (OpenStreetMap)
		wp_register_style('leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4');
		wp_register_script('leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true);
		wp_register_script('wpem-leaflet', EVENT_MANAGER_PLUGIN_URL . '/assets/js/wpem-leaflet.js', array('leaflet-js', 'jquery'), EVENT_MANAGER_VERSION, true);
		wp_localize_script('wpem-leaflet', 'wpem_leaflet_params', array(
			'nominatim_url' => 'https://nominatim.openstreetmap.org/search',
			'user_agent' => 'WP-Event-Manager-Apollo-Rio'
		));
		wp_enqueue_style('leaflet-css');
		wp_enqueue_script('leaflet-js');
		wp_enqueue_script('wpem-leaflet');

		// Ajax File Upload
		if (apply_filters('event_manager_ajax_file_upload_enabled', true)) {
			wp_register_script('wp-event-manager-ajax-file-upload', EVENT_MANAGER_PLUGIN_URL . '/assets/js/ajax-file-upload.min.js', array('jquery', 'plupload-all'), EVENT_MANAGER_VERSION, true);
			
			ob_start();
			get_event_manager_template('form-fields/uploaded-file-html.php', array('name' => '', 'value' => '', 'extension' => 'jpg'));
			$js_field_html_img = ob_get_clean();

			ob_start();
			get_event_manager_template('form-fields/uploaded-file-html.php', array('name' => '', 'value' => '', 'extension' => 'zip'));
			$js_field_html = ob_get_clean();

			wp_localize_script('wp-event-manager-ajax-file-upload', 'event_manager_ajax_file_upload', array(
				'ajax_url'               => $ajax_url,
				'js_field_html_img'      => esc_js(str_replace("\n", "", $js_field_html_img)),
				'js_field_html'          => esc_js(str_replace("\n", "", $js_field_html)),
				'i18n_invalid_file_type' => __('Invalid file type. Accepted types:', 'wp-event-manager')
			));
		}

		// Common js
		wp_register_script('wp-event-manager-common', EVENT_MANAGER_PLUGIN_URL . '/assets/js/common.min.js', array('jquery'), EVENT_MANAGER_VERSION, true);	
		wp_enqueue_style('wp-event-manager-frontend', EVENT_MANAGER_PLUGIN_URL . '/assets/css/frontend.min.css');
		wp_enqueue_script('wp-event-manager-common');

		// Event submission
		global $wp_locale;
		wp_register_script('wp-event-manager-event-submission', EVENT_MANAGER_PLUGIN_URL . '/assets/js/event-submission.min.js', array('jquery','jquery-ui-core','jquery-ui-datepicker'), EVENT_MANAGER_VERSION, true);
		wp_localize_script('wp-event-manager-event-submission', 'wp_event_manager_event_submission', array(
			'start_of_week' => get_option('start_of_week'),
			'i18n_datepicker_format' => WP_Event_Manager_Date_Time::get_datepicker_format(),
			'i18n_timepicker_format' => WP_Event_Manager_Date_Time::get_timepicker_format(),
			'i18n_timepicker_step' => WP_Event_Manager_Date_Time::get_timepicker_step(),
			'monthNames' => $this->strip_array_indices($wp_locale->month),
			'i18n_dayNames' => $this->strip_array_indices($wp_locale->weekday),
			'i18n_dayNamesMin' => $this->strip_array_indices($wp_locale->weekday_abbrev),
			'ajax_url' => admin_url('admin-ajax.php'),
			'show_past_date' => apply_filters('event_manager_show_past_date_frontend', false),
		));

		// Date pickers
		wp_register_style('wp-event-manager-lightpick-datepicker-style', EVENT_MANAGER_PLUGIN_URL . '/assets/js/lightpick-datepicker/lightpick.css');
		wp_register_script('wp-event-manager-lightpick-datepicker', EVENT_MANAGER_PLUGIN_URL . '/assets/js/lightpick-datepicker/lightpick.js', array('jquery-ui-core', 'jquery-ui-button', 'jquery-ui-datepicker', 'jquery-ui-menu', 'jquery-ui-widget', 'moment'), EVENT_MANAGER_VERSION, true);

		wp_register_style('wp-event-manager-jquery-ui-daterangepicker', EVENT_MANAGER_PLUGIN_URL . '/assets/js/jquery-ui-daterangepicker/jquery.comiseo.daterangepicker.css');
		wp_register_style('wp-event-manager-jquery-ui-daterangepicker-style', EVENT_MANAGER_PLUGIN_URL . '/assets/js/jquery-ui-daterangepicker/styles.css');
		wp_register_script('wp-event-manager-jquery-ui-daterangepicker', EVENT_MANAGER_PLUGIN_URL . '/assets/js/jquery-ui-daterangepicker/jquery.comiseo.daterangepicker.js', array('jquery-ui-core', 'jquery-ui-button', 'jquery-ui-datepicker', 'jquery-ui-menu', 'jquery-ui-widget', 'moment'), EVENT_MANAGER_VERSION, true);
		
		wp_register_script('wp-event-manager-content-event-listing', EVENT_MANAGER_PLUGIN_URL . '/assets/js/content-event-listing.min.js', array('jquery','wp-event-manager-common'), EVENT_MANAGER_VERSION, true);
		wp_localize_script('wp-event-manager-content-event-listing', 'event_manager_content_event_listing', array(
			'i18n_datepicker_format' => WP_Event_Manager_Date_Time::get_datepicker_format(),
			'i18n_initialText' => __('Select Date Range', 'wp-event-manager'),
			'i18n_applyButtonText' => __('Apply', 'wp-event-manager'),
			'i18n_clearButtonText' => __('Clear', 'wp-event-manager'),
			'i18n_cancelButtonText' => __('Cancel', 'wp-event-manager'),
			'i18n_monthNames' => $this->strip_array_indices($wp_locale->month),
			'i18n_dayNames' => $this->strip_array_indices($wp_locale->weekday),
			'i18n_dayNamesMin' => $this->strip_array_indices($wp_locale->weekday_abbrev),
			'i18n_today' => __('Today', 'wp-event-manager'),
			'i18n_tomorrow' => __('Tomorrow', 'wp-event-manager'),
			'i18n_thisWeek' => __('This Week', 'wp-event-manager'),
			'i18n_nextWeek' => __('Next Week', 'wp-event-manager'),
			'i18n_thisMonth' => __('This Month', 'wp-event-manager'),
			'i18n_nextMonth' => __('Next Month', 'wp-event-manager'),
			'i18n_thisYear' => __('This Year', 'wp-event-manager'),
			'i18n_nextYear' => __('Next Year', 'wp-event-manager')
		));

		// Ajax filters
		wp_register_script('wp-event-manager-ajax-filters', EVENT_MANAGER_PLUGIN_URL . '/assets/js/event-ajax-filters.min.js', $ajax_filter_deps, EVENT_MANAGER_VERSION, true);
		wp_localize_script('wp-event-manager-ajax-filters', 'event_manager_ajax_filters', array(
			'ajax_url' => $ajax_url,
			'is_rtl' => is_rtl() ? 1 : 0,
			'lang' => apply_filters('wpem_lang', null)
		));

		// Dashboards
		wp_register_script('wp-event-manager-dj-dashboard', EVENT_MANAGER_PLUGIN_URL . '/assets/js/dj-dashboard.min.js', array('jquery'), EVENT_MANAGER_VERSION, true);
		wp_localize_script('wp-event-manager-dj-dashboard', 'event_manager_dj_dashboard', array(
			'i18n_btnOkLabel' => __('Delete', 'wp-event-manager'),
			'i18n_btnCancelLabel' => __('Cancel', 'wp-event-manager'),
			'i18n_confirm_delete' => __('Are you sure you want to delete this DJ?', 'wp-event-manager')
		));

		wp_register_script('wp-event-manager-local-dashboard', EVENT_MANAGER_PLUGIN_URL . '/assets/js/local-dashboard.min.js', array('jquery'), EVENT_MANAGER_VERSION, true);
		wp_localize_script('wp-event-manager-local-dashboard', 'event_manager_local_dashboard', array(
			'i18n_btnOkLabel' => __('Delete', 'wp-event-manager'),
			'i18n_btnCancelLabel' => __('Cancel', 'wp-event-manager'),
			'i18n_confirm_delete' => __('Are you sure you want to delete this local?', 'wp-event-manager')
		));

		// DJ and Local scripts
		wp_register_script('wp-event-manager-dj', EVENT_MANAGER_PLUGIN_URL . '/assets/js/dj.min.js', array('jquery','wp-event-manager-common'), EVENT_MANAGER_VERSION, true);
		wp_localize_script('wp-event-manager-dj', 'event_manager_dj', array(
			'i18n_upcomingEventsTitle' => __('Upcoming Events', 'wp-event-manager'),
			'i18n_pastEventsTitle' => __('Past Events', 'wp-event-manager'),
			'i18n_currentEventsTitle' => __('Current Events', 'wp-event-manager')
		));

		wp_register_script('wp-event-manager-local', EVENT_MANAGER_PLUGIN_URL . '/assets/js/local.min.js', array('jquery','wp-event-manager-common'), EVENT_MANAGER_VERSION, true);
		wp_localize_script('wp-event-manager-local', 'event_manager_local', array(
			'i18n_upcomingEventsTitle' => __('Upcoming Events', 'wp-event-manager'),
			'i18n_pastEventsTitle' => __('Past Events', 'wp-event-manager'),
			'i18n_currentEventsTitle' => __('Current Events', 'wp-event-manager')
		));

		// Registration
		wp_register_script('wp-event-manager-event-registration', EVENT_MANAGER_PLUGIN_URL . '/assets/js/event-registration.min.js', array('jquery'), EVENT_MANAGER_VERSION, true);

		// jQuery UI and timepicker
		wp_enqueue_style('wp-event-manager-jquery-ui-css', EVENT_MANAGER_PLUGIN_URL . '/assets/js/jquery-ui/jquery-ui.css');
		wp_enqueue_style('wp-event-manager-jquery-timepicker-css', EVENT_MANAGER_PLUGIN_URL . '/assets/js/jquery-timepicker/jquery.timepicker.min.css');
		wp_register_script('wp-event-manager-jquery-timepicker', EVENT_MANAGER_PLUGIN_URL. '/assets/js/jquery-timepicker/jquery.timepicker.min.js', array('jquery','jquery-ui-core'), EVENT_MANAGER_VERSION, true);
		wp_enqueue_script('wp-event-manager-jquery-timepicker');

		// Slick slider
		wp_register_script('wp-event-manager-slick-script', EVENT_MANAGER_PLUGIN_URL . '/assets/js/slick/slick.min.js', array('jquery'));
		wp_register_style('wp-event-manager-slick-style', EVENT_MANAGER_PLUGIN_URL . '/assets/js/slick/slick.css', array());

		// Grid and fonts
		wp_register_style('wp-event-manager-grid-style', EVENT_MANAGER_PLUGIN_URL . '/assets/css/wpem-grid.min.css');
		wp_register_style('wp-event-manager-font-style', EVENT_MANAGER_PLUGIN_URL . '/assets/fonts/style.css');
		wp_enqueue_style('wp-event-manager-grid-style');
		wp_enqueue_style('wp-event-manager-font-style');
	}

	/**
	 * Cleanup event posting cookies.
	 * @since 1.0.0
	 */
	public function cleanup_event_posting_cookies() {
		if(isset($_COOKIE['wp-event-manager-submitting-event-id'])) {
			setcookie('wp-event-manager-submitting-event-id', '', 0, COOKIEPATH, COOKIE_DOMAIN, false);
		}
		if(isset($_COOKIE['wp-event-manager-submitting-event-key'])) {
			setcookie('wp-event-manager-submitting-event-key', '', 0, COOKIEPATH, COOKIE_DOMAIN, false);
		}
	}
	
	/**
	 * Check cron status.
	 * @since 1.0.0
	 **/
	public function check_schedule_crons(){
		if(!wp_next_scheduled('event_manager_check_for_expired_events')) {
			wp_schedule_event(time(), 'hourly', 'event_manager_check_for_expired_events');
		}
		if(!wp_next_scheduled('event_manager_delete_old_previews')) {
			wp_schedule_event(time(), 'daily', 'event_manager_delete_old_previews');
		}
		if(!wp_next_scheduled('event_manager_clear_expired_transients')) {
			wp_schedule_event(time(), 'twicedaily', 'event_manager_clear_expired_transients');
		}
	}

	/**
	 * Restrict access to the dashboard for non-DJ and non-administrator users.
	 *
	 * This function checks if the current user has the 'dj' or 'administrator' role.
	 * If the user lacks both capabilities, an informational message is displayed,
	 * and further access to the dashboard is restricted.
	 *
	 * @return void
	 */
	public function wpem_restrict_non_dj_access_to_dashboard() {
		if (is_user_logged_in()) {
			$current_user = wp_get_current_user();
    
			// Get allowed roles from option, fallback to all roles if not set
			$allowed_roles = get_option('event_manager_allowed_submission_roles', array_keys(wp_roles()->roles));
    
			// Ensure $allowed_roles is always an array
			if (!is_array($allowed_roles)) {
				// Convert comma-separated string to array safely
				$allowed_roles = array_filter(array_map('trim', explode(',', $allowed_roles)));
			}
    
			$allowed_roles = array_map('strtolower', $allowed_roles);
			$user_roles    = array_map('strtolower', $current_user->roles);
    
			if (!in_array('administrator', $user_roles) && !array_intersect($allowed_roles, $user_roles)) {
				?>
				<p class="account-sign-in wpem-alert wpem-alert-info">
					<?php esc_html_e('You do not have permission to manage this dashboard.', 'wp-event-manager'); ?>
				</p>
				<?php
				exit;
			}
		}
	}
}

/**
 * Create link on plugin page for event manager plugin settings.
 */
function add_plugin_page_event_manager_settings_link($links) {
	$links[] = '<a href="' .
		admin_url('edit.php?post_type=event_listing&page=event-manager-settings') .
		'">' . __('Settings', 'wp-event-manager') . '</a>';
	return $links;
}
add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'add_plugin_page_event_manager_settings_link');

/**
 * Main instance of WP Event Manager.
 *
 * Returns the main instance of WP Event Manager to prevent the need to use globals.
 *
 * @since  2.5
 * @return WP_Event_Manager
 */
function WPEM() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName
	return WP_Event_Manager::instance();
}
$GLOBALS['event_manager'] = WPEM();

// Wrappers de compatibilidade para hooks/funções antigas
add_action('event_manager_dj_dashboard_before', function() {
	do_action('event_manager_dj_dashboard_before');
});
add_action('event_manager_local_dashboard_before', function() {
	do_action('event_manager_local_dashboard_before');
});