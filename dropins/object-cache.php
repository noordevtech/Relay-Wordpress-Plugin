<?php
/**
 * Plugin Name: Relay Cache Drop-In
 * Plugin URI: https://github.com/noordevtech/relay-wordpress-plugin
 * Description: WordPress object cache drop-in backed by the Relay PHP extension (Redis + in-process shared memory).
 * Version: 1.0.0
 * Author: NoorDev
 * License: GPL-2.0-or-later
 *
 * @package RelayCache
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// If Relay isn't available, silently fall back to the default in-memory cache by not defining WP_Object_Cache.
if ( ! extension_loaded( 'relay' ) || ! class_exists( '\\Relay\\Relay' ) ) {
	return;
}

/**
 * Retrieve the global cache instance.
 *
 * @return WP_Object_Cache
 */
function wp_cache_init() {
	$GLOBALS['wp_object_cache'] = new WP_Object_Cache();
	return $GLOBALS['wp_object_cache'];
}

function wp_cache_add( $key, $data, $group = '', $expire = 0 ) {
	return $GLOBALS['wp_object_cache']->add( $key, $data, $group, (int) $expire );
}

function wp_cache_add_multiple( array $data, $group = '', $expire = 0 ) {
	$values = array();
	foreach ( $data as $key => $value ) {
		$values[ $key ] = wp_cache_add( $key, $value, $group, $expire );
	}
	return $values;
}

function wp_cache_replace( $key, $data, $group = '', $expire = 0 ) {
	return $GLOBALS['wp_object_cache']->replace( $key, $data, $group, (int) $expire );
}

function wp_cache_set( $key, $data, $group = '', $expire = 0 ) {
	return $GLOBALS['wp_object_cache']->set( $key, $data, $group, (int) $expire );
}

function wp_cache_set_multiple( array $data, $group = '', $expire = 0 ) {
	$values = array();
	foreach ( $data as $key => $value ) {
		$values[ $key ] = wp_cache_set( $key, $value, $group, $expire );
	}
	return $values;
}

function wp_cache_get( $key, $group = '', $force = false, &$found = null ) {
	return $GLOBALS['wp_object_cache']->get( $key, $group, $force, $found );
}

function wp_cache_get_multiple( $keys, $group = '', $force = false ) {
	return $GLOBALS['wp_object_cache']->get_multiple( $keys, $group, $force );
}

function wp_cache_delete( $key, $group = '' ) {
	return $GLOBALS['wp_object_cache']->delete( $key, $group );
}

function wp_cache_delete_multiple( array $keys, $group = '' ) {
	$values = array();
	foreach ( $keys as $key ) {
		$values[ $key ] = wp_cache_delete( $key, $group );
	}
	return $values;
}

function wp_cache_incr( $key, $offset = 1, $group = '' ) {
	return $GLOBALS['wp_object_cache']->incr( $key, $offset, $group );
}

function wp_cache_decr( $key, $offset = 1, $group = '' ) {
	return $GLOBALS['wp_object_cache']->decr( $key, $offset, $group );
}

function wp_cache_flush() {
	return $GLOBALS['wp_object_cache']->flush();
}

function wp_cache_flush_runtime() {
	return $GLOBALS['wp_object_cache']->flush_runtime();
}

function wp_cache_flush_group( $group ) {
	return $GLOBALS['wp_object_cache']->flush_group( $group );
}

function wp_cache_supports( $feature ) {
	switch ( $feature ) {
		case 'add_multiple':
		case 'set_multiple':
		case 'get_multiple':
		case 'delete_multiple':
		case 'flush_runtime':
		case 'flush_group':
			return true;
		default:
			return false;
	}
}

function wp_cache_close() {
	return true;
}

function wp_cache_add_global_groups( $groups ) {
	$GLOBALS['wp_object_cache']->add_global_groups( $groups );
}

function wp_cache_add_non_persistent_groups( $groups ) {
	$GLOBALS['wp_object_cache']->add_non_persistent_groups( $groups );
}

function wp_cache_switch_to_blog( $blog_id ) {
	$GLOBALS['wp_object_cache']->switch_to_blog( $blog_id );
}

