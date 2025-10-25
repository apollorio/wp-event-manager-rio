<?php
/*
* This file is use to create a sortcode of wp event manager plugin. 
* This file include sortcode of event/dj/local listing,event/dj/local submit form and event/dj/local dashboard etc.
*/

if(!defined('ABSPATH')) exit; // Exit if accessed directly
/**
 * WP_Event_Manager_Shortcodes class.
 */
class WP_Event_Manager_Shortcodes{
	private $event_dashboard_message = '';
	private $dj_dashboard_message = '';
	private $local_dashboard_message = '';

	/**
	 * Constructor.
	 */
	public function __construct(){
		add_action('wp', array($this, 'shortcode_action_handler'));

		add_action('event_manager_event_dashboard_content_edit', array($this, 'edit_event'));
		add_action('event_manager_dj_dashboard_content_edit', array($this, 'edit_dj'));
		add_action('event_manager_local_dashboard_content_edit', array($this, 'edit_local'));
		add_action('event_manager_event_filters_end', array($this, 'event_filter_results'), 30);
		add_action('event_manager_output_events_no_results', array($this, 'output_no_results'));
		add_action('single_event_listing_dj_action_start', array($this, 'dj_more_info_link'));

		// Shortcodes of events
		add_shortcode('submit_event_form', array($this, 'submit_event_form'));
		add_shortcode('event_dashboard', array($this, 'event_dashboard'));
		add_shortcode('events', array($this, 'output_events'));
		add_shortcode('event', array($this, 'output_event'));
		add_shortcode('event_summary', array($this, 'output_event_summary'));
		add_shortcode('past_events', array($this, 'output_past_events'));
		add_shortcode('event_register', array($this, 'output_event_register'));
		add_shortcode('upcoming_events', array($this, 'output_upcoming_events'));
		add_shortcode('related_events', array($this, 'output_related_events'));

		// Hide the shortcode if dj not enabled
		if(get_option('enable_event_dj')) {
			add_shortcode('submit_dj_form', array($this, 'submit_dj_form'));
			add_shortcode('dj_dashboard', array($this, 'dj_dashboard'));

			add_shortcode('event_djs', array($this, 'output_event_djs'));
			add_shortcode('event_dj', array($this, 'output_event_dj'));
			add_shortcode('single_event_dj', array($this, 'output_single_event_dj'));
		}
		// Hide the shortcode if local not enabled
		if(get_option('enable_event_local')) {
			add_shortcode('submit_local_form', array($this, 'submit_local_form'));
			add_shortcode('local_dashboard', array($this, 'local_dashboard'));

			add_shortcode('event_locals', array($this, 'output_event_locals'));
			add_shortcode('event_local', array($this, 'output_event_local'));
			add_shortcode('single_event_local', array($this, 'output_single_event_local'));
		}
	}

	/**
	 * Handle actions which need to be run before the shortcode e.g. post actions.
	 */
	public function shortcode_action_handler() {
		global $post;

		if ( ! $post instanceof WP_Post ) {
			return;
		}
		if ( has_shortcode($post->post_content, 'event_dashboard') ) {
			$this->event_dashboard_handler();
			$this->dj_dashboard_handler();
			$this->local_dashboard_handler();
		} elseif ( has_shortcode($post->post_content, 'dj_dashboard') ) {
			$this->dj_dashboard_handler();
		} elseif ( has_shortcode($post->post_content, 'local_dashboard') ) {
			$this->local_dashboard_handler();
		}
	}

	/**
	 * Show the event submission form.
	 */
	public function submit_event_form($atts = array()){
		return $GLOBALS['event_manager']->forms->get_form('submit-event', $atts);
	}

	/**
	 * Show the dj submission form.
	 */
	public function submit_dj_form($atts = array()){
		if ( ! is_user_logged_in() || ! current_user_can( 'manage_djs' ) ) {
			ob_start();
			echo '<div class="wpem-alert wpem-alert-danger">' . esc_html__( 'Please login as dj to add dj!', 'wp-event-manager' ) . '</div>';
			return ob_get_clean();
		}

		return $GLOBALS['event_manager']->forms->get_form('submit-dj', $atts);
	}

	/**
	 * Show the dj submission form.
	 */
	public function submit_local_form($atts = array()){
		if ( ! is_user_logged_in() || ( ! current_user_can( 'manage_locals' ) ) ) {
			ob_start();
			echo '<div class="wpem-alert wpem-alert-danger">' . esc_html__( 'Please login as dj to add local!', 'wp-event-manager' ) . '</div>';
			return ob_get_clean();
		}
		return $GLOBALS['event_manager']->forms->get_form('submit-local', $atts);
	}

