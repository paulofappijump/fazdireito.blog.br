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
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'fazdireito' );

/** Database username */
define( 'DB_USER', 'admincogna' );

/** Database password */
define( 'DB_PASSWORD', 'szb9iaAM6Ao-L8g11d98uwGh' );

/** Database hostname */
define( 'DB_HOST', 'cognablogs-db.ci9niqaqsefm.us-east-1.rds.amazonaws.com' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

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
define( 'AUTH_KEY',         'rbZd#?dt+ds;XGuP! l$UU[{=R0$<g4ok5e~6`LrEae$VLN:WDF/3l f!YSNyn1i' );
define( 'SECURE_AUTH_KEY',  'W~[wscok=7$:fcLf%%{Le<sjw|iL]N5!/_TcaYDMosdPfX%vRy`AX2|]C>]-t*f=' );
define( 'LOGGED_IN_KEY',    'qH%wux~Tg2q!?r GBz|:3#}U[EBC8ohBgIOD(U(ZpE%raN~$A>u|yRCtuGYJFb{z' );
define( 'NONCE_KEY',        'N,S-ky>W`Kdf}2~R pFo2Dg.^03!H{7adK{{(CZ|V_*-3Y9S9==~hh/t<A*2CQXA' );
define( 'AUTH_SALT',        'D@_-}r)NDgr}*SifFOxR0z=u~Gf#aRTF@xt-SCs#0i?jKzY+2G^qbH+U!^{C,3vq' );
define( 'SECURE_AUTH_SALT', '8n[XxOD> [^(.sz}H^0uYwE/ge.[1P(D-Vu)g~muFOv$rvIJD #YY[3)Hh6IHQG}' );
define( 'LOGGED_IN_SALT',   '<ignyH/Z%2]Ii&9<_nopqErM>fO#i.8$mw/D:JK(sPNJTn3aEMM*1|P,r]G$v-wA' );
define( 'NONCE_SALT',       'pt<cTtbrrvjAa@;HRA]!b(?uOIS.e-zRm>f[[pAN>mF5Viq/Cza>S1)ving(Y5P?' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';

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
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
