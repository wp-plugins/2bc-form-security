<?php
/**
 * Plugin Name: 2BC Form Security
 * Plugin URI: http://2bcoding.com/plugins/2bc-form-security/
 * Description: Increase security and reduce spam by adding a honeypot and Google reCAPTCHA V2 to the log in form, registration form, and comment form
 * Version: 2.0.1
 * Author: 2BCoding
 * Author URI: http://2bcoding.com/
 * Text Domain: 2bc-form-security
 * License: GPL2
 *
 * @package WordPress
 * @subpackage 2BC-Form-Security
 */

/*
 * Copyright 2015  2BCoding  (email : info@2bcoding.com)
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

/************************************************
 * EXIT CONDITIONS
 ***********************************************/
if ( !defined('ABSPATH') ) {
	// Exit if accessed directly
	exit;
}

/************************************************
 * PLUGIN INIT
 ***********************************************/
// instantiate class
$core = twobc_form_security::get_instance();

// hooks - plugin init
add_action('plugins_loaded', array($core, 'hook_plugins_loaded'));
add_action('init', array($core, 'hook_init'));
add_action('admin_enqueue_scripts', array($core, 'hook_admin_enqueue_scripts'));
add_action('admin_menu', array($core, 'hook_admin_menu'));
add_action('admin_init', array($core, 'hook_admin_init'));

// hooks - admin notices
add_action('admin_notices', array($core, 'hook_admin_notices'));

// hooks - honeypot CSS
add_action('wp_enqueue_scripts', array($core, 'hook_wp_enqueue_scripts'));
add_action('wp_head', array($core, 'hook_wp_head'));

// hooks - log in form
add_action('login_enqueue_scripts', array($core, 'hook_login_enqueue_scripts'));
add_action('login_form', array($core, 'hook_login_form'));
add_action('login_head', array($core, 'hook_login_head'));
add_filter('wp_authenticate_user', array($core, 'hook_wp_authenticate_user'), 10, 2);

// hooks - registration form
add_action('register_form', array($core, 'hook_register_form'));
add_filter('registration_errors', array($core, 'hook_registration_errors'), 10, 3);

// hooks - comment form
add_action('comment_form', array($core, 'hook_comment_form'));
add_filter('pre_comment_approved', array($core, 'hook_pre_comment_approved'), 10, 2);

// hooks - login errors
add_filter('wp_login_errors', array($core, 'hook_wp_login_errors'), 10, 2);

// hooks - dashboard widget
add_action('wp_dashboard_setup', array($core, 'hook_wp_dashboard_setup'));

// hooks - ajax functions
add_action('wp_ajax_twobc_formsecurity_reset_log', array($core, 'ajax_reset_report_log'));
add_action('wp_ajax_twobcfs_recaptcha_valid', array($core, 'ajax_recaptcha_valid'));
add_action('wp_ajax_twobcfs_recacptcha_verify_api', array($core, 'ajax_recaptcha_verify_api'));

// hooks - BuddyPress
add_action('bp_include', array($core, 'hook_buddypress'));
add_action('bp_enqueue_scripts', array($core, 'hook_bp_enqueue_scripts'));
// BuddyPress registration form
add_action('bp_head', array($core, 'hook_bp_head'));
add_action('bp_after_registration_submit_buttons', array($core, 'hook_bp_after_submit'));
add_action('bp_signup_validate', array($core, 'hook_bp_signup_validate'));

/*************************************************
 * Class Definition
 ************************************************/
/**
 * Class twobc_form_security
 */
class twobc_form_security {
	private static $instance = null;
	private static $plugin_version = '2.0.1';
	private static $plugin_options;
	private static $plugin_url;
	private static $plugin_path;

	private static $google_verify_url = 'https://www.google.com/recaptcha/api/siteverify';

	private static $honeypot_name;

	private static $buddypress = false;

	/**
	 * Constructor - setup static properties
	 */
	private function __construct() {
		self::$plugin_url = plugin_dir_url(__FILE__);
		self::$plugin_path = plugin_dir_path(__FILE__);
		self::$plugin_options = self::get_options();
	}

	/**
	 * Return instance of current class
	 *
	 * @return null|twobc_form_security
	 */
	public static function get_instance() {
		if ( null === self::$instance )
			self::$instance = new self;

		return self::$instance;
	}

	/**
	 * Get current plugin options
	 *
	 * @return mixed|void
	 */
	public static function get_options() {
		if ( empty(self::$plugin_options) )
			self::$plugin_options = get_option('twobc_formsecurity_options');

		return self::$plugin_options;
	}

	/**
	 * Get the default plugin options
	 *
	 * @uses get_options_fields()
	 * @return array
	 */
	private static function get_options_default() {
		$option_fields = self::get_option_fields();

		$default_options = array();
		foreach ($option_fields as $_section) {
			foreach ($_section['fields'] as $_field_name => $_field_opts) {
				$default_options[$_field_name] = $_field_opts['default'];
			}
		}

		$default_options['version'] = self::$plugin_version;

		return $default_options;
	}

	/**
	 * Return plugin option fields for various functions
	 *
	 * @return array
	 */
	private static function get_option_fields() {
		$checkbox_value = '1';

		$option_fields = array(
			'section_recaptcha' => array(
				'title' => esc_html(__('Google reCAPTCHA Options', '2bc-form-security')),
				'callback' => 'options_recaptcha_cb',
				'fields' => array(
					'enable_recaptcha' => array(
						'title' => esc_html(__('Enable reCAPTCHA', '2bc-form-security')),
						'type' => 'checkbox',
						'description' => esc_html(__('Enable the Google reCAPTCHA tool for the checked forms',
							 '2bc-form-security')),
						'value' => $checkbox_value,
						'default' => 0,
					),

					'site_key' => array(
						'title' => esc_html(__('Site Key', '2bc-form-security')),
						'type' => 'text',
						'description' => esc_html(__('Enter your Google reCAPTCHA Site key for this site',
							 '2bc-form-security')),
						'default' => '',
					),

					'secret_key' => array(
						'title' => esc_html(__('Secret Key', '2bc-form-security')),
						'type' => 'text',
						'description' => esc_html(__('Enter your Google reCAPTCHA Secret key for this site',
							 '2bc-form-security')),
						'default' => '',
					),

					'record_ips' => array(
						'title' => esc_html(__('Record Users IP', '2bc-form-security')),
						'type' => 'checkbox',
						'description' => esc_html(__(
'Attempt to get the users IP.  Will send to Google for extra security validation, and will be displayed in the Reports tab.',
							 '2bc-form-security')),
						'value' => $checkbox_value,
						'default' => 0,
					),

					'recaptcha_theme' => array(
						'title' => esc_html(__('reCAPTCHA Theme', '2bc-form-security')),
						'type' => 'select',
						'description' => esc_html(__('Select which theme to display the Google reCAPTCHA tool in',
							 '2bc-form-security')),
						'default' => 'light',
						'options' => array(
							'light' => esc_html(__('Light', '2bc-form-security')),
							'dark' => esc_html(__('Dark', '2bc-form-security')),
						),
					),
				),
			),

			'section_display' => array(
					'title' => esc_html(__('Where To Display reCAPTCHA', '2bc-form-security')),
					'callback' => 'options_display_cb',
					'fields' => array(
							// display checkboxes
							'display_register' => array(
								'title' => esc_html(__('Registration Form', '2bc-form-security')),
								'type' => 'checkbox',
								'value' => $checkbox_value,
								'description' => esc_html(
									__('Display form security tools in the WordPress registration form',
									'2bc-form-security')),
								'default' => 0,
							),
							'display_login' => array(
								'title' => esc_html(__('Login Form', '2bc-form-security')),
								'type' => 'checkbox',
								'value' => $checkbox_value,
								'description' => esc_html(
									__('Display form security tools in the WordPress login form',
									'2bc-form-security')),
								'default' => 0,
							),
							'display_comment' => array(
								'title' => esc_html(__('Comment Form', '2bc-form-security')),
								'type' => 'checkbox',
								'value' => $checkbox_value,
								'description' => esc_html(
									__('Display form security tools in the WordPress comment form',
									'2bc-form-security')),
								'default' => 0,
							),
					),
			),

			'section_errors' => array(
				'title' => esc_html(__('Error Handling', '2bc-form-security')),
				'callback' => 'options_errors_cb',
				'fields' => array(
					'login_errors' => array(
						'title' => esc_html(__('Login Errors', '2bc-form-security')),
						'type' => 'radio',
						'description' => esc_html(__('Select how to handle login errors', '2bc-form-security')),
						'default' => 'formsec_error',
						'options' => array(
							'formsec_error' => wp_kses_post(__(
'<strong>2BC Form Security Error</strong> &ndash; Return a <code>twobc_form_security</code> error message that says <em>Security checks failed</em>',
								 '2bc-form-security')),
							'generic_errors' => wp_kses_post(__(
'<strong>Generic Errors</strong> &ndash; Return a generic <code>login_failed</code> error that simply says <em>Login failed</em>, to prevent hackers from learning valid user account names'
								, '2bc-form-security')),
						),
					),
					'comment_status' => array(
						'title' => esc_html(__('Comment Status', '2bc-form-security')),
						'type' => 'select',
						'description' => wp_kses_post(__(
'Control how failed comments are handled.  Options are to automatically mark as <strong>Spam</strong> (default), or put into the <strong>Moderation Queue</strong>.',
							 '2bc-form-security')),
						'default' => 'spam',
						'options' => array(
							'spam' => esc_html(__('Spam', '2bc-form-security')),
							'zero' => esc_html(__('Moderation Queue', '2bc-form-security')),
						),
					),
				),
			),

			'section_reports' => array(
				'title' => esc_html(__('Reports', '2bc-form-security')),
				'callback' => 'options_reports_cb',
				'fields' => array(
					'enable_reporting' => array(
						'title' => esc_html(__('Enable Reporting', '2bc-form-security')),
						'type' => 'checkbox',
						'value' => $checkbox_value,
						'description' => wp_kses_post(__(
		'Record security failures in the <strong>Reports</strong> tab to help gauge the effectiveness of the fields',
							'2bc-form-security')),
						'default' => 0,
					),
				),
			),

		);

		return $option_fields;
	}

