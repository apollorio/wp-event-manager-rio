<?php
/**
 * WP_Event_Manager_Form_Submit_dj class.
 * (renomeado de dj para dj)
 */
if(!class_exists('WP_Event_Manager_Form')) {
	include_once(dirname(__FILE__) . '/wp-event-manager-form-abstract.php');
}
class WP_Event_Manager_Form_Submit_dj extends WP_Event_Manager_Form {
	
	public    $form_name = 'submit-dj';
	public    $steps;
	public    $resume_edit;
	public    $fields;
	protected $dj_id;
	protected $preview_dj;
	
	/** @var 
	* WP_Event_Manager_Form_Submit_dj The single instance of the class 
	*/
	protected static $_instance = null;
	/**
	 * Main Instance.
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
		add_action('wp', array($this, 'process'));
		$this->steps  =(array) apply_filters('submit_dj_steps', array(
			'submit' => array(
				'name'     => __('Submit Details', 'wp-event-manager'),
				'view'     => array($this, 'submit'),
				'handler'  => array($this, 'submit_handler'),
				'priority' => 10
				),
			'done' => array(
				'name'     => __('Done', 'wp-event-manager'),
				'view'     => array($this, 'done'),
				'priority' => 30
			)
		));

		uasort($this->steps, array($this, 'sort_by_priority'));
		// Get step/event
		if(isset($_POST['step'])) {
			$this->step = is_numeric($_POST['step']) ? max(absint($_POST['step']), 0) : array_search(esc_attr($_POST['step']), array_keys($this->steps));
		} elseif(!empty($_GET['step'])) {
			$this->step = is_numeric($_GET['step']) ? max(absint($_GET['step']), 0) : array_search(esc_attr($_GET['step']), array_keys($this->steps));
		}

		$this->dj_id =!empty($_REQUEST['dj_id']) ? absint($_REQUEST[ 'dj_id' ]) : 0;
		if(!event_manager_user_can_edit_event($this->dj_id)){
			$this->dj_id = 0;
		}
		
		// Allow resuming from cookie.
		$this->resume_edit = false;
		if(!isset($_GET[ 'new' ]) &&(!$this->dj_id) &&!empty($_COOKIE['wp-event-manager-submitting-dj-id']) &&!empty($_COOKIE['wp-event-manager-submitting-dj-key'])){
			$dj_id     = absint($_COOKIE['wp-event-manager-submitting-dj-id']);
			$dj_status = get_post_status($dj_id);
			if('preview' === $dj_status && esc_attr(get_post_meta($dj_id, '_wpem_unique_key', true)) === $_COOKIE['wp-event-manager-submitting-dj-key']) {
				$this->dj_id = $dj_id;
			}
		}
		// Load event details
		if($this->dj_id) {
			$dj_status = get_post_status($this->dj_id);
			if('expired' === $dj_status) {
				if(!event_manager_user_can_edit_event($this->dj_id)) {
					$this->dj_id = 0;
					$this->step   = 0;
				}
			} elseif(!in_array($dj_status, apply_filters('event_manager_valid_submit_dj_statuses', array('publish')))) {
				$this->dj_id = 0;
				$this->step   = 0;
			}
		}
	}

	/**
	 * Get the submitted event ID.
	 * @return int
	*/
	public function get_dj_id() {
		return absint($this->dj_id);
	}

