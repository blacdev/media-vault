<?php
/**
 * Plugin Name:       Secure Media Vault
 * Plugin URI:        https://github.com/blacdev/media-vault
 * Description:       Secure uploads of images (JPG, PNG) and audio (MP3, WAV) to a folder you control, optional Dropbox storage, reusable galleries and audio playlists via shortcodes, protected upload forms and Contact Form 7 support.
 * Version:           1.7.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            blacdev
 * Author URI:        https://github.com/blacdev
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       secure-media-vault
 * Domain Path:       /languages
 *
 * @package SecureMediaVault
 */

defined( 'ABSPATH' ) || exit;

// Never fatal the site: if another copy of this plugin (or anything using the same names) is loaded, stand down.
if ( defined( 'SMV_VERSION' ) || class_exists( 'SMV_Plugin', false ) ) {
	add_action(
		'admin_notices',
		function () {
			if ( current_user_can( 'activate_plugins' ) ) {
				echo '<div class="notice notice-error"><p><strong>Secure Media Vault:</strong> another copy of this plugin (or a plugin using the same names) is already active, so this copy did not load. Deactivate one of them.</p></div>';
			}
		}
	);
	return;
}

define( 'SMV_VERSION', '1.7.0' );
define( 'SMV_DB_VERSION', '4' );
define( 'SMV_FILE', __FILE__ );
define( 'SMV_DIR', plugin_dir_path( __FILE__ ) );
define( 'SMV_URL', plugin_dir_url( __FILE__ ) );

require_once SMV_DIR . 'includes/class-smv-crypto.php';
require_once SMV_DIR . 'includes/class-smv-settings.php';
require_once SMV_DIR . 'includes/class-smv-installer.php';
require_once SMV_DIR . 'includes/class-smv-files.php';
require_once SMV_DIR . 'includes/class-smv-collections.php';
require_once SMV_DIR . 'includes/class-smv-dropbox.php';
require_once SMV_DIR . 'includes/class-smv-storage.php';
require_once SMV_DIR . 'includes/class-smv-download.php';
require_once SMV_DIR . 'includes/class-smv-shortcodes.php';
require_once SMV_DIR . 'includes/class-smv-forms.php';
require_once SMV_DIR . 'includes/class-smv-cf7.php';
require_once SMV_DIR . 'includes/class-smv-ajax.php';
require_once SMV_DIR . 'includes/class-smv-admin.php';
require_once SMV_DIR . 'includes/class-smv-plugin.php';

register_activation_hook( __FILE__, array( 'SMV_Installer', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'SMV_Installer', 'deactivate' ) );

// Boot on init (priority 0): after translations are allowed to load (WP 6.7+), before any request handling.
add_action( 'init', array( 'SMV_Plugin', 'instance' ), 0 );
