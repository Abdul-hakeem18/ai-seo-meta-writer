<?php
/**
 * Settings page: API endpoint, key, model, tone and post types.
 *
 * @package AISEOMetaWriter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers and reads plugin settings.
 */
class AISMW_Settings {

	const OPTION = 'aismw_settings';
	const PAGE   = 'ai-seo-meta-writer';

	/**
	 * Default values.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return array(
			'endpoint'   => 'https://api.openai.com/v1/chat/completions',
			'api_key'    => '',
			'model'      => 'gpt-4o-mini',
			'tone'       => 'professional',
			'post_types' => array( 'post', 'page', 'product' ),
		);
	}

	/** Hook into WordPress. */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
	}

	/**
	 * Read one setting. The API key can also come from a constant in wp-config.php,
	 * which keeps it out of the database.
	 *
	 * @param string $key Setting name.
	 * @return mixed
	 */
	public function get( $key ) {
		if ( 'api_key' === $key && defined( 'AISMW_API_KEY' ) && AISMW_API_KEY ) {
			return AISMW_API_KEY;
		}
		$options = wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
		return isset( $options[ $key ] ) ? $options[ $key ] : null;
	}

	/** Add Settings → AI SEO Meta Writer. */
	public function add_page() {
		add_options_page(
			__( 'AI SEO Meta Writer', 'ai-seo-meta-writer' ),
			__( 'AI SEO Meta Writer', 'ai-seo-meta-writer' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render_page' )
		);
	}

	/** Register the option with a sanitize callback. */
	public function register_setting() {
		register_setting(
			self::PAGE,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * Validate and sanitise submitted settings.
	 *
	 * @param array $input Submitted values.
	 * @return array
	 */
	public function sanitize( $input ) {
		$input    = (array) $input;
		$current  = wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
		$clean    = array();
		$endpoint = isset( $input['endpoint'] ) ? esc_url_raw( trim( $input['endpoint'] ), array( 'https', 'http' ) ) : '';

		$clean['endpoint'] = $endpoint ? $endpoint : $current['endpoint'];
		$clean['model']    = isset( $input['model'] ) ? sanitize_text_field( $input['model'] ) : $current['model'];
		$clean['tone']     = isset( $input['tone'] ) && in_array( $input['tone'], array( 'professional', 'friendly', 'persuasive', 'luxury' ), true ) ? $input['tone'] : 'professional';

		// An empty key field means "keep the saved key", so the key is never echoed back into the form.
		$new_key          = isset( $input['api_key'] ) ? trim( sanitize_text_field( $input['api_key'] ) ) : '';
		$clean['api_key'] = '' !== $new_key ? $new_key : $current['api_key'];

		$types               = isset( $input['post_types'] ) ? array_map( 'sanitize_key', (array) $input['post_types'] ) : array();
		$clean['post_types'] = array_values( array_intersect( $types, get_post_types( array( 'public' => true ) ) ) );

		return $clean;
	}

	/** Render the settings form. */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$opts      = wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
		$has_key   = '' !== (string) $this->get( 'api_key' );
		$key_const = defined( 'AISMW_API_KEY' ) && AISMW_API_KEY;
		$types     = get_post_types( array( 'public' => true ), 'objects' );
		unset( $types['attachment'] );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'AI SEO Meta Writer', 'ai-seo-meta-writer' ); ?></h1>
			<p><?php esc_html_e( 'Works with any OpenAI-compatible chat completions API (OpenAI, DeepSeek, OpenRouter, or a local model).', 'ai-seo-meta-writer' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( self::PAGE ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="aismw-endpoint"><?php esc_html_e( 'API endpoint', 'ai-seo-meta-writer' ); ?></label></th>
						<td><input type="url" id="aismw-endpoint" class="regular-text code" name="<?php echo esc_attr( self::OPTION ); ?>[endpoint]" value="<?php echo esc_attr( $opts['endpoint'] ); ?>" />
						<p class="description"><?php esc_html_e( 'Example for DeepSeek: https://api.deepseek.com/chat/completions', 'ai-seo-meta-writer' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="aismw-key"><?php esc_html_e( 'API key', 'ai-seo-meta-writer' ); ?></label></th>
						<td>
							<?php if ( $key_const ) : ?>
								<p><?php esc_html_e( 'Set in wp-config.php (AISMW_API_KEY).', 'ai-seo-meta-writer' ); ?></p>
							<?php else : ?>
								<input type="password" id="aismw-key" class="regular-text" autocomplete="off" name="<?php echo esc_attr( self::OPTION ); ?>[api_key]" value="" placeholder="<?php echo $has_key ? esc_attr__( 'Saved. Leave blank to keep it.', 'ai-seo-meta-writer' ) : ''; ?>" />
								<p class="description"><?php esc_html_e( 'For better security, define AISMW_API_KEY in wp-config.php instead.', 'ai-seo-meta-writer' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aismw-model"><?php esc_html_e( 'Model', 'ai-seo-meta-writer' ); ?></label></th>
						<td><input type="text" id="aismw-model" class="regular-text" name="<?php echo esc_attr( self::OPTION ); ?>[model]" value="<?php echo esc_attr( $opts['model'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="aismw-tone"><?php esc_html_e( 'Tone', 'ai-seo-meta-writer' ); ?></label></th>
						<td><select id="aismw-tone" name="<?php echo esc_attr( self::OPTION ); ?>[tone]">
							<?php foreach ( array( 'professional', 'friendly', 'persuasive', 'luxury' ) as $tone ) : ?>
								<option value="<?php echo esc_attr( $tone ); ?>" <?php selected( $opts['tone'], $tone ); ?>><?php echo esc_html( ucfirst( $tone ) ); ?></option>
							<?php endforeach; ?>
						</select></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Post types', 'ai-seo-meta-writer' ); ?></th>
						<td><fieldset>
							<?php foreach ( $types as $type ) : ?>
								<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[post_types][]" value="<?php echo esc_attr( $type->name ); ?>" <?php checked( in_array( $type->name, (array) $opts['post_types'], true ) ); ?> /> <?php echo esc_html( $type->labels->name ); ?></label><br />
							<?php endforeach; ?>
						</fieldset></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