	/**
	 * Handles actions on event dashboard.
	 */
	public function event_dashboard_handler(){

		if(!empty($_REQUEST['action']) && !empty($_REQUEST['_wpnonce']) && wp_verify_nonce(sanitize_key($_REQUEST['_wpnonce']), 'event_manager_my_event_actions')) {
			$action = sanitize_title($_REQUEST['action']);
			$event_id = absint($_REQUEST['event_id']);

			try {
				// Get Event
				$event    = get_post($event_id);
				// Check ownership
				if(!$event || $event->post_type !== 'event_listing' || !event_manager_user_can_edit_event($event_id)) {
					throw new Exception(__('Invalid ID', 'wp-event-manager'));
				}

				switch ($action) {
					case 'mark_cancelled':
						// Check status
						if($event->_cancelled == 1)
							throw new Exception(__('This event has already been cancelled.', 'wp-event-manager'));

						// Update
						update_post_meta($event_id, '_cancelled', 1);

						do_action('after_event_cancelled', $action, $event_id);
						// Message
						// translators: %s is the title of the cancelled event.
						$this->event_dashboard_message = '<div class="event-manager-message wpem-alert wpem-alert-success">' . sprintf(__('%s has been cancelled.', 'wp-event-manager'), esc_html($event->post_title)) . '</div>';
						break;
					case 'mark_not_cancelled':
						// Check status
						if($event->_cancelled != 1) {
							throw new Exception(__('This event is not cancelled.', 'wp-event-manager'));
						}
						// Update
						update_post_meta($event_id, '_cancelled', 0);
						// Message
						// translators: %s is the title of the not cancelled event.
						$this->event_dashboard_message = '<div class="event-manager-message wpem-alert wpem-alert-success">' . sprintf(__('%s has been marked as not cancelled.', 'wp-event-manager'), esc_html($event->post_title)) . '</div>';
						break;
					case 'delete':
						$events_status = get_post_status($event_id);
						// Trash it
						wp_trash_post($event_id);

						if(!in_array($events_status, ['trash'])) {
							// Message
							// translators: %s is the title of the deleted event.
							$this->event_dashboard_message = '<div class="event-manager-message wpem-alert wpem-alert-danger">' . sprintf(__('%s has been deleted.', 'wp-event-manager'), esc_html($event->post_title)) . '</div>';
						}
						break;
					case 'duplicate':
						if(!event_manager_get_permalink('submit_event_form')) {
							throw new Exception(__('Missing submission page.', 'wp-event-manager'));
						}
						$new_event_id = event_manager_duplicate_listing($event_id);
						if($new_event_id) {
							wp_safe_redirect(esc_url_raw(add_query_arg(array('event_id' => absint($new_event_id)), event_manager_get_permalink('submit_event_form'))));
							exit;
						}
						break;
					case 'relist':
						// Redirect to post page
						wp_safe_redirect(esc_url_raw(add_query_arg(array('event_id' => absint($event_id)), event_manager_get_permalink('submit_event_form'))));
						break;
					default:
						do_action('event_manager_event_dashboard_do_action_' . $action);
						break;
				}
				do_action('event_manager_my_event_do_action', $action, $event_id);
			} catch (Exception $e) {
				$this->event_dashboard_message = '<div class="event-manager-error wpem-alert wpem-alert-danger">' . esc_html($e->getMessage()) . '</div>';
			}
		}
	}

	/**
	 * Shortcode which lists the logged in user's events.
	 */
	public function event_dashboard($atts){

		global $wpdb, $event_manager_keyword;

		if(!is_user_logged_in()) {
			ob_start();
			get_event_manager_template('event-dashboard-login.php');
			return ob_get_clean();
		}

		$atts = shortcode_atts(array('posts_per_page' => 10), $atts);
		$posts_per_page = (int) $atts['posts_per_page'];

		wp_enqueue_script('wp-event-manager-event-dashboard');

		ob_start();

		$search_order_by = 	isset($_GET['search_order_by']) ? esc_attr( wp_unslash( $_GET['search_order_by']) ) : '';

		if(isset($search_order_by) && !empty($search_order_by)) {
			$search_order_by = explode('|', $search_order_by);
			$orderby = $search_order_by[0];
			$order = $search_order_by[1];
		} else {
			$orderby = 'date';
			$order = 'desc';
		}

		// ....If not show the event dashboard
		$args = apply_filters('event_manager_get_dashboard_events_args', array(
			'post_type'           => 'event_listing',
			'post_status'         => array('publish', 'expired', 'pending'),
			'ignore_sticky_posts' => 1,
			'posts_per_page'      => intval($posts_per_page),
			'offset'              => (max(1, get_query_var('paged')) - 1) * $posts_per_page,
			'orderby'             => $orderby,
			'order'               => $order,
			'author'              => get_current_user_id()
		));

		$event_manager_keyword = isset($_GET['search_keywords']) ? esc_attr( wp_unslash( $_GET['search_keywords']) ) : '';
		if(!empty($event_manager_keyword) && strlen($event_manager_keyword) >= apply_filters('event_manager_get_listings_keyword_length_threshold', 2)) {
			$args['s'] = $event_manager_keyword;
			add_filter('posts_search', 'get_event_listings_keyword_search');
		}

		if(isset($args['orderby']) && !empty($args['orderby'])) {
			if($args['orderby'] == 'event_location') {
				$args['meta_query'] = array(
					'relation' => 'AND',
					'event_location_type_clause' => array(
						'key'     => '_event_online',
						'compare' => 'EXISTS',
					),
					'event_location_clause' => array(
						'key'     => '_event_location',
						'compare' => 'EXISTS',
					), 
				);
				$args['orderby'] = array(
					'event_location_type_clause' => ($search_order_by[1]==='desc') ? 'asc' : 'desc',
					'event_location_clause' => $search_order_by[1],
				);
				
			} elseif($args['orderby'] == 'event_start_date') {
				$args['meta_key'] = '_event_start_date';
				$args['orderby'] = 'meta_value';
				$args['meta_type'] = 'DATETIME';
			}
			elseif($args['orderby'] == 'event_end_date') {
				$args['meta_key'] = '_event_end_date';
				$args['orderby'] = 'meta_value';
				$args['meta_type'] = 'DATETIME';
			}
		}

		$events = new WP_Query($args);

		echo wp_kses_post($this->event_dashboard_message);
		echo wp_kses_post($this->dj_dashboard_message);
		echo wp_kses_post($this->local_dashboard_message);

		$event_dashboard_columns = apply_filters('event_manager_event_dashboard_columns', array(
			'event_title' => __('Title', 'wp-event-manager'),
			'event_location' => __('Location', 'wp-event-manager'),
			'event_start_date' => __('Start Date', 'wp-event-manager'),
			'event_end_date' => __('End Date', 'wp-event-manager'),
			'view_count' => __('Viewed', 'wp-event-manager'),
			'event_action' => __('Action', 'wp-event-manager'),
		));

		get_event_manager_template('event-dashboard.php', array('events' => $events->posts, 'max_num_pages' => $events->max_num_pages, 'event_dashboard_columns' => $event_dashboard_columns, 'atts' => $atts));

		remove_filter('posts_search', 'get_event_listings_keyword_search');

		return ob_get_clean();
	}

