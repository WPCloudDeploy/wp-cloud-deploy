<?php
/**
 * Trait:
 * Contains functions that check if ssh was successful as well as replacing tokens in the scripts before they are sent to the server.
 * Used only by the class-wordpress.php file which defines the
 * WPCD_WORDPRESS_APP class.
 *
 * @package wpcd
 */

/**
 * Trait wpcd_wpapp_script_handlers
 */
trait wpcd_wpapp_script_handlers {

	/**
	 * This interprets the ssh result and reduces to a boolean value to indicate if a command succeeded or failed.
	 *
	 * @param string $result result.
	 * @param string $command command.
	 * @param string $action action.
	 */
	public function is_ssh_successful( $result, $command, $action = '' ) {

		// If $result is not a string, return false since we can't do a proper compare to anything.
		if ( ! is_string( $result ) ) {
			return false;
		}

		switch ( $command ) {
			case 'disable_remove_site.txt':
				$return =
				( strpos( $result, ' has been ' ) !== false )
				||
				( strpos( $result, ' local backups have been ' ) !== false );
				break;
			case 'manage_https.txt':
				$return =
				( strpos( $result, 'SSL has been ' ) !== false )
				||
				( strpos( $result, 'SSL Already Enabled' ) !== false )
				||
				( strpos( $result, 'SSL is already disabled for' ) !== false )
				||
				( strpos( $result, 'http2 is already enabled for domain' ) !== false )
				||
				( strpos( $result, 'http2 enabled for domain' ) !== false )
				||
				( strpos( $result, 'http2 disabled for domain' ) !== false )
				||
				( strpos( $result, 'http2 is already disabled for domain' ) !== false )
				||
				( strpos( $result, 'Successfully received certificate' ) !== false )
				||
				( strpos( $result, 'certificate has been successfully installed' ) !== false );

				break;
			case 'add_remove_sftp.txt':
				$return =
				( 'sftp-add-user' === $action && strpos( $result, 'Added SFTP user ' ) !== false )
				||
				( 'sftp-remove-user' === $action && strpos( $result, 'Removed SFTP user ' ) !== false )
				||
				( 'sftp-change-password' === $action && strpos( $result, 'Password changed for ' ) !== false )
				||
				( 'sftp-remove-key' === $action && strpos( $result, 'Public key removed for ' ) !== false )
				||
				( 'sftp-remove-password' === $action && strpos( $result, 'Password removed for ' ) !== false )
				||
				( 'sftp-set-key' === $action && strpos( $result, 'Public key set for ' ) !== false );
				break;
			case 'manage_site_users.txt':
				$return =
				( 'site-user-change-password' === $action && strpos( $result, 'Password changed for ' ) !== false )
				||
				( 'site-user-remove-key' === $action && strpos( $result, 'Public key removed for ' ) !== false )
				||
				( 'site-user-remove-password' === $action && strpos( $result, 'Password removed for ' ) !== false )
				||
				( 'site-user-set-key' === $action && strpos( $result, 'Public key set for ' ) !== false );
				break;
			case 'basic_auth_misc.txt':
				$return =
				( strpos( $result, 'Basic authentication disabled for' ) !== false )
				||
				( strpos( $result, 'Basic authentication enabled for' ) !== false )
				||
				( strpos( $result, 'Basic auth is already enabled' ) !== false );
				break;
			case 'basic_auth_wplogin_misc.txt':
				$return =
				( strpos( $result, 'Basic authentication disabled for' ) !== false )
				||
				( strpos( $result, 'Basic authentication enabled for' ) !== false )
				||
				( strpos( $result, 'Wp Admin auth is already enabled' ) !== false );
				break;
			case 'toggle_https_misc.txt':
				$return =
				( strpos( $result, 'HTTPS redirect disabled for' ) !== false )
				||
				( strpos( $result, 'HTTPS redirect enabled for' ) !== false )
				||
				( strpos( $result, 'SSL redirection is already disabled for' ) !== false );
				break;
			case 'toggle_wp_linux_cron_misc.txt':
				$return =
				( strpos( $result, 'System cron enabled for' ) !== false )
				||
				( strpos( $result, 'System cron disabled for' ) !== false );
				break;
			case 'toggle_password_auth_misc.txt':
				$return =
				( strpos( $result, 'SSH password auth has been enabled for user' ) !== false )
				||
				( strpos( $result, 'SSH password auth has been disabled for user' ) !== false );
				break;
			case 'change_php_version_misc.txt':
				$return =
				( strpos( $result, 'PHP version changed to' ) !== false )
				||
				( strpos( $result, 'PHP version remains at' ) !== false );
				break;
			case 'change_php_option_misc.txt':
				$return = strpos( $result, 'Successfully changed PHP value' ) !== false;
				break;
			case 'toggle_php_active_misc.txt':
				$return =
				( strpos( $result, 'has been disabled' ) !== false )
				||
				( strpos( $result, 'already disabled' ) !== false )
				||
				( strpos( $result, 'has been enabled' ) !== false )
				||
				( strpos( $result, 'already enabled' ) !== false );
				break;
			case 'backup_restore.txt':
				$return =
				( strpos( $result, 'Backup has been completed!' ) !== false )
				||
				( strpos( $result, 'has been restored' ) !== false );
				break;
			case 'backup_restore_schedule.txt':
				$return =
				( strpos( $result, 'Backup job configured!' ) !== false )
				||
				( strpos( $result, 'Backup job removed!' ) !== false )
				||
				( strpos( $result, 'Full backup job configured!' ) !== false )
				||
				( strpos( $result, 'Full backup job removed!' ) !== false );
				break;
			case 'backup_restore_save_credentials.txt':
				$return = ( strpos( $result, 'AWS credentials have been saved' ) !== false );
				break;
			case 'change_domain_quick.txt':
				$return = ( strpos( $result, 'changed to' ) !== false );
				break;
			case 'change_domain_full.txt':
				$return =
				( strpos( $result, 'changed to' ) !== false )
				||
				( strpos( $result, 'Dry run completed' ) !== false );
				break;
			case 'clone_site.txt':
				$return = ( strpos( $result, 'has been cloned' ) !== false );
				break;
			case 'manage_phpmyadmin.txt':
				$return =
				( strpos( $result, 'phpMyAdmin installed for' ) !== false )
				||
				( strpos( $result, 'phpMyAdmin updated for' ) !== false )
				||
				( strpos( $result, 'Access credentials have been updated' ) !== false )
				||
				( strpos( $result, 'phpMyAdmin has been removed for' ) !== false );
				break;
			case 'manage_database_operation.txt':
				$return =
				( strpos( $result, 'Mysql host is already set to localhost' ) !== false )
				||
				( strpos( $result, 'Database has been switched to' ) !== false )
				||
				( strpos( $result, 'Database has been copied' ) !== false );
				break;
			case 'manage_tinyfilemanager.txt':
				$return =
				( strpos( $result, 'Filemanager installed for' ) !== false )
				||
				( strpos( $result, 'FileManager updated for' ) !== false )
				||
				( strpos( $result, 'Access credentials have been updated' ) !== false )
				||
				( strpos( $result, 'FileManager has been removed for' ) !== false );
				break;
			case '6g_firewall.txt':
				$return =
				( strpos( $result, 'Enabled 6G Firewall' ) !== false )
				||
				( strpos( $result, 'Disabled 6G Firewall' ) !== false );
				break;
			case '7g_firewall.txt':
				$return =
				( strpos( $result, 'Enabled 7G Firewall' ) !== false )
				||
				( strpos( $result, 'Disabled 7G Firewall' ) !== false );
				break;
			case 'manage_nginx_pagecache.txt':
				$return =
				( strpos( $result, 'WordPress Cache has been enabled' ) !== false )
				||
				( strpos( $result, 'WordPress Cache has been disabled' ) !== false )
				||
				( strpos( $result, 'WordPress Cache has been cleared' ) !== false );
				break;
			case 'toggle_wp_debug.txt':
				$return =
				( strpos( $result, 'WordPress debug flags enabled' ) !== false )
				||
				( strpos( $result, 'WordPress debug flags disabled' ) !== false );
				break;
			case 'multisite.txt':
				$return =
				( strpos( $result, 'WordPress Multisite has been enabled for' ) !== false )
				||
				( strpos( $result, 'configuration has been set up' ) !== false )
				||
				( strpos( $result, 'has been deregistered' ) !== false )
				||
				( strpos( $result, 'SSL enabled for' ) !== false )
				||
				( strpos( $result, 'SSL is already disabled for' ) !== false )
				||
				( strpos( $result, 'HTTPS disabled for' ) !== false );
				break;
			case 'multisite_wildcard_ssl.txt':
				$return =
				( strpos( $result, 'Wildcard HTTPS has been configured for' ) !== false )
				||
				( strpos( $result, 'HTTPS disabled for' ) !== false )
				||
				( strpos( $result, 'SSL is already disabled for' ) !== false );
				break;
			case 'site_sync_origin_setup.txt':
				$return =
				( strpos( $result, 'Authentication is already set up' ) !== false )
				||
				( strpos( $result, 'Authentication has been set up' ) !== false );
				break;
			case 'site_sync_destination_setup.txt':
				$return =
				( strpos( $result, 'Setup has been completed' ) !== false );
				break;
			case 'site_sync.txt':
				$return =
				( strpos( $result, 'Site Sync Completed Successfully' ) !== false )
				||
				( strpos( $result, 'MT Site Sync Completed Successfully' ) !== false )
				||
				( strpos( $result, 'Site sync has been scheduled' ) !== false );
				break;
			case 'site_sync_unschedule.txt':
				$return =
				( strpos( $result, 'Site sync job removed' ) !== false )
				||
				( strpos( $result, 'Schedule Site Sync For This Site Disabled' ) !== false )
				||
				( strpos( $result, 'No such job configured with given domain and destination ip' ) !== false )
				||
				( strpos( $result, 'No syncing job is configured as cron' ) !== false );
				break;
			case 'enable_disable_php_functions.txt':
				$return =
				( strpos( $result, 'has been enabled' ) !== false )
				||
				( strpos( $result, 'has been disabled' ) !== false );
				break;
			case 'reset_site_permissions.txt':
				$return =
				( strpos( $result, 'Permissions have been reset for' ) !== false );
				break;
			case 'server_redirect.txt':
				// Even though this name has "server" in it, it's mostly a site-level item.
				$return =
				( strpos( $result, 'Redirect rule added' ) !== false )
				||
				( strpos( $result, 'Redirect rule has been removed' ) !== false )
				||
				( strpos( $result, 'All Rewrite rules have been removed' ) !== false );
				break;
			case 'nginx_options.txt':
			case 'ols_options.txt':
				// This one is a mix of server and site level items - mostly site level items.
				$return =
				( strpos( $result, 'already enabled' ) !== false )
				||
				( strpos( $result, 'already disabled' ) !== false )
				||
				( strpos( $result, 'Success!' ) !== false );
				break;
			case 'ols_manage_admin_console.txt':
				$return =
				( strpos( $result, 'Set OpenLiteSpeed Web Admin access' ) !== false )
				||
				( strpos( $result, 'OpenLiteSpeed WebAdmin password not changed' ) !== false )
				||
				( strpos( $result, 'Enabled OLS/LSWS admin port on firewall!' ) !== false )
				||
				( strpos( $result, 'Disabled OLS/LSWS admin port on firewall!' ) !== false );
				break;
			case 'php_workers.txt':
				$return =
				( strpos( $result, 'PHP Workers Updated' ) !== false );
				break;
			case 'fail2ban_site.txt':
				// There is also a fail2ban section in the servers section below!
				$return =
				( strpos( $result, 'Fail2ban plugin has been installed for' ) !== false )
				||
				( strpos( $result, 'Fail2ban Plugin has been removed from' ) !== false );
				break;
			case 'reliable_updates.txt':
				$return =
				( strpos( $result, 'Updates are complete' ) !== false );
				break;
			case 'copy_site_to_existing_site.txt':
				$return =
				( strpos( $result, 'Copy to existing site is complete' ) !== false );
				break;
			case 'change_file_upload_size.txt':
				$return =
				( strpos( $result, 'File upload limits have been changed for' ) !== false );
				break;
			case 'update_wp_site_option.txt':
				$return =
				( strpos( $result, 'Updated Option Value' ) !== false );
				break;
			case 'change_wp_credentials.txt':
				$return =
				( strpos( $result, 'Updated credentials for user' ) !== false );
				break;
			case 'add_wp_user.txt':
				$return =
				( strpos( $result, 'Added user' ) !== false );
				break;
			case 'update_wp_config_option.txt':
				$return =
				( strpos( $result, 'Updated WPConfig Option Value' ) !== false );
				break;
			case 'passwordless_login.txt':
				// for this one we just want to make sure that the last line has a string that starts with http:
				list($url_array[]) = array_slice( explode( PHP_EOL, trim( $result ) ), -1, 1 );
				$return            =
				( strpos( $url_array[0], 'http://' ) !== false )
				||
				( strpos( $url_array[0], 'https://' ) !== false );
				break;
			case 'git_control_site_command.txt':
			case 'git_control_site.txt':
				$return =
				( strpos( $result, 'Git Init Complete For Domain' ) !== false )
				||
				( strpos( $result, 'Git has been removed from' ) !== false )
				||
				( strpos( $result, 'Git sync succeeded' ) !== false )
				||
				( strpos( $result, 'Git branch switch and checkout succeeded' ) !== false )
				||
				( strpos( $result, 'Git create new branch and checkout succeeded' ) !== false )
				||
				( strpos( $result, 'Git commit and push succeeded' ) !== false )
				||
				( strpos( $result, 'Git tag and push succeeded' ) !== false )
				||
				( strpos( $result, 'Git pull tag succeeded' ) !== false )
				||
				( strpos( $result, 'Git fetch tag succeeded' ) !== false )
				||
				( strpos( $result, 'Version folder has been removed for' ) !== false )
				||
				( strpos( $result, 'All version folders have been removed for' ) !== false )
				||
				( strpos( $result, 'Git switch version succeeded' ) !== false )
				||
				( strpos( $result, 'Git credentials successfully set up for domain' ) !== false )
				||
				( strpos( $result, 'Git clone successful' ) !== false )
				||
				( strpos( $result, 'Multi-tenant: Fetch version succeeded' ) !== false )
				||
				( strpos( $result, 'Multi-tenant: Site conversion succeeded' ) !== false );
				break;
			case 'mt_clone_site.txt':
				$return = ( strpos( $result, 'has been cloned' ) !== false && strpos( $result, 'Git tag and push succeeded' ) !== false && strpos( $result, 'Multi-tenant: Fetch version succeeded' ) !== false );
				break;
			case 'mt_convert_site.txt':
				$return = ( strpos( $result, 'Multi-tenant: Site conversion succeeded for' ) !== false );
				break;
			case 'renew_all_certificates.txt':
				$return = ( strpos( $result, 'Certificate renewal attempt completed' ) !== false );
				break;
			case 'manage_logtivity.txt':
				$return =
				( strpos( $result, 'Logtivity installed and license activated' ) !== false )
				||
				( strpos( $result, 'Logtivity license activated' ) !== false )
				||
				( strpos( $result, 'Logtivity has been removed' ) !== false );
				$return = $return && ( strpos( $result, 'Please provide a valid API key' ) == false ); // If the string 'Please provide a valid API key' is in the output, the thing has failed.
				break;
			case 'manage_solidwp_security.txt':
				$return =
				( strpos( $result, 'Solidwp installed and license activated' ) !== false )
				||
				( strpos( $result, 'Solidwp license activated' ) !== false )
				||
				( strpos( $result, 'Solidwp has been removed' ) !== false );
				break;

			/**************************************************************
			* The items below this are SERVER items, not APP items        *
			*/
			case 'backup_restore_delete_and_prune_server.txt':
				$return =
				( strpos( $result, 'All backups have been deleted' ) !== false )
				||
				( strpos( $result, 'All backups older than' ) !== false );
				break;
			case 'install_memcached.txt':
				$return =
				( strpos( $result, 'Memcached has been installed' ) !== false )
				||
				( strpos( $result, 'Memcached is already installed' ) !== false );
				break;
			case 'manage_memcached.txt':
				$return =
				( strpos( $result, 'Memcached server has been restarted' ) !== false )
				||
				( strpos( $result, 'Memcached cache has been cleared' ) !== false )
				||
				( strpos( $result, 'Memcached has been enabled' ) !== false )
				||
				( strpos( $result, 'Memcached has been disabled' ) !== false )
				||
				( strpos( $result, 'Memcached has been removed from the system' ) !== false );
				break;
			case 'install_redis.txt':
				$return =
				( strpos( $result, 'Redis has been installed' ) !== false )
				||
				( strpos( $result, 'Redis is already installed' ) !== false );
				break;
			case 'manage_redis.txt':
				$return =
				( strpos( $result, 'Redis server has been restarted' ) !== false )
				||
				( strpos( $result, 'Redis cache has been cleared' ) !== false )
				||
				( strpos( $result, 'Redis has been enabled' ) !== false )
				||
				( strpos( $result, 'Redis has been disabled' ) !== false )
				||
				( strpos( $result, 'Redis has been removed from the system' ) !== false );
				break;
			case 'add_wp_admin.txt':
				$return =
				( strpos( $result, 'added as an administrator to' ) !== false );
				break;
			case 'restart_php_service.txt':
				$return =
				( strpos( $result, 'PHP service has restarted for version' ) !== false );
				break;
			case 'toggle_edd_nginx_rules.txt':
				$return =
				( strpos( $result, 'Easy Digital Downloads NGINX directives enabled for' ) !== false )
				||
				( strpos( $result, 'Easy Digital Downloads NGINX directives disabled for' ) !== false );
				break;
			case 'email_gateway.txt':
				$return =
				( strpos( $result, 'The email gateway has now been configured' ) !== false )
				||
				( strpos( $result, 'Test email has been sent' ) !== false )
				||
				( strpos( $result, 'Email gateway successfully removed' ) !== false );
				break;
			case 'run_upgrades_290.txt':
			case 'run_upgrades_460.txt':
			case 'run_upgrades_461.txt':
			case 'run_upgrades_462.txt':
			case 'run_upgrades_530.txt':
				$return =
				( strpos( $result, 'upgrade completed' ) !== false )
				||
				( strpos( $result, 'Upgrade Completed' ) !== false )
				||
				( strpos( $result, '7G Firewall is already installed' ) !== false );
				break;
			case 'run_upgrade_install_php_81.txt':
				$return = ( strpos( $result, 'PHP 8.1 has been installed' ) !== false );
				break;
			case 'run_upgrade_install_php_82.txt':
				$return = ( strpos( $result, 'PHP 8.2 has been installed' ) !== false );
				break;
			case 'run_upgrade_install_old_php_version.txt':
				$return = ( strpos( $result, 'has been installed' ) !== false );
				break;
			case 'run_upgrade_7g.txt':
				$return = ( strpos( $result, 'The 7G Firewall has been upgraded' ) !== false );
				break;
			case 'run_remove_6g.txt':
				$return = ( strpos( $result, 'The 6G Firewall has been removed' ) !== false );
				break;
			case 'run_upgrade_wpcli.txt':
				$return = ( strpos( $result, 'WPCLI has been upgraded' ) !== false );
				break;
			case 'run_upgrade_install_php_intl.txt':
				$return = ( strpos( $result, 'PHP intl module has been installed' ) !== false );
				break;
			case 'run_upgrade_cache_enabler_nginx_config.txt':
				$return = ( strpos( $result, 'Cache Enabler NGINX Config Has Been Upgraded' ) !== false );
				break;
			case 'server_status_callback.txt':
				$return =
				( strpos( $result, 'Server status job configured' ) !== false )
				||
				( strpos( $result, 'Server status job removed' ) !== false )
				||
				( strpos( $result, 'Server status job scheduled successfully' ) !== false )
				||
				( strpos( $result, 'Server status job executed successfully' ) !== false );
				break;
			case 'maldet.txt':
				$return =
				( strpos( $result, 'Maldet has been installed' ) !== false )
				||
				( strpos( $result, 'LMD is already installed!' ) !== false )
				||
				( strpos( $result, 'clamscan and LMD uninstalled' ) !== false )
				||
				( strpos( $result, 'Clamscan database has been updated' ) !== false )
				||
				( strpos( $result, 'Malware Detection has been updated' ) !== false )
				||
				( strpos( $result, 'Scanning has been completed' ) !== false )
				||
				( strpos( $result, 'Cron has been disabled' ) !== false )
				||
				( strpos( $result, 'Cron has been enabled' ) !== false )
				||
				( strpos( $result, 'Malware data has been purged' ) !== false )
				||
				( strpos( $result, 'Malware services have been restarted' ) !== false );
				break;
			case 'server_restart_callback.txt':
				$return =
				( strpos( $result, 'Server restart callback job configured' ) !== false )
				||
				( strpos( $result, 'Server restart callback job removed' ) !== false )
				||
				( strpos( $result, 'Server restart callback job executed successfully' ) !== false );
				break;
			case 'monitorix.txt':
				$return =
				( strpos( $result, 'Monitorix has been installed' ) !== false )
				||
				( strpos( $result, 'Monitorix has been removed' ) !== false )
				||
				( strpos( $result, 'Monitorix has been updated' ) !== false )
				||
				( strpos( $result, 'has been enabled for' ) !== false )
				||
				( strpos( $result, 'has been disabled for' ) !== false )
				||
				( strpos( $result, 'SSL has been enabled for' ) !== false )
				||
				( strpos( $result, 'SSL is already disabled for' ) !== false )
				||
				( strpos( $result, 'SSL has been disabled for' ) !== false );
				break;
			case 'netdata_install.txt':
				$return =
				( strpos( $result, 'Netdata has been installed' ) !== false )
				||
				( strpos( $result, 'Netdata is already installed' ) !== false );
				break;
			case 'netdata.txt':
				$return =
				( strpos( $result, 'Netdata has been installed' ) !== false )
				||
				( strpos( $result, 'Netdata has been removed' ) !== false )
				||
				( strpos( $result, 'Netdata has been updated' ) !== false )
				||
				( strpos( $result, 'Basic Auth has been enabled for' ) !== false )
				||
				( strpos( $result, 'Basic Auth already enabled' ) !== false )
				||
				( strpos( $result, 'Basic Auth has been disabled' ) !== false )
				||
				( strpos( $result, 'Basic Auth has been updated' ) !== false )
				||
				( strpos( $result, 'SSL has been enabled for' ) !== false )
				||
				( strpos( $result, 'SSL was not enabled for netdata so nothing to disable' ) !== false )
				||
				( strpos( $result, 'SSL has been disabled for' ) !== false )
				||
				( strpos( $result, 'Registry already enabled ' ) !== false )
				||
				( strpos( $result, 'Registry enabled to' ) !== false )
				||
				( strpos( $result, 'Registry already pointed to ' ) !== false )
				||
				( strpos( $result, 'Registry pointed to' ) !== false );
				break;
			case 'monit.txt':
				$return =
				( strpos( $result, 'Monit has been installed' ) !== false )
				||
				( strpos( $result, 'Monit has been removed' ) !== false )
				||
				( strpos( $result, 'Monit has been updated' ) !== false )
				||
				( strpos( $result, 'has been enabled' ) !== false )
				||
				( strpos( $result, 'has been disabled' ) !== false )
				||
				( strpos( $result, 'SSL has been enabled for' ) !== false )
				||
				( strpos( $result, 'SSL has been disabled for' ) !== false )
				||
				( strpos( $result, 'SSL is already disabled for' ) !== false )
				||
				( strpos( $result, 'Monit email settings updated' ) !== false )
				||
				( strpos( $result, 'All monitors enabled' ) !== false )
				||
				( strpos( $result, 'All monitors disabled' ) !== false )
				||
				( strpos( $result, 'Callbacks have been enabled' ) !== false )
				||
				( strpos( $result, 'Callbacks have been disabled' ) !== false )
				||
				( strpos( $result, 'Monit has been activated' ) !== false )
				||
				( strpos( $result, 'Monit has been temporarily deactivated' ) !== false );
				break;
			case 'schedule_server_reboot.txt':
				$return =
				( strpos( $result, 'The server reboot has been scheduled' ) !== false );
				break;
			case 'backup_config_files.txt':
				$return =
				( strpos( $result, 'Backup cron job has been configured' ) !== false )
				||
				( strpos( $result, 'Cron for conf backup has been removed' ) !== false )
				||
				( strpos( $result, 'Backup files have been removed' ) !== false );
				break;
			case 'goaccess.txt':
				$return =
				( strpos( $result, 'goaccess is already installed' ) !== false )
				||
				( strpos( $result, 'Goaccess has been installed' ) !== false )
				||
				( strpos( $result, 'goaccess has been removed' ) !== false )
				||
				( strpos( $result, 'Goaccess has been disabled' ) !== false )
				||
				( strpos( $result, 'goaccess has been enabled' ) !== false )
				||
				( strpos( $result, 'SSL Already Enabled' ) !== false )
				||
				( strpos( $result, 'SSL has been enabled for' ) !== false )
				||
				( strpos( $result, 'SSL has been disabled' ) !== false )
				||
				( strpos( $result, 'SSL Not enabled for' ) !== false )
				||
				( strpos( $result, 'Basic Auth already enabled' ) !== false )
				||
				( strpos( $result, 'Basic auth has been enabled' ) !== false )
				||
				( strpos( $result, 'Basic Auth already disabled' ) !== false )
				||
				( strpos( $result, 'Auth has been updated' ) !== false )
				||
				( strpos( $result, 'whitelisted' ) !== false )
				||
				( strpos( $result, 'removed from whitelist' ) !== false )
				||
				( strpos( $result, 'is not whitelisted' ) !== false )
				||
				( strpos( $result, 'All whiteslited ips has been removed' ) !== false );
				break;
			case 'fail2ban.txt':
				$return =
				( strpos( $result, 'Fail2ban installation complete' ) !== false )
				||
				( strpos( $result, 'fail2ban has been removed' ) !== false )
				||
				( strpos( $result, 'fail2ban has been purged' ) !== false )
				||
				( strpos( $result, 'Fail2ban parameters have been successfully updated' ) !== false )
				||
				( strpos( $result, 'Protocol has been added' ) !== false )
				||
				( strpos( $result, 'The specified protocol has been removed' ) !== false )
				||
				( strpos( $result, 'The protocol was not enabled and therefore could not be removed' ) !== false )
				||
				( strpos( $result, 'Fail2ban parameters have been successfully updated' ) !== false )
				||
				( strpos( $result, 'Fail2ban software has been successfully updated' ) !== false )
				||
				( strpos( $result, 'has been unbanned' ) !== false )
				||
				( strpos( $result, 'has been banned' ) !== false );
				break;
			case 'server_update.txt':
				$return =
				( strpos( $result, 'Updates have been scheduled to run via cron' ) !== false )
				||
				( strpos( $result, 'Security Updates have been scheduled to run via cron' ) !== false );
				break;
			case 'server_php_version.txt':
				$return =
				( strpos( $result, 'Server level PHP version has been updated to' ) !== false );
				break;
			case 'git_control_server.txt':
				$return =
				( strpos( $result, 'Git has been installed' ) !== false )
				||
				( strpos( $result, 'Git has been updated' ) !== false );
				break;
			case 'ubuntu_pro_activate.txt':
				$return =
				( strpos( $result, 'Ubuntu Pro token has been applied to this server' ) !== false );
				break;
			case 'ubuntu_pro_actions.txt':
				$return =
				( strpos( $result, 'This machine is not attached to an Ubuntu Pro subscription' ) !== false )
				||
				( strpos( $result, 'Ubuntu Pro token has been removed from this server' ) !== false );
				break;

			/**
			 *************************************************************
			 * The items below this are SERVER SYNC items, not APP items.
			 **************************************************************
			 */
			case 'server_sync_origin_setup.txt':
				$return =
				( strpos( $result, 'Setup has been finished for this server. But you are not done yet' ) !== false );
				break;
			case 'server_sync_destination_setup.txt':
				$return =
				( strpos( $result, 'Setup has been completed!' ) !== false );
				break;
			case 'server_sync_manage.txt':
				$return =
				( strpos( $result, 'The syncronization job has been started' ) !== false )
				||
				( strpos( $result, 'The scheduled sync job has been disabled' ) !== false )
				||
				( strpos( $result, 'The scheduled sync job has been re-enabled' ) !== false )
				||
				( strpos( $result, 'The sync service has been permanently removed' ) !== false );
				break;

		}

		/* Sometimes we get a false positive so check for some things that might indicate a generic failure. */
		if ( $return ) {
			$return = $return
				&&
				( strpos( $result, 'dpkg was interrupted, you must manually run' ) === false )
				&&
				( strpos( $result, 'Installation of required packages failed' ) === false );
		}
		if ( $return && ( false === boolval( wpcd_get_option( 'wordpress_app_ignore_journalctl_xe' ) ) ) ) {
			$return = $return
				&&
				( strpos( $result, 'journalctl -xe' ) === false );
		}

		return apply_filters( 'wpcd_is_ssh_successful', $return, $result, $command, $action, $this->get_app_name() );

	}

