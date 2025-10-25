
<?php
if(!defined('ABSPATH')) {
	exit;
}

/**
 * WP_Event_Manager_Install
 */
class WP_Event_Manager_Install {

	/**
	 * Install WP Event Manager.
	 */
	public static function install() {
		global $wpdb;
		self::init_user_roles();
		self::default_terms();

		// Redirect to setup screen for new installs
		if(!get_option('wp_event_manager_version')) {
			set_transient('_event_manager_activation_redirect', 1, HOUR_IN_SECONDS);
		}
		
		// Update featured posts ordering.
		if(version_compare(get_option('wp_event_manager_version', EVENT_MANAGER_VERSION), '2.5', '<')) {
			$wpdb->query("UPDATE {$wpdb->posts} p SET p.menu_order = 0 WHERE p.post_type='event_listing';");
			$wpdb->query("UPDATE {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id SET p.menu_order = -1 WHERE pm.meta_key = '_featured' AND pm.meta_value='1' AND p.post_type='event_listing';");
		}

		// Update legacy options
		if(false === get_option('event_manager_submit_event_form_page_id', false) && get_option('event_manager_submit_page_slug')) {
			$page_id = get_page_by_path(get_option('event_manager_submit_page_slug'))->ID;
			update_option('event_manager_submit_event_form_page_id', $page_id);
		}

		if(false === get_option('event_manager_event_dashboard_page_id', false) && get_option('event_manager_event_dashboard_page_slug')) {
			$page_id = get_page_by_path(get_option('event_manager_event_dashboard_page_slug'))->ID;
			update_option('event_manager_event_dashboard_page_id', $page_id);
		}

		if(false === get_option('wp_event_manager_db_version', false)) {
			update_option('wp_event_manager_db_version', '3.1.13');
		}

		delete_transient('wp_event_manager_addons_html');
		update_option('wp_event_manager_version', EVENT_MANAGER_VERSION);
	}

	/**
	 * Update of WP Event Manager.
	 */
	public static function update() {

		global $wpdb;
		self::init_user_roles();
		// 3.1.14 change field option name
		if(!empty(get_option('event_manager_form_fields', true))) {
			$all_fields = get_option('event_manager_form_fields', true);

			if(isset($all_fields) && !empty($all_fields) && is_array($all_fields)) {
				if(isset($all_fields['event']['event_address']))
					unset($all_fields['event']['event_address']);

				if(isset($all_fields['event']['event_local_name']))
					unset($all_fields['event']['event_local_name']);

				update_option('event_manager_submit_event_form_fields', array('event' =>$all_fields['event']));
				update_option('event_manager_submit_dj_form_fields', array('dj' =>$all_fields['dj']));	
			}			
		}

		// 3.1.14 add dj pages
		$pages_to_create = [
			'submit_dj_form' => [
				'page_title' => 'Submit dj Form',
				'page_content' => '[submit_dj_form]',
			],
			'dj_dashboard' => [
				'page_title' => 'dj Dashboard',
				'page_content' => '[dj_dashboard]',
			],
			'event_djs' => [
				'page_title' => 'Event djs',
				'page_content' => '[event_djs]',
			],
			'submit_local_form' => [
				'page_title' => 'Submit local Form',
				'page_content' => '[submit_local_form]',
			],
			'local_dashboard' => [
				'page_title' => 'local Dashboard',
				'page_content' => '[local_dashboard]',
			],
			'event_locals' => [
				'page_title' => 'Event locals',
				'page_content' => '[event_locals]',
			],
		];

		foreach ($pages_to_create as $page_slug => $page) {
			self::create_page(sanitize_text_field($page['page_title']), $page['page_content'], 'event_manager_' . $page_slug . '_page_id');
		}

		delete_transient('wp_event_manager_addons_html');
		update_option('wp_event_manager_version', EVENT_MANAGER_VERSION);
	}
	
	/**
	 * Init user roles.
	 */
	private static function init_user_roles() {
		global $wp_roles;

		if(class_exists('WP_Roles') && !isset($wp_roles)) {
			$wp_roles = new WP_Roles();			
		}

		if(is_object($wp_roles)) {
			add_role('dj', __('dj', 'wp-event-manager'), array(
				'read'         => true,
				'edit_posts'   => false,
				'delete_posts' => false
			));

			$capabilities = self::get_core_capabilities();
			foreach ($capabilities as $cap_group) {
				foreach ($cap_group as $cap) {
					$wp_roles->add_cap('administrator', $cap);
				}
			}
			if ( $role = get_role( 'dj' ) ) {
				$role->add_cap( 'manage_djs' );
				$role->add_cap( 'manage_locals' );
			}
		}
	}