	/**
	 * Manage of dj fields.
	 */
	public function init_fields() {
		$this->fields = apply_filters('submit_dj_form_fields', array(
			'dj' => array(
				'dj_name' => array(
					'label'       => __('dj name', 'wp-event-manager'),
					'type'        => 'text',
					'required'    => true,
					'placeholder' => __('Enter the name of the dj', 'wp-event-manager'),
					'priority'    => 1,
					'visibility'  => 1,
				),
				'dj_logo' => array(
					'label'       => __('Logo', 'wp-event-manager'),
					'type'        => 'file',
					'required'    => false,
					'placeholder' => '',
					'priority'    => 2,
					'ajax'        => true,
					'multiple'    => false,
					'allowed_mime_types' => array(
						'jpg'  => 'image/jpeg',
						'jpeg' => 'image/jpeg',
						'gif'  => 'image/gif',
						'png'  => 'image/png'
					),
					'visibility'  => 1,
				),
				'dj_description' => array(
					'label'       => __('dj Description', 'wp-event-manager'),
					'type'        => 'wp-editor',
					'required'    => true,
					'placeholder' => '',
					'priority'    => 3,
					'visibility'  => 1,
				),	
				'dj_country' => array(
					'label'       => __('dj Country', 'wp-event-manager'),
					'type'        => 'select',
					'required'    => true,
					'placeholder' => '',
					'priority'    => 4,
					'visibility'  => 1,
					'options'     => wpem_get_all_countries(),
				),	
				'dj_email' => array(
					'label'       => __('dj Email', 'wp-event-manager'),
					'type'        => 'text',
					'required'    => true,
					'placeholder' => __('Enter your email address', 'wp-event-manager'),
					'priority'    => 5,
					'visibility'  => 1,
				),
				'dj_website' => array(
					'label'       => __('Website', 'wp-event-manager'),
					'type'        => 'text',
					'required'    => false,
					'placeholder' => __('Website URL e.g http://www.yourorganization.com', 'wp-event-manager'),
					'priority'    => 6,
					'visibility'  => 1,
				),
				'dj_facebook' => array(
					'label'       => __('Facebook', 'wp-event-manager'),
					'type'        => 'text',
					'required'    => false,
					'placeholder' => __('Facebook URL e.g http://www.facebook.com/yourdj', 'wp-event-manager'),
					'priority'    => 7,
					'visibility'  => 1,
				),
				'dj_instagram' => array(
					'label'       => __('Instagram', 'wp-event-manager'),
					'type'        => 'text',
					'required'    => false,
					'placeholder' => __('Instagram URL e.g http://www.instagram.com/yourdj', 'wp-event-manager'),
					'priority'    => 8,
					'visibility'  => 1,
				),
				'dj_youtube' => array(
					'label'       => __('Youtube', 'wp-event-manager'),
					'type'        => 'text',
					'required'    => false,
					'placeholder' => __('Youtube Channel URL e.g http://www.youtube.com/channel/yourdj', 'wp-event-manager'),
					'priority'    => 9,
					'visibility'  => 1,
				),
				'dj_twitter' => array(
					'label'       => __('Twitter', 'wp-event-manager'),
					'type'        => 'text',
					'required'    => false,
					'placeholder' => __('Twitter URL e.g http://twitter.com/yourdj', 'wp-event-manager'),
					'priority'    => 10,
					'visibility'  => 1,
				),
			)
		));
		return $this->fields;
	}

	 /**
	 * Get user selected fields from the field editor.
	 *
	 * @return fields Array
	 */
	public  function get_event_manager_fieldeditor_fields(){
		return apply_filters('event_manager_submit_dj_form_fields', get_option('event_manager_submit_dj_form_fields', false));
	}

	/**
	 * This function will initilize default fields and return as array.
	 * @return fields Array
	 **/
	public  function get_default_fields() {
		if(empty($this->fields)){
			// Make sure fields are initialized and set
			$this->init_fields();
		}
		return $this->fields;
	}
	
	/**
	 * Submit Step.
	 */
	public function submit() {

		// Init fields
		// $this->init_fields(); We dont need to initialize with this function because of field edior
		// Now field editor function will return all the fields 
		// Get merged fields from db and default fields.
		$this->merge_with_custom_fields('frontend');

		// Get date and time setting defined in admin panel Event listing -> Settings -> Date & Time formatting
		$datepicker_date_format 	= WP_Event_Manager_Date_Time::get_datepicker_format();
						
		// Covert datepicker format  into php date() function date format
		$php_date_format 		= WP_Event_Manager_Date_Time::get_view_date_format_from_datepicker_date_format($datepicker_date_format);
			
		// Load data if neccessary
		if($this->dj_id) {
			$dj = get_post($this->dj_id);
			foreach($this->fields as $group_key => $group_fields) {
				foreach($group_fields as $key => $field) {
					switch($key) {
						case 'dj_name' :
							$this->fields[ $group_key ][ $key ]['value'] = esc_attr($dj->post_title);
						break;
						case 'dj_description' :
							$this->fields[ $group_key ][ $key ]['value'] = wp_kses_post($dj->post_content);
						break;
						case  'dj_logo':
							$this->fields[ $group_key ][ $key ]['value'] = has_post_thumbnail($dj->ID) ? get_post_thumbnail_id($dj->ID) : esc_url(get_post_meta($dj->ID, '_' . $key, true));
						break;
						default:
							$this->fields[ $group_key ][ $key ]['value'] = esc_attr(get_post_meta($dj->ID, '_' . $key, true));
						break;
					}
					if(!empty($field['taxonomy'])) {
						$this->fields[ $group_key ][ $key ]['value'] = wp_get_object_terms($dj->ID, $field['taxonomy'], array('fields' => 'ids'));
					}
					
					if(!empty($field['type']) &&  $field['type'] == 'date'){
						$event_date = esc_html(get_post_meta($dj->ID, '_' . $key, true));
						$this->fields[ $group_key ][ $key ]['value'] = date($php_date_format ,strtotime($event_date));
					}
				}
			}
			$this->fields = apply_filters('submit_event_form_fields_get_dj_data', $this->fields, $dj);
		}
		
		wp_enqueue_script('wp-event-manager-event-submission');
		get_event_manager_template('dj-submit.php', 
			array(
				'form'               => esc_attr($this->form_name),
				'dj_id'       => esc_attr($this->get_dj_id()),
				'resume_edit'        => $this->resume_edit,
				'action'             => esc_url($this->get_action()),
				'dj_fields'   => $this->get_fields('dj'),
				'step'               => esc_attr($this->get_step()),
				'submit_button_text' => apply_filters('submit_dj_form_submit_button_text',  __('Submit', 'wp-event-manager'))
			),
			'wp-event-manager/dj', 
            EVENT_MANAGER_PLUGIN_DIR . '/templates/dj'
		);
	}

