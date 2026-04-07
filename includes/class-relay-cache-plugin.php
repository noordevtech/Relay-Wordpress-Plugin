<?php
/**
 * Main plugin class for Relay Cache.
 *
 * @package RelayCache
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Relay_Cache_Plugin
 */
class Relay_Cache_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Relay_Cache_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Relay_Cache_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_relay_cache_install_dropin', array( $this, 'handle_install_dropin' ) );
		add_action( 'admin_post_relay_cache_remove_dropin', array( $this, 'handle_remove_dropin' ) );
		add_action( 'admin_post_relay_cache_flush', array( $this, 'handle_flush' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
	}

	/**
	 * Plugin activation.
	 */
	public static function activate() {
		$defaults = array(
			'host'     => '127.0.0.1',
			'port'     => 6379,
			'password' => '',
			'database' => 0,
			'prefix'   => '',
			'timeout'  => 1.0,
		);
		if ( false === get_option( 'relay_cache_settings' ) ) {
			add_option( 'relay_cache_settings', $defaults );
		}
	}

	/**
	 * Plugin deactivation. Removes drop-in if it belongs to us.
	 */
	public static function deactivate() {
		$dropin = WP_CONTENT_DIR . '/object-cache.php';
		if ( file_exists( $dropin ) && self::dropin_is_ours( $dropin ) ) {
			@unlink( $dropin );
		}
	}

	/**
	 * Check whether the installed object-cache.php drop-in is the one shipped by this plugin.
	 *
	 * @param string $path Path to drop-in.
	 * @return bool
	 */
	public static function dropin_is_ours( $path ) {
		$contents = @file_get_contents( $path );
		if ( false === $contents ) {
			return false;
		}
		return false !== strpos( $contents, 'Relay Cache Drop-In' );
	}

	/**
	 * Register the admin menu page.
	 */
	public function register_admin_menu() {
		add_options_page(
			__( 'Relay Cache', 'relay-cache' ),
			__( 'Relay Cache', 'relay-cache' ),
			'manage_options',
			'relay-cache',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		register_setting(
			'relay_cache_settings_group',
			'relay_cache_settings',
			array( $this, 'sanitize_settings' )
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$out             = array();
		$out['host']     = isset( $input['host'] ) ? sanitize_text_field( $input['host'] ) : '127.0.0.1';
		$out['port']     = isset( $input['port'] ) ? absint( $input['port'] ) : 6379;
		$out['password'] = isset( $input['password'] ) ? (string) $input['password'] : '';
		$out['database'] = isset( $input['database'] ) ? absint( $input['database'] ) : 0;
		$out['prefix']   = isset( $input['prefix'] ) ? sanitize_text_field( $input['prefix'] ) : '';
		$out['timeout']  = isset( $input['timeout'] ) ? floatval( $input['timeout'] ) : 1.0;
		return $out;
	}

	/**
	 * Show transient admin notices.
	 */
	public function admin_notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$notice = get_transient( 'relay_cache_notice' );
		if ( $notice ) {
			delete_transient( 'relay_cache_notice' );
			$type = isset( $notice['type'] ) ? $notice['type'] : 'success';
			printf(
				'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
				esc_attr( $type ),
				esc_html( $notice['message'] )
			);
		}
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = wp_parse_args(
			get_option( 'relay_cache_settings', array() ),
			array(
				'host'     => '127.0.0.1',
				'port'     => 6379,
				'password' => '',
				'database' => 0,
				'prefix'   => '',
				'timeout'  => 1.0,
			)
		);

		$relay_loaded     = extension_loaded( 'relay' );
		$dropin_path      = WP_CONTENT_DIR . '/object-cache.php';
		$dropin_exists    = file_exists( $dropin_path );
		$dropin_is_ours   = $dropin_exists ? self::dropin_is_ours( $dropin_path ) : false;
		$connection_state = $this->test_connection( $settings );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Relay Cache', 'relay-cache' ); ?></h1>
			<p><?php esc_html_e( 'High-performance WordPress object cache backed by the Relay PHP extension.', 'relay-cache' ); ?></p>

			<h2><?php esc_html_e( 'Status', 'relay-cache' ); ?></h2>
			<table class="widefat striped" style="max-width:780px;">
				<tbody>
					<tr>
						<th><?php esc_html_e( 'Relay PHP extension', 'relay-cache' ); ?></th>
						<td>
							<?php if ( $relay_loaded ) : ?>
								<span style="color:#46b450;">&#10003; <?php echo esc_html( phpversion( 'relay' ) ); ?></span>
							<?php else : ?>
								<span style="color:#dc3232;">&#10007; <?php esc_html_e( 'Not installed', 'relay-cache' ); ?></span> &mdash;
								<a href="https://relay.so/docs/1.x/installation" target="_blank" rel="noopener"><?php esc_html_e( 'Installation docs', 'relay-cache' ); ?></a>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Object cache drop-in', 'relay-cache' ); ?></th>
						<td>
							<?php if ( $dropin_is_ours ) : ?>
								<span style="color:#46b450;">&#10003; <?php esc_html_e( 'Installed (Relay Cache)', 'relay-cache' ); ?></span>
							<?php elseif ( $dropin_exists ) : ?>
								<span style="color:#dba617;">&#9888; <?php esc_html_e( 'A different object-cache.php is installed.', 'relay-cache' ); ?></span>
							<?php else : ?>
								<span style="color:#dc3232;">&#10007; <?php esc_html_e( 'Not installed', 'relay-cache' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Redis connection', 'relay-cache' ); ?></th>
						<td>
							<?php if ( true === $connection_state ) : ?>
								<span style="color:#46b450;">&#10003; <?php esc_html_e( 'Connected', 'relay-cache' ); ?></span>
							<?php else : ?>
								<span style="color:#dc3232;">&#10007; <?php echo esc_html( is_string( $connection_state ) ? $connection_state : __( 'Failed', 'relay-cache' ) ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				</tbody>
			</table>

			<p style="margin-top:1em;">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
					<?php wp_nonce_field( 'relay_cache_install_dropin' ); ?>
					<input type="hidden" name="action" value="relay_cache_install_dropin" />
					<button type="submit" class="button button-primary" <?php disabled( ! $relay_loaded ); ?>>
						<?php echo $dropin_is_ours ? esc_html__( 'Reinstall Drop-In', 'relay-cache' ) : esc_html__( 'Enable Object Cache', 'relay-cache' ); ?>
					</button>
				</form>
				<?php if ( $dropin_is_ours ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
						<?php wp_nonce_field( 'relay_cache_remove_dropin' ); ?>
						<input type="hidden" name="action" value="relay_cache_remove_dropin" />
						<button type="submit" class="button"><?php esc_html_e( 'Disable Object Cache', 'relay-cache' ); ?></button>
					</form>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
						<?php wp_nonce_field( 'relay_cache_flush' ); ?>
						<input type="hidden" name="action" value="relay_cache_flush" />
						<button type="submit" class="button"><?php esc_html_e( 'Flush Cache', 'relay-cache' ); ?></button>
					</form>
				<?php endif; ?>
			</p>

			<h2><?php esc_html_e( 'Connection Settings', 'relay-cache' ); ?></h2>
			<form method="post" action="options.php">
				<?php settings_fields( 'relay_cache_settings_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="relay_host"><?php esc_html_e( 'Host', 'relay-cache' ); ?></label></th>
						<td><input name="relay_cache_settings[host]" id="relay_host" type="text" class="regular-text" value="<?php echo esc_attr( $settings['host'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="relay_port"><?php esc_html_e( 'Port', 'relay-cache' ); ?></label></th>
						<td><input name="relay_cache_settings[port]" id="relay_port" type="number" min="1" max="65535" value="<?php echo esc_attr( $settings['port'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="relay_password"><?php esc_html_e( 'Password', 'relay-cache' ); ?></label></th>
						<td><input name="relay_cache_settings[password]" id="relay_password" type="password" class="regular-text" value="<?php echo esc_attr( $settings['password'] ); ?>" autocomplete="new-password" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="relay_database"><?php esc_html_e( 'Database', 'relay-cache' ); ?></label></th>
						<td><input name="relay_cache_settings[database]" id="relay_database" type="number" min="0" value="<?php echo esc_attr( $settings['database'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="relay_prefix"><?php esc_html_e( 'Key Prefix', 'relay-cache' ); ?></label></th>
						<td><input name="relay_cache_settings[prefix]" id="relay_prefix" type="text" class="regular-text" value="<?php echo esc_attr( $settings['prefix'] ); ?>" />
							<p class="description"><?php esc_html_e( 'Optional prefix used to namespace keys (useful when sharing a Redis instance).', 'relay-cache' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="relay_timeout"><?php esc_html_e( 'Timeout (s)', 'relay-cache' ); ?></label></th>
						<td><input name="relay_cache_settings[timeout]" id="relay_timeout" type="number" step="0.1" min="0.1" value="<?php echo esc_attr( $settings['timeout'] ); ?>" /></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<h2><?php esc_html_e( 'About Relay', 'relay-cache' ); ?></h2>
			<p><?php esc_html_e( 'Relay is a modern PHP extension that combines a Redis client with an in-process shared memory cache. Frequently accessed cache entries are served from PHP memory, eliminating most network round-trips to Redis and producing dramatically faster response times.', 'relay-cache' ); ?></p>
			<ul style="list-style:disc; margin-left:1.5em;">
				<li><a href="https://relay.so/docs/1.x/installation" target="_blank" rel="noopener">relay.so/docs</a></li>
				<li><a href="https://objectcache.pro/docs/relay" target="_blank" rel="noopener">objectcache.pro/docs/relay</a></li>
			</ul>
		</div>
		<?php
	}

	/**
	 * Test the connection to Relay/Redis.
	 *
	 * @param array $settings Connection settings.
	 * @return true|string True on success, message on failure.
	 */
	public function test_connection( $settings ) {
		if ( ! extension_loaded( 'relay' ) ) {
			return __( 'Relay extension not loaded.', 'relay-cache' );
		}
		try {
			$relay = new \Relay\Relay();
			$relay->connect( $settings['host'], (int) $settings['port'], (float) $settings['timeout'] );
			if ( ! empty( $settings['password'] ) ) {
				$relay->auth( $settings['password'] );
			}
			if ( ! empty( $settings['database'] ) ) {
				$relay->select( (int) $settings['database'] );
			}
			$pong = $relay->ping();
			return ( $pong === true || $pong === '+PONG' || $pong === 'PONG' ) ? true : __( 'Unexpected ping response.', 'relay-cache' );
		} catch ( \Throwable $e ) {
			return $e->getMessage();
		}
	}

	/**
	 * Install the object-cache.php drop-in.
	 */
	public function handle_install_dropin() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'relay-cache' ) );
		}
		check_admin_referer( 'relay_cache_install_dropin' );

		$source = RELAY_CACHE_DIR . 'dropins/object-cache.php';
		$dest   = WP_CONTENT_DIR . '/object-cache.php';

		if ( file_exists( $dest ) && ! self::dropin_is_ours( $dest ) ) {
			$this->set_notice( __( 'A different object-cache.php drop-in already exists. Remove it before installing Relay Cache.', 'relay-cache' ), 'error' );
			wp_safe_redirect( admin_url( 'options-general.php?page=relay-cache' ) );
			exit;
		}

		if ( ! @copy( $source, $dest ) ) {
			$this->set_notice( __( 'Failed to copy drop-in. Check that wp-content is writable.', 'relay-cache' ), 'error' );
		} else {
			$this->set_notice( __( 'Relay object cache drop-in installed.', 'relay-cache' ), 'success' );
		}
		wp_safe_redirect( admin_url( 'options-general.php?page=relay-cache' ) );
		exit;
	}

	/**
	 * Remove the object-cache.php drop-in.
	 */
	public function handle_remove_dropin() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'relay-cache' ) );
		}
		check_admin_referer( 'relay_cache_remove_dropin' );

		$dest = WP_CONTENT_DIR . '/object-cache.php';
		if ( file_exists( $dest ) && self::dropin_is_ours( $dest ) && @unlink( $dest ) ) {
			$this->set_notice( __( 'Drop-in removed.', 'relay-cache' ), 'success' );
		} else {
			$this->set_notice( __( 'Could not remove drop-in.', 'relay-cache' ), 'error' );
		}
		wp_safe_redirect( admin_url( 'options-general.php?page=relay-cache' ) );
		exit;
	}

	/**
	 * Flush the object cache.
	 */
	public function handle_flush() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'relay-cache' ) );
		}
		check_admin_referer( 'relay_cache_flush' );
		if ( wp_cache_flush() ) {
			$this->set_notice( __( 'Object cache flushed.', 'relay-cache' ), 'success' );
		} else {
			$this->set_notice( __( 'Failed to flush object cache.', 'relay-cache' ), 'error' );
		}
		wp_safe_redirect( admin_url( 'options-general.php?page=relay-cache' ) );
		exit;
	}

	/**
	 * Store an admin notice for the next request.
	 *
	 * @param string $message Notice text.
	 * @param string $type    Notice type (success|error|warning|info).
	 */
	private function set_notice( $message, $type = 'success' ) {
		set_transient(
			'relay_cache_notice',
			array(
				'message' => $message,
				'type'    => $type,
			),
			60
		);
	}
}
