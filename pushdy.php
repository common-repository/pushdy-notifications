<?php

defined( 'ABSPATH' ) or die('This page may not be accessed directly.');

/**
 * Plugin Name: Pushdy Notifications
 * Plugin URI: https://www.pushdy.vn/
 * Description: Web push notifications from Pushdy.
 * Version: 1.0.0
 * Author: Pushdy
 * Author URI: https://www.pushdy.vn
 * License: MIT
 */

define( 'PUSHDY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * The number of seconds required to wait between requests.
 */
define( 'PUSHDY_API_RATE_LIMIT_SECONDS', 1 );
define( 'PUSHDY_URI_REVEAL_PROJECT_NUMBER', 'reveal_project_number=true' );

require_once( plugin_dir_path( __FILE__ ) . 'pushdy-utils.php' );
require_once( plugin_dir_path( __FILE__ ) . 'pushdy-admin.php' );
require_once( plugin_dir_path( __FILE__ ) . 'pushdy-public.php' );
require_once( plugin_dir_path( __FILE__ ) . 'pushdy-settings.php' );
require_once( plugin_dir_path( __FILE__ ) . 'pushdy-widget.php' );

add_action( 'init', array( 'Pushdy_Admin', 'init' ) );
add_action( 'init', array( 'Pushdy_Public', 'init' ) );

?>
