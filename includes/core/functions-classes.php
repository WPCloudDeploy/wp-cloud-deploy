<?php
/**
 * This file contains functions that spin up classes or declare globally accessible functions.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Function for calling WPCD methods and variables
 *
 * @return WP_CLOUD_DEPLOY;
 */
function WPCD() {
	return $GLOBALS['WP_CLOUD_DEPLOY'];  // No checking to see if it exists. If it doesn't we want to error out hard.
}

/**
 * Create a class var for WPCD_Server and
 * add it to the WPCD array of classes for management
 *
 * The WPCD_Server class is used to handle all things
 * related to just server operations such as creation,
 * reboot, delete and so on.
 */
add_action( 'init', 'wpcd_init_wpcd_server', -10, 1 );
function wpcd_init_wpcd_server() {
	if ( function_exists( 'WPCD' ) ) {
		if ( empty( WPCD()->classes['WPCD_SERVER'] ) ) {
			WPCD()->classes['WPCD_SERVER'] = new WPCD_Server();
		}
	}
}


/**
 * Create a class var for WPCD_Settings and
 * add it to the WPCD array of classes for management
 */
add_action( 'init', 'wpcd_init_wpcd_settings', -10, 1 );
function wpcd_init_wpcd_settings() {
	if ( function_exists( 'WPCD' ) ) {
		if ( empty( WPCD()->classes['WPCD_SETTINGS'] ) ) {
			WPCD()->classes['WPCD_SETTINGS'] = new WPCD_Settings();
		}
	}
}


/**
 * Maybe setup classes for custom tables for DNS and PROVIDERS.
 */
if ( true === wpcd_is_custom_dns_provider_tables_enabled() ) {
	require_once 'custom-table/api/class-wpcd-custom-table-api.php';
	require_once 'custom-table/api/class-wpcd-ct-provider-api.php';
	require_once 'custom-table/api/class-wpcd-ct-dns-provider-api.php';
	require_once 'custom-table/api/class-wpcd-ct-dns-zone-api.php';
	require_once 'custom-table/api/class-wpcd-ct-dns-zone-record-api.php';

	require_once 'custom-table/class-wpcd-ct-list-table.php';
	require_once 'custom-table/class-wpcd-ct-childs-list-table.php';
	require_once 'custom-table/class-wpcd-custom-table.php';
	require_once 'custom-table/class-wpcd-provider.php';
	require_once 'custom-table/class-wpcd-dns-provider.php';
	require_once 'custom-table/class-wpcd-dns-zone.php';
	require_once 'custom-table/class-wpcd-dns-zone-record.php';


	add_action( 'init', 'wpcd_init_wpcd_custom_table_classes', -10, 1 );


	/**
	 * Init custom table classes
	 */
	function wpcd_init_wpcd_custom_table_classes() {

		if ( ! function_exists( 'rwmb_request' ) ) {
			return;
		}

		if ( function_exists( 'WPCD' ) ) {
			if ( empty( WPCD()->classes['WPCD_PROVIDER_SETTINGS'] ) ) {
				WPCD()->classes['WPCD_PROVIDER_SETTINGS'] = WPCD_MB_Custom_Table::get( 'provider' );
			}

			if ( empty( WPCD()->classes['WPCD_DNS_PROVIDER_SETTINGS'] ) ) {
				WPCD()->classes['WPCD_DNS_PROVIDER_SETTINGS'] = WPCD_MB_Custom_Table::get( 'dns_provider' );
			}

			if ( empty( WPCD()->classes['WPCD_DNS_Zone'] ) ) {
				WPCD()->classes['WPCD_DNS_Zone'] = WPCD_MB_Custom_Table::get( 'dns_zone' );
			}

			if ( empty( WPCD()->classes['WPCD_DNS_Zone_Record'] ) ) {
				WPCD()->classes['WPCD_DNS_Zone_Record'] = WPCD_MB_Custom_Table::get( 'dns_zone_record' );
			}
		}
	}
}


/**
 * Create a class var for WPCD_Custom_Fields and
 * add it to the WPCD array of classes for management
 */
add_action( 'init', 'wpcd_init_wpcd_custom_fields', -10, 1 );
function wpcd_init_wpcd_custom_fields() {
	if ( function_exists( 'WPCD' ) ) {
		if ( empty( WPCD()->classes['WPCD_CUSTOM_FIELDS'] ) ) {
			WPCD()->classes['WPCD_CUSTOM_FIELDS'] = new WPCD_Custom_Fields();
		}
	}
}