	/**
	 * Different scripts needs different placeholders/handling.
	 *
	 * Filter Hook: wpcd_script_placeholders_{$this->get_app_name()}
	 *
	 * @param array  $array              The array of placeholders, usually empty but since this is the first param, its the one returned as the modified value.
	 * @param string $script_name        Script_name.
	 * @param string $script_version     The version of script to be used.
	 * @param array  $instance           Various pieces of data about the server or app being used. It can use the following keys. post_id: the ID of the post.
	 * @param string $command            The command being constructed.
	 * @param array  $additional         An array of any additional data we might need. It can use the following keys (non-exhaustive list):
	 *    command: The command to use (a script may have multiple commands)
	 *    domain: The domain of the site
	 *    user: The user to action.
	 *    email: The email to use.
	 *    public_key: The path to the public key
	 *    password: The password of the user.
	 */
	public function script_placeholders( $array, $script_name, $script_version, $instance, $command, $additional ) {
		$new_array    = array();
		$common_array = array(
			'SCRIPT_COMMON_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/9999-common-functions.txt',
			'SCRIPT_COMMON_NAME' => '9999-common-functions.sh',
		);
		switch ( $script_name ) {
			case 'after-server-create-run-commands.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'           => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/01-prepare_server.txt',
						'SCRIPT_NAME'          => '01-prepare_server.sh',
						'SCRIPT_LOGS'          => "{$this->get_app_name()}_prepare_server",
						'CALLBACK_URL'         => $this->get_command_url( $instance['post_id'], 'prepare_server', 'completed' ),
						'LONG_COMMAND_TIMEOUT' => wpcd_get_long_running_command_timeout(),
					),
					$common_array,
					$additional
				);
				break;
			case 'install_wordpress_site.txt':
				$command_name = $additional['command'];
				$new_array    = array_merge(
					array(
						'SIX_G_COMMANDS_URL'           => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/6G-Firewall-OLS.txt',
						'SCRIPT_SIX_G_COMMANDS_NAME'   => '6G-Firewall-OLS.txt',
						'SEVEN_G_COMMANDS_URL'         => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/7G-Firewall-OLS.txt',
						'SCRIPT_SEVEN_G_COMMANDS_NAME' => '7G-Firewall-OLS.txt',
						'SCRIPT_URL'                   => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/02-install_wordpress_site.txt',
						'SCRIPT_NAME'                  => '02-install_wordpress_site.sh',
						'SCRIPT_LOGS'                  => "{$this->get_app_name()}_{$command_name}",
						'CALLBACK_URL'                 => $this->get_command_url( $instance['post_id'], $command_name, 'completed' ),
					),
					$common_array,
					$additional
				);
				break;
			case 'disable_remove_site.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/03-disable-remove-site.txt',
						'SCRIPT_NAME' => '03-disable-remove-site.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'manage_https.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/04-manage_https.txt',
						'SCRIPT_NAME' => '04-manage_https.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'add_remove_sftp.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/06-add_remove_sftp.txt',
						'SCRIPT_NAME' => '06-add_remove_sftp.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'manage_site_users.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/11-manage_site_users.txt',
						'SCRIPT_NAME' => '11-manage_site_users.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'backup_restore.txt':
				$command_name = $additional['command'];
				$new_array    = array_merge(
					array(
						'SCRIPT_URL'   => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/08-backup.txt',
						'SCRIPT_NAME'  => '08-backup.sh',
						'SCRIPT_LOGS'  => "{$this->get_app_name()}_{$command_name}",
						'CALLBACK_URL' => $this->get_command_url( $instance['app_id'], $command_name, 'completed' ),
					),
					$common_array,
					$additional
				);
				break;
			case 'backup_restore_delete_and_prune.txt':
				$command_name = $additional['command'];
				$new_array    = array_merge(
					array(
						'SCRIPT_URL'   => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/08-backup.txt',
						'SCRIPT_NAME'  => '08-backup.sh',
						'SCRIPT_LOGS'  => "{$this->get_app_name()}_{$command_name}",
						'CALLBACK_URL' => $this->get_command_url( $instance['app_id'], $command_name, 'completed' ),
					),
					$common_array,
					$additional
				);
				break;
			case 'backup_restore_schedule.txt':
			case 'backup_restore_save_credentials.txt':
			case 'backup_restore_refresh_backup_list.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/08-backup.txt',
						'SCRIPT_NAME' => '08-backup.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'basic_auth_misc.txt':
			case 'basic_auth_wplogin_misc.txt':
			case 'toggle_https_misc.txt':
			case 'toggle_wp_linux_cron_misc.txt':
			case 'change_php_version_misc.txt':
			case 'get_diskspace_used_misc.txt':
			case 'change_php_option_misc.txt':
			case 'toggle_wp_debug.txt':
			case 'restart_php_service.txt':
			case 'toggle_password_auth_misc.txt':
			case 'toggle_php_active_misc.txt':
			case 'renew_all_certificates.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/10-misc.txt',
						'SCRIPT_NAME' => '10-misc.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'change_domain_quick.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/05-change_domain.txt',
						'SCRIPT_NAME' => '05-change_domain.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'change_domain_full.txt':
				$command_name = $additional['command'];
				$new_array    = array_merge(
					array(
						'SCRIPT_URL'   => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/05-change_domain.txt',
						'SCRIPT_NAME'  => '05-change_domain.sh',
						'SCRIPT_LOGS'  => "{$this->get_app_name()}_{$command_name}",
						'CALLBACK_URL' => $this->get_command_url( $instance['app_id'], $command_name, 'completed' ),
					),
					$common_array,
					$additional
				);
				break;
			case 'search_and_replace_db.txt':
				$command_name = $additional['command'];
				$new_array    = array_merge(
					array(
						'SCRIPT_URL'   => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/31-search_and_replace_db.txt',
						'SCRIPT_NAME'  => '31-search_and_replace_db.sh',
						'SCRIPT_LOGS'  => "{$this->get_app_name()}_{$command_name}",
						'CALLBACK_URL' => $this->get_command_url( $instance['app_id'], $command_name, 'completed' ),
					),
					$common_array,
					$additional
				);
				break;
			case 'clone_site.txt':
				$command_name = $additional['command'];
				$new_array    = array_merge(
					array(
						'SCRIPT_URL'   => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/09-clone_site.txt',
						'SCRIPT_NAME'  => '09-clone_site.sh',
						'SCRIPT_LOGS'  => "{$this->get_app_name()}_{$command_name}",
						'CALLBACK_URL' => $this->get_command_url( $instance['app_id'], $command_name, 'completed' ),
					),
					$common_array,
					$additional
				);
				break;
			case 'manage_phpmyadmin.txt':
				$command_name = $additional['command'];
				$new_array    = array_merge(
					array(
						'SCRIPT_URL'   => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/07-phpmyadmin.txt',
						'SCRIPT_NAME'  => '07-phpmyadmin.sh',
						'SCRIPT_LOGS'  => "{$this->get_app_name()}_{$command_name}",
						'CALLBACK_URL' => $this->get_command_url( $instance['app_id'], $command_name, 'completed' ),
					),
					$common_array,
					$additional
				);
				break;
			case 'manage_database_operation.txt':
				$command_name = $additional['command'];
				$new_array    = array_merge(
					array(
						'SCRIPT_URL'   => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/53-database-operation.txt',
						'SCRIPT_NAME'  => '53-database-operation.sh',
						'SCRIPT_LOGS'  => "{$this->get_app_name()}_{$command_name}",
						'CALLBACK_URL' => $this->get_command_url( $instance['app_id'], $command_name, 'completed' ),
					),
					$common_array,
					$additional
				);
				break;
			case 'manage_tinyfilemanager.txt':
				$command_name = $additional['command'];
				$new_array    = array_merge(
					array(
						'SCRIPT_URL'   => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/57-tinyfilemanager.txt',
						'SCRIPT_NAME'  => '57-tinyfilemanager.sh',
						'SCRIPT_LOGS'  => "{$this->get_app_name()}_{$command_name}",
						'CALLBACK_URL' => $this->get_command_url( $instance['app_id'], $command_name, 'completed' ),
					),
					$common_array,
					$additional
				);
				break;
			case '6g_firewall.txt':
				$new_array = array_merge(
					array(
						'SIX_G_COMMANDS_URL'         => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/6G-Firewall-OLS.txt',
						'SCRIPT_SIX_G_COMMANDS_NAME' => '6G-Firewall-OLS.txt',
						'SCRIPT_URL'                 => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/15-6g_firewall.txt',
						'SCRIPT_NAME'                => '15-6g_firewall.sh',
					),
					$common_array,
					$additional
				);
				break;
			case '7g_firewall.txt':
				$new_array = array_merge(
					array(
						'SEVEN_G_COMMANDS_URL'         => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/7G-Firewall-OLS.txt',
						'SCRIPT_SEVEN_G_COMMANDS_NAME' => '7G-Firewall-OLS.txt',
						'SCRIPT_URL'                   => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/40-7g_firewall.txt',
						'SCRIPT_NAME'                  => '40-7g_firewall.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'manage_nginx_pagecache.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/18-wp_cache.txt',
						'SCRIPT_NAME' => '18-wp_cache.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'add_wp_admin.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/10-misc.txt',
						'SCRIPT_NAME' => '10-misc.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'toggle_edd_nginx_rules.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/17-plugin_tweaks.txt',
						'SCRIPT_NAME' => '17-plugin_tweaks.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'multisite.txt':
			case 'multisite_wildcard_ssl.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/13-multisite.txt',
						'SCRIPT_NAME' => '13-multisite.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'site_sync_origin_setup.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/81-origin-site-sync.txt',
						'SCRIPT_NAME' => '81-origin-site-sync.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'site_sync_destination_setup.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/82-destination-site-sync.txt',
						'SCRIPT_NAME' => '82-destination-site-sync.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'site_sync.txt':
				$command_name = $additional['command'];
				$new_array    = array_merge(
					array(
						'SCRIPT_URL'   => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/81-origin-site-sync.txt',
						'SCRIPT_NAME'  => '81-origin-site-sync.sh',
						'SCRIPT_LOGS'  => "{$this->get_app_name()}_{$command_name}",
						'CALLBACK_URL' => $this->get_command_url( $instance['app_id'], $command_name, 'completed' ),
					),
					$common_array,
					$additional
				);
				break;
			case 'site_sync_unschedule.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/81-origin-site-sync.txt',
						'SCRIPT_NAME' => '81-origin-site-sync.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'enable_disable_php_functions.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/22-enable_disable_php_functions.txt',
						'SCRIPT_NAME' => '22-enable_disable_php_functions.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'reset_site_permissions.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/10-misc.txt',
						'SCRIPT_NAME' => '10-misc.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'server_redirect.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/33-server_redirect.txt',
						'SCRIPT_NAME' => '33-server_redirect.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'nginx_options.txt':
				// This one is a mix of server and site level items - mostly site level items.
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/34-nginx_options.txt',
						'SCRIPT_NAME' => '34-nginx_options.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'ols_options.txt':
				// This one is a mix of server and site level items - mostly site level items.
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/55-ols_options.txt',
						'SCRIPT_NAME' => '55-ols_options.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'ols_manage_admin_console.txt':
				// This one is a mix of server and site level items - mostly site level items.
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/56-ols_manage_admin_console.txt',
						'SCRIPT_NAME' => '56-ols_manage_admin_console.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'php_workers.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/39-php_workers.txt',
						'SCRIPT_NAME' => '39-php_workers.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'fail2ban_site.txt':
				// There is also a fail2ban section in the servers section below!
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/23-fail2ban.txt',
						'SCRIPT_NAME' => '23-fail2ban.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'reliable_updates.txt':
				$command_name = $additional['command'];
				$new_array    = array_merge(
					array(
						'SCRIPT_URL'         => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/50-reliable_updates.txt',
						'SCRIPT_NAME'        => '50-reliable_updates.sh',
						'SCRIPT_LOGS'        => "{$this->get_app_name()}_{$command_name}",
						'CALLBACK_URL'       => $this->get_command_url( $instance['app_id'], $command_name, 'completed' ),
						'SCRIPT_URL_BACKUP'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/08-backup.txt',
						'SCRIPT_NAME_BACKUP' => '08-backup.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'copy_site_to_existing_site.txt':
				$command_name = $additional['command'];
				$new_array    = array_merge(
					array(
						'SCRIPT_URL'         => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/25-copy_site_to_existing_site.txt',
						'SCRIPT_NAME'        => '25-copy_site_to_existing_site.sh',
						'SCRIPT_LOGS'        => "{$this->get_app_name()}_{$command_name}",
						'CALLBACK_URL'       => $this->get_command_url( $instance['app_id'], $command_name, 'completed' ),
						'SCRIPT_URL_BACKUP'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/08-backup.txt',
						'SCRIPT_NAME_BACKUP' => '08-backup.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'change_file_upload_size.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/10-misc.txt',
						'SCRIPT_NAME' => '10-misc.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'update_wp_site_option.txt':
			case 'change_wp_credentials.txt':
			case 'add_wp_user.txt':
			case 'update_wp_config_option.txt':
			case 'passwordless_login.txt';
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/30-wp_site_things.txt',
						'SCRIPT_NAME' => '30-wp_site_things.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'git_control_site_command.txt':
				$command_name = $additional['command'];
				$new_array    = array_merge(
					array(
						'SCRIPT_URL'   => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/58-git_control.txt',
						'SCRIPT_NAME'  => '58-git_control.sh',
						'SCRIPT_LOGS'  => "{$this->get_app_name()}_{$command_name}",
						'CALLBACK_URL' => $this->get_command_url( $instance['app_id'], $command_name, 'completed' ),
					),
					$common_array,
					$additional
				);
				break;
			case 'git_control_site.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/58-git_control.txt',
						'SCRIPT_NAME' => '58-git_control.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'mt_clone_site.txt':
				$command_name = $additional['command'];
				$new_array    = array_merge(
					array(
						'SCRIPT_URL'   => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/09-clone_site.txt',
						'SCRIPT_NAME'  => '09-clone_site.sh',
						'SCRIPT_URL2'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/58-git_control.txt',
						'SCRIPT_NAME2' => '58-git_control.sh',
						'SCRIPT_LOGS'  => "{$this->get_app_name()}_{$command_name}",
						'CALLBACK_URL' => $this->get_command_url( $instance['app_id'], $command_name, 'completed' ),
					),
					$common_array,
					$additional
				);
				break;
			case 'mt_convert_site.txt':
				$command_name = $additional['command'];
				$new_array    = array_merge(
					array(
						'SCRIPT_URL'   => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/58-git_control.txt',
						'SCRIPT_NAME'  => '58-git_control.sh',
						'SCRIPT_LOGS'  => "{$this->get_app_name()}_{$command_name}",
						'CALLBACK_URL' => $this->get_command_url( $instance['app_id'], $command_name, 'completed' ),
					),
					$common_array,
					$additional
				);
				break;
			case 'manage_logtivity.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/52-logtivity.txt',
						'SCRIPT_NAME' => '52-logtivity.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'manage_solidwp_security.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/51-solidsecurity.txt',
						'SCRIPT_NAME' => '51-solidsecurity.sh',
					),
					$common_array,
					$additional
				);
				break;

			/*********************************************************
			* The items below this are SERVER items, not APP items   *
			*/
			case 'backup_restore_delete_and_prune_server.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/08-backup.txt',
						'SCRIPT_NAME' => '08-backup.sh',
					),
					$common_array,
					$additional
				);
				break;

			case 'install_memcached.txt':
				$command_name = $additional['command'];
				$new_array    = array_merge(
					array(
						'SCRIPT_URL'   => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/16-memcached.txt',
						'SCRIPT_NAME'  => '16-memcached.sh',
						'SCRIPT_LOGS'  => "{$this->get_app_name()}_{$command_name}",
						'CALLBACK_URL' => $this->get_command_url( $instance['server_id'], $command_name, 'completed' ),
					),
					$common_array,
					$additional
				);
				break;
			case 'manage_memcached.txt':
				$command_name = $additional['command'];
				$new_array    = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/16-memcached.txt',
						'SCRIPT_NAME' => '16-memcached.sh',
					),
					$common_array,
					$additional
				);
				break;

