<?php

/**
 * Template Functions
 * Template functions specifically created for event listings and other event related methods.
 *
 * @author 	WP Event Manager
 * @category 	Core
 * @package 	Event Manager/Template
 * @version     1.0.5
 */

/**
 * Returns the translated role of the current user. If that user has
 * no role for the current `log, it returns false.
 *
 * @return string The name of the current role
 * @since 1.0.0
 **/
function get_event_manager_current_user_role(){
	global $wp_roles;
	$current_user = wp_get_current_user();
	$roles = $current_user->roles;
	$role = array_shift($roles);
	return isset($wp_roles->role_names[$role]) ? translate_user_role($wp_roles->role_names[$role]) : false;
}

/**
 * Get and include template files.
 *
 * @param mixed $template_name
 * @param array $args (default: array())
 * @param string $template_path (default: '')
 * @param string $default_path (default: '')
 * @return void
 */
function get_event_manager_template($template_name, $args = array(), $template_path = 'wp-event-manager', $default_path = ''){
	if($args && is_array($args)) {
		extract($args);
	}

    $template_name = str_replace("\0", '', $template_name);
    $template_name = str_replace('\\', '/', $template_name);
    $template_name = preg_replace('#\.\./#', '', $template_name);
    $template_name = preg_replace('#\.\.\\\#', '', $template_name);

    $template_path_full = locate_event_manager_template($template_name, $template_path, $default_path);
    if (!$template_path_full || !file_exists($template_path_full)) {
        return;
    }
    $real_template_path = realpath($template_path_full);
    $allowed_paths = [
        realpath(get_stylesheet_directory() . '/' . $template_path),
        realpath(get_template_directory() . '/' . $template_path),
        realpath($default_path),
		realpath( EVENT_MANAGER_PLUGIN_DIR . '/templates' ),
    ];
    $is_valid = false;
    foreach ($allowed_paths as $allowed_path) {
        if ($allowed_path && strpos($real_template_path, $allowed_path) === 0) {
            $is_valid = true;
            break;
        }
    }

    if (!$is_valid) {
        return;
    }
    include $real_template_path;
}

/**
 * Locate a template and return the path for inclusion.
 *
 * This is the load order:
 *
 *		yourtheme		/	$template_path	/	$template_name
 *		yourtheme		/	$template_name
 *		$default_path	/	$template_name
 *
 * @param string $template_name
 * @param string $template_path (default: 'wp-event-manager')
 * @param string|bool $default_path (default: '') False to not load a default
 * @return string
 */
function locate_event_manager_template($template_name, $template_path = 'wp-event-manager', $default_path = ''){
	// Look within passed path within the theme - this is priority.
	$template = locate_template(
		array(
			trailingslashit($template_path) . $template_name,
			$template_name
		)
	);

	// Get default template.
	if(!$template && $default_path !== false) {
		$default_path = $default_path ? $default_path : EVENT_MANAGER_PLUGIN_DIR . '/templates/';
		if(file_exists(trailingslashit($default_path) . $template_name)) {
			$template = trailingslashit($default_path) . $template_name;
		}
	}

	// Return what we found.
	return apply_filters('event_manager_locate_template', $template, $template_name, $template_path);
}

/**
 * Get template part (for templates in loops).
 *
 * @param string $slug
 * @param string $name (default: '')
 * @param string $template_path (default: 'wp-event-manager')
 * @param string|bool $default_path (default: '') False to not load a default
 */
function get_event_manager_template_part($slug, $name = '', $template_path = 'wp-event-manager', $default_path = ''){
	$template = '';
	if($name) {
		$template = locate_event_manager_template("{$slug}-{$name}.php", $template_path, $default_path);
	}
	// If template file doesn't exist, look in yourtheme/slug.php and yourtheme/wp-event-manager/slug.php
	if(!$template) {
		$template = locate_event_manager_template("{$slug}.php", $template_path, $default_path);
	}
	if($template) {
		load_template($template, false);
	}
}

/**
 * Add custom body classes.
 * @param  array $classes
 * @return array
 */
function event_manager_body_class($classes){
	$classes   = (array) $classes;
	$classes[] = sanitize_title(wp_get_theme());
	return array_unique($classes);
}
add_filter('body_class', 'event_manager_body_class');

/**
 * Get events pagination for [events] shortcode.
 * @return [type] [description]
 */
function get_event_listing_pagination($max_num_pages, $current_page = 1){
	ob_start();
	get_event_manager_template('event-pagination.php', array('max_num_pages' => $max_num_pages, 'current_page' => absint($current_page)));
	return ob_get_clean();
}

/**
 * Outputs the events status.
 *
 * @return void
 */
function display_event_status($post = null){
	echo esc_attr(get_event_status($post));
}

/**
 * Gets the events status.
 *
 * @return string
 */
function get_event_status($post = null){
	$post     = get_post($post);
	$status   = $post->post_status;
	$statuses = get_event_listing_post_statuses();

	if(isset($statuses[$status])) {
		$status = $statuses[$status];
	} else {
		$status = __('Inactive', 'wp-event-manager');
	}
	return apply_filters('display_event_status', $status, $post);
}

/**
 * Return whether or not the position has been marked as cancelled.
 *
 * @param  object $post
 * @return boolean
 */
function is_event_cancelled($post = null){
	$post = get_post($post);
	return $post->_cancelled ? true : false;
}

/**
 * Return whether or not the position has been featured.
 *
 * @param  object $post
 * @return boolean
 */
function is_event_featured($post = null){
	$post = get_post($post);
	return $post->_featured ? true : false;
}

/**
 * Return whether or not registrations are allowed.
 *
 * @param  object $post
 * @return boolean
 */
function attendees_can_apply($post = null){
	$post = get_post($post);
	return apply_filters('event_manager_attendees_can_register', (!is_event_cancelled() && !in_array($post->post_status, array('preview', 'expired'))), $post);
}

/**
 * Displays the permalink for an event.
 *
 * @access public
 * @return void
 */
function display_event_permalink($post = null){
	echo esc_attr(get_event_permalink($post));
}

/**
 * This method retrieves the registration information for the event.
 *
 * @access public
 * @param mixed $post (default: null)
 * @return object
 */
function get_event_registration_method($post = null){
	$post = get_post($post);

	if($post && $post->post_type !== 'event_listing') {
		return;
	}

	$method = new stdClass();
	$register  = $post->_registration;

	if(empty($register)) {
		$method->type = 'url';
		return apply_filters('get_event_registration_method', $method, $post);
	}

	if(strstr($register, '@') && is_email($register)) {
		$method->type      = 'email';
		$method->raw_email = $register;
		$method->email     = antispambot($register);
		$method->subject   = apply_filters('event_manager_registration_email_subject', sprintf(wp_kses('Registration via "%s" listing on %s', 'wp-event-manager'), $post->post_title, home_url()), $post);
	} else {
		if(strpos($register, 'http') !== 0)
			$register = 'http://' . $register;
		$method->type = 'url';
		$method->url  = $register;
	}

	return apply_filters('display_event_registration_method', $method, $post);
}

/**
 * Gets the permalink for the event.
 *
 * @access public
 * @param mixed $post (default: null)
 * @return string
 */
function get_event_permalink($post = null){
	$post = get_post($post);
	$link = get_permalink($post);
	return apply_filters('display_event_permalink', $link, $post);
}

/**
 * It displays the event type.
 *
 * @access public
 * @return void
 */
function display_event_type($post = null, $after = ''){

	if($event_type = get_event_type($post)) {
		if(!empty($event_type)) {
			$numType = count($event_type);
			$i = 0;
			foreach($event_type as $type) {
				echo wp_kses(('<a href="' . get_term_link($type->term_id) . '"><span class="wpem-event-type-text event-type ' . esc_attr(sanitize_title($type->slug)) . ' ">' . $type->name . '</span></a>'), array(
					'a' => array(
						'href' => array(),
						'title' => array()
					),
					'span' => array(
						'class'       => array()
					),
				));
				if($numType > ++$i) {
					echo esc_html($after);
				}
			}
		}
	}
}

/**
 * The event type is retrieved here.
 *
 * @access public
 * @param mixed $post (default: null)
 * @return void
 */
function get_event_type($post = null){
	$post = get_post($post);
	if($post->post_type !== 'event_listing' || !get_option('event_manager_enable_event_types')) {
		return;
	}
	$types = wp_get_post_terms($post->ID, 'event_listing_type');

	// Return single if not enabled.
	if(empty($types))
		$types = '';
	return apply_filters('display_event_type', $types, $post);
}
/**
 * It displays the event category.
 *
 * @access public
 * @return void
 */
function display_event_category($post = null, $after = ''){
	if($event_category = get_event_category($post)) {
		if(!empty($event_category)) {
			$numCategory = count($event_category);
			$i = 0;
			foreach($event_category as $cat) {
				echo wp_kses(('<a href="' . get_term_link($cat->term_id) . '"><span class="wpem-event-category-text event-category ' . esc_attr(sanitize_title($cat->slug)) . ' ">' . $cat->name . '</span></a>'), array(
					'a' => array(
						'href' => array(),
						'title' => array()
					),
					'span' => array(
						'class'       => array()
					),

				));
				if($numCategory > ++$i) {
					echo esc_html($after);
				}
			}
		}
	}
}

/**
 * The event category is retrieved here.
 *
 * @access public
 * @param mixed $post (default: null)
 * @return void
 */
function get_event_category($post = null){
	$post = get_post($post);
	if($post->post_type !== 'event_listing' || !get_option('event_manager_enable_categories')) {
		return;
	}
	$categories = wp_get_post_terms($post->ID, 'event_sounds');

	// Return single if not enabled.
	if(empty($categories))
		$categories = '';
	return apply_filters('display_event_category', $categories, $post);
}