/**
 * WordPress Object Cache backed by Relay.
 */
class WP_Object_Cache {

	/**
	 * Runtime cache for the current request.
	 *
	 * @var array
	 */
	protected $cache = array();

	/**
	 * Relay client.
	 *
	 * @var \Relay\Relay|null
	 */
	protected $relay = null;

	/**
	 * Connected state.
	 *
	 * @var bool
	 */
	protected $connected = false;

	/**
	 * Global groups (shared across sites).
	 *
	 * @var array
	 */
	protected $global_groups = array();

	/**
	 * Non-persistent groups (runtime only).
	 *
	 * @var array
	 */
	protected $non_persistent_groups = array();

	/**
	 * Current blog prefix for multisite.
	 *
	 * @var int
	 */
	protected $blog_prefix = 1;

	/**
	 * Key prefix.
	 *
	 * @var string
	 */
	protected $key_prefix = '';

	/**
	 * Multisite flag.
	 *
	 * @var bool
	 */
	protected $multisite = false;

	/**
	 * Cache hits.
	 *
	 * @var int
	 */
	public $cache_hits = 0;

	/**
	 * Cache misses.
	 *
	 * @var int
	 */
	public $cache_misses = 0;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->multisite   = function_exists( 'is_multisite' ) && is_multisite();
		$this->blog_prefix = $this->multisite ? (int) get_current_blog_id() : 1;
		$this->connect();
	}

	/**
	 * Establish a connection to Relay/Redis.
	 */
	protected function connect() {
		$defaults = array(
			'host'     => '127.0.0.1',
			'port'     => 6379,
			'password' => '',
			'database' => 0,
			'prefix'   => '',
			'timeout'  => 1.0,
		);

		// The object-cache.php drop-in is loaded extremely early in wp-settings.php,
		// before $wpdb and the options API are available. We MUST NOT call get_option()
		// here. Configuration is read exclusively from wp-config.php constants.
		$settings = $defaults;

		if ( defined( 'WP_RELAY_HOST' ) ) {
			$settings['host'] = WP_RELAY_HOST;
		}
		if ( defined( 'WP_RELAY_PORT' ) ) {
			$settings['port'] = WP_RELAY_PORT;
		}
		if ( defined( 'WP_RELAY_PASSWORD' ) ) {
			$settings['password'] = WP_RELAY_PASSWORD;
		}
		if ( defined( 'WP_RELAY_DATABASE' ) ) {
			$settings['database'] = WP_RELAY_DATABASE;
		}
		if ( defined( 'WP_RELAY_PREFIX' ) ) {
			$settings['prefix'] = WP_RELAY_PREFIX;
		}
		if ( defined( 'WP_RELAY_TIMEOUT' ) ) {
			$settings['timeout'] = WP_RELAY_TIMEOUT;
		}

		$this->key_prefix = (string) $settings['prefix'];

		try {
			$this->relay = new \Relay\Relay();
			$this->relay->connect( $settings['host'], (int) $settings['port'], (float) $settings['timeout'] );
			if ( ! empty( $settings['password'] ) ) {
				$this->relay->auth( $settings['password'] );
			}
			if ( ! empty( $settings['database'] ) ) {
				$this->relay->select( (int) $settings['database'] );
			}
			$this->connected = true;
		} catch ( \Throwable $e ) {
			$this->connected = false;
			$this->relay     = null;
		}
	}

	/**
	 * Build the storage key for a (key, group) pair.
	 *
	 * @param string $key   Cache key.
	 * @param string $group Cache group.
	 * @return string
	 */
	protected function build_key( $key, $group ) {
		if ( empty( $group ) ) {
			$group = 'default';
		}
		$prefix = in_array( $group, $this->global_groups, true ) ? 'global' : (string) $this->blog_prefix;
		$full   = $this->key_prefix . 'wp:' . $prefix . ':' . $group . ':' . $key;
		return $full;
	}

	/**
	 * Determine whether a group should skip persistent storage.
	 *
	 * @param string $group Group.
	 * @return bool
	 */
	protected function is_non_persistent( $group ) {
		return in_array( $group, $this->non_persistent_groups, true );
	}

	public function add_global_groups( $groups ) {
		$groups              = (array) $groups;
		$this->global_groups = array_unique( array_merge( $this->global_groups, $groups ) );
	}

	public function add_non_persistent_groups( $groups ) {
		$groups                      = (array) $groups;
		$this->non_persistent_groups = array_unique( array_merge( $this->non_persistent_groups, $groups ) );
	}

	public function switch_to_blog( $blog_id ) {
		if ( ! $this->multisite ) {
			return false;
		}
		$this->blog_prefix = (int) $blog_id;
		return true;
	}

	public function add( $key, $data, $group = 'default', $expire = 0 ) {
		if ( function_exists( 'wp_suspend_cache_addition' ) && wp_suspend_cache_addition() ) {
			return false;
		}
		$id = $this->build_key( $key, $group );
		if ( isset( $this->cache[ $id ] ) ) {
			return false;
		}
		if ( $this->connected && ! $this->is_non_persistent( $group ) ) {
			try {
				$options = array( 'nx' );
				if ( $expire > 0 ) {
					$options['ex'] = (int) $expire;
				}
				$ok = $this->relay->set( $id, $this->maybe_serialize( $data ), $options );
				if ( ! $ok ) {
					// Fall back to EXISTS check.
					if ( $this->relay->exists( $id ) ) {
						return false;
					}
					$this->relay->set( $id, $this->maybe_serialize( $data ) );
					if ( $expire > 0 ) {
						$this->relay->expire( $id, (int) $expire );
					}
				}
			} catch ( \Throwable $e ) {
				return false;
			}
		}
		$this->cache[ $id ] = is_object( $data ) ? clone $data : $data;
		return true;
	}

	public function replace( $key, $data, $group = 'default', $expire = 0 ) {
		$id = $this->build_key( $key, $group );
		if ( $this->connected && ! $this->is_non_persistent( $group ) ) {
			try {
				if ( ! $this->relay->exists( $id ) ) {
					return false;
				}
				$this->relay->set( $id, $this->maybe_serialize( $data ) );
				if ( $expire > 0 ) {
					$this->relay->expire( $id, (int) $expire );
				}
			} catch ( \Throwable $e ) {
				return false;
			}
		} elseif ( ! isset( $this->cache[ $id ] ) ) {
			return false;
		}
		$this->cache[ $id ] = is_object( $data ) ? clone $data : $data;
		return true;
	}

	public function set( $key, $data, $group = 'default', $expire = 0 ) {
		$id = $this->build_key( $key, $group );
		if ( $this->connected && ! $this->is_non_persistent( $group ) ) {
			try {
				if ( $expire > 0 ) {
					$this->relay->setex( $id, (int) $expire, $this->maybe_serialize( $data ) );
				} else {
					$this->relay->set( $id, $this->maybe_serialize( $data ) );
				}
			} catch ( \Throwable $e ) {
				// Keep runtime cache updated even if persistence fails.
			}
		}
		$this->cache[ $id ] = is_object( $data ) ? clone $data : $data;
		return true;
	}

	public function get( $key, $group = 'default', $force = false, &$found = null ) {
		$id = $this->build_key( $key, $group );

		if ( ! $force && isset( $this->cache[ $id ] ) ) {
			$found = true;
			++$this->cache_hits;
			$value = $this->cache[ $id ];
			return is_object( $value ) ? clone $value : $value;
		}

		if ( $this->connected && ! $this->is_non_persistent( $group ) ) {
			try {
				$raw = $this->relay->get( $id );
				if ( false !== $raw && null !== $raw ) {
					$value              = $this->maybe_unserialize( $raw );
					$this->cache[ $id ] = is_object( $value ) ? clone $value : $value;
					$found              = true;
					++$this->cache_hits;
					return $value;
				}
			} catch ( \Throwable $e ) {
				// Fall through to miss.
			}
		}

		$found = false;
		++$this->cache_misses;
		return false;
	}

	public function get_multiple( $keys, $group = 'default', $force = false ) {
		$values = array();
		foreach ( (array) $keys as $key ) {
			$values[ $key ] = $this->get( $key, $group, $force );
		}
		return $values;
	}

	public function delete( $key, $group = 'default' ) {
		$id = $this->build_key( $key, $group );
		unset( $this->cache[ $id ] );
		if ( $this->connected && ! $this->is_non_persistent( $group ) ) {
			try {
				return (bool) $this->relay->del( $id );
			} catch ( \Throwable $e ) {
				return false;
			}
		}
		return true;
	}

	public function incr( $key, $offset = 1, $group = 'default' ) {
		$id = $this->build_key( $key, $group );
		if ( $this->connected && ! $this->is_non_persistent( $group ) ) {
			try {
				if ( ! $this->relay->exists( $id ) ) {
					return false;
				}
				$value              = $this->relay->incrBy( $id, (int) $offset );
				$this->cache[ $id ] = $value;
				return $value;
			} catch ( \Throwable $e ) {
				return false;
			}
		}
		if ( ! isset( $this->cache[ $id ] ) ) {
			return false;
		}
		$value              = (int) $this->cache[ $id ] + (int) $offset;
		$this->cache[ $id ] = $value;
		return $value;
	}

	public function decr( $key, $offset = 1, $group = 'default' ) {
		$id = $this->build_key( $key, $group );
		if ( $this->connected && ! $this->is_non_persistent( $group ) ) {
			try {
				if ( ! $this->relay->exists( $id ) ) {
					return false;
				}
				$value              = $this->relay->decrBy( $id, (int) $offset );
				$this->cache[ $id ] = $value;
				return $value;
			} catch ( \Throwable $e ) {
				return false;
			}
		}
		if ( ! isset( $this->cache[ $id ] ) ) {
			return false;
		}
		$value              = (int) $this->cache[ $id ] - (int) $offset;
		$this->cache[ $id ] = $value;
		return $value;
	}

	public function flush() {
		$this->cache = array();
		if ( $this->connected ) {
			try {
				$this->relay->flushdb();
				return true;
			} catch ( \Throwable $e ) {
				return false;
			}
		}
		return true;
	}

	public function flush_runtime() {
		$this->cache = array();
		return true;
	}

	public function flush_group( $group ) {
		$pattern = $this->build_key( '*', $group );
		foreach ( array_keys( $this->cache ) as $id ) {
			if ( 0 === strpos( $id, rtrim( $pattern, '*' ) ) ) {
				unset( $this->cache[ $id ] );
			}
		}
		if ( $this->connected ) {
			try {
				$iter = null;
				do {
					$keys = $this->relay->scan( $iter, $pattern, 500 );
					if ( is_array( $keys ) && ! empty( $keys ) ) {
						$this->relay->del( $keys );
					}
				} while ( $iter > 0 );
				return true;
			} catch ( \Throwable $e ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Serialize non-scalar values so they can be stored as Redis strings.
	 *
	 * @param mixed $data Value.
	 * @return string
	 */
	protected function maybe_serialize( $data ) {
		if ( is_numeric( $data ) || is_string( $data ) ) {
			return (string) $data;
		}
		return "\x00relay_serialized\x00" . serialize( $data ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
	}

	/**
	 * Reverse of maybe_serialize().
	 *
	 * @param string $data Value.
	 * @return mixed
	 */
	protected function maybe_unserialize( $data ) {
		if ( is_string( $data ) && 0 === strpos( $data, "\x00relay_serialized\x00" ) ) {
			return unserialize( substr( $data, 18 ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
		}
		return $data;
	}

	/**
	 * Informational stats output, as other object cache drop-ins provide.
	 */
	public function stats() {
		echo '<p>';
		echo '<strong>Cache Hits:</strong> ' . (int) $this->cache_hits . '<br />';
		echo '<strong>Cache Misses:</strong> ' . (int) $this->cache_misses . '<br />';
		echo '<strong>Relay connected:</strong> ' . ( $this->connected ? 'yes' : 'no' ) . '<br />';
		echo '</p>';
	}
}