	/**
	 * Edit event form.
	 */
	public function edit_event(){
		global $event_manager;

		$dj_id = isset($_REQUEST['dj_id']) ? absint($_REQUEST['dj_id']) : 0;
		$local_id     = isset($_REQUEST['local_id']) ? absint($_REQUEST['local_id']) : 0;

		if ($dj_id && get_post_type($dj_id) === 'event_dj') {
			echo $event_manager->forms->get_form('edit-dj');
		} elseif ($local_id && get_post_type($local_id) === 'event_local') {
			echo $event_manager->forms->get_form('edit-local');
		} else {
			echo $event_manager->forms->get_form('edit-event');
		}
	}

	/**
	 * Handles actions on dj dashboard.
	 */
	public function dj_dashboard_handler() {
    	if (!empty($_REQUEST['action']) && !empty($_REQUEST['_wpnonce']) && wp_verify_nonce(sanitize_key($_REQUEST['_wpnonce']), 'event_manager_my_dj_actions')) {

			$action = sanitize_title($_REQUEST['action']);
			$dj_id = absint($_REQUEST['dj_id']);

			try {
				$event = get_post($dj_id);

				if (!$event || $event->post_type !== 'event_dj') {
					throw new Exception(__('Invalid dj.', 'wp-event-manager'));
				}

				if (!event_manager_user_can_edit_event($dj_id)) {
					throw new Exception(__('You do not have permission to perform this action.', 'wp-event-manager'));
				}

				switch ($action) {
					case 'delete':
						wp_trash_post($dj_id);
						$this->dj_dashboard_message = '<div class="event-manager-message wpem-alert wpem-alert-danger">' .sprintf(esc_html__('%s has been deleted.', 'wp-event-manager'), esc_html($event->post_title)) .'</div>';
						wp_safe_redirect(esc_url_raw(add_query_arg(array(
							'local_id' => absint($dj_id),
							'action' => 'dj_dashboard'
						), event_manager_get_permalink('event_dashboard'))));
						exit;

					case 'duplicate':
						if (!event_manager_get_permalink('submit_dj_form')) {
							throw new Exception(__('Missing submission page.', 'wp-event-manager'));
						}

						$new_dj_id = event_manager_duplicate_listing($dj_id);
						if ($new_dj_id) {
							wp_update_post(array(
								'ID' => esc_attr($new_dj_id),
								'post_status' => 'publish',
							));

							wp_safe_redirect(esc_url_raw(add_query_arg(array(
								'action' => 'edit',
								'dj_id' => absint($new_dj_id)
							), event_manager_get_permalink('submit_dj_form'))));
							exit;
						}
						break;

					default:
						do_action('event_manager_dj_dashboard_do_action_' . $action);
						break;
				}

				do_action('event_manager_my_dj_do_action', $action, $dj_id);

			} catch (Exception $e) {
				$this->dj_dashboard_message = '<div class="event-manager-error wpem-alert wpem-alert-danger">' .esc_html($e->getMessage()) .'</div>';
			}
		}
	}

	/**
	 * Shortcode which lists the logged in user's djs.
	 */
	public function dj_dashboard($atts){

		if(!is_user_logged_in()) {
			ob_start(); 
			$login_url = get_option('event_manager_login_page_url');
			$login_url = !empty($login_url) ? $login_url : wp_login_url();
			$login_url = apply_filters('event_manager_event_dashboard_login_url', $login_url); ?>
			<div id="event-manager-event-dashboard">
				<p class="account-sign-in wpem-alert wpem-alert-info">
					<?php esc_attr_e('You need to be signed in to manage your dj listings.', 'wp-event-manager'); ?> 
					<a href="<?php echo esc_url($login_url); ?>">
						<?php esc_attr_e('Sign in', 'wp-event-manager'); ?>
					</a>
				</p>
			</div>
			<?php 
			return ob_get_clean();
		}

		$atts = shortcode_atts(array(
			'posts_per_page' => 10,
		), $atts);

		wp_enqueue_script('wp-event-manager-dj-dashboard');

		ob_start();

		// If doing an action, show conditional content if needed....
		if(!empty($_REQUEST['action'])) {
			$action = esc_attr($_REQUEST['action']);

			// Show alternative content if a plugin wants to
			if(has_action('event_manager_dj_dashboard_content_' . $action)) {
				do_action('event_manager_dj_dashboard_content_' . $action, $atts);
				return ob_get_clean();
			}
		}
		$paged = max(1, get_query_var('paged'));
		// ....If not show the event dashboard
		$args = apply_filters('event_manager_get_dashboard_djs_args', array(
			'post_type'           => 'event_dj',
			'post_status'         => array('publish'),
			'ignore_sticky_posts' => 1,
			'posts_per_page'      => intval($atts['posts_per_page']),
			'offset'              => (int)($paged - 1) * intval($atts['posts_per_page']),
			'orderby'             => 'date',
			'order'               => 'desc',
			'author'              => get_current_user_id()
		));

		$djs = new WP_Query($args);
		
		echo wp_kses_post($this->dj_dashboard_message);

		$dj_dashboard_columns = apply_filters('event_manager_dj_dashboard_columns', array(
			'dj_name' => __('dj name', 'wp-event-manager'),
			'dj_details' => __('Details', 'wp-event-manager'),
			'dj_events' => __('Events', 'wp-event-manager'),
			'dj_action' => __('Action', 'wp-event-manager'),
		));

		get_event_manager_template(
			'dj-dashboard.php',
			array(
				'djs' => $djs->posts,
				'max_num_pages' => $djs->max_num_pages,
				'dj_dashboard_columns' => $dj_dashboard_columns
			),
			'wp-event-manager/dj',
			EVENT_MANAGER_PLUGIN_DIR . '/templates/dj'
		);

		return ob_get_clean();
	}

	/**
	 * Edit event form.
	 */
	public function edit_dj(){
		global $event_manager;
		printf($event_manager->forms->get_form('edit-dj'));
		// echo $event_manager->forms->get_form('edit-dj');
	}