/**
 * Returns the registration fields used when an account is required.
 *
 * @since 2.2
 * @return array $registration_fields
 */
function wp_event_manager_get_registration_fields(){
	$generate_username_from_email      = event_manager_generate_username_from_email();
	$use_standard_password_setup_email = event_manager_use_standard_password_setup_email();
	$account_required  = event_manager_user_requires_account();

	$registration_fields = array();
	if(event_manager_enable_registration()) {

		$registration_fields['create_account_email'] = array(
			'type'        => 'text',
			'label'       => __('Your email', 'wp-event-manager'),
			'placeholder' => __('you@yourdomain.com', 'wp-event-manager'),
			'required'    => $account_required,
			'value'       => isset($_POST['create_account_email']) ? sanitize_email($_POST['create_account_email']) : '',
		);

		if(!$generate_username_from_email) {
			$registration_fields['create_account_username'] = array(
				'type'     => 'text',
				'label'    => __('Username', 'wp-event-manager'),
				'required' => $account_required,
				'value'    => isset($_POST['create_account_username']) ? sanitize_text_field(wp_unslash($_POST['create_account_username'])) : '',
			);
		}
		if(!$use_standard_password_setup_email) {
			$registration_fields['create_account_password'] = array(
				'type'         => 'password',
				'label'        => __('Password', 'wp-event-manager'),
				'placeholder' => __('Password', 'wp-event-manager'),
				'autocomplete' => false,
				'required'     => $account_required,
			);
			$password_hint = event_manager_get_password_rules_hint();
			if($password_hint) {
				$registration_fields['create_account_password']['description'] = $password_hint;
			}
			$registration_fields['create_account_password_verify'] = array(
				'type'         => 'password',
				'label'        => __('Verify Password', 'wp-event-manager'),
				'placeholder' => __('Confirm Password', 'wp-event-manager'),
				'autocomplete' => false,
				'required'     => $account_required,
			);
		}
	}
	return apply_filters('event_manager_get_registration_fields', $registration_fields);
}

/**
 * Displays the publish date for event.
 * @param mixed $post (default: null)
 * @return [type]
 */
function display_event_publish_date($post = null){
	$date_format = get_option('event_manager_date_format');
	if($date_format === 'default') {
		$display_date = __('Posted on ', 'wp-event-manager') . get_post_time(get_option('date_format'));
	} else {
		$display_date = sprintf(wp_kses('Posted %s ago', 'wp-event-manager'), human_time_diff(get_post_time('U'), current_time('timestamp')));
	}
	printf(
		'<time datetime="%s">%s</time>',
		esc_attr(get_post_time('Y-m-d')),  // Escape the date output from get_post_time
		esc_html($display_date)  // Escape the display date for safe HTML output
	);
}

/**
 * The event publish date is retrieved here.
 * @param mixed $post (default: null)
 * @return [type]
 */
function get_event_publish_date($post = null){
	$date_format = get_option('event_manager_date_format');
	if($date_format === 'default') {
		return get_post_time(get_option('date_format'));
	} else {
		return sprintf(wp_kses('Posted %s ago', 'wp-event-manager'), human_time_diff(get_post_time('U'), current_time('timestamp')));
	}
}

/**
 * Gets event location.
 *
 * @access public
 * @param mixed $post (default: null)
 * @return void
 */
function get_event_location($post = null){

	$post = get_post($post);
	if($post->post_type !== 'event_listing')
		return;
	if(!empty($post->_event_location))
		return apply_filters('display_event_location', $post->_event_location, $post);
	else 
		return apply_filters('display_event_location', '-', $post);
}

/**
 * Displays event location.
 * @param  boolean $map_link whether or not to link to the map on google maps
 * @return [type]
 */
function display_event_location($map_link = true, $post = null){

	$location = get_event_location($post);
	if(is_event_online($post)) {
		echo wp_kses_post(apply_filters('display_event_location_anywhere_text', __('Online Event', 'wp-event-manager')));
	} else {
		if($map_link === true || ($map_link && $map_link !== '-')) {
			$location_safe = is_string($location) ? $location : '';
			// OpenStreetMap link
			echo wp_kses_post(apply_filters('display_event_location_map_link', '<a href="https://www.openstreetmap.org/search?query=' . urlencode($location_safe) . '" target="_blank">' . $location_safe . '</a>', $location_safe, $post));
		} else {
			echo wp_kses_post($location);
		}
	}
}

/**
 * Here you can get the event ticket option.
 *
 * @access public
 * @param mixed $post (default: null)
 * @return string
 */
function get_event_ticket_option($post = null){

	$post = get_post($post);
	if($post->post_type !== 'event_listing')
		return;
	$ticket_option = '';
	if($post->_event_ticket_options == 'paid')
		$ticket_option = __('Paid', 'wp-event-manager');
	elseif($post->_event_ticket_options == 'free')
		$ticket_option = __('Free', 'wp-event-manager');
	elseif($post->_event_ticket_options == '')
		$ticket_option = '';

	return apply_filters('display_event_ticket_option', $ticket_option, $post);
}

/**
 * Display or retrieve the current event ticket price information with optional content.
 *
 * @access public
 * @param mixed $id (default: null
 * @return void
 */
function display_event_ticket_option($before = '', $after = '', $echo = true, $post = null){

	$event_ticket_option = get_event_ticket_option($post);
	if(wem_safe_strlen($event_ticket_option) == 0)
		return;

	$event_ticket_option = esc_attr(strip_tags($event_ticket_option));

	//find the option value from the field editor array
	$fields = get_option('event_manager_form_fields', true);
	if(is_array($fields) && count($fields) > 0) {
		$ticket_option_field = array_column($fields, 'event_ticket_options');
		foreach($ticket_option_field as $key => $value) {
			if(isset($value['options']) && isset($value['options'][$event_ticket_option])) {
				$event_ticket_option = $value['options'][$event_ticket_option];
			}
		}
	}

	$event_ticket_option = $before . $event_ticket_option . $after;
	if($echo)
		echo esc_attr($event_ticket_option);
	else
		return $event_ticket_option;
}

/**
 * Gets the registration end date of event.
 *
 * @access public
 * @param mixed $post (default: null)
 * @return string
 */
function get_event_registration_end_date($post = null){
	$post = get_post($post);
	if($post->post_type !== 'event_listing')
		return;

	return apply_filters('display_event_registration_end_date', $post->_event_registration_deadline, $post);
}

/**
 * Display or retrieve the current event registration end date.
 *
 * @access public
 * @param mixed $id (default: null)
 * @return void
 */
function display_event_registration_end_date($before = '', $after = '', $echo = true, $post = null){

	$event_registration_end_date = get_event_registration_end_date($post);
	if(wem_safe_strlen($event_registration_end_date) == 0)
		return;

	$date_format 		= WP_Event_Manager_Date_Time::get_event_manager_view_date_format();
	$event_registration_end_date 	= date_i18n($date_format, strtotime($event_registration_end_date));
	$event_registration_end_date = $before . $event_registration_end_date . $after;

	if($echo)
		echo esc_attr($event_registration_end_date);
	else
		return $event_registration_end_date;
}

/**
 * Gets the banner of event.
 *
 * @access public
 * @param mixed $post (default: null)
 * @return string
 */
function get_event_banner($post = null){

	$post = get_post($post);
	if($post->post_type !== 'event_listing')
		return;
	if(isset($post->_event_banner) && empty($post->_event_banner))
		$event_banner = get_event_thumbnail($post);
	else
		$event_banner = $post->_event_banner;
	return apply_filters('display_event_banner', $event_banner, $post);
}

/**
 * Gets the thumbnail of event.
 *
 * @access public
 * @param mixed $post (default: null)
 * @return string
 */
function get_event_thumbnail($post = null, $size = 'full') {
    $post = get_post($post);
    
    if($post->post_type !== 'event_listing') {
        return;
    }

    $use_custom_thumbnail = get_option('event_manager_use_custom_thumbnail');

    if ($use_custom_thumbnail) {
        $event_thumbnail = get_the_post_thumbnail_url($post, $size);
        
        // If no thumbnail is set, show the default placeholder
        if (empty($event_thumbnail)) {
            $event_thumbnail = apply_filters('event_manager_default_event_thumbnail', EVENT_MANAGER_PLUGIN_URL . '/assets/images/wpem-placeholder-wide.jpg');
        }
    } else {
        
        $event_thumbnail = get_the_post_thumbnail_url($post, $size);
        
        // If no thumbnail is set, check for the banner
        if (empty($event_thumbnail)) {
            if (!empty($post->_event_banner)) {
                // If event banner is set, use it
                $event_banner = $post->_event_banner;
                if (is_array($event_banner)) {
					$event_banner = array_filter($event_banner);
					$event_banner = array_values($event_banner);
                    $event_thumbnail = isset($event_banner[0]) ? $event_banner[0] : null;
                } else {
                    $event_thumbnail = $event_banner;
                }
            } else {
                // If no banner is set, show the default placeholder
                $event_thumbnail = apply_filters('event_manager_default_event_banner', EVENT_MANAGER_PLUGIN_URL . '/assets/images/wpem-placeholder-wide.jpg');
            }
        }
    }
	
    return apply_filters('display_event_thumbnail', $event_thumbnail, $post);
}

/**
 * Displays the banner of event.
 *
 * @access public
 * @param string $size (default: 'full')
 * @param mixed $default (default: null)
 * @return void
 */