	public static function options_recaptcha_cb() {

	}

	public static function options_display_cb() {

	}

	public static function options_honeypot_cb() {

	}

	public static function options_errors_cb() {

	}

	public static function options_compatibility_cb() {

	}

	public static function options_reports_cb() {

	}

	/*************************************************
	 * Plugin Init
	 ************************************************/
	/**
	 * plugins_loaded hook
	 *  Generate Honeypot name
	 *  Set log table in wpdb
	 *  Add twobc_wpadmin_input_fields
	 *  Handle install and upgrade
	 */
	public static function hook_plugins_loaded() {
		// generate honeypot name
		self::$honeypot_name = self::sanitize_html_class(wp_create_nonce('twobc_formsec_hp'));

		// UPDATE - add the logs table to $wpdb global
		global $wpdb;
		$wpdb->twobc_formsecurity_log = $wpdb->prefix . 'twobc_formsecurity_log';


		// add twobc_wpadmin_input_fields for option fields
		require_once(self::$plugin_path . 'includes/class_twobc_wpadmin_input_fields_1_0_2.php');

		// handle install and upgrade
		$plugin_version = self::$plugin_version;
		$plugin_options_default = self::get_options_default();
		// UPDATE - add activated option to default options
		$plugin_options_default['activated'] = true;

		$plugin_options = self::$plugin_options;
		$update_options = false;

		// install check
		if ( empty($plugin_options) ) {
			// init with default values
			$update_options = true;
			$plugin_options = $plugin_options_default;
		}

		// handle upgrade check
		if ( $plugin_version != $plugin_options['version'] ) {
			// init any empty db fields to catch any new additions
			foreach ( $plugin_options_default as $_name => $_value ) {
				if ( !isset($plugin_options[$_name]) ) {
					$plugin_options[$_name] = $_value;
				}

				// UPDATE - rewrite deprecated arguments
				if ( 'login_errors' == $_name  && 'incorrect_password' == $plugin_options['login_errors'] ) {
					$plugin_options['login_errors'] = $plugin_options_default['login_errors'];
				}
			}

			// set the updated settings
			$update_options = true;
		}

		if ( $update_options ) {
			update_option('twobc_formsecurity_options', $plugin_options);

			// update log structure
			self::init_logs();
		}
	}

	/**
	 * init hook - load plugin textdomain, register scripts and styles
	 */
	public static function hook_init() {
		load_plugin_textdomain('2bc-form-security', false, self::$plugin_path . 'lang');

		// register recaptcha script for later enqueues
		wp_register_script(
			'twobc_formsecurity_captcha', // handle
			'https://www.google.com/recaptcha/api.js', //src
			array(), // dependencies
			self::$plugin_version, // version
			true // in footer
		);

		// register recaptcha style for later enqueues
		wp_register_style(
			'twobc_formsecurity_captcha_style', // handle
			self::$plugin_url . 'includes/css/2bc-form-security.css', // URL src
			array(), // dependencies
			self::$plugin_version // version
		);
	}

	/*************************************************
	 * Admin - Options
	 ************************************************/
	/**
	 * admin_menu hook - add the 2BC Form Security menu option
	 */
	public static function hook_admin_menu() {
		add_options_page(
			esc_html(__('2BC Form Security', '2bc-form-security')), // page title
			esc_html(__('2BC Form Security', '2bc-form-security')), // menu title
			'manage_options', // capability required
			'twobc_formsecurity', // page slug
			array(self::get_instance(), 'settings_page_cb') // display callback
		);
	}

	/**
	 * admin_enqueue_scripts hook
	 * Selectively load the recaptcha api, admin script, and admin style
	 */
	public static function hook_admin_enqueue_scripts() {
		$current_screen = get_current_screen();

		switch ($current_screen->base) {
			case 'settings_page_twobc_formsecurity' :
				// load recaptcha API, for validation of API keys
				wp_enqueue_script('twobc_formsecurity_captcha');
				// no break, continue processing
			case 'dashboard' :
				// load plugin JS
				wp_enqueue_script(
					'twobc_formsecurity_admin_js', // string
					self::$plugin_url . 'includes/js/2bc-form-security-admin.js', // src
					array('jquery'), // dependencies
					self::$plugin_version, // script version
					true // in footer
				);

				wp_localize_script(
					'twobc_formsecurity_admin_js', // script handle
					'twoBCFormSecurity', // object name
					array( // data to pass
						'_ajax_nonce' => wp_create_nonce('twobc-formsecurity-ajaxnonce'),
						'errorSecretKey' => esc_html(__(
							'ERROR: Invalid Secret Key', 'Error', '2bc-form-security')),
						'errorResponse' => esc_html(__(
		'ERROR: The API keys are good, however Google says you are a robot&hellip; maybe you should try again?',
							 'Error', '2bc-form-security')),
						'errorGeneric' => esc_html(__(
		'ERROR: No error message provided - likely a site_key / secret_key mismatch.',
							 'Error', '2bc-form-security')),
						'noresponse' => esc_html(__(
		'ERROR: No response returned from Google...',
							 'Error', '2bc-form-security')),
						'instructMessage1' => esc_html(__(
							'Complete the reCAPTCHA widget below to confirm the API keys.', '2bc-form-security')),
						'instructMessage2' => wp_kses_post(__(
		'API Keys Confirmed! Click the <strong>Save all settings</strong> button to activate the reCAPTCHA widget.',
							'2bc-form-security')),
						'clearLogsButton' => esc_html(__(
							'Are you sure you want to clear the tracking log?', '2bc-form-security')),
						'recaptchaValidNonce' => wp_create_nonce('twobc_formsecurity_nonce_recaptcha_valid'),
					)
				);

				// load plugin styles
				wp_enqueue_style(
					'twobc_formsecurity_admin_css', // handle
					self::$plugin_url . 'includes/css/2bc-form-security-admin.css', // src
					array(), // dependencies
					self::$plugin_version // style version
				);

				break;

			default :
		}
	}

	/**
	 * admin_init hook - register settings for options screen
	 *
	 * @uses get_option_fields()
	 */
	public static function hook_admin_init() {
		$core = self::get_instance();

		// admin menu and pages
		register_setting(
			'twobc_formsecurity_options', // option group name, declared with settings_fields()
			'twobc_formsecurity_options', // option name, best to set same as group name
			array($core, 'options_sanitize_cb') // sanitization callback
		);

		$options_fields = self::get_option_fields();

		foreach ( $options_fields as $_section_name => $_section_opts ) {
			// add sections
			add_settings_section(
				'twobc_formsecurity_options_' . $_section_name, // section id
				$_section_opts['title'], // section title
				array($core, $_section_opts['callback']), // display callback
				'twobc_formsecurity' // page to display on
			);

			// add fields
			foreach ( $_section_opts['fields'] as $_field_name => $_field_opts ) {
				$additional_args = array(
					'type' => $_field_opts['type'],
					'name' => $_field_name,
					'description' => $_field_opts['description'],
					'default' => $_field_opts['default'],
				);

				$additional_args = array_merge($additional_args, $_field_opts);

				add_settings_field(
					$_field_name, //field id
					$_field_opts['title'], // field title
					array($core, 'wpaf_option_field'), // callback
					'twobc_formsecurity', // page to display on
					'twobc_formsecurity_options_' . $_section_name, // section to display in
					$additional_args
				);
			}
		}

		// UPDATE - save tab selection as cookie
		if ( !empty($_GET['tab']) ) {
			if ( 'settings' == $_GET['tab'] || 'reports' == $_GET['tab'] )
				setcookie('twobc_formsec_opt_tab', esc_attr($_GET['tab']), time() * 1209600, '/'); // 2 weeks
		}
	}