	/**
	 * Handles actions on local dashboard
	 */
	public function local_dashboard_handler() {
		if ( !empty($_REQUEST['action']) && !empty($_REQUEST['_wpnonce']) && wp_verify_nonce(sanitize_key($_REQUEST['_wpnonce']), 'event_manager_my_local_actions')	) {
			$action = sanitize_title($_REQUEST['action']);
			$local_id = absint($_REQUEST['local_id']);

			try {
				$local = get_post($local_id);

				// Validate post and type
				if (!$local || $local->post_type !== 'event_local') {
					throw new Exception(__('Invalid local.', 'wp-event-manager'));
				}

				// Ownership check
				if (!event_manager_user_can_edit_event($local_id)) {
					throw new Exception(__('You do not have permission to perform this action.', 'wp-event-manager'));
				}

				switch ($action) {
					case 'delete':
						wp_trash_post($local_id);
						$this->local_dashboard_message = '<div class="event-manager-message wpem-alert wpem-alert-danger">' . sprintf(esc_html__('%s has been deleted.', 'wp-event-manager'), esc_html($local->post_title)) . '</div>';
						wp_safe_redirect(esc_url_raw(add_query_arg(array(
							'local_id' => absint($local_id),
							'action' => 'local_dashboard'
						), event_manager_get_permalink('event_dashboard'))));
						exit;

					case 'duplicate':
						if (!event_manager_get_permalink('submit_local_form')) {
							throw new Exception(__('Missing submission page.', 'wp-event-manager'));
						}
						$new_local_id = event_manager_duplicate_listing($local_id);
						if ($new_local_id) {
							wp_update_post(array(
								'ID' => esc_attr($new_local_id),
								'post_status' => 'publish',
							));
							wp_safe_redirect(esc_url_raw(add_query_arg(array(
								'action' => 'edit',
								'local_id' => absint($new_local_id),
							), event_manager_get_permalink('submit_local_form'))));
							exit;
						}
						break;

					default:
						do_action('event_manager_local_dashboard_do_action_' . $action);
						break;
				}

				do_action('event_manager_my_local_do_action', $action, $local_id);
			} catch (Exception $e) {
				$this->local_dashboard_message = '<div class="event-manager-error wpem-alert wpem-alert-danger">' .esc_html($e->getMessage()) .'</div>';
			}
		}
	}


	/**
	 * Shortcode which lists the logged in user's locals.
	 */
	public function local_dashboard($atts)	{
		if(!is_user_logged_in()) {
			ob_start();	?>
			<div id="event-manager-event-dashboard">
				<p class="account-sign-in wpem-alert wpem-alert-info"><?php esc_attr_e('You need to be signed in to manage your local listings.', 'wp-event-manager'); ?> <a href="<?php echo esc_url(apply_filters('event_manager_event_dashboard_login_url', esc_url(get_option('event_manager_login_page_url'),esc_url(wp_login_url())))); ?>"><?php esc_attr_e('Sign in', 'wp-event-manager'); ?></a></p>
			</div>
			<?php 
			return ob_get_clean();
		}

		$atts = shortcode_atts(array(
			'posts_per_page' => 10,
		), $atts);
		wp_enqueue_script('wp-event-manager-local-dashboard');

		ob_start();

		// If doing an action, show conditional content if needed....
		if(!empty($_REQUEST['action'])) {
			$action = esc_attr($_REQUEST['action']);
			// Show alternative content if a plugin wants to
			if(has_action('event_manager_local_dashboard_content_' . $action)) {

				do_action('event_manager_local_dashboard_content_' . $action, $atts);

				return ob_get_clean();
			}
		}

		// ....If not show the event dashboard
		$args     = apply_filters('event_manager_get_dashboard_local_args', array(
			'post_type'           => 'event_local',
			'post_status'         => array('publish'),
			'ignore_sticky_posts' => 1,
			'posts_per_page'      => (int) $atts['posts_per_page'],
			'offset'              => (max(1, get_query_var('paged')) - 1) * $atts['posts_per_page'],
			'orderby'             => 'date',
			'order'               => 'desc',
			'author'              => get_current_user_id()
		));

		$locals = new WP_Query($args);

		echo esc_html($this->local_dashboard_message);

		$local_dashboard_columns = apply_filters('event_manager_local_dashboard_columns', array(
			'local_name' => __('local name', 'wp-event-manager'),
			'local_details' => __('Details', 'wp-event-manager'),
			'local_events' => __('Events', 'wp-event-manager'),
			'local_action' => __('Action', 'wp-event-manager'),
		));

		get_event_manager_template(
			'local-dashboard.php',
			array(
				'locals' => $locals->posts,
				'max_num_pages' => $locals->max_num_pages,
				'local_dashboard_columns' => $local_dashboard_columns
			),
			'wp-event-manager/local',
			EVENT_MANAGER_PLUGIN_DIR . '/templates/local'
		);

		return ob_get_clean();
	}

	/**
	 * Edit local form.
	 */
	public function edit_local(){
		global $event_manager;
		printf($event_manager->forms->get_form('edit-local'));
		// echo $event_manager->forms->get_form('edit-local');
	}