function display_event_banner($size = 'full', $default = null, $post = null){

	$banner = get_event_banner($post);
	$alt_text = !empty(esc_attr(get_dj_name($post))) ? esc_attr(get_dj_name($post)) : get_the_title();
	$alt_text = apply_filters('display_event_alt_text', $alt_text, $post);
	if(!empty($banner) && !is_array($banner)  && (strstr($banner, 'http') || file_exists($banner))) {
		if($size !== 'full') {
			$banner = event_manager_get_resized_image($banner, $size);
		}
		echo('<link rel="image_src" href="' . esc_attr($banner) . '"/>');
		echo('<img itemprop="image" content="' . esc_attr($banner) . '" src="' . esc_attr($banner) . '" alt="' . esc_attr($alt_text) . '" />');
	} else if($default) {

		echo('<img itemprop="image" content="' . esc_attr($default) . '" src="' . esc_attr($default) . '" alt="' . esc_attr($alt_text) . '" />');
	} else if(is_array($banner)) {
		$banner = array_filter($banner);
		$banner = array_values($banner);
		if (!empty($banner) && isset($banner[0])) {
			echo('<img itemprop="image" content="' . esc_attr($banner[0]) . '" src="' . esc_attr($banner[0]) . '" alt="' . esc_attr($alt_text) . '" />');
		} else {
			// fallback to placeholder if array is empty
			$placeholder = apply_filters('event_manager_default_event_banner', EVENT_MANAGER_PLUGIN_URL . '/assets/images/wpem-placeholder.jpg');
			echo('<img itemprop="image" content="' . esc_attr($placeholder) . '" src="' . esc_attr($placeholder) . '" alt="' . esc_attr($alt_text) . '" />');
		}
	} else {
		echo('<img itemprop="image" content="' . esc_attr(apply_filters('event_manager_default_event_banner', EVENT_MANAGER_PLUGIN_URL . '/assets/images/wpem-placeholder.jpg')) . '" src="' . esc_attr(apply_filters('event_manager_default_event_banner', EVENT_MANAGER_PLUGIN_URL . '/assets/images/wpem-placeholder.jpg')) . '" alt="' . esc_attr($alt_text) . '" />');
	}
}

/**
 * Retrieves the start date of event.
 *
 * @access public
 * @param int $post (default: null)
 * @return string
 */
function get_event_start_date($post = null){
	$post = get_post($post);
	if($post->post_type !== 'event_listing') {
		return '';
	}
	$event_start_date 	= $post->_event_start_date;
	return apply_filters('display_event_start_date', $event_start_date, $post);
}

/**
 * Display or retrieve the current event start date.
 *
 * @access public
 * @param mixed $id (default: null)
 * @return void
 */
function display_event_start_date($before = '', $after = '', $echo = true, $post = null){
	$event_start_date = get_event_start_date($post);
	if(wem_safe_strlen($event_start_date) == 0)
		return;
	$date_format 		= WP_Event_Manager_Date_Time::get_event_manager_view_date_format();
	$event_start_date 	= date_i18n($date_format, strtotime($event_start_date));
	$event_start_date = $before . $event_start_date . $after;

	if($echo)
		echo esc_attr($event_start_date);
	else
		return $event_start_date;
}

/**
 * Retrieves the start time of event.
 *
 * @access public
 * @param int $post (default: null)
 * @return string
 */
function get_event_start_time($post = null){

	$post = get_post($post);
	if($post->post_type !== 'event_listing'  || empty($post->_event_start_time)) {
		return '';
	}

	$event_timezone 	= get_event_timezone_abbr($post);
	$time_format 		= WP_Event_Manager_Date_Time::get_timepicker_format();
	$event_start_time 	= date_i18n($time_format, strtotime($post->_event_start_time));

	if($event_timezone)
		$event_start_time = $event_start_time . ' (' . $event_timezone . ')';
	else
		$event_start_time = $event_start_time;

	return apply_filters('display_event_start_time', $event_start_time, $post);
}

/**
 * Display or retrieve the current event start time.
 *
 * @access public
 * @param mixed $id (default: null)
 * @return void
 */
function display_event_start_time($before = '', $after = '', $echo = true, $post = null){
	$event_start_time = get_event_start_time($post);
	if(wem_safe_strlen($event_start_time) == 0)
		return;

	$event_start_time = esc_attr(strip_tags($event_start_time));
	$event_start_time = $before . $event_start_time . $after;
	if($echo)
		echo esc_attr($event_start_time);
	else
		return $event_start_time;
}

/**
 * Retrieves the end date of event.
 *
 * @access public
 * @param int $post (default: null)
 * @return string
 */
function get_event_end_date($post = null){
	$post = get_post($post);

	if($post->post_type !== 'event_listing') {
		return '';
	}
	$event_end_date = $post->_event_end_date;
	return apply_filters('display_event_end_date', $event_end_date, $post);
}

/**
 * Display or retrieve the current event end date.
 *
 * @access public
 * @param mixed $id (default: null)
 * @return void
 */
function display_event_end_date($before = '', $after = '', $echo = true, $post = null){

	$event_end_date = get_event_end_date($post);
	if(wem_safe_strlen($event_end_date) == 0)
		return;

	$event_end_date = esc_attr(strip_tags($event_end_date));
	$date_format = WP_Event_Manager_Date_Time::get_event_manager_view_date_format();
	$event_end_date = date_i18n($date_format, strtotime($event_end_date));
	$event_end_date = $before . $event_end_date . $after;

	if($echo)
		echo esc_attr($event_end_date);
	else
		return $event_end_date;
}

/**
 * Retrieves the end time of event.
 *
 * @access public
 * @param int $post (default: null)
 * @return string
 */
function get_event_end_time($post = null){

	$post = get_post($post);
	if($post->post_type !== 'event_listing' || empty($post->_event_end_time)) {
		return '';
	}

	$event_timezone 	= get_event_timezone_abbr($post);
	$time_format 		= WP_Event_Manager_Date_Time::get_timepicker_format();
	$event_end_time 	= date_i18n($time_format, strtotime($post->_event_end_time));

	if($event_timezone)
		$event_end_time = $event_end_time . ' (' . $event_timezone . ')';
	else
		$event_end_time = $event_end_time;

	return apply_filters('display_event_end_time', $event_end_time, $post);
}

/**
 * Display or retrieve the current event end time.
 *
 * @access public
 * @param mixed $id (default: null)
 * @return void
 */
function display_event_end_time($before = '', $after = '', $echo = true, $post = null){

	$event_end_time = get_event_end_time($post);
	if(wem_safe_strlen($event_end_time) == 0)
		return;

	$event_end_time = esc_attr(strip_tags($event_end_time));
	$event_end_time = $before . $event_end_time . $after;

	if($echo)
		echo esc_attr($event_end_time);
	else
		return $event_end_time;
}

/**
 * Retrieves the user selected timezone of event.
 *
 * @access public
 * @since 3.0
 * @param int $post (default: null)
 * @return string
 */
function get_event_timezone($post = null, $abbr = true){	
	$post = get_post($post);

	if($post->post_type !== 'event_listing') {
		return '';
	}

	if(WP_Event_Manager_Date_Time::get_event_manager_timezone_setting() == 'site_timezone')
		return false;

	$timezone = $post->_event_timezone;

	if(empty($timezone)) {
		$timezone = wp_timezone_string();
	}

	return apply_filters('display_event_timezone', $timezone, $post);
}

/**
 * Display or retrieve the user selected timezone.
 *
 * @access public
 * @param mixed $id (default: null)
 * @return void
 */
function display_event_timezone($before = '', $after = '', $echo = true, $post = null){

	$event_timezone = get_event_timezone($post);
	if(wem_safe_strlen($event_timezone) == 0)
		return;

	$event_timezone = $before . $event_timezone . $after;

	if($echo)
		echo esc_attr($event_timezone);
	else
		return $event_timezone;
}

/**
 * Retrieves the user selected timezone in abbriviation of event.
 *
 * @since 3.0
 * @access public
 * @param int $post (default: null)
 * @return string
 */
function get_event_timezone_abbr($post = null){

	$event_timezone = get_event_timezone($post);
	if($event_timezone)
		$event_timezone = WP_Event_Manager_Date_Time::convert_event_timezone_into_abbr($event_timezone);

	return apply_filters('display_event_timezone_abbr', $event_timezone, $post);
}

/**
 * Display or retrieve the user selected timezone in abbriviation.
 *
 * @access public
 * @param mixed $id (default: null)
 * @return void
 */
function display_event_timezone_abbr($before = '', $after = '', $echo = true, $post = null){

	$event_timezone = get_event_timezone_abbr($post);
	if(wem_safe_strlen($event_timezone) == 0)
		return;

	$event_timezone = $before . $event_timezone . $after;

	if($echo)
		echo esc_attr($event_timezone);
	else
		return $event_timezone;
}

/**
 * Retrieves the event local name.
 *
 * @access public
 * @param mixed $post (default: null)
 * @return void
 */
function get_event_local_name($post = null, $link = false){

	$post = get_post($post);
	/* if($post->post_type !== 'event_listing') */
	if(empty($post) || !in_array($post->post_type, ['event_listing', 'event_local']))
		return;

	if(!empty($post->_event_local_ids)) {
		$local_name = '';
		if($link) {
			$local_name .= '<a href="' . get_permalink($post->_event_local_ids) . '">';
		}
		$local_name .= esc_attr(get_post_meta($post->_event_local_ids, '_local_name', true));

		if($link) {
			$local_name .= '</a>';
		}

		return apply_filters('display_event_local_name', $local_name, $post);
	}

	if($post->post_type == 'event_local')
		return apply_filters('display_event_local_name', $post->_local_name, $post);
	else
		return apply_filters('display_event_local_name', $post->_event_local_name, $post);
}

