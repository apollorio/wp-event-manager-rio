<?php
/*
* This file the functionality of ajax for event listing and file upload.
*/ 

if(!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * WP_Event_Manager_Ajax class.
*/
class WP_Event_Manager_Ajax {
	/**
	 * The single instance of the class.
	 *
	 * @var self
	 * @since  2.5
	 */
	private static $_instance = null;

	/**
	 * Allows for accessing single instance of class. Class should only be constructed once per call.
	 *
	 * @since  2.5
	 * @static
	 * @return self Main instance.
	 */
	public static function instance() {
		if(is_null(self::$_instance)) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Constructor.
	*/
	public function __construct() {
		add_action('init', array(__CLASS__, 'add_endpoint'));
		add_action('template_redirect', array(__CLASS__, 'do_em_ajax'), 0);

		// EM Ajax endpoints
		add_action('event_manager_ajax_get_listings', array($this, 'get_listings'));
		add_action('event_manager_ajax_upload_file', array($this, 'upload_file'));
		add_action('event_manager_ajax_load_more_upcoming_events', array($this, 'load_more_upcoming_events'));
		add_action('event_manager_ajax_get_upcoming_listings', array($this, 'get_upcoming_listings'));

		// BW compatible handlers
		add_action('wp_ajax_nopriv_event_manager_get_listings', array($this, 'get_listings'));
		add_action('wp_ajax_event_manager_get_listings', array($this, 'get_listings'));
		add_action('wp_ajax_nopriv_event_manager_upload_file', array($this, 'upload_file'));
		add_action('wp_ajax_event_manager_upload_file', array($this, 'upload_file'));
		add_action('wp_ajax_add_dj', array($this, 'add_dj'));
		add_action('wp_ajax_nopriv_add_dj', array($this, 'add_dj'));

		add_action('wp_ajax_add_local', array($this, 'add_local'));
		add_action('wp_ajax_nopriv_add_local', array($this, 'add_local'));
	}
	
	/**
	 * Add our endpoint for frontend ajax requests.
	*/
	public static function add_endpoint() {

		add_rewrite_tag('%em-ajax%', '([^/]*)');
		add_rewrite_rule('em-ajax/([^/]*)/?', 'index.php?em-ajax=$matches[1]', 'top');
		add_rewrite_rule('index.php/em-ajax/([^/]*)/?', 'index.php?em-ajax=$matches[1]', 'top');
	}

	/**
	 * Get Event Manager Ajax Endpoint.
	 * @param  string $request Optional
	 * @param  string $ssl     Optional
	 * @return string
	 */
	public static function get_endpoint($request = '%%endpoint%%', $ssl = null) {

		if(strstr(get_option('permalink_structure'), '/index.php/')) {
			$endpoint = trailingslashit(home_url('/index.php/em-ajax/' . $request . '/', 'relative'));
		} elseif(get_option('permalink_structure')) {
			$endpoint = trailingslashit(home_url('/em-ajax/' . $request . '/', 'relative'));
		} else {
			$endpoint = add_query_arg('em-ajax', $request, trailingslashit(home_url('', 'relative')));
		}
		return esc_url_raw($endpoint);
	}

	/**
	 * Check for WC Ajax request and fire action.
	 */
	public static function do_em_ajax() {
		global $wp_query;
		if(!empty($_GET['em-ajax'])) {
			 $wp_query->set('em-ajax', esc_attr($_GET['em-ajax']));
		}

   		if($action = $wp_query->get('em-ajax')) {
   			if(!defined('DOING_AJAX')) {
				define('DOING_AJAX', true);
			}
			// Not home - this is an ajax endpoint
			$wp_query->is_home = false;
   			do_action('event_manager_ajax_' . esc_attr($action));
   			die();
   		}
	}

	function load_more_upcoming_events($atts) {
		$paged = isset($_POST['value']) ? intval($_POST['value']) : 1;
		$per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : esc_attr(get_option('event_manager_per_page'));
		$orderby = isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : 'date';
		$order = isset($_POST['order']) ? sanitize_text_field($_POST['order']) : 'DESC';

		$args = array(
			'post_type'      => 'event_listing',
			'post_status'    => array('publish'),
			'posts_per_page' => $per_page,
			'paged'          => $paged,
			'order'           => $order,
			'orderby'         => $orderby,
			'meta_query'     => array(
				array(
					'relation' => 'OR',
					array(
						'key'     => '_event_start_date',
						'value'   => current_time('Y-m-d H:i:s'),
						'type'    => 'DATETIME',
						'compare' => '>='
					),
					array(
						'key'     => '_event_end_date',
						'value'   => current_time('Y-m-d H:i:s'),
						'type'    => 'DATETIME',
						'compare' => '>='
					)
				),
				array(
					'key'     => '_cancelled',
					'value'   => '1',
					'compare' => '!='
				),
			)
		);

		if('featured' === $orderby) {
			$args['meta_query'] = array(
				'relation' => 'AND',
				'featured_clause' => array(
					'key'     => '_featured',
					'compare' => 'EXISTS',
				),
				'event_start_date_clause' => array(
					'key'     => '_event_start_date',
					'compare' => 'EXISTS',
				), 
				'event_start_time_clause' => array(
					'key'     => '_event_start_time',
					'compare' => 'EXISTS',
				), 
			);
			$args['orderby'] = array(
				'featured_clause' => 'desc',
				'event_start_date_clause' => $order,
				'event_start_time_clause' => $order,
			);
		}

		if('rand_featured' === $orderby) {
			$args['orderby'] = array(
				'menu_order' => 'ASC',
				'rand'       => 'ASC',
			);
		}
		// If orderby meta key _event_start_date 
		if('event_start_date' === $orderby) {
			$args['orderby'] ='meta_value';
			$args['meta_key'] ='_event_start_date';
			$args['meta_type'] ='DATETIME';
		}
		// If orderby event_start_date and time  both
		if('event_start_date_time' === $orderby) {
			$args['meta_query'] = array(
				'relation' => 'AND',
				'event_start_date_clause' => array(
					'key'     => '_event_start_date',
					'compare' => 'EXISTS',
				),
				'event_start_time_clause' => array(
					'key'     => '_event_start_time',
					'compare' => 'EXISTS',
				), 
			);
			$args['orderby'] = array(
				'event_start_date_clause' => $order,
				'event_start_time_clause' => $order,
			);
		}
		$upcoming_events = new WP_Query($args);

		if ($upcoming_events->have_posts()) {
			ob_start();

			while ($upcoming_events->have_posts()) {
				$upcoming_events->the_post();
				get_event_manager_template_part('content', 'past_event_listing');
			}

			$events_html = ob_get_clean();
			$no_more_events = $upcoming_events->found_posts <= $paged * $per_page;

			wp_send_json_success(array(
				'events_html' => $events_html,
				'no_more_events' => $no_more_events
			));
		} else {
			wp_send_json_error(array(
				'error' => __('No more events found.', 'wp-event-manager')
			));
		}

		wp_reset_postdata();
	}

	/**
	 * Get Upcoming Listings
	 */
	function get_upcoming_listings($atts) {

		$search_location = isset( $_POST['search_location'] ) ? sanitize_text_field( $_POST['search_location'] ) : '';
		$search_categories = isset( $_POST[''] ) ? sanitize_text_field( $_POST['search_categories'] ) : '';
		$event_manager_keyword = isset( $_POST['search_keywords'] ) ? sanitize_text_field( $_POST['search_keywords'] ) : '';
		if( is_array( $search_categories ) ) {
		$search_categories = array_filter( array_map( 'sanitize_text_field', array_map( 'stripslashes', $search_categories ) ) );
		} else {
			$search_categories = sanitize_text_field( stripslashes( $search_categories ) );
			$search_categories = explode( ',', $search_categories );
		}
		$search_event_types = isset( $_POST['search_event_types'] ) ? sanitize_text_field( $_POST['search_event_types'] ) : '';
		if( is_array( $search_event_types ) ) {
			$search_event_types= array_filter( array_map( 'sanitize_text_field', array_map( 'stripslashes', $search_event_types) ) );
		} else {
			$search_event_types = sanitize_text_field( stripslashes( $search_event_types ) );
			$search_event_types= explode( ',', $search_event_types );
		}
		$paged = isset($_POST['value']) ? intval($_POST['value']) : 1;
		$per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : esc_attr(get_option('event_manager_per_page'));
		$orderby = isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : 'date';
		$order = isset($_POST['order']) ? sanitize_text_field($_POST['order']) : 'DESC';

		$args = array(
			'post_type'      => 'event_listing',
			'post_status'    => array('publish'),
			'posts_per_page' => $per_page,
			'orderby'        => $orderby,
			'order'          => $order,
			'paged'          => $paged,
			'meta_query'     => array(
				array(
					'relation' => 'OR',
					array(
						'key'     => '_event_start_date',
						'value'   => current_time('Y-m-d H:i:s'),
						'type'    => 'DATETIME',
						'compare' => '>='
					),
					array(
						'key'     => '_event_end_date',
						'value'   => current_time('Y-m-d H:i:s'),
						'type'    => 'DATETIME',
						'compare' => '>='
					)
				),
				array(
					'key'     => '_cancelled',
					'value'   => '1',
					'compare' => '!='
				),
			)
		);

		if('featured' === $orderby) {
			$args['meta_query'] = array(
				'relation' => 'AND',
				'featured_clause' => array(
					'key'     => '_featured',
					'compare' => 'EXISTS',
				),
				'event_start_date_clause' => array(
					'key'     => '_event_start_date',
					'compare' => 'EXISTS',
				), 
				'event_start_time_clause' => array(
					'key'     => '_event_start_time',
					'compare' => 'EXISTS',
				), 
			);
			$args['orderby'] = array(
				'featured_clause' => 'desc',
				'event_start_date_clause' => $order,
				'event_start_time_clause' => $order,
			);
		}

		if('rand_featured' === $orderby) {
			$args['orderby'] = array(
				'menu_order' => 'ASC',
				'rand'       => 'ASC',
			);
		}
		// If orderby meta key _event_start_date 
		if('event_start_date' === $orderby) {
			$args['orderby'] ='meta_value';
			$args['meta_key'] ='_event_start_date';
			$args['meta_type'] ='DATETIME';
		}
		// If orderby event_start_date and time  both
		if('event_start_date_time' === $orderby) {
			$args['meta_query'] = array(
				'relation' => 'AND',
				'event_start_date_clause' => array(
					'key'     => '_event_start_date',
					'compare' => 'EXISTS',
				),
				'event_start_time_clause' => array(
					'key'     => '_event_start_time',
					'compare' => 'EXISTS',
				), 
			);
			$args['orderby'] = array(
				'event_start_date_clause' => $order,
				'event_start_time_clause' => $order,
			);
		}

		if ( isset( $search_location ) && !empty( $search_location ) ) {
			$args['meta_query'][] = array(
				'key'     => '_event_location',
				'value'   => $search_location,
				'compare' => 'LIKE',
			);
		}
		$tax_query = array();
		if ( isset( $search_event_types ) && !empty( $search_event_types ) && !empty( $search_event_types[0] ) ) {
			$field    = is_numeric($search_event_types[0]) ? 'term_id' : 'slug';
			$operator = 'all' === get_option('event_manager_event_type_filter_type', 'all') && count($search_event_types) > 1 ? 'AND' : 'IN';
			$tax_query[] = array(
				'taxonomy'         => 'event_listing_type',
				'field'            => $field,
				'terms'            => array_filter(array_values($search_event_types)),
				'include_children' => $operator !== 'AND',
				'operator'         => $operator,
			);
		}

		if ( isset( $search_categories ) && !empty( $search_categories ) && !empty( $search_categories[0] ) ) {
			$field    = is_numeric($search_categories[0]) ? 'term_id' : 'slug';
			$operator = 'all' === get_option('event_manager_category_filter_type', 'all') && count($search_categories) > 1 ? 'AND' : 'IN';
			$tax_query[] = array(
				'taxonomy'         => 'event_sounds',
				'field'            => $field,
				'terms'            => array_filter(array_values($search_categories)),
				'include_children' => $operator !== 'AND',
				'operator'         => $operator,
			);
		}

		if ( !empty( $tax_query ) ) {
			$args['tax_query'] = array_merge( array( 'relation' => 'AND' ), $tax_query );
		}

		$upcoming_events = new WP_Query($args);

		if ($upcoming_events->have_posts()) {
			ob_start();

			while ($upcoming_events->have_posts()) {
				$upcoming_events->the_post();
				get_event_manager_template_part('content', 'past_event_listing');
			}

			$events_html = ob_get_clean();
			$no_more_events = $upcoming_events->found_posts <= $paged * $per_page;

			wp_send_json_success(array(
				'events_html' => $events_html,
				'no_more_events' => $no_more_events
			));
			// wp_send_json_success(array(
			// 	'events_html' => $events_html,
			// ));
			} else {
				$no_events_html = '<div class="no_event_listings_found wpem-alert wpem-alert-danger">';
				$no_events_html .= esc_html__('There are no events matching your search.', 'wp-event-manager');
				$no_events_html .= '</div>';

				wp_send_json_success(array(
					'events_html' => $no_events_html
				));
			}
		wp_reset_postdata();
	}

	/**
	 * Get listings via ajax.
	 */
	public function get_listings() {
		global $wp_post_types;
		$result            = array();
		$search_location   = esc_attr(stripslashes($_REQUEST['search_location']));
		$search_keywords   = esc_attr(stripslashes($_REQUEST['search_keywords']));
		$post_type_label   = $wp_post_types['event_listing']->labels->name;
		$orderby           = esc_attr($_REQUEST['orderby']);
		$search_datetimes = '';
		$search_categories = '';
		$search_event_types = '';
		$search_ticket_prices = '';

		if (isset($_REQUEST['search_datetimes'])) {
			$raw_dates = is_array($_REQUEST['search_datetimes']) 
				? array_filter(array_map('stripslashes', $_REQUEST['search_datetimes'])) 
				: array_filter([stripslashes($_REQUEST['search_datetimes'])]);

			if (!empty($raw_dates[0])) {
				$decoded = json_decode($raw_dates[0], true);

				if (!empty($decoded['start']) && !empty($decoded['end'])) {
					$search_datetimes = [$raw_dates[0]];
				} else {
					$search_datetimes = [];
				}
			} else {
				$search_datetimes = [];
			}
		}

		if(isset($_REQUEST['search_categories'])) {
			$search_categories = is_array($_REQUEST['search_categories']) ?  array_filter( array_map('stripslashes', $_REQUEST['search_categories'])) : array_filter(array(stripslashes($_REQUEST['search_categories'])));
		}

		if(isset($_REQUEST['search_event_types'])) {
			$search_event_types =  is_array($_REQUEST['search_event_types']) ?  array_filter( array_map('stripslashes', $_REQUEST['search_event_types'])) :	array_filter(array(stripslashes($_REQUEST['search_event_types'])));
		}

		if(isset($_REQUEST['search_ticket_prices'])) {
			$search_ticket_prices = is_array($_REQUEST['search_ticket_prices']) ?  array_filter( array_map('stripslashes', $_REQUEST['search_ticket_prices'])) : array_filter(array(stripslashes($_REQUEST['search_ticket_prices'])));
		} 
		$args = array(
			'search_location'    	=> $search_location,
			'search_keywords'    	=> $search_keywords,
			'search_datetimes'  	=> $search_datetimes,
			'search_categories'  	=> $search_categories,
			'search_event_types'  	=> $search_event_types,
			'search_ticket_prices'	=> $search_ticket_prices,			
			'orderby'            	=> $orderby,
			'order'              	=> esc_attr($_REQUEST['order']),
			'offset'             	=> (absint($_REQUEST['page']) - 1) * absint($_REQUEST['per_page']),
			'posts_per_page'     	=> absint($_REQUEST['per_page']),
			'lang'    	            => apply_filters('wpem_set_default_page_language', $_REQUEST['lang']),
		);

		if(isset($_REQUEST['cancelled']) && ($_REQUEST['cancelled'] === 'true' || $_REQUEST['cancelled'] === 'false')) {
			$args['cancelled'] = $_REQUEST['cancelled'] === 'true' ? true : false;
		}

		if(isset($_REQUEST['featured']) && ($_REQUEST['featured'] === 'true' || $_REQUEST['featured'] === 'false')) {
			$args['featured'] = $_REQUEST['featured'] === 'true' ? true : false;
			$args['orderby']  = 'featured' === $orderby ? 'date' : $orderby;
		}

		if(isset($_REQUEST['event_online']) && ($_REQUEST['event_online'] === 'true' || $_REQUEST['event_online'] === 'false')) {
			$args['event_online'] = $_REQUEST['event_online'] === 'false' ? $_REQUEST['event_online'] : true;
		}

		ob_start();
		$events = get_event_listings(apply_filters('event_manager_get_listings_args', $args, $_REQUEST));
		$result['found_events'] = false;
		$fully_registered_events = 0;
		if($events->have_posts()) : $result['found_events'] = true;
			while ($events->have_posts()) : $events->the_post(); 
				
				$hide_event = apply_filters('wpem_hide_selected_event', false, get_the_id());
				if($hide_event == true){
					$fully_registered_events++;
					continue;
				}
				get_event_manager_template_part('content', 'event_listing');
			endwhile; 
			$events->found_posts -= $fully_registered_events;
			?>
		<?php else : 
			$default_events = get_posts(array(
					'numberposts' => -1,
					'post_type'   => 'event_listing',
					'post_status'   => 'publish'
			));
			if(count($default_events) == 0): ?>
				<div class="no_event_listings_found wpem-alert wpem-alert-danger wpem-mb-0"><?php esc_attr_e('There are currently no events.', 'wp-event-manager'); ?></div>
			<?php else: get_event_manager_template_part('content', 'no-events-found');
			endif;
		endif;

		$result['html']    = ob_get_clean();
		$result['filter_value'] = array();	
		// Categories
		if($search_categories) {
			$showing_categories = array();
			foreach ($search_categories as $category) {
				$category_object = get_term_by(is_numeric($category) ? 'id' : 'slug', $category, 'event_sounds');
				if(!is_wp_error($category_object)) {
					$showing_categories[] = $category_object->name;
				}
			}
			$result['filter_value'][] = implode(', ', $showing_categories);
		}

		// Event types
		if($search_event_types) {
			$showing_event_types = array();
			foreach ($search_event_types as $event_type) {
				$event_type_object = get_term_by(is_numeric($event_type) ? 'id' : 'slug', $event_type, 'event_listing_type');
				if(!is_wp_error($event_type_object)) {
					$showing_event_types[] = $event_type_object->name;
				}
			}
			$result['filter_value'][] = implode(', ', $showing_event_types);
		}
		
		// Datetimes
		if($search_datetimes) {	
			$showing_datetimes= array();			
			foreach ($search_datetimes as $datetime) { 	
			    $showing_datetimes[]=WP_Event_Manager_Filters::get_datetime_value($datetime);
			}
			$result['filter_value'][] = implode(', ', $showing_datetimes);		
		}
		
		// Ticket prices	
		if($search_ticket_prices) {		
		    $showing_ticket_prices = array();	
			foreach ($search_ticket_prices as $ticket_price) { 	
			    $showing_ticket_prices []= WP_Event_Manager_Filters::get_ticket_price_value($ticket_price);
			}	
			 $result['filter_value'][] = implode(', ', $showing_ticket_prices);		
		}	

		if($search_keywords) {
			$result['filter_value'][] = '&ldquo;' . $search_keywords . '&rdquo;'; 	
		}		
       
        $last_filter_value = array_pop($result['filter_value']);   
        $result_implode=implode(', ', $result['filter_value']);
        if(count($result['filter_value']) >= 1) {
            $result['filter_value']= explode(" ",  $result_implode); 
            $result['filter_value'][]=  " &amp; ";
        } else {
            if(!empty($last_filter_value))
                $result['filter_value']= explode(" ",  $result_implode); 
        }      
        $result['filter_value'][] =  $last_filter_value ." " . $post_type_label;
        
		if($search_location) {
			$result['filter_value'][] = sprintf(wp_kses('located in &ldquo;%s&rdquo;', 'wp-event-manager') , $search_location) ;
		}

		if(sizeof($result['filter_value']) > 1) {
			//translators: %d is the number of matching records found.
        	$message = sprintf( esc_html(_n('Search completed. Found %d matching record.','Search completed. Found %d matching records.',$events->found_posts,'wp-event-manager')), $events->found_posts);
			$result['showing_applied_filters'] = true;
		} else {
			$message = "";
			$result['showing_applied_filters'] = false;			
		}
		
		$search_values = array(
				'location'   => $search_location,
				'keywords'   => $search_keywords,
				'datetimes'  => $search_datetimes,
				'tickets'	 => $search_ticket_prices,
				'types'		 => $search_event_types,
				'categories' => $search_categories
		);
		$result['filter_value'] = apply_filters('event_manager_get_listings_custom_filter_text', $message, $search_values);
		
		// Generate RSS link
		$result['showing_links'] = event_manager_get_filtered_links(array(
			'search_keywords'   => $search_keywords,			
			'search_location'   => $search_location,
			'search_datetimes' => $search_datetimes,
			'search_categories' => $search_categories,
			'search_event_types' => $search_event_types,
			'search_ticket_prices' => $search_ticket_prices
		));
		
		// Generate pagination
		if(isset($_REQUEST['show_pagination']) && $_REQUEST['show_pagination'] === 'true') {
			$result['pagination'] = get_event_listing_pagination($events->max_num_pages, absint($_REQUEST['page']));
		}
		$result['max_num_pages'] = $events->max_num_pages;
		wp_send_json(apply_filters('event_manager_get_listings_result', $result, $events));
	}

	/**
	 * Upload file via ajax.
	 *
	 * No nonce field since the form may be statically cached.
	 */
	public function upload_file() {
		if(!event_manager_user_can_upload_file_via_ajax()) {
			wp_send_json_error(new WP_Error('upload', __('You must be logged in to upload files using this method.', 'wp-event-manager')));
			return;
		}

		$data = array('files' => array());
		if(!empty($_FILES)) {
			foreach ($_FILES as $file_key => $file) {
				$files_to_upload = event_manager_prepare_uploaded_files($file);
				foreach ($files_to_upload as $file_to_upload) {
					$uploaded_file = event_manager_upload_file($file_to_upload, array('file_key' => $file_key));
					if(is_wp_error($uploaded_file)) {
						$data['files'][] = array('error' => $uploaded_file->get_error_message());
					} else {
						$data['files'][] = $uploaded_file;
					}
				}
			}
		}
		wp_send_json($data);
	}

	/**
	 * Add dj.
	 * add dj with popup action
	 * @access public
	 * @param 
	 * @return array
	 * @since 3.1.16
	 */
	public function add_dj() {

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			check_ajax_referer( 'wpem_add_dj_action', 'wpem_add_dj_nonce' );
		}

		if ( ! is_user_logged_in() || ! current_user_can( 'manage_djs' ) ) {
			wp_send_json( [
				'code'    => 403,
				'message' => '<div class="wpem-alert wpem-alert-danger">' . esc_html__( 'Please login as dj to add an dj!', 'wp-event-manager' ) . '</div>',
			] );
			wp_die();
		}
		
		if ( ! isset( $_POST['wpem_add_dj_nonce'] ) 
			|| ! wp_verify_nonce( $_POST['wpem_add_dj_nonce'], 'wpem_add_dj_action' ) ) {
			wp_send_json([
				'code'    => 403,
				'message' => '<div class="wpem-alert wpem-alert-danger">' . esc_html__( 'Security check failed.', 'wp-event-manager' ) . '</div>',
			]);
			wp_die();
		}

		$params = array();
		parse_str($_REQUEST['form_data'], $params);
		$params['dj_description'] = sanitize_text_field($_REQUEST['dj_description']);
		$params['submit_dj'] = 'Submit';

		$data = [];

		if(!empty($params['dj_name']) && isset($params['dj_id'])  && $params['dj_id'] == 0){
			$_POST = $params;

			if(isset($_COOKIE['wp-event-manager-submitting-dj-id']))
			    unset($_COOKIE['wp-event-manager-submitting-dj-id']);				
			if(isset($_COOKIE['wp-event-manager-submitting-dj-key']))
			    unset($_COOKIE['wp-event-manager-submitting-dj-key']);

			$GLOBALS['event_manager']->forms->get_form('submit-dj', array());
			$form_submit_dj_instance = call_user_func(array('WP_Event_Manager_Form_Submit_dj', 'instance'));
			$event_fields =	$form_submit_dj_instance->merge_with_custom_fields('frontend');

			// Submit current event with $_POST values
			$form_submit_dj_instance->submit_handler();

			$dj_id = $form_submit_dj_instance->get_dj_id();

			if(isset($dj_id) && !empty($dj_id)){
				$dj = get_post($dj_id);

				$data = [
					'code' => 200,
					'dj' => [
						'dj_id' 	=> $dj_id,
						'dj_name' => $dj->post_title,
					],
					'message' => '<div class="wpem-alert wpem-alert-danger">'. __('Successfully created', 'wp-event-manager') . '</div>',
				];
			} else {
				$data = [
					'code' => 404,
					'message' => '<div class="wpem-alert wpem-alert-danger">'. $form_submit_dj_instance->get_errors() . '</div>',
				];
			}
		} else {
			$data = [
				'code' => 404,
				'message' => '<div class="wpem-alert wpem-alert-danger">'. __('dj Name is a required field.', 'wp-event-manager') . '</div>',
			];
		}
		wp_send_json($data);
		wp_die();
	}

	/**
	 * Add local.
	 * add local with popup action
	 * @access public
	 * @param 
	 * @return array
	 * @since 3.1.16
	 */
	public function add_local() {

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			check_ajax_referer( 'wpem_add_local_action', 'wpem_add_local_nonce' );
		}

		if ( ! is_user_logged_in() || ( ! current_user_can( 'manage_locals' ) ) ) {
			wp_send_json( [
				'code'    => 403,
				'message' => '<div class="wpem-alert wpem-alert-danger">' . esc_html__( 'Please login as dj to add local!', 'wp-event-manager' ) . '</div>',
			] );
			wp_die();
		}
		
		if ( ! isset( $_POST['wpem_add_local_nonce'] ) 
			|| ! wp_verify_nonce( $_POST['wpem_add_local_nonce'], 'wpem_add_local_action' ) ) {
			wp_send_json([
				'code'    => 403,
				'message' => '<div class="wpem-alert wpem-alert-danger">' . esc_html__( 'Security check failed.', 'wp-event-manager' ) . '</div>',
			]);
			wp_die();
		}

		$params = array();
		parse_str($_REQUEST['form_data'], $params);
		$params['local_description'] = sanitize_text_field($_REQUEST['local_description']);
		$params['submit_local'] = 'Submit';

		$data = [];

		if(!empty($params['local_name']) && isset($params['local_id'])  && $params['local_id'] == 0) {
			$_POST = $params;

			if(isset($_COOKIE['wp-event-manager-submitting-local-id']))
			    unset($_COOKIE['wp-event-manager-submitting-local-id']);				
			if(isset($_COOKIE['wp-event-manager-submitting-local-key']))
			    unset($_COOKIE['wp-event-manager-submitting-local-key']);

			$GLOBALS['event_manager']->forms->get_form('submit-local', array());
			$form_submit_local_instance = call_user_func(array('WP_Event_Manager_Form_Submit_local', 'instance'));
			$event_fields =	$form_submit_local_instance->merge_with_custom_fields('frontend');

			// Submit current event with $_POST values
			$form_submit_local_instance->submit_handler();
			$local_id = $form_submit_local_instance->get_local_id();

			if(isset($local_id) && !empty($local_id)){
				$local = get_post($local_id);

				$data = [
					'code' => 200,
					'local' => [
						'local_id' 	=> $local_id,
						'local_name' => $local->post_title,
					],
					'message' => '<div class="wpem-alert wpem-alert-danger">'. __('Successfully created', 'wp-event-manager') . '</div>',
				];
			}else{
				$data = [
					'code' => 404,
					'message' => '<div class="wpem-alert wpem-alert-danger">'. $form_submit_local_instance->get_errors() . '</div>',
				];
			}
		} else {
			$data = [
				'code' => 404,
				'message' => '<div class="wpem-alert wpem-alert-danger">'. __('local Name is a required field.', 'wp-event-manager') . '</div>',
			];
		}
		wp_send_json($data);
		wp_die();
	}
}
 WP_Event_Manager_Ajax::instance();