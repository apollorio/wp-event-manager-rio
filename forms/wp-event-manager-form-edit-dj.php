<?php
include_once('wp-event-manager-form-submit-dj.php');

/**
 * WP_Event_Manager_Form_Edit_Event class.
 */
class WP_Event_Manager_Form_Edit_DJ extends WP_Event_Manager_Form_Submit_DJ {

	public $form_name           = 'edit-dj';
	public $dj_id;

	/** @var WP_Event_Manager_Form_Edit_DJ The single instance of the class */

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
		$this->dj_id = !empty($_REQUEST['dj_id']) ? absint($_REQUEST[ 'dj_id' ]) : 0;
		if  (!event_manager_user_can_edit_event($this->dj_id)) {
			$this->dj_id = 0;
		}
	}

	/**
	 * output function.
	*/
	public function output($atts = array()) {
		$this->submit_handler();
		$this->submit();
	}

	/**
	 * Submit Step.
	 */
	public function submit() {
		$dj = get_post($this->dj_id);
		if(empty($this->dj_id ) || ($dj->post_status !== 'publish')) {
			echo wp_kses_post(wpautop(__('Invalid listing', 'wp-event-manager')));
			return;
		}

		// Init fields
		// $this->init_fields(); We dont need to initialize with this function because of field edior
		// Now field editor function will return all the fields 
		// Get merged fields from db and default fields.
		$this->merge_with_custom_fields('frontend');
		
		// Get date and time setting defined in admin panel Event listing -> Settings -> Date & Time formatting
		$datepicker_date_format 	= WP_Event_Manager_Date_Time::get_datepicker_format();
		
		// Covert datepicker format  into php date() function date format
		$php_date_format 		= WP_Event_Manager_Date_Time::get_view_date_format_from_datepicker_date_format($datepicker_date_format);
		
		foreach ($this->fields as $group_key => $group_fields) {
			foreach ($group_fields as $key => $field) {
				if(!isset($this->fields[ $group_key ][ $key ]['value'])) {
					if('dj_name' === $key) {
						$this->fields[ $group_key ][ $key ]['value'] = esc_attr($dj->post_title);
					} elseif('dj_description' === $key) {
						$this->fields[ $group_key ][ $key ]['value'] = wp_kses_post($dj->post_content);
					} elseif('dj_logo' === $key) {
						/*$this->fields[ $group_key ][ $key ]['value'] = has_post_thumbnail($dj->ID) ? get_post_thumbnail_id($dj->ID) : esc_url(get_post_meta($dj->ID, '_' . $key, true));*/
						$this->fields[ $group_key ][ $key ]['value'] = get_post_meta($dj->ID, '_' . $key, true);
						
					} elseif(!empty($field['taxonomy'])) {
						$this->fields[ $group_key ][ $key ]['value'] = wp_get_object_terms($dj->ID, esc_attr($field['taxonomy']), array('fields' => 'ids'));
					} else {
						$this->fields[ $group_key ][ $key ]['value'] = esc_attr(get_post_meta($dj->ID, '_' . esc_attr($key), true));
					}
				}
				if(!empty($field['type']) &&  $field['type'] == 'date'){
					$dj_date = esc_html(get_post_meta($dj->ID, '_' . $key, true));
					$this->fields[ $group_key ][ $key ]['value'] = !empty($dj_date) ? date($php_date_format ,strtotime($dj_date)) :'';
				}
			}
		}
		$this->fields = apply_filters('submit_dj_form_fields_get_dj_data', $this->fields, $dj);
		wp_enqueue_script('wp-event-manager-event-submission');
		get_event_manager_template('dj-submit.php', 
			array(
				'form'               	=> esc_attr($this->form_name),
				'dj_id'          => esc_attr($this->get_dj_id()),
				'action'             	=> esc_url($this->get_action()),
				'dj_fields'     	=> $this->get_fields('dj'),
				'step'               	=> esc_attr($this->get_step()),
				'submit_button_text' 	=> __('Save changes', 'wp-event-manager')
			),
			'wp-event-manager/dj', 
            EVENT_MANAGER_PLUGIN_DIR . '/templates/dj'
		);
	}

	/**
	 * Submit Step is posted.
	 */
	public function submit_handler() {
		if(empty($_POST['submit_dj'])) {
			return;
		}
		try {
			// Get posted values
			$values = $this->get_posted_fields();

			// Validate required
			if(is_wp_error(($return = $this->validate_fields($values)))) {
				throw new Exception($return->get_error_message());
			}
			
			// Update the event
			$dj_name        = html_entity_decode( $values['dj']['dj_name'] );
			$dj_description = html_entity_decode( $values['dj']['dj_description'] );

			$dj_name        = wp_strip_all_tags( $dj_name );

			$this->save_dj( $dj_name, $dj_description, '', $values, false );

			$this->update_dj_data($values);

			// Successful
			switch (get_post_status($this->dj_id)) {
				case 'publish' :
					echo wp_kses_post('<div class="event-manager-message wpem-alert wpem-alert-success">' . __('Your changes have been saved.', 'wp-event-manager') . ' <a href="' . get_permalink($this->dj_id) . '">' . __('View &rarr;', 'wp-event-manager') . '</a>' . '</div>');
					break;
				default :
					echo wp_kses_post('<div class="event-manager-message wpem-alert wpem-alert-success">' . __('Your changes have been saved.', 'wp-event-manager') . '</div>');
					break;
			}
		} catch (Exception $e) {
			echo wp_kses_post('<div class="event-manager-error wpem-alert wpem-alert-danger">' .  esc_html($e->getMessage()) . '</div>');
			return;
		}
	}
}