/**
 * Display or retrieve the current event local name.
 *
 * @access public
 *
 * @param mixed $id (default: null)
 * @return void
 */
function display_event_local_name($before = '', $after = '', $echo = true, $post = null){

	$event_local_name = get_event_local_name($post);
	if(!is_string($event_local_name)) $event_local_name = '';
	if(wem_safe_strlen($event_local_name) == 0)
		return '';
	$event_local_name = esc_attr(strip_tags((string)$event_local_name));
	$event_local_name = $before . $event_local_name . $after;
	if($echo)
		echo esc_attr($event_local_name);
	else
		return $event_local_name;
}

/**
 * Online status of event.
 *
 * @access public
 * @param mixed $post (default: null)
 * @return void
 */
function is_event_online($post = null){

	$post = get_post($post);
	if($post->post_type !== 'event_listing')
		return;

	if(get_event_location($post) == 'Online Event' || $post->_event_online == 'yes')
		return true;
	else
		return false;
}

/**
 * Gets the address of event.
 *
 * @access public
 * @param mixed $post (default: null)
 * @return void
 */
function get_event_address($post = null){

	$post = get_post($post);
	if($post->post_type !== 'event_listing')
		return;

	return apply_filters('display_event_address', $post->_event_address, $post);
}

/**
 * Display or retrieve the current event address.
 *
 * @access public
 *
 * @param mixed $id (default: null)
 * @return void
 */
function display_event_address($before = '', $after = '', $echo = true, $post = null){

	$event_address = get_event_address($post);
	if(!is_string($event_address)) $event_address = '';
	if(wem_safe_strlen($event_address) == 0)
		return '';
	$event_address = esc_attr(strip_tags((string)$event_address));
	$event_address = $before . $event_address . $after;
	if($echo)
		echo esc_attr($event_address);
	else
		return $event_address;
}

/**
 * Gets the pincode of event.
 *
 * @access public
 * @param mixed $post (default: null)
 * @return void
 */
function get_event_pincode($post = null){

	$post = get_post($post);
	if($post->post_type !== 'event_listing')
		return;

	return apply_filters('display_event_pincode', $post->_event_pincode, $post);
}

/**
 * Display or retrieve the current event pincode.
 *
 * @access public
 * @param mixed $id (default: null)
 * @return void
 */
function display_event_pincode($before = '', $after = '', $echo = true, $post = null){

	$event_pincode = get_event_pincode($post);
	if(!is_string($event_pincode)) $event_pincode = '';
	if(wem_safe_strlen($event_pincode) == 0)
		return '';
	$event_pincode = esc_attr(strip_tags((string)$event_pincode));
	$event_pincode = $before . $event_pincode . $after;
	if($echo)
		echo esc_attr($event_pincode);
	else
		return $event_pincode;
}

/**
 * Gets the dj name.
 *
 * @access public
 * @param int $post (default: null)
 * @return string
 */
function get_dj_name($post = null, $link = false, $link_type = 'frontend'){

	$post = get_post($post);

	if(empty($post) || !in_array($post->post_type, ['event_listing', 'event_dj'])) {
		return '';
	}

	if(!empty($post->_event_dj_ids)) {
		$dj_name = '';

		foreach($post->_event_dj_ids as $key => $dj_id) {
			if($key > 0) {
				$dj_name .= ', ';
			}
			if($link) {
				if($link_type == 'backend') {
					$dj_name .= '<a href="' . get_edit_post_link($dj_id) . '">';
				} else {
					$dj_name .= '<a href="' . get_permalink($dj_id) . '">';
				}
			}
			$dj_name .= esc_attr(get_post_meta($dj_id, '_dj_name', true));
			if($link) {
				$dj_name .= '</a>';
			}
		}
		return apply_filters('display_dj_name', $dj_name, $post);
	}
	return apply_filters('display_dj_name', $post->_dj_name, $post);
}

/**
 * Display or retrieve the current organization or company name who oraganizing events with optional content.
 *
 * @access public
 * @param mixed $id (default: null)
 * @return void
 */
function display_dj_name($before = '', $after = '', $echo = true, $post = null){
	$dj_name = get_dj_name($post);

	if(wem_safe_strlen($dj_name) == 0)
		return;

	$dj_name = esc_attr(strip_tags($dj_name));
	$dj_name = $before . $dj_name . $after;
	if($echo)
		echo esc_attr($dj_name);
	else
		return $dj_name;
}

/**
 * Gets the dj description.
 *
 * @access public
 * @param int $post (default: null)
 * @return string
 */
function get_dj_description($post = null){

	$post = get_post($post);
	if(empty($post) || !in_array($post->post_type, ['event_listing', 'event_dj'])) {
		return '';
	}

	return apply_filters('display_dj_description', $post->_dj_description, $post);
}

/**
 * It displays the dj logo.
 *
 * @access public
 * @param string $size (default: 'full')
 * @param mixed $default (default: null)
 * @return void
 */
function display_dj_logo($size = 'full', $default = null, $post = null){ 

	$logo = get_dj_logo($post, $size);

	if(has_post_thumbnail($post)) {
		echo '<img class="dj_logo" src="' . esc_url($logo) . '" alt="' . esc_attr(get_dj_name($post)) . '" />';
		// Before 1.0., logo URLs were stored in post meta.
	} elseif(!empty($logo) && !is_array($logo) && (strstr($logo, 'http') || file_exists($logo))) {
		if($size !== 'full') {
			$logo = event_manager_get_resized_image($logo, $size);
		}
		echo '<img src="' . esc_url($logo) . '" alt="' . esc_attr(get_dj_name($post)) . '" />';
	} elseif($default) {
		echo '<img src="' . esc_url($default) . '" alt="' . esc_attr(get_dj_name($post)) . '" />';
	} else if(is_array($logo) && isset($logo[0])) {
		echo '<img itemprop="image" content="' . esc_url($logo[0]) . '" src="' . esc_url($logo[0]) . '" alt="' . esc_attr(get_dj_name($post)) . '" />';
	} else {
		echo '<img src="' . esc_url(apply_filters('event_manager_default_dj_logo', EVENT_MANAGER_PLUGIN_URL . '/assets/images/wpem-placeholder.jpg')) . '" alt="' . esc_attr(get_dj_name($post)) . '" />';
	}
}

/**
 * Gets the dj logo.
 *
 * @access public
 * @param mixed $post (default: null)
 * @return string
 */
function get_dj_logo($post = null, $size = 'full'){

	$post = get_post($post);
	if(empty($post) || !in_array($post->post_type, ['event_listing', 'event_dj']))
		return;

	if(has_post_thumbnail($post->ID)) {
		$src = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID), $size);
		if(!isset($src) || empty($src)){
			return $src ? $src[0] : '';
		}else{
			return $src ? $src[0] : '';
		}
	} elseif(!empty($post->_dj_logo)) {
		return $post->_dj_logo;
		// Before were stored in post meta.
		return apply_filters('display_dj_logo', $post->_dj_logo, $post);
	}

	return '';
}

/**
 * Retrieves the local description.
 *
 * @access public
 * @param int $post (default: null)
 * @return string
 */
function get_local_description($post = null){

	$post = get_post($post);
	if(empty($post) || !in_array($post->post_type, ['event_listing', 'event_local'])) {
		return '';
	}

	return apply_filters('display_local_description', $post->_local_description, $post);
}

/**
 * It displays local logo.
 *
 * @access public
 * @param string $size (default: 'full')
 * @param mixed $default (default: null)
 * @return void
 */
function display_local_logo($size = 'full', $default = null, $post = null){

	$logo = get_local_logo($post, $size);

	if(has_post_thumbnail($post)) {
		printf('<img class="local_logo" src="' . esc_url($logo) . '" alt="' . esc_attr(get_event_local_name($post)) . '" />');
		// Before 1.0., logo URLs were stored in post meta.
	} elseif(!empty($logo) && !is_array($logo) && (strstr($logo, 'http') || file_exists($logo))) {
		if($size !== 'full') {
			$logo = event_manager_get_resized_image($logo, $size);
		}
		printf('<img src="' . esc_url($logo) . '" alt="' . esc_attr(get_event_local_name($post)) . '" />');
	} elseif($default) {
		printf('<img src="' . esc_url($default) . '" alt="' . esc_attr(get_event_local_name($post)) . '" />');
	} else if(is_array($logo) && isset($logo[0])) {
		printf('<img itemprop="image" content="' . esc_url($logo[0]) . '" src="' . esc_url($logo[0]) . '" alt="' . esc_attr(get_dj_name($post)) . '" />');
	} else {
		printf('<img src="' . esc_url(apply_filters('event_manager_default_local_logo', EVENT_MANAGER_PLUGIN_URL . '/assets/images/wpem-placeholder.jpg')) . '" alt="' . esc_attr(get_event_local_name($post)) . '" />');
	}
}

/**
 * Gets local logo.
 *
 * @access public
 * @param mixed $post (default: null)
 * @return string
 */
function get_local_logo($post = null, $size = 'full'){

	$post = get_post($post);
	if(empty($post) || !in_array($post->post_type, ['event_listing', 'event_local']))
		return;

	if(has_post_thumbnail($post->ID)) {
		$src = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID), $size);
		return $src ? $src[0] : '';
	} elseif(!empty($post->_local_logo)) {
		// Before were stored in post meta.
		return apply_filters('display_local_logo', $post->_local_logo, $post);
	}

	return '';
}

/**
 * Resize and get url of the image.
 *
 * @param  string $logo
 * @param  string $size
 * @return string
 */
