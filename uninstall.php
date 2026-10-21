<?php
/**
 * Remove plugin data on uninstall. Descriptions saved into Yoast SEO or
 * Rank Math are left in place because they belong to those plugins.
 *
 * @package AISEOMetaWriter
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'aismw_settings' );
delete_post_meta_by_key( '_aismw_meta_description' );