/**
 * Function for calling WPCD_CUSTOM_FIELDS methods and variables
 *
 * @return WPCD_CUSTOM_FIELDS;
 */
function WPCD_CUSTOM_FIELDS() {
	return WPCD()->classes['WPCD_CUSTOM_FIELDS'];
}

/**
 * Create a class var for WPCD_WooCommerce and
 * add it to the WPCD array of classes for management
 */
add_action( 'init', 'wpcd_init_wpcd_woocommerce', -10, 1 );
function wpcd_init_wpcd_woocommerce() {
	if ( function_exists( 'WPCD' ) ) {
		if ( empty( WPCD()->classes['WPCD_WOOCOMMERCE'] ) ) {
			WPCD()->classes['WPCD_WOOCOMMERCE'] = new WPCD_WOOCOMMERCE();
		}
	}
}

/**
 * Function for calling WPCD_WooCommerce methods and variables
 *
 * @return WPCD_WOOCOMMERCE;
 */
function WPCD_WOOCOMMERCE() {
	return WPCD()->classes['WPCD_WOOCOMMERCE'];
}


/**
 * Create a class var for WPCD_DNS and
 * add it to the WPCD array of classes for management
 */
add_action( 'init', 'wpcd_init_wpcd_dns', -10, 1 );
function wpcd_init_wpcd_dns() {
	if ( function_exists( 'WPCD' ) ) {
		if ( empty( WPCD()->classes['WPCD_DNS'] ) ) {
			WPCD()->classes['WPCD_DNS'] = new WPCD_DNS();
		}
	}
}

/**
 * Function for calling WPCD_DNS methods and variables
 *
 * @return WPCD_DNS;
 */
function WPCD_DNS() {
	return WPCD()->classes['WPCD_DNS'];
}

/**
 * Create a class var for WPCD_VPN_APP and
 * add it to the WPCD array of classes for management
 *
 * This class handles all items related to the management
 * of the VPN app.
 *
 * @TODO: This really shouldn't happen here.  Instead
 * we need a process to have apps register their main
 * classes with WPCD and go from there.
 */
add_action( 'init', 'wpcd_init_vpn_app', -10, 1 );
function wpcd_init_vpn_app() {
	if ( function_exists( 'WPCD' ) ) {
		if ( class_exists( 'WPCD_VPN_APP' ) ) {
			if ( empty( WPCD()->classes['WPCD_VPN_APP'] ) ) {
				WPCD()->classes['WPCD_VPN_APP'] = new WPCD_VPN_APP();
			}
		}
	}
}

/**
 * Function for calling WPCD_VPN_APP methods and variables
 *
 * @return WPCD_VPN_APP;
 */
function WPCD_VPN_APP() {
	return WPCD()->classes['WPCD_VPN_APP'];
}

/**
 * Create a class var for WPCD_BASIC_SERVER_APP and
 * add it to the WPCD array of classes for management
 *
 * This class handles all items related to the management
 * of the BASIC SERVER app.
 *
 * @TODO: This really shouldn't happen here.  Instead
 * we need a process to have apps register their main
 * classes with WPCD and go from there.
 */
add_action( 'init', 'wpcd_init_basic_server_app', -10, 1 );
function wpcd_init_basic_server_app() {
	if ( function_exists( 'WPCD' ) ) {
		if ( class_exists( 'WPCD_BASIC_SERVER_APP' ) ) {
			if ( empty( WPCD()->classes['WPCD_BASIC_SERVER_APP'] ) ) {
				WPCD()->classes['WPCD_BASIC_SERVER_APP'] = new WPCD_BASIC_SERVER_APP();
			}
		}
	}
}

/**
 * Function for calling WPCD_BASIC_SERVER_APP methods and variables
 *
 * @return WPCD_BASIC_SERVER_APP;
 */
function WPCD_BASIC_SERVER_APP() {
	return WPCD()->classes['WPCD_BASIC_SERVER_APP'];
}

/**
 * Create a class var for WPCD_STABLEDIFF_APP and
 * add it to the WPCD array of classes for management
 *
 * This class handles all items related to the management
 * of the STABLEDIFF app.
 *
 * @TODO: This really shouldn't happen here.  Instead
 * we need a process to have apps register their main
 * classes with WPCD and go from there.
 */