	/**
	 * Output of events.
	 *
	 * @access public
	 * @param mixed $args
	 * @return void
	 */
	public function output_events($atts){
		ob_start();

		extract($atts = shortcode_atts(apply_filters('event_manager_output_events_defaults', array(
			'per_page'                  => esc_attr(get_option('event_manager_per_page')),
			'orderby'                   => 'meta_value', // meta_value
			'order'                     => 'ASC',
			
			// Filters + cats
			'show_filters'              => true,
			'filter_style'              => '',
			'show_categories'           => true,
			'show_event_types'          => true,
			'show_ticket_prices'        => true,
			'show_category_multiselect' => get_option('event_manager_enable_default_category_multiselect', false),
			'show_event_type_multiselect' => get_option('event_manager_enable_default_event_type_multiselect', false),
			'show_pagination'           => false,
			'show_more'                 => true,
			
			// Limit what events are shown based on category and type
			'categories'                => '',
			'event_types'               => '',
			'ticket_prices'             => '',
			'featured'                  => null, // True to show only featured, false to hide featured, leave null to show both.
			'cancelled'                 => null, // True to show only cancelled, false to hide cancelled, leave null to show both/use the settings.

			// Default values for filters
			'location'                  => '',
			'keywords'                  => '',
			'selected_datetime'         => '',
			'selected_category'         => '',
			'selected_event_type'       => '',
			'selected_ticket_price'     => '',
			'layout_type'      			=> 'all',
			'event_online'      		=> '',
			'title'                     => __('Events', 'wp-event-manager'),
		)), $atts));

		$current_page = max(1, get_query_var('paged'));
		// Categories
		if(!esc_attr(get_option('event_manager_enable_categories'))) {
			$show_categories = false;
		}

		// Event types
		if(!esc_attr(get_option('event_manager_enable_event_types'))) {
			$show_event_types = false;
		}

		// Event ticket prices		
		if(!esc_attr(get_option('event_manager_enable_event_ticket_prices_filter'))) {
			$show_ticket_prices = false;
		}
		// String and bool handling
		$show_filters              = $this->string_to_bool($show_filters);
		$show_categories           = $this->string_to_bool($show_categories);
		$show_event_types          = $this->string_to_bool($show_event_types);
		$show_ticket_prices        = $this->string_to_bool($show_ticket_prices);
		$show_category_multiselect = $this->string_to_bool($show_category_multiselect);
		$show_event_type_multiselect = $this->string_to_bool($show_event_type_multiselect);
		$show_more                 = $this->string_to_bool($show_more);
		$show_pagination           = $this->string_to_bool($show_pagination);

		// Order by meta value and it will take default sort order by start date of event
		if(is_null($orderby) ||  empty($orderby)) {
			$orderby  = 'meta_value';
		}

		if(!is_null($featured)) {
			$featured = (is_bool($featured) && $featured) || in_array($featured, array('1', 'true', 'yes')) ? true : false;
		}

		if(!is_null($cancelled)) {
			$cancelled = (is_bool($cancelled) && $cancelled) || in_array($cancelled, array('1', 'true', 'yes')) ? true : false;
		}

		if(!empty($selected_datetime)){
			if(is_array($selected_datetime)){
			}
		}
		// Set value for the event datetimes
		$datetimes = WP_Event_Manager_Filters::get_datetimes_filter();

		// Set value for the ticket prices		
		// $ticket_prices	=	WP_Event_Manager_Filters::get_ticket_prices_filter();
		
		// Array handling
		$datetimes            = is_array($datetimes) ? $datetimes : array_filter(array_map('trim', explode(',', $datetimes)));
		$categories           = is_array($categories) ? $categories : array_filter(array_map('trim', explode(',', $categories)));
		$event_types          = is_array($event_types) ? $event_types : array_filter(array_map('trim', explode(',', $event_types)));
		if(!empty($ticket_prices)){
			$ticket_prices        = is_array($ticket_prices) ? $ticket_prices : array_filter(array_map('trim', explode(',', $ticket_prices)));
		}
		// Get keywords, location, datetime, category, event type and ticket price from query string if set
		if(!empty($_GET['search_keywords'])) {
			$keywords = isset($_GET['search_keywords']) ? sanitize_text_field($_GET['search_keywords']) : '';
		}

		if(!empty($_GET['search_location'])) {
			$location = isset($_GET['search_location']) ? sanitize_text_field($_GET['search_location']) : '';
		}

		if(!empty($_GET['search_datetime'])) {
			$search_datetime = isset($_GET['search_datetime']) ? sanitize_text_field($_GET['search_datetime']) : '';
		}

		if(!empty($_GET['search_category'])) {
			if (!empty($_GET['search_category'])) {
				if (is_array($_GET['search_category'])) {
					$search_category = array_map('sanitize_text_field', $_GET['search_category']);
				} else {
					$search_category = array_map('sanitize_text_field', explode(',', $_GET['search_category']));
				}
			} else {
				$search_category = array();
			}
		}

		if(!empty($_GET['search_event_type'])) {
			if (!empty($_GET['search_event_type'])) {
				if (is_array($_GET['search_event_type'])) {
					$search_event_type = array_map('sanitize_text_field', $_GET['search_event_type']);
				} else {
					$search_event_type = array_map('sanitize_text_field', explode(',', $_GET['search_event_type']));
				}
			} else {
				$search_event_type = array();
			}
		}

		if(!empty($_GET['search_ticket_price'])) {
			$selected_ticket_price = isset($_GET['search_ticket_price']) ? sanitize_text_field($_GET['search_ticket_price']) : '';
		}
		$allowed_templates = apply_filters('event_manager_output_events_defaults', array(
			'event-classic-filters.php',
			'event-crystal-filters.php',
		));

		$filter_file_option = get_option('event_manager_filter_design');
		$filter_file = $filter_file_option ? basename($filter_file_option . '.php') : 'event-classic-filters.php';

		if($show_filters) {
			$event_filter_args = array(
				'per_page' => $per_page,
				'orderby' => $orderby,
				'order' => $order,
				'datetimes' => $datetimes,
				'selected_datetime' => $selected_datetime,
				'show_categories' => $show_categories,
				'show_category_multiselect' => $show_category_multiselect,
				'categories' => $categories,
				'selected_category' => !empty($selected_category) ? explode(',', $selected_category) : '',
				'show_event_types' => $show_event_types,
				'show_event_type_multiselect' => $show_event_type_multiselect,
				'event_types' => $event_types,
				'selected_event_type' => !empty($selected_event_type) ? explode(',', $selected_event_type) : '',
				'show_ticket_prices' => $show_ticket_prices,
				'ticket_prices' => $ticket_prices,
				'selected_ticket_price' => $selected_ticket_price,
				'atts' => $atts,
				'location' => $location,
				'keywords' => $keywords,
				'event_online' => $event_online,
			);
			// Only load if it's in the allowed list
			if (in_array($filter_file, $allowed_templates, true)) {
				get_event_manager_template($filter_file, $event_filter_args);
			} else {
				get_event_manager_template('event-classic-filters.php', $event_filter_args);
			}
			//get_event_manager_template('event-listings-start.php', array('layout_type' => esc_attr( $layout_type ), 'title' => $title));
			//get_event_manager_template('event-listings-end.php', array('show_filters' => $show_filters, 'show_more' => $show_more, 'show_pagination' => $show_pagination));

		} else {
			
			if (!empty($selected_datetime)) {
				// Get date and time settings defined in the admin panel Event listing -> Settings -> Date & Time formatting
				$datepicker_date_format = WP_Event_Manager_Date_Time::get_datepicker_format();
				
				// Convert datepicker format into PHP date() function date format
				$php_date_format = WP_Event_Manager_Date_Time::get_view_date_format_from_datepicker_date_format($datepicker_date_format);

				$selected_datetime = explode(',', $selected_datetime);

				$start_date = esc_attr(strip_tags($selected_datetime[0]));
				$end_date = isset($selected_datetime[1]) ? esc_attr(strip_tags($selected_datetime[1])) : $start_date;

				if ($start_date == 'today') {
					$start_date = date($php_date_format);
				} else if ($start_date == 'tomorrow') {
					$start_date = date($php_date_format, strtotime('+1 day'));
				}

				if ($end_date == 'today') {
					$end_date = date($php_date_format);
				} else if ($end_date == 'tomorrow') {
					$end_date = date($php_date_format, strtotime('+1 day'));
				}

				// Parse and format the dates
				$arr_selected_datetime['start'] = WP_Event_Manager_Date_Time::date_parse_from_format($php_date_format, $start_date);
				$arr_selected_datetime['end'] = WP_Event_Manager_Date_Time::date_parse_from_format($php_date_format, $end_date);

				$arr_selected_datetime['start'] = date_i18n($php_date_format, strtotime($arr_selected_datetime['start']));
				$arr_selected_datetime['end'] = date_i18n($php_date_format, strtotime($arr_selected_datetime['end']));

				$selected_datetime = json_encode($arr_selected_datetime);
			}

		}
		if(empty($event_types) && !empty($selected_event_type)) {
			$event_types = array_filter(array_map('trim', explode(',', $selected_event_type)));
		}
		if(empty($categories) && !empty($selected_category)) {
			$categories = array_filter(array_map('trim', explode(',', $selected_category)));
		}
		$events = get_event_listings(apply_filters('event_manager_output_events_args', array(
			'search_location'   => $location,
			'search_keywords'   => $keywords,
			'search_datetimes'  => array($selected_datetime),
			'search_categories' => !empty($categories) ? $categories : '',
			'search_event_types'	=> !empty($event_types) ? $event_types : '',
			'search_ticket_prices'  => !empty($ticket_prices) ? $ticket_prices : '',
			'orderby'           => $orderby,
			'order'             => $order,
			'posts_per_page'    => $per_page,
			'featured'          => $featured,
			'cancelled'         => $cancelled,
			'event_online'    	=> $event_online,
			'paged'             => $current_page,
		)));

		if($layout_type == 'all'){
			$default_view = get_option('event_manager_default_view');

			if (!empty($default_view)) {
				$layout_type = $default_view;
				if ($default_view == 'calendar') {
					$layout_type = 'all';
				}
			}
		}
		
		if($events->have_posts()) :

			wp_enqueue_script('wp-event-manager-ajax-filters');
			get_event_manager_template('event-listings-start.php', array('layout_type' => esc_attr( $layout_type ), 'title' => $title));
			while ($events->have_posts()) : $events->the_post();
				$hide_event = apply_filters('wpem_hide_selected_event', false, get_the_id());
				if($hide_event == true){
					continue;
				}
				get_event_manager_template_part('content', 'event_listing');
			endwhile; 
			get_event_manager_template('event-listings-end.php', array('show_pagination' => $show_pagination, 'show_more' => $show_more, 'per_page' => $per_page, 'events' => $events, 'show_filters' => $show_filters));
		 else :
			
			get_event_manager_template('event-listings-start.php', array('layout_type' => esc_attr( $layout_type ), 'title' => $title));
			$default_events = get_posts(array(
				'numberposts' => -1,
				'post_type'   => 'event_listing',
				'post_status'   => 'publish'
			));
			if(count($default_events) == 0): ?>
				<div class="no_event_listings_found wpem-alert wpem-alert-danger wpem-mb-0"><?php esc_attr_e('There are currently no events.', 'wp-event-manager'); ?></div>
			<?php else:
				 do_action('event_manager_output_events_no_results');
			endif;
		endif;
		wp_reset_postdata();

		$data_attributes_string = '';

		$data_attributes        = array(
			'location'        => $location,
			'keywords'        => $keywords,
			'show_filters'    => $show_filters ? 'true' : 'false',
			'show_pagination' => $show_pagination ? 'true' : 'false',
			'per_page'        => $per_page,
			'orderby'         => $orderby,
			'order'           => $order,
			'datetimes'       => $selected_datetime,
			'categories'      => !empty($categories) ? implode(',', $categories) : '',
			'event_types'     => !empty($event_types) ? implode(',', $event_types) : '',
			'ticket_prices'   => !empty($ticket_prices) ? implode(',', $ticket_prices) : '',
			'event_online'    => $event_online,
		);

		if(!is_null($featured)) {
			$data_attributes['featured'] = $featured ? 'true' : 'false';
		}

		if(!is_null($cancelled)) {
			$data_attributes['cancelled']   = $cancelled ? 'true' : 'false';
		}

		foreach ($data_attributes as $key => $value) {
			$data_attributes_string .= 'data-' . esc_attr($key) . '="' . esc_attr($value) . '" ';
		}

		$event_listings_output = apply_filters('event_manager_event_listings_output', ob_get_clean());
		return '<div class="event_listings" ' . $data_attributes_string . '>' . $event_listings_output . '</div>';
	}