function event_manager_get_resized_image($logo, $size){

	global $_wp_additional_image_sizes;
	if($size !== 'full' && strstr($logo, WP_CONTENT_URL) && (isset($_wp_additional_image_sizes[$size]) || in_array($size, array('thumbnail', 'medium', 'large')))) {
		if(in_array($size, array('thumbnail', 'medium', 'large'))) {
			$img_width  = get_option($size . '_size_w');
			$img_height = get_option($size . '_size_h');
			$img_crop   = get_option($size . '_size_crop');
		} else {
			$img_width  = $_wp_additional_image_sizes[$size]['width'];
			$img_height = $_wp_additional_image_sizes[$size]['height'];
			$img_crop   = $_wp_additional_image_sizes[$size]['crop'];
		}
		$upload_dir        = wp_upload_dir();
		$logo_path         = str_replace(array($upload_dir['baseurl'], $upload_dir['url'], WP_CONTENT_URL), array($upload_dir['basedir'], $upload_dir['path'], WP_CONTENT_DIR), $logo);
		$path_parts        = pathinfo($logo_path);
		$dims              = $img_width . 'x' . $img_height;
		$resized_logo_path = str_replace('.' . $path_parts['extension'], '-' . $dims . '.' . $path_parts['extension'], $logo_path);

		if(strstr($resized_logo_path, 'http:') || strstr($resized_logo_path, 'https:')) {
			return $logo;
		}

		if(!file_exists($resized_logo_path)) {

			ob_start();

			$image = wp_get_image_editor($logo_path);
			if(!is_wp_error($image)) {
				$resize = $image->resize($img_width, $img_height, $img_crop);
				if(!is_wp_error($resize)) {
					$save = $image->save($resized_logo_path);
					if(!is_wp_error($save)) {
						$logo = dirname($logo) . '/' . basename($resized_logo_path);
					}
				}
			}
			ob_get_clean();
		} else {
			$logo = dirname($logo) . '/' . basename($resized_logo_path);
		}
	}
	return $logo;
}

/**
 * Retrieves the current organization's contact person name with optional content.
 *
 * @access public
 * @param mixed $post (default: null)
 * @return string
 */
function get_event_dj_contact_person_name($post = null){

	$post = get_post($post);
	if($post->post_type !== 'event_listing')
		return;

	return apply_filters('display_dj_contact_person_name', $post->_dj_contact_person_name, $post);
}

/**
 * Display or retrieve the current organization's contact person name with optional content.
 *
 * @access public
 * @param mixed $id (default: null)
 * @return void
 */
function display_dj_contact_person_name($before = '', $after = '', $echo = true, $post = null){

	$dj_contact_person_name = get_event_dj_contact_person_name($post);
	if(wem_safe_strlen($dj_contact_person_name) == 0)
		return;

	$dj_contact_person_name = esc_attr(strip_tags($dj_contact_person_name));
	$dj_contact_person_name = $before . $dj_contact_person_name . $after;
	if($echo)
		echo esc_attr($dj_contact_person_name);
	else
		return $dj_contact_person_name;
}

/**
 * Retrieves the current dj email with optional content of event.
 *
 * @access public
 * @param mixed $post (default: null)
 * @return string
 */
function get_event_dj_email($post = null){

	$post = get_post($post);

	if(empty($post) || !in_array($post->post_type, ['event_listing', 'event_dj']))
		return;

	if(!empty($post->_event_dj_ids)) {
		$djs_email = '';
		foreach($post->_event_dj_ids as $key => $dj_id) {
			if($key > 0) {
				$djs_email .= ', ';
			}
			$djs_email .= esc_html(get_post_meta($dj_id, '_dj_email', true));
		}
		return apply_filters('display_dj_email', $djs_email, $post);
	}
	return apply_filters('display_dj_email', $post->_dj_email, $post);
}

/**
 * Display or retrieve the current dj email with optional content.
 *
 * @access public
 * @param mixed $id (default: null)
 * @return void
 */
function display_dj_email($before = '', $after = '', $echo = true, $post = null){

	$dj_email = get_event_dj_email($post);
	if(wem_safe_strlen($dj_email) == 0)
		return;

	$dj_email = esc_attr(strip_tags($dj_email));
	$dj_email = $before . $dj_email . $after;
	if($echo)
		echo esc_attr($dj_email);
	else
		return $dj_email;
}

/**
 * Get the dj video URL.
 *
 * @param mixed $post (default: null)
 * @return string
 */
function get_dj_video($post = null){
	$post = get_post($post);
	if(empty($post) || !in_array($post->post_type, ['event_listing', 'event_dj']))
		return;

	return apply_filters('display_dj_video', $post->_dj_video, $post);
}

/**
 * Output the dj video.
 */
function display_dj_video($before = '', $after = '', $echo = true, $post = null){

	$video_embed = false;
	$video       = get_dj_video($post);
	$filetype    = wp_check_filetype($video);
	if(!empty($video)) {
		// FV Wordpress Flowplayer Support for advanced video formats
		if(shortcode_exists('flowplayer')) {
			$video_embed = '[flowplayer src="' . esc_attr($video) . '"]';
		} elseif(!empty($filetype['ext'])) {
			$video_embed = wp_video_shortcode(array('src' => $video));
		} else {
			$video_embed = wp_oembed_get($video);
		}
	}
	$video_embed = apply_filters('display_dj_video_embed', $video_embed, $post);
	if($video_embed) {
		printf('<div class="dj_video">%s</div>',esc_attr($video_embed));
	}
}

/**
 * Retrieves the current dj website.
 *
 * @access public
 * @param int $post (default: null)
 * @return void
 */
function get_dj_website($post = null){

	$post = get_post($post);
	if(empty($post) || !in_array($post->post_type, ['event_listing', 'event_dj']))
		return;

	if(!empty($post->_event_dj_ids)) {
		$website = '';
		foreach($post->_event_dj_ids as $key => $dj_id) {
			$website .= esc_url(get_post_meta($dj_id, '_dj_website', true));
			if($website && !strstr($website, 'http:') && !strstr($website, 'https:')) {
				$website .= 'http://' . $website;
			}
		}
		return apply_filters('display_dj_website', $website, $post);
	}

	$website = $post->_dj_website;
	if($website && !strstr($website, 'http:') && !strstr($website, 'https:')) {
		$website = 'http://' . $website;
	}
	return apply_filters('display_dj_website', $website, $post);
}

/**
 * Display or retrieve the current dj website with optional content.
 *
 * @access public
 * @param mixed $id (default: null)
 * @return void
 */
function display_dj_website($before = '', $after = '', $echo = true, $post = null){
	$dj_website = get_dj_website($post);

	if(!is_string($dj_website)) $dj_website = '';
	if(wem_safe_strlen($dj_website) == 0)
		return;

	$dj_website = esc_attr(strip_tags((string)$dj_website));
	$dj_website = $before . $dj_website . $after;
	if($echo)
		echo esc_attr($dj_website);
	else
		return $dj_website;
}

/**
 * Retrieves the current local website.
 *
 * @access public
 * @param int $post (default: null)
 * @return void
 */
function get_local_website($post = null){

	$post = get_post($post);
	if(empty($post) || !in_array($post->post_type, ['event_listing', 'event_local']))
		return;

	if(!empty($post->_event_local_ids)) {
		$website = '';
		$website .= esc_url(get_post_meta($post->_event_local_ids, '_local_website', true));

		if($website && !strstr($website, 'http:') && !strstr($website, 'https:')) {
			$website .= 'http://' . $website;
		}
		return apply_filters('display_local_website', $website, $post);
	}

	$website = $post->_local_website;
	if($website && !strstr($website, 'http:') && !strstr($website, 'https:')) {
		$website = 'http://' . $website;
	}
	return apply_filters('display_local_website', $website, $post);
}

/**
 * Display or retrieve the current local website with optional content.
 *
 * @access public
 * @param mixed $id (default: null)
 * @return void
 */
function display_local_website($before = '', $after = '', $echo = true, $post = null){

	$local_website = get_local_website($post);
	if(!is_string($local_website)) $local_website = '';
	if(wem_safe_strlen($local_website) == 0)
		return;

	$local_website = esc_attr(strip_tags((string)$local_website));
	$local_website = $before . $local_website . $after;
	if($echo)
		echo esc_attr($local_website);
	else
		return $local_website;
}

/**
 * Display or retrieve the current dj tagline with optional content.
 *
 * @access public
 * @param mixed $id (default: null)
 * @return void
 */
function display_dj_tagline($before = '', $after = '', $echo = true, $post = null){

	$dj_tagline = get_dj_tagline($post);

	if(!is_string($dj_tagline)) $dj_tagline = '';
	if(wem_safe_strlen($dj_tagline) == 0)
		return;

	$dj_tagline = esc_attr(strip_tags((string)$dj_tagline));
	$dj_tagline = $before . $dj_tagline . $after;
	if($echo)
		echo esc_attr($dj_tagline);
	else
		return $dj_tagline;
}

/**
 * Gets dj tagline.
 *
 * @access public
 * @param int $post (default: 0)
 * @return void
 */
function get_dj_tagline($post = null){
	$post = get_post($post);
	if(empty($post) || !in_array($post->post_type, ['event_listing', 'event_dj']))
		return;
	return apply_filters('display_dj_tagline', $post->_dj_tagline, $post);
}

/**
 * Retrieves the current dj twitter link.
 *
 * @access public
 * @param int $post (default: 0)
 * @return void
 */
function get_dj_twitter($post = null){
	$post = get_post($post);
	if(empty($post) || !in_array($post->post_type, ['event_listing', 'event_dj']))
		return;

	$dj_twitter = $post->_dj_twitter;

	if(!is_string($dj_twitter)) $dj_twitter = '';
	if(strlen($dj_twitter ?? '') == 0)
		return;

	if(strpos($dj_twitter, '@') === 0)
		$dj_twitter = substr($dj_twitter, 1);

	return apply_filters('display_dj_twitter', $dj_twitter, $post);
}

