<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://codex.wordpress.org/Editing_wp-config.php
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'local' );

/** MySQL database username */
define( 'DB_USER', 'root' );

/** MySQL database password */
define( 'DB_PASSWORD', 'root' );

/** MySQL hostname */
define( 'DB_HOST', 'localhost' );

/** Database Charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The Database Collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         'w8FnPMh0iH8W/thqmaaKG/3Ri1DUjkUeAp54wvBYPAo1D0W5SA20fzytHVEMB0WKouwJ/1hprr9Y88JEwv04nw==');
define('SECURE_AUTH_KEY',  'lopU/5p3v1WRL+bGM3B8vQsVVAEBZxz5kwqtfRuVkx1+nEkO7oIJX5eUtwGErFgQebq4xSRRmL7heKZzIoGPzA==');
define('LOGGED_IN_KEY',    'peksIFVuNvSAt91zHAwZW8njGr73zdYKYNU7/eC4IjskYxFe7xrHfIDTKbKaGX6KIDJR0hU+otiX45YSsks1hg==');
define('NONCE_KEY',        '0h9+6uc5dkKfRyYK9279IO4n2VeJFAURrBmF+NizR3hRDqfbVJeZPAl4wO96BlxQT+5OBpqxgj1wKWdA3mEMtA==');
define('AUTH_SALT',        'w/QkCxosXlINWHx7I6GnYI6JWF1n9aHyCQzHKx3niepQc6Yo1s7fhw9QknFeHDoXuakrZ9anROdQGmTgTA7e9A==');
define('SECURE_AUTH_SALT', 'O3IpQxLYTTD7RRCKb5XaF64b5pU2CghDhEINafjmbsDukC51HIbY5N35WmC/ff8sd0Nr82Y6EWctZB8Kc64Y7w==');
define('LOGGED_IN_SALT',   'lY5LcR6idld3YnivYZUoJE3CgCMaG9EQtmkZouF4GCsKOH8aoyMtK+B3KWhw9We7GYyjuLsYRE4cM2ZXqSDQgg==');
define('NONCE_SALT',       'O1JX26WzztLSBOehhKD1RMO9mEIESIJyurii8jk3mdZrk7EBq1f4KuGXmwtHFxQqyzG0TSWMbARPp9/LzcAQkQ==');

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';




/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __FILE__ ) . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
