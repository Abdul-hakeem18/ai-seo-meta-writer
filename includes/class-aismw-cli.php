<?php
/**
 * WP-CLI command for bulk generation.
 *
 * @package AISEOMetaWriter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Generate meta descriptions in bulk.
 */
class AISMW_CLI {

	/**
	 * Settings.
	 *
	 * @var AISMW_Settings
	 */
	private $settings;

	/**
	 * Client.
	 *
	 * @var AISMW_Client
	 */
	private $client;

	/**
	 * Bridge.
	 *
	 * @var AISMW_SEO_Bridge
	 */
	private $bridge;

	/**
	 * Constructor.
	 *
	 * @param AISMW_Settings   $settings Settings.
	 * @param AISMW_Client     $client   Client.
	 * @param AISMW_SEO_Bridge $bridge   Bridge.
	 */
	public function __construct( AISMW_Settings $settings, AISMW_Client $client, AISMW_SEO_Bridge $bridge ) {
		$this->settings = $settings;
		$this->client   = $client;
		$this->bridge   = $bridge;
	}

	/**
	 * Generate descriptions for items that don't have one yet.
	 *
	 * ## OPTIONS
	 *
	 * [--post_type=<type>]
	 * : Post type to process. Default: product.
	 *
	 * [--limit=<number>]
	 * : Maximum items to process. Default: 20.
	 *
	 * [--dry-run]
	 * : Show the suggestions without saving them.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ai-meta generate --post_type=product --limit=50
	 *     wp ai-meta generate --post_type=post --dry-run
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Named args.
	 */
	public function generate( $args, $assoc_args ) {
		$type    = isset( $assoc_args['post_type'] ) ? sanitize_key( $assoc_args['post_type'] ) : 'product';
		$limit   = isset( $assoc_args['limit'] ) ? max( 1, (int) $assoc_args['limit'] ) : 20;
		$dry_run = isset( $assoc_args['dry-run'] );

		$ids = get_posts(
			array(
				'post_type'      => $type,
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'OR',
					array(
						'key'     => $this->bridge->meta_key(),
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'   => $this->bridge->meta_key(),
						'value' => '',
					),
				),
			)
		);

		if ( empty( $ids ) ) {
			WP_CLI::success( 'Nothing to do: every item already has a description.' );
			return;
		}

		$done = 0;
		foreach ( $ids as $id ) {
			$result = $this->client->generate_for_post( get_post( $id ) );
			if ( is_wp_error( $result ) ) {
				WP_CLI::warning( "#{$id}: " . $result->get_error_message() );
				continue;
			}
			if ( ! $dry_run ) {
				$this->bridge->save( $id, $result );
			}
			WP_CLI::log( "#{$id} " . get_the_title( $id ) . ': ' . $result );
			++$done;
		}

		WP_CLI::success( sprintf( '%d of %d items %s.', $done, count( $ids ), $dry_run ? 'previewed' : 'updated' ) );
	}
}