	/**
	 * Prepare form field for the options screen
	 *
	 * @param $field_args
	 *
	 * @uses twobc_wpadmin_input_fields_1_0_2
	 */
	public static function wpaf_option_field($field_args) {
		$wpaf = new twobc_wpadmin_input_fields_1_0_2(
			array(
				'nonce' => false,
			)
		);

		$field_args = array_merge($wpaf->field_default_args(), $field_args);

		$current_value = self::$plugin_options;
		$field_args['current_value'] = ( isset($current_value[$field_args['name']]) ?
			$current_value[$field_args['name']] : '' );

		// field nonce
		echo '<input type="hidden" id="twobc_formsecurity_nonce_' . $field_args['name'] . '"';
		echo ' name="twobc_formsecurity_nonce_' . $field_args['name'] . '"';
		echo ' value="' . wp_create_nonce('twobc_formsecurity_nonce_' . $field_args['name']) . '"';
		echo '>
';

		// handle the disabling of the recaptcha api fields
		switch ( $field_args['name']) {

			case 'site_key' :
				if ( empty(self::$plugin_options['recaptcha_valid']) && !self::recaptcha_has_args() ) {
					echo '	<p class="twobcfs_help_text">';
					printf(wp_kses_post(__(
						'Enter your Google reCAPTCHA V2 API keys below.  If you do not have API keys, see the 2BCoding blog post on<br>
%1$sHow To Get Google reCAPTCHA V2 API Keys%2$s', '2bc-form-security')),
'<a href="http://2bcoding.com/how-to-get-google-recaptcha-v2-api-keys/" target="_blank">', '</a>');
					echo '</p>
';
				}
				// no break, continue processing

			case 'secret_key' :
				if ( !empty(self::$plugin_options['recaptcha_valid']) ) {
					// output hidden fields so that the API keys are stored in the plugin options
					echo '	<input type="hidden" name="twobc_formsecurity_options[' . $field_args['name'] . ']" value="' .
						self::$plugin_options[$field_args['name']] . '">
	';
					$field_args['disabled'] = true;
				}

				break;

			case 'enable_recaptcha' :
				if ( empty(self::$plugin_options['recaptcha_valid']) ) {
					$field_args['disabled'] = true;
				} else {
					$field_args['class'] = array('recaptcha_valid');
				}

				break;

			default :
		}

		// fix name
		$field_args['name'] = 'twobc_formsecurity_options[' . $field_args['name'] . ']';

		echo '<fieldset>
';
		$wpaf->field($field_args);

		// UPDATE - add button to change current API keys
		if ( 'twobc_formsecurity_options[secret_key]' == $field_args['name'] ) {
			echo '	<input type="button" class="button" id="twobcfs_change_api" value="' .
				esc_html(__('Change API Keys', '2bc-form-security')) . '">
';
		}

		echo '</fieldset>
';

		if ( 'twobc_formsecurity_options[display_register]' == $field_args['name'] && self::$buddypress ) {
			echo '	<p class="options_flag_buddypress">
		<img src="' . self::$plugin_url .
				'images/buddypress.png" height="25" width="25" alt="BuddyPress icon"><strong>';
			echo esc_html(__('BuddyPress detected!', '2bc-form-security'));
		}
	}

	/**
	 * Options page callback
	 */
	public static function settings_page_cb() {
		//must check that the user has the required capability
		if ( !current_user_can('manage_options') ) {
			wp_die(esc_html(__(
				'You do not have sufficient permissions to access this page.', '2bc-form-security'
			)));
		}

		echo '<div id="twobc_formsecurity_options_wrap" class="wrap">
';
		settings_errors(
			'twobc_formsecurity_options', // settings group name
			false, // re-sanitize values on errors
			true // hide on update - set to true to get rid of duplicate Updated messages
		);

		// UPDATE - trying to get tabs to work - settings, and reports
		$tab_class_settings = $tab_class_reports = 'nav-tab';
		if ( empty($_GET['tab']) ) {
			// try to get value from cookie
			if ( !empty($_COOKIE['twobc_formsec_opt_tab']) ) {
				$active_tab = esc_attr($_COOKIE['twobc_formsec_opt_tab']);
			} else {
				// default to settings
				$active_tab = 'settings';
			}
		} else {
			$active_tab = esc_attr($_GET['tab']);
		}

		switch ( $active_tab ) {
			case 'reports' :
				$tab_class_reports .= ' nav-tab-active';
				break;

			case 'settings' :
			default :
				$tab_class_settings .= ' nav-tab-active';
		}

		echo '	<h2 class="nav-tab-wrapper">
';
		echo '		<a href="?page=twobc_formsecurity&tab=settings" class="' . $tab_class_settings . '">'.
			esc_html(__('Settings', '2bc-form-security')) . '</a>
';
		echo '		<a href="?page=twobc_formsecurity&tab=reports" class="' . $tab_class_reports . '">'.
			esc_html(__('Reports', '2bc-form-security')). '</a>
';
		echo '	</h2>
';

		echo '	<h2>' . esc_html(__('2BC Form Security Options', '2bc-form-security')) . '</h2>
';
		echo '	<p>';
		printf(wp_kses_post(__('More help available at the %1$s2BC Form Security documentation page%2$s.',
			'2bc-form-security')),
			'<a href="http://2bcoding.com/plugins/2bc-form-security/2bc-form-security-documentation/" target="_blank">',
			'</a>');
		echo '</p>
';

		// tab logic
		switch ($active_tab) {
			case 'settings' :
				echo '	<div class="twobc_formsecurity_options_settings">
';


				echo '		<form method="post" action="options.php">
';
				// setup form nonces
				settings_fields('twobc_formsecurity_options');

				do_settings_sections('twobc_formsecurity');

				submit_button(esc_html(__('Save all settings', '2bc-form-security')));

				echo '		</form>
';
				echo '	</div>
';


				break;

			case 'reports' :
				echo '	<div class="twobc_formsecurity_options_reports">
';
				echo self::get_reports_screen();

				echo '	</div>
';
				break;

			default :
				echo '	<div class="error"><p>' . wp_kses_post(__('<strong>ERROR:</strong> Invalid tab!',
					'2bc-form-security')) . '</p></div>
';
		}

		echo '</div>
';
	}

	/**
	 * Sanitize settings from options screen
	 *
	 * @param $saved_settings
	 *
	 * @uses get_option_fields
	 *
	 * @return mixed
	 */
	public static function options_sanitize_cb($saved_settings) {
		$settings_errors = array(
			'updated' => false,
			'error' => array(),
		);

		$option_fields = self::get_option_fields();

		// get field names and types
		$known_fields = array();
		foreach ($option_fields as $_section) {
			foreach ($_section['fields'] as $_field_name => $_field_opts) {
				$known_fields[$_field_name] = $_field_opts['type'];
			}
		}

		foreach ( $saved_settings as $setting_key => $setting_val ) {
			// security checks - nonce, capability
			if (
				// check that field is known
				isset($known_fields[$setting_key])
				// check user capabilities
				&& current_user_can('manage_options')
				// check form nonce
				&& check_admin_referer(
					'twobc_formsecurity_options-options' // action
				)
				// check field nonce
				&& (
					!empty($_REQUEST['twobc_formsecurity_nonce_' . $setting_key])
					&& wp_verify_nonce(
						$_REQUEST['twobc_formsecurity_nonce_' . $setting_key], // nonce to verify
						'twobc_formsecurity_nonce_' . $setting_key // custom nonce action
					)
				)

			) {
				$settings_errors['updated'] = true;
				switch ( $known_fields[$setting_key] ) {
					case 'text' :
						$saved_settings[$setting_key] = sanitize_text_field($setting_val);

						if ( 'honeypot_name' == $setting_key )
							$saved_settings[$setting_key] = sanitize_title($saved_settings[$setting_key]);

						break;

					case 'checkbox' :
						$saved_settings[$setting_key] = '1';
						break;

					case 'number' :
						if ( is_numeric($setting_val) ) {
							$saved_settings[$setting_key] = intval($setting_val);
						} else {
							unset($saved_settings[$setting_key]);
						}
						break;

					case 'select' :
					case 'radio' :
						$saved_settings[$setting_key] = sanitize_text_field($setting_val);
						break;

					case 'colorpicker' :
						if ( preg_match('|^#([A-Fa-f0-9]{3}){1,2}$|', $setting_val) )
							$saved_settings[$setting_key] = ($setting_val);
						break;

					default :
						// unknown field type?  Shouldn't happen, but unset to be safe
						unset($saved_settings[$setting_key]);
				}
			} elseif ( // UPDATE - check for recaptcha_valid field
				// check that field is known
				'recaptcha_valid' == $setting_key
				// check user capabilities
				&& current_user_can('manage_options')
				// check form nonce
				&& check_admin_referer(
					'twobc_formsecurity_options-options' // action
				)
				// check field nonce
				&& (
					!empty($_REQUEST['twobc_formsecurity_nonce_recaptcha_valid'])
					&& wp_verify_nonce(
						$_REQUEST['twobc_formsecurity_nonce_recaptcha_valid'], // nonce to verify
						'twobc_formsecurity_nonce_recaptcha_valid' // custom nonce action
					)
				)
			) {
				self::$plugin_options['recaptcha_valid'] = ( !empty($setting_val) ? '1' : '0' );

			} else { // unknown field or security fail, unset to be safe
				unset($saved_settings[$setting_key]);
			}
		}
		// separate validation for un-checked checkboxes
		foreach ( $known_fields as $field_name => $field_type ) {
			if (
				'checkbox' == $field_type
				&& !isset($saved_settings[$field_name])
			) {
				$saved_settings[$field_name] = '0';
				$settings_errors['updated'] = true;
			}
		}

		// register errors
		if ( !empty($settings_errors['errors']) && is_array($settings_errors['errors']) ) {
			foreach ( $settings_errors['errors'] as $error ) {
				add_settings_error(
					'twobc_formsecurity_options', // Slug title of the setting
					'twobc_formsecurity_options_error', // Slug of error
					$error, // Error message
					'error' // Type of error (**error** or **updated**)
				);
			}
		}
		if ( true === $settings_errors['updated'] ) {
			add_settings_error(
				'twobc_formsecurity_options', // Slug title of the setting
				'twobc_formsecurity_options_error', // Slug of error
				esc_html(__('Settings saved.', '2bc-form-security')), // Error message
				'updated' // Type of error (**error** or **updated**)
			);
		}

		// SYSTEM FIELDS
		// recaptcha_valid
		if ( !isset($saved_settings['recaptcha_valid']) )
			$saved_settings['recaptcha_valid'] = self::$plugin_options['recaptcha_valid'];

		// UPDATE - mark activated as false
		$saved_settings['activated'] = false;

		// set plugin version number
		$saved_settings['version'] = self::$plugin_version;

		// update the static class property
		self::$plugin_options = $saved_settings;

		// return the database options to be saved
		return $saved_settings;
	}




	/*************************************************
	 * Admin - Reports
	 ************************************************/
	/**
	 * Initialize or update the report log table
	 */
	public static function init_logs() {
		// add upgrade.php for dbDelta() function
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		global $wpdb;

		$logs_table = "CREATE TABLE {$wpdb->twobc_formsecurity_log} (
id mediumint(9) NOT NULL AUTO_INCREMENT,
user_ip varchar(40) NOT NULL,
register_hp bigint(20) unsigned NOT NULL default 0,
register_rcp_null bigint(20) unsigned NOT NULL default 0,
register_rcp_bad bigint(20) unsigned NOT NULL default 0,
login_hp bigint(20) unsigned NOT NULL default 0,
login_rcp_null bigint(20) unsigned NOT NULL default 0,
login_rcp_bad bigint(20) unsigned NOT NULL default 0,
comment_hp bigint(20) unsigned NOT NULL default 0,
comment_rcp_null bigint(20) unsigned NOT NULL default 0,
comment_rcp_bad bigint(20) unsigned NOT NULL default 0,
total bigint(20) unsigned NOT NULL default 0,
PRIMARY KEY  (id),
KEY user_ip (user_ip)
) {$wpdb->get_charset_collate()};";

		dbDelta($logs_table);

		$undefined_check = $wpdb->get_results(
			"SELECT ID
			FROM $wpdb->twobc_formsecurity_log
			WHERE user_ip = 'undefined'
			"
		);

		if ( empty($undefined_check) ) {

			$db_row = array(
				'user_ip' => 'undefined',
				'register_hp' => 0,
				'register_rcp_null' => 0,
				'register_rcp_bad' => 0,
				'login_hp' => 0,
				'login_rcp_null' => 0,
				'login_rcp_bad' => 0,
				'comment_hp' => 0,
				'comment_rcp_null' => 0,
				'comment_rcp_bad' => 0,
				'total' => 0,
			);

			$db_formats = array(
				'%s',
				'%d',
				'%d',
				'%d',
				'%d',
				'%d',
				'%d',
				'%d',
				'%d',
				'%d',
				'%d',
			);

			$wpdb->insert($wpdb->twobc_formsecurity_log, $db_row, $db_formats);
		}
	}

	/**
	 * Update a row in the log table for reports screen
	 *
	 * @param $args
	 *
	 * @return bool|false|int
	 */
	private static function update_report_log($args) {
		$default_args = array(
			'ip' => 'undefined',
			'location' => '',
			'honeypot' => false,
			'recap_null' => false,
			'recap_bad' => false,
		);

		$args = wp_parse_args($args, $default_args);

		// exit conditions - empty location, or if location isn't valid
		$locations_valid = array(
			'register' => 'register',
			'login' => 'login',
			'comment' => 'comment',
		);
		if ( empty($args['location']) || !isset($locations_valid[$args['location']]) ) {
			// TODO: add error for debug mode
			return false;
		}

		// first, check for existing row
		global $wpdb;
		$row_test = $wpdb->get_results(
			"SELECT *
			FROM $wpdb->twobc_formsecurity_log
			WHERE user_ip = '{$args['ip']}'
			LIMIT 1
			",
			ARRAY_A
		);

		// prepare new db row
		switch ( true ) {
			case (is_array($row_test) && empty($row_test)) :
				// no results - insert new row
				$db_row = array(
					'id' => '',
					'user_ip' => $args['ip'],
					'register_hp' => 0,
					'register_rcp_null' => 0,
					'register_rcp_bad' => 0,
					'login_hp' => 0,
					'login_rcp_null' => 0,
					'login_rcp_bad' => 0,
					'comment_hp' => 0,
					'comment_rcp_null' => 0,
					'comment_rcp_bad' => 0,
					'total' => 0,
				);
				break;

			case (is_array($row_test) && !empty($row_test)) :
				// success, we got something - update
				$db_row = reset($row_test);
				break;

			case (is_null($row_test)) :

			default :
				// TODO: add error for debugging messages
				return false;
		}

		// compute which fields to update
		if ( $args['honeypot'] ) {
			$db_row[$args['location'] . '_hp']++;
		}

		if ( $args['recap_null'] ) {
			$db_row[$args['location'] . '_rcp_null']++;
		}

		if ( $args['recap_bad'] ) {
			$db_row[$args['location'] . '_rcp_bad']++;
		}

		// finally, update or insert the new row
		$db_formats = array(
			'%d',
			'%s',
			'%d',
			'%d',
			'%d',
			'%d',
			'%d',
			'%d',
			'%d',
			'%d',
			'%d',
			'%d',
		);

		// calculate total
		$array_sum = $db_row;
		unset($array_sum['id']);
		unset($array_sum['user_ip']);
		unset($array_sum['total']);
		$db_row['total'] = array_sum($array_sum);

		// use *replace* to update or insert as needed
		$return = $wpdb->replace($wpdb->twobc_formsecurity_log, $db_row, $db_formats);

		return $return;
	}

	/**
	 * Get HTML display for the reports tab
	 *
	 * @uses get_pagination_buttons
	 *
	 * @return string
	 */
	private static function get_reports_screen() {
		$return = '';
		global $wpdb;

		$return .= '	<h3>' . esc_html(__('Total', '2bc-form-security')) . '</h3>
';

		$total = $wpdb->get_var(
			"SELECT sum(total)
			FROM $wpdb->twobc_formsecurity_log"
		);

		$return .= '	<ul>
';
		$return .= '		<li>' . esc_html(__('Security checks failed:', '2bc-form-security')) . ' <span>' .
			number_format($total) . '</span></li>
';
		$return .= '	</ul>
';

		$return .= '	<h3>' . esc_html(__('Security Methods', '2bc-form-security')) . '</h3>
';

		$security_methods = array(
			'honeypot' => $wpdb->get_var(
			"SELECT sum(register_hp+login_hp+comment_hp)
			FROM $wpdb->twobc_formsecurity_log"
			),
			'recap_null' => $wpdb->get_var(
				"SELECT sum(register_rcp_null+login_rcp_null+comment_rcp_null)
			FROM $wpdb->twobc_formsecurity_log"
			),
			'recap_bad' => $wpdb->get_var(
				"SELECT sum(register_rcp_bad+login_rcp_bad+comment_rcp_bad)
			FROM $wpdb->twobc_formsecurity_log"
			),
		);

		$return .= '	<ul>
';

		$return .= '		<li>' . esc_html(__('Honeypot failures:', '2bc-form-security')) . ' <span>' .
			number_format($security_methods['honeypot']) . '</span></li>
';
		$return .= '		<li>' . esc_html(__('Google reCAPTCHA &ndash; empty:', '2bc-form-security')) .
			' <span>' . number_format($security_methods['recap_null']) . '</span></li>
';
		$return .= '		<li>' . esc_html(__('Google reCAPTCHA &ndash; failures:', '2bc-form-security')) .
			' <span>' . number_format($security_methods['recap_bad']) . '</span></li>
';

		$return .= '	</ul>
';

		$return .= '	<h3>' . esc_html(__('Locations', '2bc-form-security')) . '</h3>
';

		$locations = array(
			'register' => $wpdb->get_var(
				"SELECT sum(register_hp+register_rcp_null+register_rcp_bad)
			FROM $wpdb->twobc_formsecurity_log"
			),
			'login' => $wpdb->get_var(
				"SELECT sum(login_hp+login_rcp_null+login_rcp_bad)
			FROM $wpdb->twobc_formsecurity_log"
			),
			'comment' => $wpdb->get_var(
				"SELECT sum(comment_hp+comment_rcp_null+comment_rcp_bad)
			FROM $wpdb->twobc_formsecurity_log"
			),
		);

		$return .= '	<ul>
';

		$return .= '		<li>' . esc_html(__('Registration form failures:', '2bc-form-security')) .
			' <span>' . number_format($locations['register']) . '</span></li>
';
		$return .= '		<li>' . esc_html(__('Login form failures:', '2bc-form-security')) .
			' <span>' . number_format($locations['login']) . '</span></li>
';
		$return .= '		<li>' . esc_html(__('Comment form failures:', '2bc-form-security')) .
			' <span>' . number_format($locations['comment']) . '</span></li>
';

		$return .= '	</ul>
';

		$return .= '	<h3 id="twobc_formsecurity_reports_table">' . esc_html(__('Log', '2bc-form-security')) . self::get_clear_logs_button() . '</h3>
';

		$page_num = (!empty($_REQUEST['page_num']) ? intval($_REQUEST['page_num']) : 1);
		$page_limit = 500;
		$id_start = ($page_limit * $page_num) - $page_limit;

		$table_rows = $wpdb->get_results(
			"SELECT *
			FROM $wpdb->twobc_formsecurity_log
			WHERE id > $id_start
			ORDER BY id ASC
			LIMIT $page_limit",
			ARRAY_A
		);

		$return .= self::get_pagination_buttons($page_num, count($table_rows));

		$return .= '	<table>
	<thead>
		<tr>
';
		$return .= '			<th class="col_id">' . esc_html(__('ID', '2bc-form-security')) . '</th>
';
		$return .= '			<th class="col_ip">' . esc_html(__('IP Address', '2bc-form-security')) . '</th>
';
		$return .= '			<th class="col_register">' . esc_html(__('Registration', '2bc-form-security')) . '</th>
';
		$return .= '			<th class="col_login">' . esc_html(__('Login', '2bc-form-security')) . '</th>
';
		$return .= '			<th class="col_comment">' . esc_html(__('Comment', '2bc-form-security')) . '</th>
';
		$return .= '			<th class="col_hp">' . esc_html(__('Honeypots', '2bc-form-security')) . '</th>
';
		$return .= '			<th class="col_rcp_null">' . esc_html(__('reCAPTCHA | null', '2bc-form-security')) . '</th>
';
		$return .= '			<th class="col_rcp_bad">' . esc_html(__('reCAPTCHA | bad', '2bc-form-security')) . '</th>
';
		$return .= '			<th class="col_total">' . esc_html(__('Total', '2bc-form-security')) . '</th>
';

		$return .= '		</tr>
	</thead>
	<tbody>
';

		if ( !empty($table_rows) ) {
			$last_key = count($table_rows) - 1;

			foreach ($table_rows as $_key => $_row_vars) {
				$return .= '	<tr class="';
				$classes = '';
				if ( 0 == $_key ) {
					$classes .= 'first';
				}

				if ( $last_key == $_key ) {
					if ( !empty($classes) ) {
						$classes .= ' ';
					}
					$classes .= 'last';
				}

				if ( !empty($classes) ) {
					$classes .= ' ';
				}

				$classes .= ( $_row_vars['id'] & 1 ? 'odd' : 'even' );

				$return .= $classes;

				$return .= '">
';
				$return .= '		<td class="col_id">' . $_row_vars['id'] . '</td>
';

				$return .= '		<td class="col_ip">' . $_row_vars['user_ip'] . '</td>
';
				$return .= '		<td class="col_register">' .
					number_format($_row_vars['register_hp'] + $_row_vars['register_rcp_null'] + $_row_vars['register_rcp_bad']) .
					'</td>
';
				$return .= '		<td class="col_login">' .
					number_format($_row_vars['login_hp'] + $_row_vars['login_rcp_null'] + $_row_vars['login_rcp_bad']) .
					'</td>
';
				$return .= '		<td class="col_comment">' .
					number_format($_row_vars['comment_hp'] + $_row_vars['comment_rcp_null'] + $_row_vars['comment_rcp_bad']) .
					'</td>
';

				$return .= '		<td class="col_hp">' .
					number_format($_row_vars['register_hp'] + $_row_vars['login_hp'] + $_row_vars['comment_hp']) .
					'</td>
';
				$return .= '		<td class="col_rcp_null">' .
					number_format($_row_vars['register_rcp_null'] + $_row_vars['login_rcp_null'] + $_row_vars['comment_rcp_null']) .
					'</td>
';
				$return .= '		<td class="col_rcp_bad">' .
					number_format($_row_vars['register_rcp_bad'] + $_row_vars['login_rcp_bad'] + $_row_vars['comment_rcp_bad']) .
					'</td>
';
				$return .= '		<td class="col_total">' .
					number_format($_row_vars['total']) .
					'</td>
';


				$return .= '	</tr>
';
			}
		}

		$return .= '	</tbody>
</table>

';

		$return .= self::get_pagination_buttons($page_num, count($table_rows));

		return $return;
	}

	/**
	 * Get the HTML markup for the Clear Logs button
	 *
	 * @return string
	 */
	private static function get_clear_logs_button() {
		$return = '<button type="button" class="twobc_formsecurity_clear_log_btn">' .
			esc_html(__('Clear Log', '2bc-form-security')) . '</button>
';

		return $return;
	}

	/**
	 * Get the pagination buttons for the reports screen based on arguments
	 *
	 * @param $page_num
	 * @param $count
	 * @param int $per_page
	 *
	 * @return string
	 */
	private static function get_pagination_buttons($page_num, $count, $per_page = 500) {
		$admin_url = self::get_admin_url(
			'/options-general.php', // path
			array ( // query args
				'page' => 'twobc_formsecurity',
				'tab' => 'reports',
			)
		);

		$prev = '<a href="' . add_query_arg(array('page_num' => $page_num-1), $admin_url) .
			'#twobc_formsecurity_reports_table" class="twobc_formsecurity_page_prev">' .
			wp_kses_post(__('&laquo; Previous Page ', '2bc-form-security')) . '</a>
';
		$next = '<a href="' . add_query_arg(array('page_num' => $page_num+1), $admin_url) .
			'#twobc_formsecurity_reports_table" class="twobc_formsecurity_page_next">' .
			wp_kses_post(__('Next Page &raquo;', '2bc-form-security')) . '</a>
';

		switch (true) {
			// no pages to display
			case ( 1 == $page_num && $count < $per_page ) :
				$return = '';
				break;

			// first page
			case ( 1 == $page_num && $count == $per_page ) :
				$return = $next;
				break;

			// last page
			case ( $count < $per_page ) :
				$return = $prev;
				break;

			// all other pages
			default :
				$return = $prev . $next;
		}

		return $return;
	}

	function hook_admin_notices() {
		global $current_screen;

		if (
			!empty($current_screen)
			&& 'settings_page_twobc_formsecurity' == $current_screen->base
			&& !empty($_GET['tab'])
			&& 'reports' == $_GET['tab']
			&& empty(self::$plugin_options['enable_reporting'])
		) {
			echo '<div class="error">
	<p>';
			echo wp_kses_post(__(
'<strong>ERROR:</strong> Reporting is not currently enabled! Click the Settings tab to turn reporting on.',
				'2bc-form-security'));
			echo '</p>
</div>
';
		}

		// UPDATE - check for plugin activation
		if (
			self::$plugin_options['activated']
			&& ( !empty($current_screen) && 'settings_page_twobc_formsecurity' != $current_screen->base )
		) {
			echo '<div class="updated">
	<p>';
			$admin_url = self::get_admin_url(
				'/options-general.php', // path
				array( // query args
					'page' => 'twobc_formsecurity',
				)
			);
			printf(wp_kses_post(__(
'<strong>2BC Form Security</strong> has been activated! Visit the %1$sSettings Page%2$s to activate reCAPTCHA. Save the settings to dismiss this message.',
				'2bc-form-security')), '<a href="' . $admin_url . '">', '</a>');
			echo '</p>
</div>
';
		}
	}


	/*************************************************
	 * Form Utilities
	 ************************************************/
	/**
	 * Get HTML markup for the recaptcha div
	 *
	 * @param bool $noscript - return the additional noscript tag for non-javascript cases
	 *
	 * @return string
	 */
	public static function get_recaptcha_div($noscript = true, $error_wrap = false) {
		$return = '';

		if (
			self::recaptcha_has_args()
			&& !empty(self::$plugin_options['enable_recaptcha'])
			&& !empty(self::$plugin_options['recaptcha_valid'])
		) {
			if ( $error_wrap ) {
				/* $return .=
'<input type="text" name="twobcfs_error_wrap" value="" style="height: 0; width: 0; border: none; font-size: 0; line-height: 0; padding: 0; margin: 0;">'; */
			}

			$return .= '<div class="g-recaptcha" data-sitekey="' . esc_attr(self::$plugin_options['site_key']) .
				'" data-theme="' . self::$plugin_options['recaptcha_theme'] . '"></div>';

			if ( !empty($noscript) ) {
				$return .= '<noscript>
  <div style="width: 302px; height: 352px;">
    <div style="width: 302px; height: 352px; position: relative;">
      <div style="width: 302px; height: 352px; position: absolute;">
        <iframe src="https://www.google.com/recaptcha/api/fallback?k=' . self::$plugin_options['site_key'] . '"
                frameborder="0" scrolling="no"
                style="width: 302px; height:352px; border-style: none;">
        </iframe>
      </div>
      <div style="width: 250px; height: 80px; position: absolute; border-style: none;
                  bottom: 21px; left: 25px; margin: 0px; padding: 0px; right: 25px;">
        <textarea id="g-recaptcha-response" name="g-recaptcha-response"
                  class="g-recaptcha-response"
                  style="width: 250px; height: 80px; border: 1px solid #c1c1c1;
                         margin: 0px; padding: 0px; resize: none;" value="">
        </textarea>
      </div>
    </div>
  </div>
</noscript>';
			}
		}

		return $return;
	}

	/**
	 * Get the HTML markup for the honeypot
	 *
	 * @return string
	 */
	public static function get_honeypot() {
		$return = '<input type="text" class="' . self::$honeypot_name . '" name="' .
			self::$honeypot_name . '[]" value="" autocomplete="off" />
';

		return $return;
	}

	/**
	 * Get the CSS necessary to hide the honeypot
	 *
	 * @return string
	 */
	private static function get_honeypot_css() {
		$return = '<style type="text/css">';
		$return .= '.' . self::$honeypot_name . '{position:absolute;top:-999px;left:-999px;}';
		$return .= '</style>
';

		return apply_filters('twobcfs_hp_css', $return);
	}

	/**
	 * Validate the security fields according to which location is being called
	 *
	 * @param $location
	 *
	 * @return bool|int
	 */
	private static function validate_security_fields($location) {
		// exit conditions - location must be present
		if ( empty($location) )
			return -1;

		$return = true;

		// init reporting array
		$reporting = array(
			'ip' => ( !empty(self::$plugin_options['record_ips']) && self::validate_ip($_SERVER['REMOTE_ADDR'] ) ?
				$_SERVER['REMOTE_ADDR'] : 'undefined'),
			'location' => $location,
			'honeypot' => false,
			'recap_null' => false,
			'recap_bad' => false,
		);

		// check the honeypots
		/* $hp_name = ( empty(self::$plugin_options['honeypot_name'] ) ?
			self::$honeypot_name : self::$plugin_options['honeypot_name']); */
		if (
			!empty($_REQUEST[self::$honeypot_name])
			&& is_array($_REQUEST[self::$honeypot_name])
		) {
			// remove empty values, but leave strings of 0
			$honeypots = array_filter($_REQUEST[self::$honeypot_name], 'strlen');

			if ( !empty($honeypots) ) {
				$return = false;
				$reporting['honeypot'] = true;
			}
		} else { // fields changed or missing, throw an error
			$return = false;
			$reporting['honeypot'] = true;
		}

		// check for valid reCAPTCHA response
		if (
			!empty(self::$plugin_options['enable_recaptcha'])
			&& self::recaptcha_has_args()
			&& self::$plugin_options['recaptcha_valid']
			) {
			if ( empty($_REQUEST['g-recaptcha-response']) ) {
				$return = false;
				$reporting['recap_null'] = true;
			} else {
				// make request to Google API to check response
				$http_args = array(
					'method' => 'POST',
					'body' => array(
						'secret' => self::$plugin_options['secret_key'],
						'response' => $_REQUEST['g-recaptcha-response'],
					),
				);

				if ( !empty(self::$plugin_options['record_ips']) && self::validate_ip($_SERVER['REMOTE_ADDR']) )
					$http_args['body']['remoteip'] = $_SERVER['REMOTE_ADDR'];

				$google_response = wp_remote_retrieve_body(wp_remote_post(self::$google_verify_url, $http_args));

				if ( !is_wp_error($google_response) ) {
					$google_response = json_decode($google_response, true);

					if ( empty($google_response['success']) ) {
						// detect bad sitekey error
						if ( !empty($google_response['error-codes']) ) {
							if (
								false === strpos($google_response['error-codes'], 'missing-input-secret')
								&& false === strpos($google_response['error-codes'], 'invalid-input-secret')
							) {
								// must be bad response, return false
								$return = false;
								$reporting['recap_bad'] = true;
							} else {
								// site key is invalid despite api check
								// make sure to disable
								self::$plugin_options['recaptcha_valid'] = '0';
								self::$plugin_options['enable_recaptcha'] = '0';
								update_option('twobc_formsecurity_options', self::$plugin_options);
							}
						}
					}
				} // TODO: Add debugging messages
			}
		}

		// UPDATE - trigger log update
		if ( !empty(self::$plugin_options['enable_reporting']) ) {
			self::update_report_log($reporting);
		}

		return $return;
	}


	/*************************************************
	 * Login Form
	 ************************************************/
	/**
	 * login_enqueue_scripts hook - load captcha api and plugin style
	 */
	public static function hook_login_enqueue_scripts() {
		if (
			!empty(self::$plugin_options['display_login'])
			&& !empty(self::$plugin_options['enable_recaptcha'])
			&& !empty(self::$plugin_options['recaptcha_valid'])
		) {
			wp_enqueue_script('twobc_formsecurity_captcha');

			wp_enqueue_style('twobc_formsecurity_captcha_style');

		}
	}

	/**
	 * login_head hook - display honeypot CSS
	 */
	public static function hook_login_head() {
		echo self::get_honeypot_css();
	}

	/**
	 * login_form hook - get the reCAPTCHA and Honeypot markup for the login form
	 */
	public static function hook_login_form() {
		// honeypot
		echo self::get_honeypot();

		// recaptcha
		if (
			!empty(self::$plugin_options['display_login'])
			&& !empty(self::$plugin_options['enable_recaptcha'])
			&& !empty(self::$plugin_options['recaptcha_valid'])
		) {
			echo self::get_recaptcha_div();
		}
	}

	/**
	 * wp_authenticate_user hook - validate security fields and return appropriate error
	 *
	 * @param $user_maybe
	 * @param $password
	 *
	 * @return WP_Error|WP_User
	 */
	public static function hook_wp_authenticate_user($user_maybe, $password) {
		$validated = false;

		// only deal with users who have entered all details
		if ( $user_maybe instanceof WP_User ) {
			$validated = self::validate_security_fields('login');

			if ( !$validated )
				$user_maybe = self::get_plugin_error('twobc_form_security');
		}

		return $user_maybe;
	}


	/*************************************************
	 * Registration Form
	 ************************************************/
	/**
	 * register_form hook - get reCAPTCHA and Honeypot markup for the registration form
	 */
	public static function hook_register_form() {
		// honeypot
		echo self::get_honeypot();

		// recaptcha
		if (
			!empty(self::$plugin_options['display_register'])
			&& !empty(self::$plugin_options['enable_recaptcha'])
			&& !empty(self::$plugin_options['recaptcha_valid'])
		) {
			echo self::get_recaptcha_div();
		}
	}

	/**
	 * registration_errors hook - validate security fields on registration form
	 *
	 * @param $errors
	 * @param $sanitized_user_login
	 * @param $user_email
	 *
	 * @return mixed
	 */
	public static function hook_registration_errors($errors, $sanitized_user_login, $user_email) {
		$validated = self::validate_security_fields('register');

		if ( !$validated && !isset($errors->errors['twobc_form_security']) ) {
				$errors->add('twobc_form_security',
					wp_kses_post(__('<strong>ERROR:</strong> Security checks failed', '2bc-form-security')));
		}

		return $errors;
	}

	/*************************************************
	 * Comment Form
	 ************************************************/
	/**
	 * wp_enqueue_scripts hook - add reCAPTCHA api and plugin styling
	 */
	public static function hook_wp_enqueue_scripts() {
		// WP Comments
		if (
			(!empty(self::$plugin_options['display_comment']) && comments_open())
			&& !empty(self::$plugin_options['enable_recaptcha'])
			&& !empty(self::$plugin_options['recaptcha_valid'])
		) {
			wp_enqueue_script('twobc_formsecurity_captcha');

			wp_enqueue_style(
				'twobc_formsecurity_style', // handle
				self::$plugin_url . 'includes/css/2bc-form-security.css', // URL src
				array(), // dependencies
				self::$plugin_version, // version
				false // in footer
			);
		}


	}

	/**
	 * wp_head hook - display honeypot CSS when comment form is going to be present
	 */
	public static function hook_wp_head() {
		if ( comments_open() ) {
			echo self::get_honeypot_css();
		}
	}

	/**
	 * comment_form hook - display reCAPTCHA and Honeypot markup on the comment form
	 */
	public static function hook_comment_form() {
		// honeypot
		echo self::get_honeypot();

		if (
			!empty(self::$plugin_options['display_comment'])
			&& !empty(self::$plugin_options['enable_recaptcha'])
			&& !empty(self::$plugin_options['recaptcha_valid'])
		) {
			echo self::get_recaptcha_div();
		}
	}

	/**
	 * Validate security fields on comment form and put comment into appropriate queue if it failed
	 *
	 * @param $approved
	 * @param $commentdata
	 *
	 * @return int|string
	 */
	public static function hook_pre_comment_approved($approved, $commentdata) {
		$failed_comment_status = ( 'zero' == self::$plugin_options['comment_status'] ? 0 : 'spam' );

		$approved = ( self::validate_security_fields('comment') ? 1 : $failed_comment_status );

		return $approved;
	}

	/*************************************************
	 * Error Handling
	 ************************************************/

	/**
	 * Filter login errors and rewrite any specific errors to a generic error
	 *
	 * Used to try to prevent hackers from learning valid usernames
	 *
	 * @param $errors
	 * @param $redirect_to
	 *
	 * @return WP_Error
	 */
	public static function hook_wp_login_errors($errors, $redirect_to) {
		if ( !empty($errors->errors) ) {
			switch ( self::$plugin_options['login_errors'] ) {
				case 'generic_errors' :
					// selectively replace errors
					$break = false;
					foreach ( $errors->errors as $_error_code => $_error_vals ) {
						switch ( $_error_code ) {
							case 'empty_password' :
							case 'empty_username' :
							case 'invalid_username' :
							case 'incorrect_password' :
							case 'twobc_form_security' :
								if ( !isset($errors->errors['generic_login_error']) ) {
									$errors = self::get_plugin_error('generic_login_error');
									$break = true;
								}

								break;


							default :
						}

						// break out of loop if we've replaced with generic error
						if ( $break )
							break;
					}
					break;

				case 'formsec_error' :
				default :
			}
		}

		return $errors;
	}

	/*************************************************
	 * Dashboard Widget
	 ************************************************/
	/**
	 * wp_dashboard_setup hook - setup plugin widget on dashboard
	 */
	public static function hook_wp_dashboard_setup() {
		if ( current_user_can('manage_options') ) {
			wp_add_dashboard_widget(
				'twobc_formsecurity_admin_widget', // widget slug
				esc_html(__('2BC Form Security Summary', '2bc-form-security')), // widget title
				array(self::get_instance(), 'admin_widget_display_cb') // display callback

			);
		}
	}

	/**
	 * Dashboard widget display callback
	 */
	public static function admin_widget_display_cb() {
		$output = '';
		global $wpdb;
		$admin_url = self::get_admin_url(
			'/options-general.php', // path
			array( // query args
				'page' => 'twobc_formsecurity',
			)
		);

		$output .= '<div class="twobc_formsecurity_widget_col col_1"
 style="display:inline-block;width:47%;padding:0 1%;vertical-align:top;">
';

		$output .= '	<h4><a href="' . add_query_arg(array('tab' => 'reports'), $admin_url) . '">'
			. esc_html(__('Report Summary', '2bc-form-security')) . '</a></h4>
';

		$total = $wpdb->get_var(
			"SELECT sum(total)
			FROM $wpdb->twobc_formsecurity_log"
		);
		$output .= '	<ul class="first_instance">
';
		$output .= '		<li>' . sprintf(wp_kses_post(__('Total events: %s', '2bc-form-security')), '<span>'
			. number_format($total)) . '</span></li>
';
		$output .= '	</ul>
';

		$output .= '	<ul>
';

		$honeypot = $wpdb->get_var(
			"SELECT sum(register_hp+login_hp+comment_hp)
			FROM $wpdb->twobc_formsecurity_log"
		);

		$output .= '		<li>' . sprintf(wp_kses_post(__('Honeypot events: %s', '2bc-form-security')), '<span>'
			. number_format($honeypot)) . '</span></li>
';

		$recapt_null = $wpdb->get_var(
			"SELECT sum(register_rcp_null+login_rcp_null+comment_rcp_null)
			FROM $wpdb->twobc_formsecurity_log"
		);

		$output .= '		<li>' . sprintf(wp_kses_post(__('reCAPTCHA | null: %s', '2bc-form-security')), '<span>'
			. number_format($recapt_null)) . '</span></li>
';

		$recapt_bad = $wpdb->get_var(
			"SELECT sum(register_rcp_bad+login_rcp_bad+comment_rcp_bad)
			FROM $wpdb->twobc_formsecurity_log"
		);
		$output .= '		<li>' . sprintf(wp_kses_post(__('reCAPTCHA | bad: %s', '2bc-form-security')), '<span>'
			. number_format($recapt_bad)) . '</span></li>
';

		$output .= '	</ul>
';

		$output .= '	<ul class="last_instance">
';

		$reg_form = $wpdb->get_var(
			"SELECT sum(register_hp+register_rcp_null+register_rcp_bad)
			FROM $wpdb->twobc_formsecurity_log"
		);
		$output .= '		<li>' . sprintf(wp_kses_post(__('Registration form: %s', '2bc-form-security')), '<span>'
			. number_format($reg_form)) . '</span></li>';

		$login_form = $wpdb->get_var(
			"SELECT sum(login_hp+login_rcp_null+login_rcp_bad)
			FROM $wpdb->twobc_formsecurity_log"
		);
		$output .= '		<li>' . sprintf(wp_kses_post(__('Login form: %s', '2bc-form-security')), '<span>'
			. number_format($login_form)) . '</span></li>
';

		$comment_form = $wpdb->get_var(
			"SELECT sum(comment_hp+comment_rcp_null+comment_rcp_bad)
			FROM $wpdb->twobc_formsecurity_log"
		);
		$output .= '		<li>' . sprintf(wp_kses_post(__('Comment form: %s', '2bc-form-security')), '<span>'
			. number_format($comment_form)) . '</span></li>
';
		$output .= '	</ul>
';
		$output .= '</div>
';

		$output .= '<div class="twobc_formsec_widget_col col_2" style="display:inline-block;width:47%;padding:0 1%;vertical-align:top;">
';
		$output .= '	<h4><a href="' . add_query_arg(array('tab' => 'settings'), $admin_url)
			. '">' . esc_html(__('Current Settings', '2bc-form-security')) . '</a></h4>
';
		$output .= '		<ul>
';

		$i18n_yes = esc_html(__('Yes', '2bc-form-security'));
		$i18n_no = esc_html(__('No', '2bc-form-security'));

		$widget_settings = array(
			'enable_recaptcha' => sprintf(wp_kses_post(__('reCAPTCHA enabled: %s', '2bc-form-security')), '<span>' .
				(  !empty(self::$plugin_options['enable_recaptcha']) ? $i18n_yes : $i18n_no )) . '</span>',
			'recaptcha_valid' => sprintf(wp_kses_post(__('reCAPTCHA valid: %s', '2bc-form-security')), '<span>' .
				(  !empty(self::$plugin_options['recaptcha_valid']) ? $i18n_yes : $i18n_no )) . '</span>',
			'display_register' => sprintf(wp_kses_post(__('Register form: %s', '2bc-form-security')), '<span>' .
				(  !empty(self::$plugin_options['display_register']) ? $i18n_yes : $i18n_no )) . '</span>',
			'display_login' => sprintf(wp_kses_post(__('Login form: %s', '2bc-form-security')), '<span>' .
				(  !empty(self::$plugin_options['display_login']) ? $i18n_yes : $i18n_no )) . '</span>',
			'display_comment' => sprintf(wp_kses_post(__('Comment form: %s', '2bc-form-security')), '<span>' .
				(  !empty(self::$plugin_options['display_comment']) ? $i18n_yes : $i18n_no )) . '</span>',
			'record_ips' => sprintf(wp_kses_post(__('Record IPs: %s', '2bc-form-security')), '<span>' .
				(  !empty(self::$plugin_options['record_ips']) ? $i18n_yes : $i18n_no )) . '</span>',
			'enable_reporting' => sprintf(wp_kses_post(__('Reporting Enabled: %s', '2bc-form-security')), '<span>'
				. (  !empty(self::$plugin_options['enable_reporting']) ? $i18n_yes : $i18n_no )) . '</span>',
		);

		foreach ($widget_settings as $_setting_name => $_setting_title) {
			$output .= '			<li class="' . ( !empty(self::$plugin_options[$_setting_name]) ? 'indicate_yes' : 'indicate_no' ) . '">' . $_setting_title . '</li>
';
		}

		$output .= '		</ul>
';
		$output .= '</div>
';
		echo $output;
	}

	/*************************************************
	 * Plugin Utilities
	 ************************************************/

	/**
	 * Verify reCAPTCHA has the necessary options set
	 *
	 * @return bool
	 */
	private static function recaptcha_has_args() {
		if ( !empty(self::$plugin_options['site_key']) && !empty(self::$plugin_options['secret_key']) ) {
			$return = true;
		} else {
			$return = false;
		}

		return $return;
	}

	/**
	 * Ensures an IP address is both valid, and does not fall within a private network range.
	 *
	 * @url http://stackoverflow.com/questions/1634782/what-is-the-most-accurate-way-to-retrieve-a-users-correct-ip-address-in-php?rq=1
	 *
	 * @param $ip string | IPv4 or IPv6 address
	 *
	 * @return bool
	 */
	private static function validate_ip($ip) {
		if ( false === filter_var($ip, FILTER_VALIDATE_IP,
			FILTER_FLAG_IPV4 |
				FILTER_FLAG_IPV6 |
				FILTER_FLAG_NO_PRIV_RANGE |
				FILTER_FLAG_NO_RES_RANGE)
		) {
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Get a WordPress error that contains custom error messages from 2BC Form Security
	 *
	 * Building in the ability to return the incorrect_password error, so that other security plugins that check
	 *    for this error will continue to function
	 *
	 * @param $type string | twobc_form_security, incorrect_password, generic_login_error
	 *
	 * @return WP_Error
	 */
	private static function get_plugin_error($type) {
		$translate_security = wp_kses_post(__('<strong>ERROR:</strong> Security checks failed.', '2bc-form-security'));
		$translate_generic = wp_kses_post(__('<strong>ERROR:</strong> Login failed.'));

		switch ( $type ) {
			case 'twobc_form_security' :
				$returned_error = new WP_Error('twobc_form_security', $translate_security);
				break;

			case 'incorrect_password' :
				$returned_error = new WP_Error('incorrect_password', $translate_security);
				break;

			case 'generic_login_error' :
				$returned_error = new WP_Error('generic_login_error', $translate_generic);
				break;


			default :
				$returned_error = new WP_Error();
		}

		return $returned_error;
	}

	/**
	 * Leverages WordPress's sanitize_html_class, while also ensuring that the class name does not
	 * 	begin with a number.  This is accomplished by spelling out the number, if appropriate
	 *
	 * @param $class
	 *
	 * @return mixed|string
	 */
	private static function sanitize_html_class($class) {
		$class = sanitize_html_class($class);

		$first_char = substr($class, 0, 1);

		if ( is_numeric($first_char) ) {
			// replace first digit with phonetic spelling of number
			switch ( $first_char ) {
				case '0' :
					$replacement = 'zero';
					break;

				case '1' :
					$replacement = 'one';
					break;

				case '2' :
					$replacement = 'two';
					break;

				case '3' :
					$replacement = 'three';
					break;

				case '4' :
					$replacement = 'four';
					break;

				case '5' :
					$replacement = 'five';
					break;

				case '6' :
					$replacement = 'six';
					break;

				case '7' :
					$replacement = 'seven';
					break;

				case '8' :
					$replacement = 'eight';
					break;

				case '9' :
					$replacement = 'nine';
					break;

				default :
					// we can't handle numbers that don't exist... what, like imaginary numbers? not sure I need this
					return $class;
			}

			$class = substr_replace($class, $replacement, 0, 1);

		}

		return $class;
	}

	/**
	 * Get correct admin URL, works with of multi-site
	 * Optionally add query args to URL
	 *
	 * @param string $path
	 * @param array $query_args
	 * @return string
	 */
	private static function get_admin_url($path = '', $query_args = array()) {
		$admin_url = ( !is_multisite() ? admin_url($path) : network_admin_url($path) );

		if ( !empty($query_args) && is_array($query_args) )
			$admin_url = add_query_arg($query_args, $admin_url);

		return $admin_url;
	}

	/**
	 * Reset the report log via an AJAX request
	 */
	public static function ajax_reset_report_log() {
		// verify ajax nonce
		check_ajax_referer('twobc-formsecurity-ajaxnonce');
		// verify user permissions
		if ( !current_user_can('manage_options') )
			die(__('You do not have sufficient permissions to access this page.', '2bc-form-security'));

		// proceed with clearing logs
		global $wpdb;
		$wpdb->query(
			"DROP TABLE IF EXISTS $wpdb->twobc_formsecurity_log"
		);

		self::init_logs();

		die();
	}

	/**
	 * Verify the API keys by calling Google API
	 * Called from the server through an AJAX request so that Google will respond correctly
	 */
	public static function ajax_recaptcha_verify_api() {
		// verify ajax nonce
		check_ajax_referer('twobc-formsecurity-ajaxnonce');
		// verify user permissions
		if ( !current_user_can('manage_options') )
			die(esc_html(__('You do not have sufficient permissions to access this page.', '2bc-form-security')));

		$site_key = ( !empty($_REQUEST['twobcfs_site_key']) ? esc_attr($_REQUEST['twobcfs_site_key']) : false );
		$secret_key = ( !empty($_REQUEST['twobcfs_secret_key']) ? esc_attr($_REQUEST['twobcfs_secret_key']) : false );

		if ( empty($site_key) || empty($secret_key) )
			die(esc_html(__('Google reCAPTCHA API keys missing or invalid.', '2bc-form-security')));

		$response = ( !empty($_REQUEST['twobcfsRecaptchaResponse']) ?
			esc_attr($_REQUEST['twobcfsRecaptchaResponse']) : false );

		if ( empty($response) )
			die(esc_html(__('Google reCAPTCHA not clicked.', '2bc-form-security')));

		if ( empty($secret_key) )
			die(esc_html(__('Could not get secret key', '2bc-form-security')));

		$http_args = array(
			'method' => 'POST',
			'body' => array(
				'secret' => $secret_key,
				'response' => $response,
			),
		);

		$return = wp_remote_retrieve_body(wp_remote_post(self::$google_verify_url, $http_args));

		if ( is_wp_error($return) ) {
			$return = $return->get_error_message();
		}

		// cleaning out any notices or errors from debug mode
		ob_clean();
		die($return);
	}

	/*************************************************
	 * BuddyPress
	 ************************************************/
	/**
	 * Add BuddyPress functionality - Registration Form
	 */

	/**
	 * BuddyPress flag
	 */
	public static function hook_buddypress() {
		self::$buddypress = true;
	}

	/**
	 * Honeypot CSS
	 */
	public static function hook_bp_head() {
		if ( function_exists('bp_is_register_page') && bp_is_register_page() ) {
			echo self::get_honeypot_css();
		}
	}

	/**
	 * Enqueue CAPTCHA style and script
	 */
	public static function hook_bp_enqueue_scripts() {
		if (
			(function_exists('bp_is_register_page') && bp_is_register_page())
			&& !empty(self::$plugin_options['display_register'])
			&& !empty(self::$plugin_options['enable_recaptcha'])
			&& !empty(self::$plugin_options['recaptcha_valid'])
		) {
			wp_enqueue_script('twobc_formsecurity_captcha');
			wp_enqueue_style('twobc_formsecurity_captcha_style');
		}
	}

	/**
	 * Add security fields after submit button
	 */
	public static function hook_bp_after_submit() {
		// honeypot
		echo self::get_honeypot();

		if ( !empty(self::$plugin_options['display_register']) ) {
			echo self::get_recaptcha_div(true, true);
		}
	}

	/**
	 * Check security fields
	 */
	public static function hook_bp_signup_validate() {

		if ( false === self::validate_security_fields('register') ) {
			global $bp;
			$bp->signup->errors['signup_username'] = __('Security checks failed.', '2bc-form-security');
		}
	}

} // end of class 2bc_form_security
