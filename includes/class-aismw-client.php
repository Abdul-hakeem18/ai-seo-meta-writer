<?php
/**
 * Calls an OpenAI-compatible chat completions API.
 *
 * @package AISEOMetaWriter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Thin HTTP client around wp_remote_post.
 */
class AISMW_Client {

	/**
	 * Settings.
	 *
	 * @var AISMW_Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param AISMW_Settings $settings Settings.
	 */
	public function __construct( AISMW_Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Generate a meta description for a post.
	 *
	 * @param WP_Post $post    Post object.
	 * @param string  $keyword Optional focus keyword.
	 * @return string|WP_Error
	 */
	public function generate_for_post( WP_Post $post, $keyword = '' ) {
		$content = $post->post_content;
		if ( 'product' === $post->post_type && '' !== trim( $post->post_excerpt ) ) {
			$content = $post->post_excerpt . "\n\n" . $content; // WooCommerce short description first.
		}

		$messages = AISMW_Generator::build_messages(
			get_the_title( $post ),
			$content,
			$post->post_type,
			(string) $this->settings->get( 'tone' ),
			$keyword
		);

		$raw = $this->chat( $messages );
		if ( is_wp_error( $raw ) ) {
			return $raw;
		}
		$text = AISMW_Generator::clean( $raw );
		if ( '' === $text ) {
			return new WP_Error( 'aismw_empty', __( 'The AI returned an empty answer. Try again.', 'ai-seo-meta-writer' ) );
		}

		/**
		 * Filter the generated description before it is shown or saved.
		 *
		 * @param string  $text Generated description.
		 * @param WP_Post $post Post.
		 */
		return apply_filters( 'aismw_generated_description', $text, $post );
	}

	/**
	 * Send chat messages and return the reply text.
	 *
	 * @param array $messages Chat messages.
	 * @return string|WP_Error
	 */
	public function chat( array $messages ) {
		$key = (string) $this->settings->get( 'api_key' );
		if ( '' === $key ) {
			return new WP_Error( 'aismw_no_key', __( 'Add your API key in Settings → AI SEO Meta Writer.', 'ai-seo-meta-writer' ) );
		}

		$response = wp_remote_post(
			(string) $this->settings->get( 'endpoint' ),
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'       => (string) $this->settings->get( 'model' ),
						'messages'    => $messages,
						'temperature' => 0.7,
						'max_tokens'  => 120,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'aismw_http', __( 'Could not reach the AI service: ', 'ai-seo-meta-writer' ) . $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code ) {
			$message = isset( $body['error']['message'] ) ? $body['error']['message'] : 'HTTP ' . $code;
			return new WP_Error( 'aismw_api', __( 'The AI service returned an error: ', 'ai-seo-meta-writer' ) . sanitize_text_field( $message ) );
		}

		if ( ! isset( $body['choices'][0]['message']['content'] ) ) {
			return new WP_Error( 'aismw_format', __( 'Unexpected response from the AI service.', 'ai-seo-meta-writer' ) );
		}

		return (string) $body['choices'][0]['message']['content'];
	}
}