	/**
	 * Output some content when no results were found.
	 */
	public function output_no_results()	{
		get_event_manager_template('content-no-events-found.php');
	}

	/**
	 * Output anchor tag close: single dj details url
	 */
	public function dj_more_info_link($dj_id)	{
		global $post;

		if(empty($post) || 'event_listing' !== $post->post_type) {
			return;
		}

		if(isset($dj_id) && !empty($dj_id)) {
			$dj_url = get_permalink($dj_id);
			if(isset($dj_url) && !empty($dj_url)) {
				printf('<div class="wpem-dj-page-url-button"><a href="%s" class="wpem-theme-button"><span>%s</span></a></div>',  esc_url(get_permalink($dj_id)), esc_html__('More info', 'wp-event-manager'));
			}
		}
	}

	/**
	 * Get string as a bool.
	 * @param  string $value
	 * @return bool
	 */
	public function string_to_bool($value)	{
		return (is_bool($value) && $value) || in_array($value, array('1', 'true', 'yes')) ? true : false;
	}

	/**
	 * Show results div.
	 */
	public function event_filter_results()	{
		echo wp_kses_post('<div class="showing_applied_filters"></div>');
	}

	/**
	 * Output data of event.
	 *
	 * @access public
	 * @param array $args
	 * @return string
	 */
	public function output_event($atts) {
		if ( defined('REST_REQUEST') && REST_REQUEST ) {
			return '';
		}
		
		extract(shortcode_atts(array(
			'id' => esc_attr(''),
		), $atts));

		$event_post = get_post($id);
		if (!$event_post || $event_post->post_type !== 'event_listing' || !current_user_can('read_post', $id)) {
    		return __('You are not allowed to view this event.', 'wp-event-manager');
		}
		if('' === get_option('event_manager_hide_expired_content', 1)) {
			$post_status = array('publish', 'expired');
		} else {
			$post_status = 'publish';
		}

		ob_start();

		$args = array(
			'post_type'   => 'event_listing',
			'post_status' => $post_status,
			'p'           => $id
		);

		$events = new WP_Query($args);
		if($events->have_posts()) :
			while ($events->have_posts()) : $events->the_post(); ?>
				<div class="clearfix" />
				<?php get_event_manager_template_part('content-single', 'event_listing'); 
			endwhile;
		endif;
		wp_reset_postdata();
		return '<div class="event_shortcode single_event_listing">' . ob_get_clean() . '</div>';
	}