	/**
	 * Validate the posted fields.
	 *
	 * @return bool on success, WP_ERROR on failure
	 */
	protected function validate_fields($values) {
		if ( ! is_user_logged_in() || ( ! current_user_can( 'manage_djs' ) && ! current_user_can( 'manage_options' ) ) ) {
			return new WP_Error('validation-error', esc_html__( 'Please login as dj to add or update an dj!', 'wp-event-manager')) ;
		}

		$this->fields =  apply_filters('before_submit_dj_form_validate_fields', $this->fields , $values);
	    foreach($this->fields as $group_key => $group_fields){     	      
				 
			foreach($group_fields as $key => $field) {
				if (!is_user_logged_in() && isset($field['type']) && $field['type'] === 'media-library-image') {
					$$field['required'] = false;
				}
				if( isset( $field['visibility'] ) && ( $field['visibility'] == 0 || $field['visibility'] = false ) )
					continue;
				
				if($field['required'] && empty($values[ $group_key ][ $key ])) {	    
					return new WP_Error('validation-error', sprintf(wp_kses('%s is a required field.', 'wp-event-manager'), esc_attr($field['label']))) ;
				}

				if(!empty($field['taxonomy']) && in_array($field['type'], array('term-checklist', 'term-select', 'term-multiselect'))) {
					if(is_array($values[ $group_key ][ $key ])) {
						$check_value = $values[ $group_key ][ $key ];
					} else {
						$check_value = empty($values[ $group_key ][ $key ]) ? array() : array($values[ $group_key ][ $key ]);
					}
					foreach($check_value as $term) {    
						if(!term_exists($term, $field['taxonomy'])) {
							return new WP_Error('validation-error', sprintf(wp_kses('%s is invalid', 'wp-event-manager'), esc_attr($field['label']))) ;    
						}
					}
				}

				if('file' === $field['type'] &&!empty($field['allowed_mime_types'])) {
					if(is_array($values[ $group_key ][ $key ])) {
						$check_value = array_filter($values[ $group_key ][ $key ]);
					} else {
						$check_value = array_filter(array($values[ $group_key ][ $key ]));
					}
					if(!empty($check_value)) {
						foreach($check_value as $file_url) {
							$file_url = current(explode('?', $file_url));
							$file_info = wp_check_filetype($file_url);
								if(!is_numeric($file_url) && $file_info && !in_array($file_info['type'], $field['allowed_mime_types'])) {
									throw new Exception(sprintf(wp_kses('"%s"(filetype %s) needs to be one of the following file types: %s', 'wp-event-manager'), esc_attr($field['label']), esc_attr($file_info['ext']), esc_attr(implode(', ', array_keys($field['allowed_mime_types']))) ));
							}
						}
					}
				}
			}
		}
		
		// dj email validation
		if(isset($values['dj']['dj_email']) && !empty($values['dj']['dj_email'])) {
			if (!is_email($values['dj']['dj_email'])) {
				throw new Exception(esc_attr__('Please enter a valid dj email address', 'wp-event-manager'));
			}
							
		}
		return apply_filters('submit_dj_form_validate_fields', true, $this->fields, $values);
	}

