<?php
/**
 * Pure helpers: build the prompt and clean the model's answer.
 * No WordPress calls here, so this class can be unit tested on its own.
 *
 * @package AISEOMetaWriter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds prompts and normalises generated meta descriptions.
 */
class AISMW_Generator {

	/** Recommended maximum length shown in search results. */
	const MAX_LENGTH = 155;

	/** How much source text we send to the model. */
	const SOURCE_CHARS = 2000;

	/**
	 * Build the chat messages for the model.
	 *
	 * @param string $title     Post or product title.
	 * @param string $content   Post content (HTML allowed; it is stripped).
	 * @param string $post_type Post type label, e.g. "product".
	 * @param string $tone      Writing tone, e.g. "professional".
	 * @param string $keyword   Optional focus keyword.
	 * @return array<int, array{role: string, content: string}>
	 */
	public static function build_messages( $title, $content, $post_type, $tone = 'professional', $keyword = '' ) {
		$text = self::plain_text( $content );
		if ( function_exists( 'mb_substr' ) ) {
			$text = mb_substr( $text, 0, self::SOURCE_CHARS );
		} else {
			$text = substr( $text, 0, self::SOURCE_CHARS );
		}

		$rules = sprintf(
			'Write one SEO meta description for a %1$s. Rules: at most %2$d characters; %3$s tone; plain text only, no quotes, no emojis, no hashtags; include a reason to click; do not invent prices, discounts or facts that are not in the source.',
			$post_type,
			self::MAX_LENGTH,
			$tone
		);
		if ( '' !== $keyword ) {
			$rules .= sprintf( ' Use the focus keyword "%s" naturally once.', $keyword );
		}

		return array(
			array(
				'role'    => 'system',
				'content' => 'You are an SEO copywriter for WordPress sites. ' . $rules . ' Reply with the meta description only.',
			),
			array(
				'role'    => 'user',
				'content' => "Title: {$title}\n\nContent:\n{$text}",
			),
		);
	}

	/**
	 * Turn HTML/shortcode content into clean plain text.
	 *
	 * @param string $content Raw post content.
	 * @return string
	 */
	public static function plain_text( $content ) {
		$content = preg_replace( '/\[[^\]]*\]/', ' ', (string) $content ); // Shortcodes.
		$content = preg_replace( '/<(script|style)[^>]*>.*?<\/\1>/is', ' ', $content );
		$content = html_entity_decode( strip_tags( $content ), ENT_QUOTES, 'UTF-8' );
		return trim( preg_replace( '/\s+/u', ' ', $content ) );
	}

	/**
	 * Clean the model output and keep it within the length limit,
	 * cutting at a word boundary instead of mid-word.
	 *
	 * @param string $raw Model output.
	 * @param int    $max Maximum characters.
	 * @return string
	 */
	public static function clean( $raw, $max = self::MAX_LENGTH ) {
		$text = self::plain_text( $raw );
		$text = preg_replace( '/^[\s"\'“”‘’]+|[\s"\'“”‘’]+$/u', '', $text );
		$text = preg_replace( '/^(meta description|description)\s*:\s*/i', '', $text );

		$len = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
		if ( $len <= $max ) {
			return $text;
		}

		// Leave room for the ellipsis, then cut back to the last full word.
		$cut   = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max - 1 ) : substr( $text, 0, $max - 1 );
		$space = function_exists( 'mb_strrpos' ) ? mb_strrpos( $cut, ' ' ) : strrpos( $cut, ' ' );
		if ( false !== $space && $space > $max * 0.6 ) {
			$cut = function_exists( 'mb_substr' ) ? mb_substr( $cut, 0, $space ) : substr( $cut, 0, $space );
		}
		return preg_replace( '/[\s,;:\-–—.]+$/u', '', $cut ) . '…';
	}
}