	/**
	 * Event Summary shortcode.
	 *
	 * @access public
	 * @param array $args
	 * @return string
	 */
	public function output_event_summary($atts)	{
		$atts = shortcode_atts(array(
			'id'       => '',
			'width'    => '250px',
			'align'    => 'left',
			'featured' => null,
			'limit'    => -1,
		), $atts);

		$id       = absint($atts['id']);
		$width    = preg_match('/^\d+(px|%)$/', $atts['width']) ? $atts['width'] : '250px';
		$align    = in_array($atts['align'], array('left', 'right', 'center'), true) ? $atts['align'] : 'left';
		$limit    = intval($atts['limit']);
		$featured = $atts['featured'];

		ob_start();

		$args = array(
			'post_type'   => 'event_listing',
			'post_status' => 'publish'
		);

		if(!$id) {
			$args['posts_per_page'] = $limit;
			$args['orderby']        = 'rand';
			if(!is_null($featured)) {
				$args['meta_query'] = array(array(
					'key'     => '_featured',
					'value'   => '1',
					'compare' => ($featured == "true") ? '=' : '!='

				));
			}
		} else {
			$args['p'] = absint($id);
			if(!is_null($featured)) {
				$args['meta_query'] = array(array(
					'key'     => '_featured',
					'value'   => '1',
					'compare' => ($featured == "true") ? '=' : '!='

				));
			}
		}

		$events = new WP_Query($args);
		if($events->have_posts()) { 
			while ($events->have_posts()) : $events->the_post();
				echo wp_kses_post('<div class="event_summary_shortcode align' . esc_attr($align) . '" style="width: ' . esc_attr($width) . '">');
				get_event_manager_template_part('content-summary', 'event_listing');
				echo wp_kses_post('</div>');
			endwhile;
		}else{
			echo '<div class="entry-content"><div class="wpem-local-connter"><div class="wpem-alert wpem-alert-info">';
			echo esc_attr_e('There are no events.','wp-event-manager');    
			echo '</div></div></div>';
		}
		wp_reset_postdata();
		return ob_get_clean();
	}

	/**
	 * Show the registration area.
	 */
	public function output_event_register($atts){
		ob_start();	
		
		$atts = shortcode_atts(array(
			'id' => '',
		), $atts);

		$id = absint($atts['id']);

		if (!$id) {
			return '';
		}

		$post = get_post($id);
		if ( ! $post || $post->post_type !== 'event_listing' ) {
			return '';
		}
		// If post is private, check capability
		if ( 'private' === get_post_status( $post ) && ! current_user_can( 'read_post', $post->ID ) ) {
			return '';
		}
		setup_postdata($post);?>
		<div class="event-manager-registration-wrapper">
			<?php $register = get_event_registration_method($post->ID);
			if (!empty($register) && isset($register->type)) {
				do_action('event_manager_registration_details_' . sanitize_key($register->type), $register);
			} ?>
		</div>
		<?php
		wp_reset_postdata();
		return ob_get_clean();
	}