/**
 * Display or retrieve the current dj twitter link with optional content.
 *
 * @param mixed $id (default: null)
 * @return void
 */
function display_dj_twitter($before = '', $after = '', $echo = true, $post = null) {

	$dj_twitter = get_dj_twitter($post);

	if(strlen($dj_twitter ?? '') == 0)
		return;

	$dj_twitter = esc_attr(strip_tags((string)$dj_twitter));
	$dj_twitter = $before . '<a href="http://twitter.com/' . $dj_twitter . '" class="dj_twitter" target="_blank">' . $dj_twitter . '</a>' . $after;
	if($echo)
		echo esc_attr($dj_twitter);
	else
		return $dj_twitter;
}

/**
 * Retrieves the current local twitter link.
 *
 * @access public
 * @param int $post (default: 0)
 * @return void
 */
function get_local_twitter($post = null){

	$post = get_post($post);

	if(empty($post) || !in_array($post->post_type, ['event_listing', 'event_local']))
		return;

	$local_twitter = $post->_local_twitter;
	if(!is_string($local_twitter)) $local_twitter = '';
	if(strlen($local_twitter ?? '') == 0)
		return;

	if(strpos($local_twitter, '@') === 0)
		$local_twitter = substr($local_twitter, 1);

	return apply_filters('display_local_twitter', $local_twitter, $post);
}

/**
 * Display or retrieve the current local twitter link with optional content.
 *
 * @access public
 * @param mixed $id (default: null)
 * @return void
 */
function display_local_twitter($before = '', $after = '', $echo = true, $post = null){

	$local_twitter = get_local_twitter($post);

	if(wem_safe_strlen($local_twitter) == 0)
		return;

	$local_twitter = esc_attr(strip_tags((string)$local_twitter));
	$local_twitter = $before . '<a href="http://twitter.com/' . $local_twitter . '" class="local_twitter" target="_blank">' . $local_twitter . '</a>' . $after;
	if($echo)
		echo esc_attr($local_twitter);
	else
		return $local_twitter;
}

/**
 * retrieve the current dj page on facebook.
 *
 * @access public
 * @param int $post (default: 0)
 * @return void
 */
function get_dj_facebook($post = null){

	$post = get_post($post);
	if(empty($post) || !in_array($post->post_type, ['event_listing', 'event_dj']))
		return;

	$dj_facebook = $post->_dj_facebook;
	if(!is_string($dj_facebook)) $dj_facebook = '';
	if(strlen($dj_facebook ?? '') == 0)
		return;

	return apply_filters('display_dj_facebook', $dj_facebook, $post);
}

/**
 * Display or retrieve the current dj page on facebook.
 *
 * @access public
 * @param mixed $id (default: null)
 * @return void
 */
function display_dj_facebook($before = '', $after = '', $echo = true, $post = null){

	$dj_facebook = get_dj_facebook($post);
	if(wem_safe_strlen($dj_facebook) == 0)
		return;

	$dj_facebook = esc_attr(strip_tags((string)$dj_facebook));
	$dj_facebook = $before . $dj_facebook . $after;
	if($echo)
		echo esc_attr($dj_facebook);
	else
		return $dj_facebook;
}

/**
 * Retrieves the current local page on facebook.
 *
 * @access public
 * @param int $post (default: 0)
 * @return void
 */
function get_local_facebook($post = null){

	$post = get_post($post);
	if(empty($post) || !in_array($post->post_type, ['event_listing', 'event_local']))
		return;

	$local_facebook = $post->_local_facebook;
	if(!is_string($local_facebook)) $local_facebook = '';
	if(strlen($local_facebook ?? '') == 0)
		return;

	return apply_filters('display_local_facebook', $local_facebook, $post);
}

/**
 * Display or retrieve the current local page on facebook.
 *
 * @access public
 * @param mixed $id (default: null)
 * @return void
 */
function display_local_facebook($before = '', $after = '', $echo = true, $post = null){

	$local_facebook = get_local_facebook($post);
	if(wem_safe_strlen($local_facebook) == 0)
		return;

	$local_facebook = esc_attr(strip_tags((string)$local_facebook));
	$local_facebook = $before . $local_facebook . $after;
	if($echo)
		echo esc_attr($local_facebook);
	else
		return $local_facebook;
}

/**
 * Retrieves the current dj page on Linkedin.
 *
 * @access public
 * @param int $post (default: 0)
 * @return void
 */
function get_dj_linkedin($post = null){

	$post = get_post($post);
	if(empty($post) || !in_array($post->post_type, ['event_listing', 'event_dj']))
		return;

	$dj_linkedin = $post->_dj_linkedin;
	if(!is_string($dj_linkedin)) $dj_linkedin = '';
	if(strlen($dj_linkedin ?? '') == 0)
		return;

	return apply_filters('display_dj_linkedin', $dj_linkedin, $post);
}

/**
 * Display or retrieve the current dj page on Linkedin.
 *
 * @access public
 * @param mixed $id (default: null)
 * @return void
 */
function display_dj_linkedin($before = '', $after = '', $echo = true, $post = null){

	$dj_linkedin = get_dj_linkedin($post);
	if(wem_safe_strlen($dj_linkedin) == 0)
		return;

	$dj_linkedin = esc_attr(strip_tags((string)$dj_linkedin));
	$dj_linkedin = $before . $dj_linkedin . $after;
	if($echo)
		echo esc_attr($dj_linkedin);
	else
		return $dj_linkedin;
}

/**
 * Retrieves the current dj link on xing.
 *
 * @access public
 * @param int $post (default: 0)
 * @return void
 */
function get_dj_xing($post = null){

	$post = get_post($post);
	if(empty($post) || !in_array($post->post_type, ['event_listing', 'event_dj']))
		return;

	$dj_xing = $post->_dj_xing;
	if(!is_string($dj_xing)) $dj_xing = '';
	if(strlen($dj_xing ?? '') == 0)
		return;

	return apply_filters('display_dj_xing', $dj_xing, $post);
}

/**
 * Display or retrieve the current dj link on xing.
 *
 * @access public
 * @param mixed $id (default: null)
 * @return void
 */
function display_dj_xing($before = '', $after = '', $echo = true, $post = null){

	$dj_xing = get_dj_xing($post);
	if(wem_safe_strlen($dj_xing) == 0)
		return;

	$dj_xing = esc_attr(strip_tags((string)$dj_xing));
	$dj_xing = $before . $dj_xing . $after;
	if($echo)
		echo esc_attr($dj_xing);
	else
		return $dj_xing;
}

/**
 * Retrieves the current dj link on instagram.
 *
 * @access public
 * @param int $post (default: 0)
 * @return void
 */
function get_dj_instagram($post = null){

	$post = get_post($post);
	if(empty($post) || !in_array($post->post_type, ['event_listing', 'event_dj']))
		return;

	$dj_instagram = $post->_dj_instagram;
	if(!is_string($dj_instagram)) $dj_instagram = '';
	if(strlen($dj_instagram ?? '') == 0)
		return;

	return apply_filters('display_dj_instagram', $dj_instagram, $post);
}

/**
 * Display or retrieve the current dj link on instagram.
 *
 * @access public
 * @param mixed $id (default: null)
 * @return void
 */
function display_dj_instagram($before = '', $after = '', $echo = true, $post = null){

	$dj_instagram = get_dj_instagram($post);
	if(wem_safe_strlen($dj_instagram) == 0)
		return;

	$dj_instagram = esc_attr(strip_tags((string)$dj_instagram));
	$dj_instagram = $before . $dj_instagram . $after;
	if($echo)
		echo esc_attr($dj_instagram);
	else
		return $dj_instagram;
}

/**
 * Retrieves the current local link on instagram.
 *
 * @access public
 * @param int $post (default: 0)
 * @return void
 */
function get_local_instagram($post = null){

	$post = get_post($post);
	if(empty($post) || !in_array($post->post_type, ['event_listing', 'event_local']))
		return;

	$local_instagram = $post->_local_instagram;
	if(!is_string($local_instagram)) $local_instagram = '';
	if(strlen($local_instagram ?? '') == 0)
		return;

	return apply_filters('display_local_instagram', $local_instagram, $post);
}

/**
 * Display or retrieve the current local link on instagram.
 *
 * @access public
 * @param mixed $id (default: null)
 * @return void
 */
function display_local_instagram($before = '', $after = '', $echo = true, $post = null){

	$local_instagram = get_local_instagram($post);
	if(wem_safe_strlen($local_instagram) == 0)
		return;

	$local_instagram = esc_attr(strip_tags((string)$local_instagram));
	$local_instagram = $before . $local_instagram . $after;
	if($echo)
		echo esc_attr($local_instagram);
	else
		return $local_instagram;
}

/**
 * Retrieves the current dj link on pinterest.
 *
 * @access public
 * @param int $post (default: 0)
 * @return void
 */
function get_dj_pinterest($post = null){

	$post = get_post($post);
	if(empty($post) || !in_array($post->post_type, ['event_listing', 'event_dj']))
		return;

	$dj_pinterest = $post->_dj_pinterest;
	if(!is_string($dj_pinterest)) $dj_pinterest = '';
	if(strlen($dj_pinterest ?? '') == 0)
		return;

	return apply_filters('display_dj_pinterest', $dj_pinterest, $post);
}

/**
 * Display or retrieve the current dj link on pinterest.
 *
 * @access public
 * @param mixed $id (default: null)
 * @return void
 */
