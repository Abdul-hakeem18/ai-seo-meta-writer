<?php
/**
 * Plugin Name:       AI SEO Meta Writer
 * Plugin URI:        https://github.com/Abdul-hakeem18/ai-seo-meta-writer
 * Description:       Generates SEO meta descriptions for posts, pages and WooCommerce products with an AI model, and saves them to Yoast SEO, Rank Math or the plugin's own meta tag.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Abdul Hakeem
 * Author URI:        https://www.linkedin.com/in/abdul-hakeem-a0b075254/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ai-seo-meta-writer
 *
 * @package AISEOMetaWriter
 */

defined( 'ABSPATH' ) || exit;

define( 'AISMW_VERSION', '1.0.0' );
define( 'AISMW_FILE', __FILE__ );
define( 'AISMW_DIR', plugin_dir_path( __FILE__ ) );
define( 'AISMW_URL', plugin_dir_url( __FILE__ ) );

require_once AISMW_DIR . 'includes/class-aismw-generator.php';
require_once AISMW_DIR . 'includes/class-aismw-settings.php';
require_once AISMW_DIR . 'includes/class-aismw-client.php';
require_once AISMW_DIR . 'includes/class-aismw-seo-bridge.php';
require_once AISMW_DIR . 'includes/class-aismw-meta-box.php';

add_action(
	'plugins_loaded',
	static function () {
		$settings = new AISMW_Settings();
		$settings->register();

		$client = new AISMW_Client( $settings );
		$bridge = new AISMW_SEO_Bridge();
		$bridge->register();

		$meta_box = new AISMW_Meta_Box( $settings, $client, $bridge );
		$meta_box->register();

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once AISMW_DIR . 'includes/class-aismw-cli.php';
			WP_CLI::add_command( 'ai-meta', new AISMW_CLI( $settings, $client, $bridge ) );
		}
	}
);

add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	static function ( $links ) {
		$url = admin_url( 'options-general.php?page=' . AISMW_Settings::PAGE );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'ai-seo-meta-writer' ) . '</a>' );
		return $links;
	}
);