add_action( 'init', 'wpcd_init_stablediff_app', -10, 1 );
function wpcd_init_stablediff_app() {
	if ( function_exists( 'WPCD' ) ) {
		if ( class_exists( 'WPCD_STABLEDIFF_APP' ) ) {
			if ( empty( WPCD()->classes['WPCD_STABLEDIFF_APP'] ) ) {
				WPCD()->classes['WPCD_STABLEDIFF_APP'] = new WPCD_STABLEDIFF_APP();
			}
		}
	}
}

/**
 * Function for calling WPCD_STABLEDIFF_APP methods and variables
 *
 * @return WPCD_STABLEDIFF_APP;
 */
function WPCD_STABLEDIFF_APP() {
	return WPCD()->classes['WPCD_STABLEDIFF_APP'];
}

/**
 * Create a class var for WPCD_WORDPRESS_APP and
 * add it to the WPCD array of classes for management
 *
 * This class handles all items related to the management
 * of the WORDPRESS_APP app.
 *
 * @TODO: This really shouldn't happen here.  Instead
 * we need a process to have apps register their main
 * classes with WPCD and go from there.
 */
add_action( 'init', 'wpcd_init_wordpress_app', -10, 1 );
function wpcd_init_wordpress_app() {
	if ( function_exists( 'WPCD' ) ) {
		if ( class_exists( 'WPCD_WORDPRESS_APP' ) ) {
			if ( empty( WPCD()->classes['WPCD_WORDPRESS_APP'] ) ) {
				WPCD()->classes['WPCD_WORDPRESS_APP'] = new WPCD_WORDPRESS_APP();
			}
		}
	}
}

/**
 * Function for calling WPCD_WORDPRESS_APP methods and variables
 *
 * @return WPCD_WORDPRESS_APP;
 */
function WPCD_WORDPRESS_APP() {
	return WPCD()->classes['WPCD_WORDPRESS_APP'];
}

/**
 * Function for calling WPCD_Settings methods and variables
 *
 * @return WPCD_Settings;
 */
function WPCD_SETTINGS() {
	return WPCD()->classes['WPCD_SETTINGS'];
}

/**
 * Function for calling WPCD_Server methods and variables
 *
 * @return WPCD_Server;
 */
function WPCD_SERVER() {
	return WPCD()->classes['WPCD_SERVER'];
}

/**
 * Function for calling wpcd_posts_app_server methods and variables.
 * wpcd_posts_app_server is the class that manages the
 * wpcd_app_server CPT
 *
 * @return WPCD_POSTS_APP_SERVER;
 */
function WPCD_POSTS_APP_SERVER() {
	return WPCD()->classes['wpcd_posts_app_server'];
}

/**
 * Function for calling wpcd_posts_cloud_provider methods and variables.
 * wpcd_posts_cloud_provider is the class that manages the
 * wpcd_cloud_provider CPT
 *
 * @return WPCD_POSTS_CLOUD_PROVIDER;
 */
function WPCD_POSTS_CLOUD_PROVIDER() {
	return WPCD()->classes['wpcd_posts_cloud_provider'];
}

/**
 * Function for calling wpcd_posts_app methods and variables.
 * wpcd_posts_app is the class that manages the
 * wpcd_app CPT.
 *
 * @return WPCD_POSTS_APP;
 */
function WPCD_POSTS_APP() {
	return WPCD()->classes['wpcd_posts_app'];
}

/**
 * Function for calling wpcd_notify_log methods and variables.
 * wpcd_notify_log is the class that manages the
 * wpcd_notify_log CPT.
 *
 * @return WPCD_NOTIFY_LOG;
 */
function WPCD_POSTS_NOTIFY_LOG() {
	return WPCD()->classes['wpcd_posts_notify_log'];
}


/**
 * Function for calling wpcd_notify_user methods and variables.
 * wpcd_notify_user is the class that manages the
 * wpcd_notify_user CPT.
 *
 * @return WPCD_NOTIFY_USER;
 */
function WPCD_POSTS_NOTIFY_USER() {
	return WPCD()->classes['wpcd_posts_notify_user'];
}

/**
 * Function for calling WPCD_NOTIFY_SENT methods and variables.
 * wpcd_notify_sent is the class that manages the
 * wpcd_notify_sent CPT.
 *
 * @return WPCD_NOTIFY_SENT;
 */