	/**
	 * Submit Step is posted.
	 */
	public function submit_handler() {
		try {
			// Init fields
			// $this->init_fields(); We dont need to initialize with this function because of field edior
			// Now field editor function will return all the fields 
			// Get merged fields from db and default fields.
			$this->merge_with_custom_fields('frontend');
			
			// Get posted values
			$values = $this->get_posted_fields();
			//if(empty($_POST['submit_dj']) || !is_user_logged_in()) {
			if(empty($_POST['submit_dj'])) {
				return;
			}
			// Validate required
			if(is_wp_error(($return = $this->validate_fields($values)))) {
					if(is_object($return) && method_exists($return, 'get_error_message')) {
						$error_msg = $return->get_error_message();
					} else {
						$error_msg = 'Erro desconhecido.';
					}
					throw new Exception($error_msg);
			}

			$status = is_user_logged_in() ? 'publish' : 'pending';
			
			// Update the event
			$dj_name        = html_entity_decode( $values['dj']['dj_name'] );
			$dj_description = html_entity_decode( $values['dj']['dj_description'] );

			$dj_name        = wp_strip_all_tags( $dj_name );

			$this->save_dj($dj_name, $dj_description, $this->dj_id ? '' : $status,$values);

			$this->update_dj_data($values);
			// Successful, show next step
			$this->step ++;
		} catch(Exception $e) {
			$this->add_error($e->getMessage());
			return;
		}
	}

	/**
	 * Update or create a dj from posted data.
	 *
	 * @param  string $post_title
	 * @param  string $post_content
	 * @param  string $status
	 * @param  array $values
	 * @param  bool $update_slug
	 */
	protected function save_dj($post_title, $post_content, $status = 'publish', $values = array(), $update_slug = true) {
		$dj_data = array(
			'post_title'     => sanitize_text_field($post_title),
			'post_content'   => wp_kses_post($post_content),
			'post_type'      => 'event_dj',
			'comment_status' => 'closed'
		);

		if($status) {
			$dj_data['post_status'] = $status;
		}
		$dj_data = apply_filters('submit_dj_form_save_dj_data', $dj_data, $post_title, $post_content, $status, $values);
		if($this->dj_id) {
			$dj_data['ID'] = $this->dj_id;
			wp_update_post($dj_data);
		} else {
			$this->dj_id = wp_insert_post($dj_data);
			if(!headers_sent()) {
				$wpem_unique_key = uniqid();
				setcookie('wp-event-manager-submitting-dj-id', $this->dj_id, 0, COOKIEPATH, COOKIE_DOMAIN, false);
				setcookie('wp-event-manager-submitting-dj-key', $wpem_unique_key, 0, COOKIEPATH, COOKIE_DOMAIN, false);
				update_post_meta($this->dj_id, '_wpem_unique_key', $wpem_unique_key);
			}
		}
	}

