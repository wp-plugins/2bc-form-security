<?php
/**
 * Class twobc_wpadmin_input_fields
 * Easily add input fields to WordPress admin screens
 * 
 * @author Jason Dreher - 2BCoding, http://2bcoding.com/, jason@2bcoding.com
 * @copyright 2014
 * @version 1.0.1
 * @package WordPress 
 */
/*
 * Easily add input fields to WordPress admin screens
 * Copyright (C) 2014 2BCoding (email : info@2bcoding.com)
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

if ( !class_exists('twobc_wpadmin_input_fields_1_0_1') ) {
	/**
	 * Class twobc_wpadmin_input_fields
	 * Easily add input fields to WordPress admin screens.
	 * 
	 * Arguments should be in an array.  All arguments are optional.
	 * 		Arguments:
	 * 			* echo => true|false - echo, or return the field
	 * 			* nonce => true|false - add WordPress nonce for field
	 * 			* html5 => true|false - add self-closing tags or not
	 * 			* debug => false|true - output error messages 
	 */
	class twobc_wpadmin_input_fields_1_0_1 {
		// class constant - text domain
		const TWOBC_ADMIN_INPUT_FIELD_TEXT_DOMAIN = 'TwoBCTestimonials';
		
		// class variables
		private $echo;
		private $nonce;
		private $html5;
		private $debug;
		
		function __construct($class_args = array()) {
			// default args
			$default_args = array(
				'echo' => true,
				'nonce' => true,
				'html5' => true,
				'debug' => false,
			);
			
			// parse args
			$class_args = array_merge($default_args, $class_args);
			
			// set class variables
			$this->echo = $class_args['echo'];
			$this->html5 = $class_args['html5'];
			$this->debug = $class_args['debug'];
			$this->nonce = $class_args['nonce'];
		}

		/**
		 * Build and display or return an input field intended for WordPress admin section
		 * Can be used to add post / page / user metas, or options in a custom setting screen
		 * 
		 * @param $input_tag_args
		 *		* echo => true|false - echo, or return the field
		 *		* nonce => true|false - add WordPress nonce for field
		 *		* html5 => true|false - add self-closing tags or not
		 *		* debug => false|true - output error messages
		 * 
		 * @uses field_default_args()
		 *
		 * @return string
		 */
		function field($input_tag_args) {
			$returned_input_tag = '';
			
			// parse args
			$input_tag_args = array_merge($this->field_default_args(), $input_tag_args);
			
			// validate arguments
			$errors = array();
			
			if ( empty($input_tag_args['type']) )
				$errors[] = __('Error - type cannot be empty', self::TWOBC_ADMIN_INPUT_FIELD_TEXT_DOMAIN);
			
			// validate type
			switch ($input_tag_args['type']) {
				case 'text' :
				case 'number' :
				case 'url' :
				case 'email' :
				case 'date' :
				case 'textarea' :
				case 'checkbox' :
				case 'select' :
				case 'radio' :
					break;
				
				default :
					$errors[] = sprintf(__('Error - invalid type - %s', self::TWOBC_ADMIN_INPUT_FIELD_TEXT_DOMAIN), esc_html($input_tag_args['type']));
			}
			
			// sanitize name
			$sanitized_name = '';
			if ( empty($input_tag_args['name']) ) {
				$errors[] = __('Error - name cannot be empty', self::TWOBC_ADMIN_INPUT_FIELD_TEXT_DOMAIN);
			} else {
				$sanitized_name = esc_attr($input_tag_args['name']);
				if ( empty($sanitized_name) )
					$errors[] = sprintf(__('Error - invalid name - %s', self::TWOBC_ADMIN_INPUT_FIELD_TEXT_DOMAIN), esc_html($input_tag_args['name']));
			}
			
			// error check
			if ( empty($errors) ) {
				if ( !empty($input_tag_args['id']) )
					$sanitized_id = sanitize_html_class($input_tag_args['id']);
				
				// if ID was empty or bad, default to name
				if ( empty($sanitized_id) )
					$sanitized_id = $sanitized_name;
				
				// sanitize description
				$sanitized_description = '';
				if ( !empty($input_tag_args['description']) )
					$sanitized_description = '<span class="description">' . wp_kses_post($input_tag_args['description']) . '</span>
';
				
				// sanitize classes
				$sanitized_classes = '';
				if ( !empty($input_tag_args['class']) && is_array($input_tag_args['class']) ) {
					foreach ($input_tag_args['class'] as &$_class_name) {
						$_class_name = sanitize_html_class($_class_name);
					}
					// remove any empty values from bad class names
					$input_tag_args['class'] = array_filter($input_tag_args['class']);
					// implode class names to a string
					if ( !empty($input_tag_args['class']) )
						$sanitized_classes = implode(' ', $input_tag_args['class']);
				}
				
				// sanitize value, current value, and placeholder
				$sanitized_values = array(
					'value' => $input_tag_args['value'],
					'current_value' => $input_tag_args['current_value'],
					'placeholder' => $input_tag_args['placeholder'],
				);
				
				foreach ($sanitized_values as &$_tag) {
					if ( empty($_tag) )
						continue;
				
					switch ($input_tag_args['type']) {
						case 'text' :
							$_tag = sanitize_text_field($_tag);
							break;
						
						case 'number' :
							if ( !is_numeric($_tag) )
								$_tag = '';
							else
								$_tag = intval($_tag);
							break;
						
						case 'url' :
							$_tag = esc_url($_tag);
							break;
						
						case 'email' :
							$_tag = is_email($_tag);
							if ( false === $_tag )
								$_tag = '';
							break;
						
						case 'date' :
							$timestamp = strtotime($_tag);
							if ( !checkdate(date('n', $timestamp), date('j', $timestamp), date('Y', $timestamp)) )
								$_tag = '';
							break;
						
						case 'textarea' :
							$_tag = wp_kses_post($_tag);
							break;
						
						default :
							// checkbox, select, radio
							esc_attr($_tag);
					}
				}
				
				// sanitize min, max, cols, rows
				$sanitized_numerics = array(
					'min' => $input_tag_args['min'],
					'max' => $input_tag_args['max'],
					'cols' => $input_tag_args['cols'],
					'rows' => $input_tag_args['rows'],
				);
				
				foreach ($sanitized_numerics as &$_tag) {
					if ( empty($_tag) )
						continue;
					
					if ( !is_numeric($_tag) ) {
						$_tag = '';
					} else {
						$_tag = intval($_tag);
					}
				}
				
				// sanitize style
				$sanitized_style = '';
				if ( !empty($input_tag_args['style']) )
					$sanitized_style = trim($input_tag_args['style']);
				
				// sanitize options
				$sanitized_options = array();
				if ( !empty($input_tag_args['options']) && is_array($input_tag_args['options']) ) {
					foreach ($input_tag_args['options'] as $_option_value => $_option_text) {
						$_sanitized_value = esc_attr($_option_value);
						if ( !empty($_sanitized_value) ) {
							switch (true) {
								case ( is_string($_option_text) ) :
									$sanitized_options[$_sanitized_value] = wp_kses_post($_option_text);
									break;
								
								case ( is_array($_option_text) && isset($_option_text['label']) ) :
									// assign entire array to catch any other properties
									$sanitized_options[$_sanitized_value] = $_option_text;
									// sanitize output
									$sanitized_options[$_sanitized_value]['label'] = wp_kses_post($_option_text['label']);
									break;
								
								default :
							}
						}
					}
				}
				
				// nonce - optional
				if ( $this->nonce ) {
					$returned_input_tag .= '<input type="hidden" name="' . $sanitized_name . '_nonce" id="' . $sanitized_id . '_nonce" value="' . wp_create_nonce($sanitized_name . '_nonce') . '"' . ($this->html5 ? '>
' : ' />
');
				}
				
				// BUILD TAG - START
				switch ($input_tag_args['type']) {
					case 'text' :
						$returned_input_tag .= '<input type="text"';
						break;
					case 'number' :
						$returned_input_tag .= '<input type="number"';
						break;
					case 'url' :
						$returned_input_tag .= '<input type="url"';
						break;
					case 'email' :
						$returned_input_tag .= '<input type="email"';
						break;
					case 'date' :
						$returned_input_tag .= '<input type="date"';
						break;
					case 'textarea' :
						$returned_input_tag .= '<textarea';
						break;
					case 'checkbox' :
						$returned_input_tag .= '<input type="checkbox"';
						break;
					case 'select' :
						$returned_input_tag .= '<select';
						break;
					case 'radio' :
						// don't build a wrapping tag, all tags will be built below
						break;
					default :
						// field type has already been validated, this case should never happen
				}
				
				
				// everything but radio buttons
				if ( 'radio' != $input_tag_args['type'] ) {
					// tag name
					$returned_input_tag .= ' name="' . $sanitized_name . '"';
					// tag id
					$returned_input_tag .= ' id="' . $sanitized_id . '"';
					// tag class
					// apply from arguments, else apply appropriate classes per field type
					if ( !empty($sanitized_classes) ) {
						$returned_input_tag .= ' class="' . $sanitized_classes . '"';
					} else {
						switch ($input_tag_args['type']) {
							case 'number' :
								$returned_input_tag .= ' class="small-text"';
								break;
							case 'text' :
							case 'date' :
								$returned_input_tag .= ' class="regular-text"';
								break;
							case 'url' :
							case 'email' :
								$returned_input_tag .= ' class="regular-text code"';
								break;
							case 'textarea' :
								$returned_input_tag .= ' class="large-text"';
								break;
							default :
						}
					}
					// tag value - not for select or textarea
					switch ($input_tag_args['type']) {
						case 'select' :
						case 'textarea' :
							break;
						case 'checkbox' :
							// set value to value
							$returned_input_tag .= ' value="' . $sanitized_values['value'] . '"';
							break;
						default :
							// allow value, or current_value
							// * text
							// * number
							// * url
							// * email
							// * date
							switch (true) {
								case (!empty($sanitized_values['value'])) :
									$tag_value = $sanitized_values['value'];
									break;
								case (!empty($sanitized_values['current_value'])) :
									$tag_value = $sanitized_values['current_value'];
									break;
								default :
									$tag_value = '';
							}
							$returned_input_tag .= ' value="' . $tag_value . '"';
					}

					// tag placeholder - not for select or checkbox
					switch ($input_tag_args['type']) {
						case 'select' :
						case 'checkbox' :
							break;
						default :
							if ( !empty($sanitized_values['placeholder']) ) {
								$returned_input_tag .= ' placeholder="' . $sanitized_values['placeholder'] . '"';
							} else {
								// default placeholders
								switch ($input_tag_args['type']) {
									case 'url' :
										$returned_input_tag .= ' placeholder="http://"';
										break;
									case 'email' :
										$returned_input_tag .= ' placeholder="email@address.com"';
										break;
									case 'date' :
										$returned_input_tag .= ' placeholder="12/31/1999"';
										break;
									default :
								}
							}
					}
					// tag disabled
					if ( !empty($input_tag_args['disabled']) )
						$returned_input_tag .= ' disabled="disabled"';
					// tag style
					if ( !empty($sanitized_style) )
						$returned_input_tag .= ' style="' . $sanitized_style . '"';
					// tag numerics - min, max, cols, rows
					switch ($input_tag_args['type']) {
						// min, max
						case 'number' :
							if ( !empty($sanitized_numerics['min']) )
								$returned_input_tag .= ' min="' . $sanitized_numerics['min'] . '"';
							if ( !empty($sanitized_numerics['max']) )
								$returned_input_tag .= ' max="' . $sanitized_numerics['max'] . '"';
							break;
						// cols, rows
						case 'textarea' :
							if ( !empty($sanitized_numerics['cols']) )
								$returned_input_tag .= ' cols="' . $sanitized_numerics['cols'] . '"';
							if ( !empty($sanitized_numerics['rows']) )
								$returned_input_tag .= ' rows="' . $sanitized_numerics['rows'] . '"';
							break;
						default :
					}
					// tag checked
					if ( 'checkbox' == $input_tag_args['type'] ) {
						if ( !empty($input_tag_args['checked']) || $sanitized_values['current_value'] == $sanitized_values['value']
						)
							$returned_input_tag .= ' checked="checked"';
					}
					// input closing tag - close select and textarea, everything else according to html5 
					switch ($input_tag_args['type']) {
						case 'select' :
						case 'textarea' :
							$returned_input_tag .= '>';
							break;
						default :
							$returned_input_tag .= ($this->html5 ? '>
' : ' />
');
					}
				}
				// special processing for radio, select, and textarea
				switch ($input_tag_args['type']) {
					case 'textarea' :
						// output current value
						// new - take value, or current_value if value is blank
						switch (true) {
							case (!empty($sanitized_values['value'])) :
								$textarea_value = $sanitized_values['value'];
								break;
							case (!empty($sanitized_values['current_value'])) :
								$textarea_value = $sanitized_values['current_value'];
								break;
							default :
								$textarea_value = '';
						}
						$returned_input_tag .= $textarea_value;
						break;
					case 'radio' :
						// radio buttons - description, add to beginning of string
						$returned_input_tag .= $sanitized_description . ($this->html5 ? '<br>
' : '<br />
');
					// no break, continue processing
					case 'select' :
						// output all options
						if ( !empty($sanitized_options) && is_array($sanitized_options) ) {
							// select - placeholder processing
							if ( 'select' == $input_tag_args['type'] ) {
								if ( !empty($sanitized_values['placeholder']) && empty($sanitized_options['placeholder']) ) {
									$sanitized_options = array_merge(
										array (
											'placeholder' => $sanitized_values['placeholder'],
										),
										$sanitized_options
									);
								}
								// make sure placeholder is first
								if ( isset($sanitized_options['placeholder']) ) {
									// get first menu option
									$option_keys = array_keys($sanitized_options);
									$first_key = reset($option_keys);
									if ( 'placeholder' != $first_key ) {
										$placeholder_value = $sanitized_options['placeholder'];
										unset($sanitized_options['placeholder']);
										$sanitized_options = array_merge(
											array (
												'placeholder' => $placeholder_value,
											), $sanitized_options
										);
									}
								}
							} else {
								// remove any placeholders accidentally included
								if ( isset($sanitized_options['placeholder']) )
									unset($sanitized_options['placeholder']);
							}
							// output options
							foreach ($sanitized_options as $_option_value => $_option_text) {
								if ( 'radio' == $input_tag_args['type'] ) {
									$returned_input_tag .= '<label for="' . $sanitized_id . '_' . $_option_value . '">';
									$returned_input_tag .= '<input type="radio"';
									$returned_input_tag .= ' name="' . $sanitized_name . '"';
									$returned_input_tag .= ' id="' . $sanitized_id . '_' . $_option_value . '"';
									if ( !empty($sanitized_classes) )
										$returned_input_tag .= ' class="' . $sanitized_classes . '"';
									if ( !empty($sanitized_style) )
										$returned_input_tag .= ' style="' . $sanitized_style . '"';

								} else {
									$returned_input_tag .= '	<option';
								}
								// option value
								$returned_input_tag .= ' value="' . $_option_value . '"';
								// option disabled
								if ( !empty($input_tag_args['disabled']) )
									$returned_input_tag .= ' disabled="disabled"';
								// option checked (or selected)
								if ( !empty($input_tag_args['checked']) || $sanitized_values['current_value'] == $_option_value
								) {
									if ( 'radio' == $input_tag_args['type'] ) {
										$returned_input_tag .= ' checked="checked"';
									} else {
										$returned_input_tag .= ' selected="selected"';
									}
								}
								// close tag
								if ( 'radio' == $input_tag_args['type'] ) {
									// close according to html5
									$returned_input_tag .= ($this->html5 ? '>' : ' />');
								} else {
									// close tag
									$returned_input_tag .= '>';
								}
								// option label
								switch (true) {
									case (is_string($_option_text)) :
										$_option_label = $_option_text;
										break;
									case (is_array($_option_text) && isset($_option_text['label'])) :
										$_option_label = $_option_text['label'];
										break;
									default :
										$_option_label = '';
								}
								$returned_input_tag .= $_option_label;
								// closing tags
								if ( 'radio' == $input_tag_args['type'] ) {
									// close label, add break return
									$returned_input_tag .= '</label>';
									$returned_input_tag .= ($this->html5 ? '<br>
' : '<br />
');
								} else {
									// close option and new line
									$returned_input_tag .= '</option>
';
								}
							}
						}
						break;
					default :
						// nothing here
				}

				// close select and textarea
				switch ($input_tag_args['type']) {
					case 'select' :
						$returned_input_tag .= '</select>
';
						break;
					case 'textarea' :
						$returned_input_tag .= '</textarea>
';
						break;
					default :
				}
				// description for everything but radios
				if ( !empty($sanitized_description) ) {
					switch ($input_tag_args['type']) {
						case 'text' :
							$returned_input_tag .= ($this->html5 ? '<br>
' : '<br />
') . $sanitized_description;
							break;

						case 'radio' :
							// nothing here, description already taken care of
							break;

						default :
							// everything else
							$returned_input_tag .= $sanitized_description;
					}
				}
				// BUILD TAG - END
				
			} else { // errors are present
				// check for debug
				if ( $this->debug ) {
					foreach ($errors as $_error) {
						echo '<span class="error">' . esc_html($_error) . '</span><br>
';
					}
				}
			}
			
			// return statement
			if ( $this->echo ) {
				echo $returned_input_tag;
				return null;
			} else {
				return $returned_input_tag;
			}
		} // end of function build_input()
		
		/**
		 * Get the default arguments for field()
		 * 
		 * @return array
		 */
		function field_default_args() {
			$default_input_tag_args = array (
				'type' => false,
				'name' => false,
				'id' => false,
				'description' => '',
				'class' => array (),
				'style' => '',
				'value' => '',
				'current_value' => '',
				'disabled' => false,
				'checked' => false,
				'placeholder' => '',
				'min' => '',
				'max' => '',
				'cols' => '',
				'rows' => '',
				'options' => array (),
			);
			
			return $default_input_tag_args;
		}
		
	} // end of twobc_wpadmin_input fields class definition
} // end of class existence check

// DEBUGGING INFO
// this is the test case for the debug console
/*
<?php
$fields = new twobc_wpadmin_input_fields(
	array(
		'echo' => false,
		'debug' => true,
	)
);

echo '<pre>';
var_dump($fields);
echo '</pre>';

$text_field = array(
'type' => 'text',
'name' => 'test_text',
'current_value' => 'test_text',
);

$number = array(
'type' => 'number',
'name' => 'test_number',
'current_value' => '123',
);

$url = array(
'type' => 'url',
'name' => 'test_url',
'current_value' => 'http://www.google.com/',
);

$email = array(
'type' => 'email',
'name' => 'test_email',
'current_value' => 'email@address.com',
);

$date = array(
'type' => 'date',
'name' => 'test_date',
'current_value' => '12/31/1999',
);

$textarea = array(
'type' => 'textarea',
'name' => 'test_textarea',
'current_value' => wpautop('Lorem ipsum dolor sit amet, consectetur adipiscing elit. Phasellus quis lectus metus, at posuere neque. Sed pharetra nibh eget orci convallis at posuere leo convallis. Sed blandit augue vitae augue scelerisque bibendum. Vivamus sit amet libero turpis, non venenatis urna. In blandit, odio convallis suscipit venenatis, ante ipsum cursus augue.'),
);

$checkbox = array(
'type' => 'checkbox',
'name' => 'test_checkbox',
'value' => '1',
'current_value' => '1',
);

$select = array(
'type' => 'select',
'name' => 'test_select',
'current_value' => 'opt_2',
'options' => array(
		'opt_1' => 'Option 1',
		'opt_2' => array(
			'label' => 'Option 2',
			'disabled' => '1',
		),
		'opt_3' => 'Option 3',
		'placeholder' => 'Pick one&hellip;',
		
	),
);

$radio = array(
'type' => 'radio',
'name' => 'test_radio',
'current_value' => 'opt_2',
'placeholder' => 'Shouldn&#8217;t work&hellip;',
'options' => array(
		'opt_1' => 'Option 1',
		'opt_2' => array(
			'label' => 'Option 2',
			'disabled' => '1',
		),
		'opt_3' => 'Option 3',
		'placeholder' => 'Pick one&hellip;',
	),
);

echo '<pre>';
echo htmlspecialchars($fields->build_input_tag($text_field));
echo htmlspecialchars($fields->build_input_tag($number));
echo htmlspecialchars($fields->build_input_tag($url));
echo htmlspecialchars($fields->build_input_tag($email));
echo htmlspecialchars($fields->build_input_tag($date));
echo htmlspecialchars($fields->build_input_tag($textarea));
echo htmlspecialchars($fields->build_input_tag($checkbox));
echo htmlspecialchars($fields->build_input_tag($select));
echo htmlspecialchars($fields->build_input_tag($radio));

echo '</pre>';
*/