			case 'install_redis.txt':
				$command_name = $additional['command'];
				$new_array    = array_merge(
					array(
						'SCRIPT_URL'   => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/12-redis.txt',
						'SCRIPT_NAME'  => '12-redis.sh',
						'SCRIPT_LOGS'  => "{$this->get_app_name()}_{$command_name}",
						'CALLBACK_URL' => $this->get_command_url( $instance['server_id'], $command_name, 'completed' ),
					),
					$common_array,
					$additional
				);
				break;
			case 'manage_redis.txt':
				$command_name = $additional['command'];
				$new_array    = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/12-redis.txt',
						'SCRIPT_NAME' => '12-redis.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'email_gateway.txt':
				$command_name = $additional['command'];
				$new_array    = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/14-mail.txt',
						'SCRIPT_NAME' => '14-mail.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'monitorix.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/21-monitorix.txt',
						'SCRIPT_NAME' => '21-monitorix.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'netdata_install.txt':
				$command_name = $additional['command'];
				$new_array    = array_merge(
					array(
						'SCRIPT_URL'   => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/43-netdata.txt',
						'SCRIPT_NAME'  => '43-netdata.sh',
						'SCRIPT_LOGS'  => "{$this->get_app_name()}_{$command_name}",
						'CALLBACK_URL' => $this->get_command_url( $instance['server_id'], $command_name, 'completed' ),
					),
					$common_array,
					$additional
				);
				break;
			case 'netdata.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/43-netdata.txt',
						'SCRIPT_NAME' => '43-netdata.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'monit.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/20-monit.txt',
						'SCRIPT_NAME' => '20-monit.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'run_upgrades_290.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/1010-upgrade_290_secure_php.txt',
						'SCRIPT_NAME' => '1010-upgrade_290_secure_php.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'run_upgrades_460.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/1020-upgrade_460_performance.txt',
						'SCRIPT_NAME' => '1020-upgrade_460_performance.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'run_upgrades_461.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/1030-upgrade_461_certbot_snap.txt',
						'SCRIPT_NAME' => '1030-upgrade_461_certbot_snap.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'run_upgrades_462.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/1040-upgrade_462_install_7g_firewall.txt',
						'SCRIPT_NAME' => '1040-upgrade_462_install_7g_firewall.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'run_upgrades_530.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/1090-upgrade_530_ols_server_fix.txt',
						'SCRIPT_NAME' => '1090-upgrade_530_ols_server_fix.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'run_upgrade_install_php_81.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/1050-upgrade_install_php_81.txt',
						'SCRIPT_NAME' => '1050-upgrade_install_php_81.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'run_upgrade_install_php_82.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/1110-upgrade_install_php_82.txt',
						'SCRIPT_NAME' => '1110-upgrade_install_php_82.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'run_upgrade_install_old_php_version.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/1130-upgrade_install_php.txt',
						'SCRIPT_NAME' => '1130-upgrade_install_php.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'run_upgrade_7g.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/1060-upgrade_7g_firewall.txt',
						'SCRIPT_NAME' => '1060-upgrade_7g_firewall.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'run_remove_6g.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/1120-remove_6g_firewall.txt',
						'SCRIPT_NAME' => '1120-remove_6g_firewall.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'run_upgrade_wpcli.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/1070-upgrade_wp_cli.txt',
						'SCRIPT_NAME' => '1070-upgrade_wp_cli.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'run_upgrade_install_php_intl.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/1080-upgrade_install_php_intl_module.txt',
						'SCRIPT_NAME' => '1080-upgrade_install_php_intl_module.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'run_upgrade_cache_enabler_nginx_config.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/1100-upgrade_cache_enabler.txt',
						'SCRIPT_NAME' => '1100-upgrade_cache_enabler.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'server_status_callback.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/24-server_status.txt',
						'SCRIPT_NAME' => '24-server_status.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'maldet.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/26-lmd_clamav.txt',
						'SCRIPT_NAME' => '26-lmd_clamav.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'server_restart_callback.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/28-restart_callback.txt',
						'SCRIPT_NAME' => '28-restart_callback.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'schedule_server_reboot.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/36-schedule-server-reboot.txt',
						'SCRIPT_NAME' => '36-schedule-server-reboot.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'backup_config_files.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/37-backup-configuration.txt',
						'SCRIPT_NAME' => '37-backup-configuration.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'goaccess.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/27-goaccess.txt',
						'SCRIPT_NAME' => '27-goaccess.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'fail2ban.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/23-fail2ban.txt',
						'SCRIPT_NAME' => '23-fail2ban.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'server_update.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/29-server_update.txt',
						'SCRIPT_NAME' => '29-server_update.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'server_php_version.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/38-server_php_version.txt',
						'SCRIPT_NAME' => '38-server_php_version.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'git_control_server.txt':
				$command_name = $additional['command'];
				$new_array    = array_merge(
					array(
						'SCRIPT_URL'   => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/58-git_control.txt',
						'SCRIPT_NAME'  => '58-git_control.sh',
						'SCRIPT_LOGS'  => "{$this->get_app_name()}_{$command_name}",
						'CALLBACK_URL' => $this->get_command_url( $instance['server_id'], $command_name, 'completed' ),
					),
					$common_array,
					$additional
				);
				break;
			case 'ubuntu_pro_activate.txt':
				// This one's a long running command.
				$command_name = $additional['command'];
				$new_array    = array_merge(
					array(
						'SCRIPT_URL'   => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/54-ubuntupro.txt',
						'SCRIPT_NAME'  => '54-ubuntupro.sh',
						'SCRIPT_LOGS'  => "{$this->get_app_name()}_{$command_name}",
						'CALLBACK_URL' => $this->get_command_url( $instance['server_id'], $command_name, 'completed' ),
					),
					$common_array,
					$additional
				);
				break;
			case 'ubuntu_pro_actions.txt':
				// This one's a short command.
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/54-ubuntupro.txt',
						'SCRIPT_NAME' => '54-ubuntupro.sh',
					),
					$common_array,
					$additional
				);
				break;

			/**
			 *************************************************************
			 * The items below this are SERVER SYNC items, not APP items.
			 **************************************************************
			 */
			case 'server_sync_origin_setup.txt':
				$command_name = $additional['command'];
				$new_array    = array_merge(
					array(
						'SCRIPT_URL'   => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/71-origin.txt',
						'SCRIPT_NAME'  => '71-origin.sh',
						'SCRIPT_URL2'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/wp-sync',
						'SCRIPT_NAME2' => 'wp-sync',
					),
					$common_array,
					$additional
				);
				break;
			case 'server_sync_destination_setup.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/72-destination.txt',
						'SCRIPT_NAME' => '72-destination.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'server_sync_manage.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/71-origin.txt',
						'SCRIPT_NAME' => '71-origin.sh',
					),
					$common_array,
					$additional
				);
				break;
		}

		$new_array = apply_filters( 'wpcd_wpapp_replace_script_tokens', $new_array, $array, $script_name, $script_version, $instance, $command, $additional );

		return array_merge( $array, $new_array );
	}

}