	/**
	 * Set event meta + terms based on posted values.
	 *
	 * @param  array $values
	 */
	protected function update_dj_data($values) {
		$maybe_attach = array();
		
		// Get date and time setting defined in admin panel Event listing -> Settings -> Date & Time formatting
		$datepicker_date_format 	= WP_Event_Manager_Date_Time::get_datepicker_format();
		
		// Covert datepicker format  into php date() function date format
		$php_date_format 		= WP_Event_Manager_Date_Time::get_view_date_format_from_datepicker_date_format($datepicker_date_format);

		// Loop fields and save meta and term data
		foreach($this->fields as $group_key => $group_fields) {
			foreach($group_fields as $key => $field) {
				if(isset($field['visibility']) && ($field['visibility'] == 0 || $field['visibility'] == false)) :
					continue;
				endif; 
				// Save taxonomies
				if(!empty($field['taxonomy'])) {
					if(is_array($values[ $group_key ][ $key ])) {
						wp_set_object_terms($this->dj_id, sanitize_text_field($values[ $group_key ][ $key ]), sanitize_text_field($field['taxonomy']), false);
					} else {
						wp_set_object_terms($this->dj_id, array(sanitize_text_field($values[ $group_key ][ $key ])), sanitize_text_field($field['taxonomy']), false);
					}				
				}
				elseif($field['type'] == 'date') {
					$date = $values[ $group_key ][ $key ];	
					if(!empty($date)) {
						//Convert date and time value into DB formatted format and save eg. 1970-01-01
						$date_dbformatted = WP_Event_Manager_Date_Time::date_parse_from_format($php_date_format  , $date);
						$date_dbformatted = !empty($date_dbformatted) ? $date_dbformatted : $date;
						update_post_meta($this->dj_id, '_' . $key, $date_dbformatted);
					}
					else
						update_post_meta($this->dj_id, '_' . $key, '');
					
				} elseif('file' === $field['type']) { 
					if($key == 'dj_logo' && empty($values[ $group_key ][ $key ])){
						update_post_meta($this->dj_id, '_thumbnail_id', '');
					}
					update_post_meta($this->dj_id, '_' . $key, $values[ $group_key ][ $key ]);
					// Handle attachments.
					if(is_array($values[ $group_key ][ $key ])) {
						foreach($values[ $group_key ][ $key ] as $file_url) {
							$maybe_attach[] = $file_url;
						}
					} else {
						$maybe_attach[] = $values[ $group_key ][ $key ];
					}
				}elseif('url' === $field['type']) { 
					update_post_meta($this->dj_id, '_' . $key, esc_url($values[ $group_key ][ $key ]));

				} elseif('email' === $field['type']) { 
					update_post_meta($this->dj_id, '_' . $key, sanitize_email($values[ $group_key ][ $key ]));
					
				}elseif('text' === $field['type']) { 
					update_post_meta($this->dj_id, '_' . $key, wp_strip_all_tags( html_entity_decode( $values[ $group_key ][ $key ] ) ));
					
				}else{
					update_post_meta($this->dj_id, '_' . $key, sanitize_text_field($values[ $group_key ][ $key ]));
				}
			}
		}
		$maybe_attach = array_filter($maybe_attach);
		// Handle attachments
		if(sizeof($maybe_attach) && apply_filters('event_manager_attach_uploaded_files', true)) {
			
			// Get attachments
			$attachments     = get_posts('post_parent=' . $this->dj_id . '&post_type=attachment&fields=ids&numberposts=-1');
			$attachment_urls = array();
			// Loop attachments already attached to the event
			foreach($attachments as $attachment_key => $attachment) {
				$attachment_urls[] = wp_get_attachment_url($attachment);
			}
			foreach($maybe_attach as $attachment_url) {
				if(!in_array($attachment_url, $attachment_urls) && !is_numeric($attachment_url)) {
					$attachment_id = $this->create_attachment($attachment_url);

					if ( empty( $attachment_id ) ) {
						delete_post_thumbnail( $this->dj_id );
					} else {
						set_post_thumbnail( $this->dj_id, $attachment_id );
					}
				}
			}
		}
		do_action('event_manager_update_dj_data', $this->dj_id, $values);
	}

	/**
	 * Create an attachment.
	 * @param  string $attachment_url
	 * @return int attachment id
	 */
	protected function create_attachment($attachment_url) {
		include_once(ABSPATH . 'wp-admin/includes/image.php');
		include_once(ABSPATH . 'wp-admin/includes/media.php');
	
		$upload_dir     = wp_upload_dir();
		$attachment_url = esc_url($attachment_url, array('http', 'https'));
		if(empty($attachment_url)) {
			return 0;
		}
		
		$attachment_url_parts = wp_parse_url($attachment_url);
		if(false !== strpos($attachment_url_parts['path'], '../')) {
			return 0;
		}
		$attachment_url = sprintf('%s://%s%s', $attachment_url_parts['scheme'], $attachment_url_parts['host'], $attachment_url_parts['path']);
		$attachment_url = str_replace(array($upload_dir['baseurl'], WP_CONTENT_URL, site_url('/')), array($upload_dir['basedir'], WP_CONTENT_DIR, ABSPATH), $attachment_url);
		if(empty($attachment_url) ||!is_string($attachment_url)) {
			return 0;
		}
		
		$attachment = array(
			'post_title'   => sanitize_text_field(get_the_title($this->dj_id)),
			'post_content' => '',
			'post_status'  => 'inherit',
			'post_parent'  => $this->dj_id,
			'guid'         => $attachment_url
		);
	
		if($info = wp_check_filetype($attachment_url)) {
			$attachment['post_mime_type'] = $info['type'];
		}
	
		$attachment_id = wp_insert_attachment($attachment, $attachment_url, $this->dj_id);
	
		if(!is_wp_error($attachment_id)) {
			wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $attachment_url));
			return $attachment_id;
		}
		return 0;
	}

	/**
	 * Done Step.
	 */
	public function done() {
		do_action('event_manager_dj_submitted', $this->dj_id);
		get_event_manager_template(
			'dj-submitted.php', 
			array(
				'dj' => get_post($this->dj_id),
			),
			'wp-event-manager/dj', 
            EVENT_MANAGER_PLUGIN_DIR . '/templates/dj'
		);
	}
}