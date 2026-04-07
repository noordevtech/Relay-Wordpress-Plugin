<?php
/**
 * Plugin Name: Relay Cache
 * Plugin URI: https://github.com/noordevtech/relay-wordpress-plugin
 * Description: High-performance WordPress object cache powered by the Relay PHP extension. Relay combines a Redis client with an in-process shared memory cache, drastically reducing network round-trips to Redis.
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: NoorDev
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: relay-cache
 *
 * @package RelayCache
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RELAY_CACHE_VERSION', '1.0.0' );
define( 'RELAY_CACHE_FILE', __FILE__ );
define( 'RELAY_CACHE_DIR', plugin_dir_path( __FILE__ ) );
define( 'RELAY_CACHE_URL', plugin_dir_url( __FILE__ ) );

require_once RELAY_CACHE_DIR . 'includes/class-relay-cache-plugin.php';

register_activation_hook( __FILE__, array( 'Relay_Cache_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Relay_Cache_Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'Relay_Cache_Plugin', 'instance' ) );
