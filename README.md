# Relay Cache — WordPress Plugin

A WordPress object cache plugin backed by the [Relay](https://relay.so) PHP extension. Relay combines a Redis client with an in-process shared memory cache, serving frequently accessed entries directly from PHP memory and avoiding most Redis round-trips.

## Why Relay?

- **Up to 8× faster** response times vs. PhpRedis for cache-hit heavy workloads
- **Higher hit ratio** thanks to a local in-process cache layer
- **Lower Redis load** and more stable memory usage
- **Higher throughput** under heavy traffic
- Ideal for high-traffic sites, large WooCommerce stores, headless WordPress, LMS/membership platforms, and performance-focused hosting stacks.

## Requirements

- WordPress 5.8+
- PHP 7.4+
- [Relay PHP extension](https://relay.so/docs/1.x/installation) installed and enabled
- A reachable Redis server

## Installation

1. Copy this plugin directory to `wp-content/plugins/relay-cache`.
2. Activate **Relay Cache** in the WordPress admin.
3. Go to **Settings → Relay Cache** and configure your Redis connection (host, port, password, database, prefix, timeout).
4. Click **Enable Object Cache** to install the `wp-content/object-cache.php` drop-in.

## Configuration via `wp-config.php`

Optional constants that override the settings stored in the database:

```php
define( 'WP_RELAY_HOST',     '127.0.0.1' );
define( 'WP_RELAY_PORT',     6379 );
define( 'WP_RELAY_PASSWORD', 'secret' );
define( 'WP_RELAY_DATABASE', 0 );
define( 'WP_RELAY_PREFIX',   'mysite:' );
define( 'WP_RELAY_TIMEOUT',  1.0 );
```

## Features

- Full `WP_Object_Cache` API implementation (`add`, `set`, `get`, `delete`, `incr`, `decr`, multi-key variants, `flush`, `flush_runtime`, `flush_group`).
- Multisite-aware key namespacing with global and non-persistent group support.
- Safe fallback: if Relay isn't loaded or the connection fails, WordPress transparently falls back to its default in-memory cache for the request.
- Admin UI shows extension status, drop-in status, and a live connection test.
- One-click install / remove of the `object-cache.php` drop-in and a cache flush button.

## How it works

The plugin ships a WordPress object-cache drop-in (`dropins/object-cache.php`) which is copied to `wp-content/object-cache.php` when enabled. The drop-in creates a `\Relay\Relay` client, proxies all `wp_cache_*` calls through it, and keeps a per-request runtime cache for the lowest possible latency. Relay itself additionally maintains a shared in-process memory cache across requests, so most reads never hit the network at all.

## Links

- [Relay docs](https://relay.so/docs/1.x/installation)
- [Object Cache Pro on Relay](https://objectcache.pro/docs/relay)

## License

GPL-2.0-or-later