function WPCD_POSTS_NOTIFY_SENT() {
	return WPCD()->classes['wpcd_posts_notify_sent'];
}

/**
 * Function for calling wpcd_ssh_log methods and variables.
 * wpcd_ssh_log is the class that manages the
 * wpcd_ssh_log CPT.
 *
 * @return WPCD_SSH_LOG;
 */
function WPCD_POSTS_SSH_LOG() {
	return WPCD()->classes['wpcd_posts_ssh_log'];
}

/**
 * Function for calling wpcd_command_log methods and variables.
 * wpcd_command_log is the class that manages the
 * wpcd_command_log CPT.
 *
 * @return WPCD_COMMAND_LOG;
 */
function WPCD_POSTS_COMMAND_LOG() {
	return WPCD()->classes['wpcd_posts_command_log'];
}

/**
 * Function for calling wpcd_pending_tasks_log methods and variables.
 * wpcd_pending_tasks_log is the class that manages the
 * wpcd_pending_tasks_log CPT.
 *
 * @return WPCD_PENDING_TASKS_LOG;
 */
function WPCD_POSTS_PENDING_TASKS_LOG() {
	return WPCD()->classes['wpcd_posts_pending_tasks_log'];
}

/**
 * Function for calling wpcd_error_log methods and variables.
 * wpcd_error_log is the class that manages the
 * wpcd_error_log CPT.
 *
 * @return WPCD_ERROR_LOG;
 */
function WPCD_POSTS_ERROR_LOG() {
	return WPCD()->classes['wpcd_posts_error_log'];
}

/**
 * Function for calling wpcd_posts_team methods and variables.
 * wpcd_posts_team is the class that manages the
 * wpcd_team CPT.
 *
 * @return WPCD_POSTS_TEAM;
 */
function WPCD_POSTS_TEAM() {
	return WPCD()->classes['wpcd_posts_team'];
}

/**
 * Function for calling wpcd_posts_permission_type methods and variables.
 * wpcd_posts_permission_type is the class that manages the
 * wpcd_permission_type CPT.
 *
 * @return WPCD_POSTS_PERMISSION_TYPE;
 */
function WPCD_POSTS_PERMISSION_TYPE() {
	return WPCD()->classes['wpcd_posts_permission_type'];
}

/**
 * Create a class var for WPCD_APP_EXPIRATION and
 * add it to the WPCD array of classes for management
 */
add_action( 'init', 'wpcd_init_app_expiration', -10, 1 );
function wpcd_init_app_expiration() {
	if ( function_exists( 'WPCD' ) ) {
		if ( empty( WPCD()->classes['wpcd_app_expiration'] ) ) {
			WPCD()->classes['wpcd_app_expiration'] = new WPCD_App_Expiration();
		}
	}
}

/**
 * Function for calling wpcd_app_expiration methods and variables.
 * wpcd_app_expiration is the class that manages the app expriation methods.
 *
 * @return WPCD_App_Expiration;
 */
function WPCD_APP_EXPIRATION() {
	return WPCD()->classes['wpcd_app_expiration'];
}

/**
 * Create a class var for WPCD_SERVER_STATISTICS and
 * add it to the WPCD array of classes for management
 */
add_action( 'init', 'wpcd_init_wpcd_server_statistics', -10, 1 );
function wpcd_init_wpcd_server_statistics() {
	if ( function_exists( 'WPCD' ) ) {
		if ( empty( WPCD()->classes['wpcd_server_statistics'] ) ) {
			WPCD()->classes['wpcd_server_statistics'] = new WPCD_SERVER_STATISTICS();
		}
	}
}

/**
 * Function for calling wpcd_server_statistics methods and variables.
 * wpcd_server_statistics is the class that manages the server statistic methods.
 *
 * @return WPCD_SERVER_STATISTICS;
 */
function WPCD_SERVER_STATISTICS() {
	return WPCD()->classes['wpcd_server_statistics'];
}

/**
 * Create a class var for WPCD_DATA_SYNC_REST and
 * add it to the WPCD array of classes for management
 */
add_action( 'init', 'wpcd_init_wpcd_data_sync_rest', -10, 1 );
function wpcd_init_wpcd_data_sync_rest() {
	if ( function_exists( 'WPCD' ) ) {
		if ( empty( WPCD()->classes['wpcd_data_sync_rest'] ) ) {
			WPCD()->classes['wpcd_data_sync_rest'] = new WPCD_DATA_SYNC_REST();
		}
	}
}

