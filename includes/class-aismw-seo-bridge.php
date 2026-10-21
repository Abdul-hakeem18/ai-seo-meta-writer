<?php
/**
 * Saves descriptions where the active SEO plugin reads them.
 *
 * @package AISEOMetaWriter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Bridges to Yoast SEO, Rank Math, or the plugin's own meta tag.
 */
class AISMW_SEO_Bridge {

	const OWN_META = '_aismw_meta_description';

	/** Hook into WordPress. */
	public function register() {
		add_action( 'wp_head', array( $this, 'output_own_meta' ), 1 );
	}

	/**
	 * Which SEO plugin is active.
	 *
	 * @return string "yoast", "rankmath" or "none".
	 */
	public function provider() {
		if ( defined( 'WPSEO_VERSION' ) ) {
			return 'yoast';
		}
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			return 'rankmath';
		}
		return 'none';
	}

	/**
	 * Meta key for the active provider.
	 *
	 * @return string
	 */
	public function meta_key() {
		switch ( $this->provider() ) {
			case 'yoast':
				return '_yoast_wpseo_metadesc';
			case 'rankmath':
				return 'rank_math_description';
			default:
				return self::OWN_META;
		}
	}

	/**
	 * Current description for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public function get( $post_id ) {
		return (string) get_post_meta( $post_id, $this->meta_key(), true );
	}

	/**
	 * Save a description.
	 *
	 * @param int    $post_id     Post ID.
	 * @param string $description Description.
	 * @return bool
	 */
	public function save( $post_id, $description ) {
		return false !== update_post_meta( $post_id, $this->meta_key(), sanitize_text_field( $description ) );
	}

	/** Print a meta description tag when no SEO plugin is handling it. */
	public function output_own_meta() {
		if ( 'none' !== $this->provider() || ! is_singular() ) {
			return;
		}
		$description = $this->get( get_queried_object_id() );
		if ( '' !== $description ) {
			echo '<meta name="description" content="' . esc_attr( $description ) . '" />' . "\n";
		}
	}
}
