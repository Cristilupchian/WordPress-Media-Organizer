<?php
/**
 * Plugin Name: Media Folders Organizer
 * Description: Organize WordPress Media Library attachments into virtual folders.
 * Version: 0.1.0
 * Author: OpenAI
 * Text Domain: media-folders-organizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'MFO_PLUGIN_PATH' ) ) {
	define( 'MFO_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'MFO_PLUGIN_URL' ) ) {
	define( 'MFO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'MFO_VERSION' ) ) {
	define( 'MFO_VERSION', '0.1.0' );
}

require_once MFO_PLUGIN_PATH . 'includes/class-mfo-helpers.php';
require_once MFO_PLUGIN_PATH . 'includes/class-mfo-taxonomy.php';
require_once MFO_PLUGIN_PATH . 'includes/class-mfo-rest-folders.php';
require_once MFO_PLUGIN_PATH . 'includes/class-mfo-admin.php';
require_once MFO_PLUGIN_PATH . 'includes/class-mfo-assignments.php';

final class MFO_Plugin {
	private static $instance;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( 'MFO_Taxonomy', 'register' ) );
		add_action( 'rest_api_init', array( 'MFO_Rest_Folders', 'register_routes' ) );

		if ( is_admin() ) {
			MFO_Admin::init();
			MFO_Assignments::init();
		}
	}
}

MFO_Plugin::get_instance();
