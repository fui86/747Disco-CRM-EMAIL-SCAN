<?php


/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'u314880962_fh0qa' );

/** Database username */
define( 'DB_USER', 'u314880962_N7oOv' );

/** Database password */
define( 'DB_PASSWORD', '64dX7if0ep' );

/** Database hostname */
define( 'DB_HOST', '127.0.0.1' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',          'm!!PG*GRlv ?yH61/rEeQ!xOr)OYl2t {KpbAp^%lT|]B.Kz ^_@P2D<C* +/dkp' );
define( 'SECURE_AUTH_KEY',   '=#X~Fi4V8euJ#zh4K`)hcq&TlMj7h4!cAb_kKZIhZ4b@}X67|gTm+91(ifX(|s{Y' );
define( 'LOGGED_IN_KEY',     '8)3W`>8|mVc>;V.b9(tRe{#njFd3])xAR>n5F8ZBeL)nBK(X](U-$C&CJWFaEIv4' );
define( 'NONCE_KEY',         'pcLDTb|V}<r=0UT6zh}9z[P|*)-)l$0cz{OPd?8z](Iqj>ic0)FsimUlmq&z,u}^' );
define( 'AUTH_SALT',         '9QL51#Q1N%m8aV-$rKa)a5^}5.>Eh`/F{dElVz$@ghP8_FDmFKJ(8.+.Tf3?3p/F' );
define( 'SECURE_AUTH_SALT',  'I]g7Q]9ggi9 *>y!vj!lF%%CG hz{PL0i.&.$c% EHW*m>gpB+AwkxB%IuZpY]C#' );
define( 'LOGGED_IN_SALT',    '6Q1H9zk%-fG;%0N?lVhN{x-z,uM{h&Nv28Vq^BsrU,/pfGIE4tbE/yHa>QV#LYN ' );
define( 'NONCE_SALT',        '-}B_}fNuPUt8R+wrux/A|]:qK~0t^;H)2;*/~!bv@Ns3; &oNO|6|T<a!*>N+)b>' );
define( 'WP_CACHE_KEY_SALT', 'B:HGI{aLeB|$Yy#s;3{0&KN;${rM1BC/8FR!BVIFmb=)Eb1qXr,me413I|Kz<=c[' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';


/* Add any custom values between this line and the "stop editing" line. */



/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */

// ✅ DEBUG ABILITATO - 747 Disco CRM
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'SCRIPT_DEBUG', true );
@ini_set( 'display_errors', 0 );
@ini_set( 'log_errors', 1 );
@ini_set( 'error_log', WP_CONTENT_DIR . '/debug.log' );

define( 'FS_METHOD', 'direct' );
define( 'COOKIEHASH', '0df9b9d8072b2a38c4e1c00ed8faaedb' );
define( 'WP_AUTO_UPDATE_CORE', 'minor' );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