	/**
	 * Get capabilities.
	 * @return array
	 */	 
	private static function get_core_capabilities() {
		return array(
			'core' => array(
				'manage_event_listings',
				'manage_djs',
				'manage_locals',
			),
			'event_listing' => array(
				"edit_event_listing",
				"read_event_listing",
				"delete_event_listing",
				"edit_event_listings",
				"edit_others_event_listings",
				"publish_event_listings",
				"read_private_event_listings",
				"delete_event_listings",
				"delete_private_event_listings",
				"delete_published_event_listings",
				"delete_others_event_listings",
				"edit_private_event_listings",
				"edit_published_event_listings",
				"manage_event_listing_terms",
				"edit_event_listing_terms",
				"delete_event_listing_terms",
				"assign_event_listing_terms"
			),

			// dj capabilities
			'event_dj' => array(
				"edit_event_dj",
				"read_event_dj",
				"delete_event_dj",
				"edit_event_djs",
				"edit_others_event_djs",
				"publish_event_djs",
				"read_private_event_djs",
				"delete_event_djs",
				"delete_private_event_djs",
				"delete_published_event_djs",
				"delete_others_event_djs",
				"edit_private_event_djs",
				"edit_published_event_djs",
			),

			// local capabilities
			'event_local' => array(
				"edit_event_local",
				"read_event_local",
				"delete_event_local",
				"edit_event_locals",
				"edit_others_event_locals",
				"publish_event_locals",
				"read_private_event_locals",
				"delete_event_locals",
				"delete_private_event_locals",
				"delete_published_event_locals",
				"delete_others_event_locals",
				"edit_private_event_locals",
				"edit_published_event_locals",
			)
		);
	}
	
	/**
	 * Default taxonomy terms to set up in WP Event Manager.
	 *
	 * @return array Default taxonomy terms.
	 */
	private static function get_default_taxonomy_terms() {
		return array(
			'event_listing_type' => array(
				'Appearance or Signing',
				'Attraction',
				'Camp, Trip, or Retreat',
				'Class, Training, or Workshop',
				'Concert or Performance',
				'Conference',
				'Convention',
				'Dinner or Gala',
				'Festival or Fair',
				'Game or Competition',
				'Meeting or Networking Event',
				'Other',
				'Party or Social Gathering',
				'Race or Endurance Event',
				'Rally',
				'Screening',
				'Seminar or Talk',
				'Tour',
				'Tournament',
				'Tradeshow, Consumer Show or Expo'
			),
			'event_sounds' => array(
				'House',
				'Techno',
				'Trance',
				'Drum & Bass',
				'Dubstep',
				'Electro House',
				'Tech House',
				'Deep House',
				'Progressive House',
				'Hardstyle',
				'Hardcore',
				'Minimal Techno',
				'Acid House',
				'UK Garage',
				'Bass House',
				'Future House',
				'Tropical House',
				'Ambient',
				'Downtempo',
				'Electronica',
				'Wave',
				'Vaporwave',
				'Glitch Hop',
				'Psytrance',
				'Hardcore Techno',
				'Big Room House',
				'Jungle',
				'Gqom',
				'Eurodance',
				'Hyperpop'
			)
		);
	}

	/**
	 * Manage default term.
	 */
	private static function default_terms() {
		if(get_option('event_manager_installed_terms') == 1) {
			return;
		}
		
		$taxonomies = self::get_default_taxonomy_terms();
		foreach ($taxonomies as $taxonomy => $terms) {
			foreach ($terms as $term) {
				if(!get_term_by('slug', sanitize_title($term), $taxonomy)) {
					wp_insert_term($term, $taxonomy);
				}
			}
		}
		update_option('event_manager_installed_terms', 1);
	}

	/**
	 * Adds the employment type to default event types when updating from a previous WP Event Manager version.
	 */
	private static function add_event_types() {
		$taxonomies = self::get_default_taxonomy_terms();
		$terms      = $taxonomies['event_listing_type'];

		foreach ($terms as $term => $meta) {
			$term = get_term_by('slug', sanitize_title($term), 'event_listing_type');
			if($term) {
				foreach ($meta as $meta_key => $meta_value) {
					if(!get_term_meta((int) $term->term_id, $meta_key, true)) {
						add_term_meta((int) $term->term_id, $meta_key, $meta_value);
					}
				}
			}
		}
	}

	/**
	 * Create page.
	 */
	private static function create_page($title, $content, $option) {
		if(get_option($option) == ''){
			$page_data = array(
				'post_status'    => 'publish',
				'post_type'      => 'page',
				'post_author'    => 1,
				'post_name'      => sanitize_title($title),
				'post_title'     => $title,
				'post_content'   => $content,
				'post_parent'    => 0,
				'comment_status' => 'closed'
			);

			$page_id = wp_insert_post($page_data);
			if($option) {
				update_option($option, $page_id);
			}
		}		
	}
}