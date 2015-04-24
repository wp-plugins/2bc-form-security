<?php
/**
 * Handle plugin uninstall
 *
 * @see http://codex.wordpress.org/Function_Reference/register_uninstall_hook
 *
 * @package WordPress
 * @subpackage 2BC-Form-Security
 */

//if uninstall not called from WordPress - exit
if ( !defined('WP_UNINSTALL_PLUGIN') )
	exit();

$option_group_name = 'twobc_formsecurity_options';

global $wpdb;
$log_table = $wpdb->prefix . 'twobc_formsecurity_log';

// remove options for single site
delete_option($option_group_name);

// remove options for multi-site
delete_site_option($option_group_name);

// drop log table
$wpdb->query(
	"DROP TABLE IF EXISTS $log_table"
);