/**
 * Function for calling wpcd_data_sync_rest methods and variables.
 * wpcd_data_sync_rest is the class that manages the custom REST endpoints.
 *
 * @return WPCD_DATA_SYNC_REST;
 */
function WPCD_DATA_SYNC_REST() {
	return WPCD()->classes['wpcd_data_sync_rest'];
}


/**
 * Create a class var for WPCD_SYNC and
 * add it to the WPCD array of classes for management
 */
add_action( 'init', 'wpcd_init_wpcd_sync', -10, 1 );
function wpcd_init_wpcd_sync() {
	if ( wpcd_data_sync_allowed() ) {
		if ( function_exists( 'WPCD' ) ) {
			if ( empty( WPCD()->classes['wpcd_sync'] ) ) {
				WPCD()->classes['wpcd_sync'] = new WPCD_SYNC();
			}
		}
	}
}

/**
 * Function for calling wpcd_sync methods and variables.
 * wpcd_sync is the class that manages the custom REST endpoints.
 *
 * @return WPCD_SYNC;
 */
function WPCD_SYNC() {
	return WPCD()->classes['wpcd_sync'];
}

/**
 * Create a class var for WPCD_EMAIL_NOTIFICATIONS and
 * add it to the WPCD array of classes for management
 */
add_action( 'init', 'wpcd_init_wpcd_email_notifications', -10, 1 );
function wpcd_init_wpcd_email_notifications() {
	if ( wpcd_email_notifications_allowed() ) {
		if ( function_exists( 'WPCD' ) ) {
			if ( empty( WPCD()->classes['wpcd_email_notifications'] ) ) {
				WPCD()->classes['wpcd_email_notifications'] = new WPCD_EMAIL_NOTIFICATIONS();
			}
		}
	}
}

/**
 * Function for calling WPCD_EMAIL_NOTIFICATIONS methods and variables.
 * wpcd_email_notifications is the class that manages the manual email notification.
 *
 * @return WPCD_EMAIL_NOTIFICATIONS;
 */
function WPCD_EMAIL_NOTIFICATIONS() {
	return WPCD()->classes['wpcd_email_notifications'];
}

/**
 * Create a class var for WPCD_WORDPRESS_APP_PUBLIC and
 * add it to the WPCD array of classes for management
 *
 * The WPCD_WORDPRESS_APP_PUBLIC class is used to handle all things
 * related to just front-end views/actions for servers and WordPress apps.
 */
add_action( 'init', 'wpcd_init_wordpress_app_public', -10, 1 );
function wpcd_init_wordpress_app_public() {
	if ( function_exists( 'WPCD' ) ) {
		if ( class_exists( 'WPCD_WORDPRESS_APP_PUBLIC' ) ) {
			if ( empty( WPCD()->classes['WPCD_WORDPRESS_APP_PUBLIC'] ) ) {
				WPCD()->classes['WPCD_WORDPRESS_APP_PUBLIC'] = new WPCD_WORDPRESS_APP_PUBLIC();
			}
		}
	}
}

/**
 * Function for calling WPCD_WORDPRESS_APP_PUBLIC methods and variables
 *
 * @return WPCD_WORDPRESS_APP_PUBLIC;
 */
function WPCD_WORDPRESS_APP_PUBLIC() {
	return WPCD()->classes['WPCD_WORDPRESS_APP_PUBLIC'];
}



/**
 * Create a class var for WPCD_LOGTIVITY and
 * add it to the WPCD array of classes for management
 */
add_action( 'init', 'wpcd_init_logtivity', -10, 1 );
function wpcd_init_logtivity() {
	if ( function_exists( 'WPCD' ) ) {
		if ( class_exists( 'WPCD_LOGTIVITY' ) ) {
			if ( empty( WPCD()->classes['WPCD_LOGTIVITY'] ) ) {
				WPCD()->classes['WPCD_LOGTIVITY'] = new WPCD_LOGTIVITY();
			}
		}
	}
}

/**
 * Function for calling WPCD_LOGTIVITY methods and variables
 *
 * @return WPCD_LOGTIVITY;
 */
function WPCD_LOGTIVITY() {
	return WPCD()->classes['WPCD_LOGTIVITY'];
}