	/**
	 * Output of past event.
	 *
	 * @access public
	 * @param mixed $args
	 * @return void
	 */
	public function output_past_events($atts){
		ob_start();

		$atts = shortcode_atts(array(
			'show_pagination'      => true,
			'per_page'             => get_option('event_manager_per_page', 10),
			'order'                => 'DESC',
			'orderby'              => 'event_start_date',
			'location'             => '',
			'keywords'             => '',
			'selected_datetime'    => '',
			'selected_categories'  => '',
			'selected_event_types' => '',
			'layout_type'          => 'all',
			'title'                => __('Past Events', 'wp-event-manager'),
		), $atts);
		$per_page = absint($atts['per_page']);
		$show_pagination = $atts['show_pagination'];

		$paged = (get_query_var('paged')) ? absint(get_query_var('paged')) : 1;
		$show_pagination = sanitize_text_field($atts['show_pagination']);
		$per_page = sanitize_text_field($atts['per_page']);
		$args_past = array(
			'post_type'      => 'event_listing',
			'post_status'    => array('expired'),
			'posts_per_page' => absint($atts['per_page']),
			'paged'          => $paged,
			'order'          => $atts['order'],
			'orderby'        => $atts['orderby'],
		);
	
		// Keywords
		if (!empty($atts['keywords'])) {
			$args_past['s'] = sanitize_text_field($atts['keywords']);
		}

		// Categories
		if (!empty($atts['selected_categories'])) {
			$categories = array_map('sanitize_title', explode(',', $atts['selected_categories']));
			$args_past['tax_query'][] = [
			'taxonomy' => 'event_sounds',
				'field'    => 'slug',
				'terms'    => $categories,
			];
		}

		// Event Types
		if (!empty($atts['selected_event_types'])) {
			$event_types = array_map('sanitize_title', explode(',', $atts['selected_event_types']));
			$args_past['tax_query'][] = [
				'taxonomy' => 'event_listing_type',
				'field'    => 'slug',
				'terms'    => $event_types,
			];
		}

		// Date Filters
		if (!empty($atts['selected_datetime'])) {
			$datetimes = explode(',', sanitize_text_field($atts['selected_datetime']));
			$converted = [];

			foreach ($datetimes as $date) {
				switch (strtolower($date)) {
					case 'today':
						$converted[] = date('Y-m-d');
						break;
					case 'yesterday':
						$converted[] = date('Y-m-d', strtotime('-1 day'));
						break;
					case 'tomorrow':
						$converted[] = date('Y-m-d', strtotime('+1 day'));
						break;
					default:
						$converted[] = sanitize_text_field($date);
				}
			}

			if (count($converted) === 1) {
				$args_past['meta_query'][] = [
					'key'     => '_event_start_date',
					'value'   => $converted[0],
					'compare' => '=',
					'type'    => 'DATE'
				];
			} elseif (count($converted) === 2) {
				$args_past['meta_query'][] = [
					'key'     => '_event_start_date',
					'value'   => [$converted[0], $converted[1]],
					'compare' => 'BETWEEN',
					'type'    => 'DATE'
				];
			}
		}

		// Location
		if (!empty($atts['location'])) {
			$args_past['meta_query'][] = [
				'key'     => '_event_location',
				'value'   => sanitize_text_field($atts['location']),
				'compare' => 'LIKE'
			];
		}

		// Handle custom order by
		if ('event_start_date' === $args_past['orderby']) {
			$args_past['orderby']    = 'meta_value';
			$args_past['meta_key']   = '_event_start_date';
			$args_past['meta_type']  = 'DATETIME';
		}

		$args_past = apply_filters('event_manager_past_event_listings_args', $args_past);
		$past_events = new WP_Query($args_past);

		wp_reset_query();

		// Remove calender view
		remove_action('end_event_listing_layout_icon', 'add_event_listing_calendar_layout_icon');

		if($past_events->have_posts()) : ?>
			<div class="past_event_listings">
				<?php get_event_manager_template('event-listings-start.php', array('layout_type' => sanitize_key( $atts['layout_type'] ), 'title' => esc_html($atts['title'])));
				while ($past_events->have_posts()) : $past_events->the_post();
					get_event_manager_template_part('content', 'past_event_listing');
				endwhile;
				get_event_manager_template('event-listings-end.php');
				if($past_events->found_posts > $per_page) :
					if($show_pagination == "true") : ?>
						<div class="event-dj-pagination wpem-col-12">
							<?php get_event_manager_template('pagination.php', array('max_num_pages' => $past_events->max_num_pages)); ?>
						</div>
					<?php endif;
				endif; ?>
			</div>
		<?php else :
			do_action('event_manager_output_events_no_results');
		endif;
		wp_reset_postdata();
		$event_listings_output = apply_filters('event_manager_past_event_listings_output', ob_get_clean());
		return  $event_listings_output;
	}

	/**
	 *  It is very simply a plugin that outputs a list of all djs that have listed in selected event on your website. 
	 *  Once you have added a title to your page add the this shortcode: [single_event_dj]
	 *  This will output selected event's all djs.
	 *
	 * @access public
	 * @param array $atts
	 * @return string
	 * @since 3.1.32
	 */
	public function output_single_event_dj($atts)	{
		$atts = shortcode_atts(array(
			'id' => '',
		), $atts);

		$id = absint($atts['id']);

		ob_start();

		$event = get_post($id);
		if (!$event || $event->post_type !== 'event_listing' || !current_user_can('read_post', $id)) {
			return ''; // Or show a "not allowed" message
		}

		ob_start();

		do_action('single_event_djs_content_start');

		get_event_manager_template(
			'content-single-event-djs.php',
			array(
				'event'    	  => $event,
				'event_id'    => $id,
			),
			'wp-event-manager/dj',
			EVENT_MANAGER_PLUGIN_DIR . '/templates/dj'
		);

		wp_reset_postdata();

		do_action('single_event_djs_content_end');

		return ob_get_clean();
	}

	/**
	 *  It is very simply a plugin that outputs a list of all locals that have listed in selected event on your website. 
	 *  Once you have added a title to your page add the this shortcode: [single_event_local]
	 *  This will output selected event's all locals.
	 *
	 * @access public
	 * @param array $atts
	 * @return string
	 * @since 3.1.32
	 */
	public function output_single_event_local($atts)	{
		$atts = shortcode_atts(array(
			'id' => '',
		), $atts);

		$id = absint($atts['id']);

		ob_start();

		$event = get_post($id);
		if (!$event || $event->post_type !== 'event_listing' || !current_user_can('read_post', $id)) {
			return ''; // Or show a "not allowed" message
		}

		ob_start();

		do_action('single_event_locals_content_start');

		get_event_manager_template(
			'content-single-event-locals.php',
			array(
				'event'    	  => $event,
				'event_id'    => $id,
			),
			'wp-event-manager/local',
			EVENT_MANAGER_PLUGIN_DIR . '/templates/local'
		);

		wp_reset_postdata();

		do_action('single_event_locals_content_end');

		return ob_get_clean();
	}
}

new WP_Event_Manager_Shortcodes();