function display_dj_pinterest($before = '', $after = '', $echo = true, $post = null){

	$dj_pinterest = get_dj_pinterest($post);
	if(wem_safe_strlen($dj_pinterest) == 0)
		return;

	$dj_pinterest = esc_attr(strip_tags((string)$dj_pinterest));
	$dj_pinterest = $before . $dj_pinterest . $after;
	if($echo)
		echo esc_attr($dj_pinterest);
	else
		return $dj_pinterest;
}

/**
 * Retrieves the current dj link on youtube.
 *
 * @access public
 * @param int $post (default: 0)
 * @return void
 */
function get_dj_youtube($post = null){

	$post = get_post($post);
	if(empty($post) || !in_array($post->post_type, ['event_listing', 'event_dj']))
		return;

	$dj_youtube = $post->_dj_youtube;
	if(in_array($post->post_type, ['event_listing'])) {
		if($dj_youtube == '')
			$dj_youtube = $post->_event_video_url;
	}
	if(!is_string($dj_youtube)) $dj_youtube = '';
	if(strlen($dj_youtube ?? '') == 0)
		return;

	return apply_filters('display_dj_youtube', $dj_youtube, $post);
}

/**
 * Display or retrieve the current dj link on youtube.
 *
 * @access public
 * @param mixed $id (default: null)
 * @return void
 */
function display_dj_youtube($before = '', $after = '', $echo = true, $post = null){

	$dj_youtube = get_dj_youtube($post);
	if(wem_safe_strlen($dj_youtube) == 0)
		return;

	$dj_youtube = esc_attr(strip_tags((string)$dj_youtube));
	$dj_youtube = $before . $dj_youtube . $after;
	if($echo)
		echo esc_attr($dj_youtube);
	else
		return $dj_youtube;
}

/**
 * Retrieves the current local link on youtube.
 *
 * @access public
 * @param int $post (default: 0)
 * @return void
 */

function get_local_youtube($post = null){

	$post = get_post($post);
	if(empty($post) || !in_array($post->post_type, ['event_listing', 'event_local']))
		return;

	$local_youtube = $post->_local_youtube;
	if(!is_string($local_youtube)) $local_youtube = '';
	if(strlen($local_youtube ?? '') == 0)
		return;

	return apply_filters('display_local_youtube', $local_youtube, $post);
}

/**
 * Display or retrieve the current local link on youtube.
 *
 * @access public
 * @param mixed $id (default: null)
 * @return void
 */
function display_local_youtube($before = '', $after = '', $echo = true, $post = null){
	$local_youtube = get_local_youtube($post);
	if(wem_safe_strlen($local_youtube) == 0)
		return;

	$local_youtube = esc_attr(strip_tags((string)$local_youtube));
	$local_youtube = $before . $local_youtube . $after;
	if($echo)
		echo esc_attr($local_youtube);
	else
		return $local_youtube;
}

/**
 * Retrieves the current dj link on google plus.
 *
 * @access public
 * @param int $post (default: 0)
 * @return void
 */
function get_dj_google_plus($post = null){

	$post = get_post($post);
	if(empty($post) || !in_array($post->post_type, ['event_listing', 'event_dj']))
		return;

	$dj_google_plus = $post->_dj_google_plus;
	if(!is_string($dj_google_plus)) $dj_google_plus = '';
	if(strlen($dj_google_plus ?? '') == 0)
		return;

	return apply_filters('display_dj_google_plus', $dj_google_plus, $post);
}

/**
 * Display or retrieve the current dj link on google plus.
 *
 * @access public
 * @param mixed $id (default: null)
 * @return void
 */
function display_dj_google_plus($before = '', $after = '', $echo = true, $post = null){

	$dj_google_plus = get_dj_google_plus($post);
	if(wem_safe_strlen($dj_google_plus) == 0)
		return;

	$dj_google_plus = esc_attr(strip_tags((string)$dj_google_plus));
	$dj_google_plus = $before . $dj_google_plus . $after;
	if($echo)
		echo esc_attr($dj_google_plus);
	else
		return $dj_google_plus;
}

/**
 * Here class of event listing.
 *
 * @access public
 * @param string $class (default: '')
 * @param mixed $post_id (default: null)
 * @return void
 */
function event_listing_class($class = '', $post_id = null){
    $classes = get_event_listing_class($class, $post_id);
    echo 'class="' . esc_attr(join(' ', $classes)) . '"';
}

/**
 * Gets the class of event listing.
 *
 * @access public
 * @return array
 */
function get_event_listing_class($class = '', $post_id = null){

	$post = get_post($post_id);
	if($post->post_type !== 'event_listing') {
		return array();
	}

	$classes = array();
	if(empty($post)) {
		return $classes;
	}

	$classes[] = 'event_listing';
	if($event_type = get_event_type()) {
		if($event_type && !empty($event_type)) {
			foreach($event_type as $type) {
				$classes[] = 'event-type-' . sanitize_title($type->name);
			}
		}
	}

	if(is_event_cancelled($post)) {
		$classes[] = 'event_cancelled';
	}

	if(is_event_featured($post)) {
		$classes[] = 'event_featured';
	}

	if(!empty($class)) {
		if(!is_array($class)) {
			$class = preg_split('#\s+#', $class);
		}
		$classes = array_merge($classes, $class);
	}
	return get_post_class($classes, $post->ID);
}

/** 
 * This function is use to get the counts the event views and attendee views.
 * This function also used at event, attendee dashboard file.
 * @return number counted view.
 * @param $post
 **/
function get_post_views_count($post){
	$count_key = '_view_count';
	$count = esc_attr(get_post_meta($post->ID, $count_key, true));

	if($count == '' || $count == null) {
		delete_post_meta($post->ID, $count_key);
		add_post_meta($post->ID, $count_key, '0');
		return "-";
	}
	return $count;
}

/**
 * Count event view on the single event page.
 */
function get_single_listing_view_count($post){
	get_post_views_count($post);
}

/**
 * Returns the registration fields used when an account is required.
 *
 * @since 1.8
 *
 * @return array $registration_fields
 */
function event_manager_get_registration_fields(){
	$generate_username_from_email      = event_manager_generate_username_from_email();
	$use_standard_password_setup_email = event_manager_use_standard_password_setup_email();
	$account_required  = event_manager_user_requires_account();
	$registration_fields = array();
	if(event_manager_enable_registration()) {
		if(!$generate_username_from_email) {
			$registration_fields['create_account_username'] = array(
				'type'     => 'text',
				'label'    => __('Username', 'wp-event-manager'),
				'required' => $account_required,
				'value'    => isset($_POST['create_account_username']) ? sanitize_text_field($_POST['create_account_username']) : '',
			);
		}
		if(!$use_standard_password_setup_email) {
			$registration_fields['create_account_password'] = array(
				'type'         => 'password',
				'label'        => __('Password', 'wp-event-manager'),
				'autocomplete' => false,
				'required'     => $account_required,
			);
			$password_hint = event_manager_get_password_rules_hint();
			if($password_hint) {
				$registration_fields['create_account_password']['description'] = $password_hint;
			}
			$registration_fields['create_account_password_verify'] = array(
				'type'         => 'password',
				'label'        => __('Verify Password', 'wp-event-manager'),
				'autocomplete' => false,
				'required'     => $account_required,
			);
		}
		$registration_fields['create_account_email'] = array(
			'type'        => 'text',
			'label'       => __('Your email', 'wp-event-manager'),
			'placeholder' => __('you@yourdomain.com', 'wp-event-manager'),
			'required'    => $account_required,
			'value'       => isset($_POST['create_account_email']) ? sanitize_email($_POST['create_account_email']) : '',
		);
	}
	/**
	 * Filters the fields used at registration.
	 *
	 * @since 1.8
	 *
	 * @param array $registration_fields
	 */
	return apply_filters('event_manager_get_registration_fields', $registration_fields);
}


/**
 * Returns if we allow indexing of a event listing.
 *
 * @since 1.8
 *
 * @param WP_Post|int|null $post
 * @return bool
 */
function event_manager_allow_indexing_event_listing($post = null){
	$post = get_post($post);
	if($post && $post->post_type !== 'event_listing') {
		return true;
	}
	// Only index event listings that are not expired and published.
	$index_event_listing = !is_event_cancelled($post) && 'publish' === $post->post_status;
	/**
	 * Filter if we should allow indexing of event listing.
	 *
	 * @since 1.8
	 * @param bool $index_event_listing True if we should allow indexing of event listing.
	 */
	return apply_filters('event_manager_allow_indexing_event_listing', $index_event_listing);
}

/**
 * Returns if we output event listing structured data for a post.
 *
 * @since 1.8
 *
 * @param WP_Post|int|null $post
 * @return bool
 */
function event_manager_output_event_listing_structured_data($post = null){
	$post = get_post($post);
	if($post && $post->post_type !== 'event_listing') {
		return false;
	}
	// Only show structured data for un-filled and published event listings.
	$output_structured_data = !is_event_cancelled($post) && 'publish' === $post->post_status;
	/**
	 * Filter if we should output structured data.
	 *
	 * @since 1.8
	 * @param bool $output_structured_data True if we should show structured data for post.
	 */
	return apply_filters('event_manager_output_event_listing_structured_data', $output_structured_data);
}

/**
 * Gets the structured data for the event listing.
 *
 * @since 1.8
 * @see https://developers.google.com/search/docs/data-types/events
 *
 * @param WP_Post|int|null $post
 * @return bool|array False if functionality is disabled; otherwise array of structured data.
 */
function event_manager_get_event_listing_structured_data($post = null){
	$post = get_post($post);
	if($post && $post->post_type !== 'event_listing') {
		return false;
	}
	$data = array();
	$data['@context'] = 'http://schema.org/';
	$data['@type'] = 'Event';

	$event_expires = esc_attr(get_post_meta($post->ID, '_event_expires', true));
	if(!empty($event_expires)) {
		$data['validThrough'] = date('c', strtotime($event_expires));
	}

	$data['description'] = get_event_description($post);
	$data['name'] = strip_tags(get_event_title($post));
	$data['image'] = get_event_banner($post);
	$data['startDate'] = get_event_start_date($post);
	$data['endDate'] = get_event_end_date($post);
	$data['performer'] = get_dj_name($post);
	$data['eventAttendanceMode'] = is_event_online($post) ? 'OnlineEventAttendanceMode' : 'OfflineEventAttendanceMode';
	$data['eventStatus'] = 'EventScheduled';
	$data['dj']['@type'] = 'Organization';
	$data['dj']['name'] = get_dj_name($post);
	if($dj_website = get_dj_website($post)) {
		$data['dj']['sameAs'] = $dj_website;
		$data['dj']['url'] = $dj_website;
	}
	$location = get_event_location($post);
	if(!empty($location) && !is_event_online($post)) {
		$data['Location'] = array();
		$data['Location']['@type'] = 'Place';
		$data['Location']['name'] = $location;
		$data['Location']['address'] = event_manager_get_event_listing_location_structured_data($post);
		if(empty($data['Location']['address'])) {
			$data['Location']['address'] = $location;
		}
	} else {
		$data['Location'] = array();
		$data['Location']['@type'] = 'VirtualLocation';
		$data['Location']['url'] = get_permalink($post->ID);
	}
	/**
	 * Filter the structured data for a event listing.
	 *
	 * @since 1.8
	 *
	 * @param bool|array $structured_data False if functionality is disabled; otherwise array of structured data.
	 * @param WP_Post    $post
	 */
	return apply_filters('event_manager_get_event_listing_structured_data', $data, $post);
}

/**
 * Gets the event listing location data.
 *
 * @see http://schema.org/PostalAddress
 *
 * @param WP_Post $post
 * @return array|bool
 */
function event_manager_get_event_listing_location_structured_data($post){
	$post = get_post($post);
	if($post && $post->post_type !== 'event_listing') {
		return false;
	}
	$mapping = array();
	$mapping['streetAddress'] = array('street_number', 'street');
	$mapping['addressLocality'] = 'city';
	$mapping['addressRegion'] = 'state_short';
	$mapping['postalCode'] = 'postcode';
	$mapping['addressCountry'] = 'country_short';
	$address = array();
	$address['@type'] = 'PostalAddress';
	foreach($mapping as $schema_key => $geolocation_key) {
		if(is_array($geolocation_key)) {
			$values = array();
			foreach($geolocation_key as $sub_geo_key) {
				$geo_value = esc_attr(get_post_meta($post->ID, 'geolocation_' . $sub_geo_key, true));
				if(!empty($geo_value)) {
					$values[] = $geo_value;
				}
			}
			$value = implode(' ', $values);
		} else {
			$value = esc_attr(get_post_meta($post->ID, 'geolocation_' . $geolocation_key, true));
		}
		if(!empty($value)) {
			$address[$schema_key] = $value;
		}
	}
	// No address parts were found
	if(1 === count($address)) {
		$address = false;
	}
	/**
	 * Gets the event listing location structured data.
	 *
	 * @since 1.8
	 *
	 * @param array|bool $address Array of address data.
	 * @param WP_Post    $post
	 */
	return apply_filters('event_manager_get_event_listing_location_structured_data', $address, $post);
}


/**
 * Displays the event title for the listing.
 *
 * @since 1.8
 * @param int|WP_Post $post
 * @return string
 */
function display_event_title($post = null){
	if($event_title = get_event_title($post)) {
		echo esc_attr($event_title);
	}
}

/**
 * Gets the event title for the listing.
 *
 * @since 1.8
 * @param int|WP_Post $post (default: null)
 * @return string|bool|null
 */
function get_event_title($post = null){
	$post = get_post($post);
	if(!$post || 'event_listing' !== $post->post_type) {
		return;
	}

	$title = esc_html(get_the_title($post));
	/**
	 * Filter for the event title.
	 *
	 * @since 1.8
	 * @param string      $title Title to be filtered.
	 * @param int|WP_Post $post
	 */
	return apply_filters('display_event_title', $title, $post);
}

/**
 * Displays the event description for the listing.
 *
 * @since 1.8
 * @param int|WP_Post $post
 * @return string
 */
function display_event_description($post = null){
	if($event_description = get_event_description($post)) {
		echo esc_attr($event_description);
	}
}

/**
 * Gets the event description for the listing.
 *
 * @since 1.8
 * @param int|WP_Post $post (default: null)
 * @return string|bool|null
 */
function get_event_description($post = null){
	$post = get_post($post);
	if(!$post || 'event_listing' !== $post->post_type) {
		return;
	}

	$description = apply_filters('display_event_description', get_the_content($post));
	/**
	 * Filter for the event description.
	 *
	 * @since 1.8
	 * @param string      $title Title to be filtered.
	 * @param int|WP_Post $post
	 */
	return apply_filters('event_manager_get_event_description', $description, $post);
}

/**
 * Get event ticket price.
 * @return event ticket price
 **/
function get_event_ticket_price($post = null){

	$post = get_post($post);
	if($post->post_type !== 'event_listing' || get_event_ticket_option() == 'free')
		return;

	return apply_filters('display_event_ticket_price', $post->_event_ticket_price, $post);
}

/**
 * Display event ticket price.
 * @return
 **/
function display_event_ticket_price($before = '', $after = '', $echo = true, $post = null){

	$event_ticket_price = get_event_ticket_price($post);
	if(!is_string($event_ticket_price) || strlen($event_ticket_price) == 0)
		return;

	$event_ticket_price = strip_tags($event_ticket_price);
	$event_ticket_price = $before . $event_ticket_price . $after;
	if($echo)
		echo esc_attr($event_ticket_price);
	else
		return $event_ticket_price;
}

/**
 * Get date and time separator.
 * @since 3.1.8
 * @param null
 * @return string 
 **/
function get_wpem_date_time_separator(){
	return	apply_filters('event_manager_date_time_format_separator', get_option('event_manager_date_time_format_separator', '@'));
}

/**
 * Display date and time separator.
 * @since 3.1.8
 * @param
 * @return
 **/
function display_date_time_separator(){
	$separator = get_option('event_manager_date_time_format_separator', '@');
	if($separator){
		return	apply_filters('event_manager_date_time_format_separator', get_option('event_manager_date_time_format_separator', '@'));
	}else {
		echo ' @ ';
	}
}

/**
 * Hide feature image in single page.
 * @since 3.1.8
 * @param
 * @return
 **/
add_filter('post_thumbnail_html', 'hide_feature_image_single_page', 10, 3);
function hide_feature_image_single_page($html, $post_id, $post_image_id){
	if(is_singular('event_listing')) {
		return '';
	} else if(is_singular('event_dj')) {
		return '';
	} else if(is_singular('event_local')) {
		return '';
	}
	return $html;
}

/**
 * Display query pagination.
 * @since 3.1.18
 * @param
 * @return
 **/
function display_wpem_get_query_pagination($max_num_pages = 0, $current_page = 1, $tab = ''){
	ob_start();

	// Calculate pages to output 
	$end_size    = 3;
	$mid_size    = 3;
	$start_pages = range(1, $end_size);
	$end_pages   = range($max_num_pages - $end_size + 1, $max_num_pages);
	$mid_pages   = range($current_page - $mid_size, $current_page + $mid_size);
	$pages       = array_intersect(range(1, $max_num_pages), array_merge($start_pages, $end_pages, $mid_pages));
	$prev_page   = 0;?>
	<nav class="event-manager-pagination-2 wpem-mt-3">
		<ul class="page-numbers">
			<?php if($current_page && $current_page > 1) : ?>
				<?php
				$prev_page_link = add_query_arg(
					array(
						'pagination' => $current_page - 1,
						'tab' => $tab,

					)
				);
				?>
				<li><a href="<?php echo esc_attr($prev_page_link); ?>" class="page-numbers">&larr;</a></li>
			<?php endif; 
			foreach($pages as $page) {
				if($prev_page != $page - 1) {
					printf('<li><span class="gap">...</span></li>');
				}
				if($current_page == $page) {
					printf('<li><span class="page-numbers current">%s</span></li>',esc_attr($page));
				} else {
					$page_link = add_query_arg(
						array(
							'pagination' => $page,
							'tab' => $tab,

						)
					);
					printf('<li><a href="%s" class="page-numbers">%s</a></li>',esc_url($page_link),esc_attr($page));
				}
				$prev_page = $page;
			}
			if($current_page && $current_page < $max_num_pages) : 
				$next_page_link = add_query_arg(
					array(
						'pagination' => $current_page + 1,
						'tab' => $tab,

					)
				);
				?>
				<li><a href="<?php echo esc_attr($next_page_link); ?>" class="page-numbers">&rarr;</a></li>
			<?php endif; ?>
		</ul>
	</nav>
<?php
	 ob_get_clean();
}

/**
 * Get All Fields of Event dj Form.
 * @since 3.1.31
 * @param string
 * @return array
 **/
function get_hidden_form_fields($form_option, $key_name){
	$form_fields_array = get_option($form_option, true);
	$form_fields = array();
	if(!empty($form_fields_array)) :
		$form_field_key = $form_fields_array[$key_name] ?? array();
		foreach($form_field_key as $key => $option):
			if(isset($option['visibility']) && $option['visibility'] ==0):
				array_push($form_fields, $key);
			endif;
		endforeach;
	endif;
	return $form_fields;
}