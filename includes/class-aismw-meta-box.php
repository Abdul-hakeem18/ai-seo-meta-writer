<?php
/**
 * Editor meta box with "Generate" and "Save" buttons (AJAX).
 *
 * @package AISEOMetaWriter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Meta box UI and AJAX handlers.
 */
class AISMW_Meta_Box {

	const NONCE = 'aismw_meta_box';

	/**
	 * Settings.
	 *
	 * @var AISMW_Settings
	 */
	private $settings;

	/**
	 * API client.
	 *
	 * @var AISMW_Client
	 */
	private $client;

	/**
	 * SEO bridge.
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

	/** Hook into WordPress. */
	public function register() {
		add_action( 'add_meta_boxes', array( $this, 'add' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'wp_ajax_aismw_generate', array( $this, 'ajax_generate' ) );
		add_action( 'wp_ajax_aismw_save', array( $this, 'ajax_save' ) );
	}

	/** Register the meta box on the enabled post types. */
	public function add() {
		foreach ( (array) $this->settings->get( 'post_types' ) as $type ) {
			add_meta_box( 'aismw-box', __( 'AI meta description', 'ai-seo-meta-writer' ), array( $this, 'render' ), $type, 'side' );
		}
	}

	/**
	 * Load JS and CSS on the editor screens only.
	 *
	 * @param string $hook Admin page hook.
	 */
	public function assets( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, (array) $this->settings->get( 'post_types' ), true ) ) {
			return;
		}
		wp_enqueue_style( 'aismw-admin', AISMW_URL . 'assets/admin.css', array(), AISMW_VERSION );
		wp_enqueue_script( 'aismw-admin', AISMW_URL . 'assets/admin.js', array(), AISMW_VERSION, true );
		wp_localize_script(
			'aismw-admin',
			'aismw',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE ),
				'max'     => AISMW_Generator::MAX_LENGTH,
				'i18n'    => array(
					'working' => __( 'Writing…', 'ai-seo-meta-writer' ),
					'saved'   => __( 'Saved.', 'ai-seo-meta-writer' ),
					'error'   => __( 'Something went wrong.', 'ai-seo-meta-writer' ),
				),
			)
		);
	}

	/**
	 * Meta box HTML.
	 *
	 * @param WP_Post $post Post.
	 */
	public function render( $post ) {
		$current  = $this->bridge->get( $post->ID );
		$provider = $this->bridge->provider();
		$labels   = array(
			'yoast'    => 'Yoast SEO',
			'rankmath' => 'Rank Math',
			'none'     => __( 'this plugin\'s meta tag', 'ai-seo-meta-writer' ),
		);
		?>
		<div class="aismw" data-post="<?php echo esc_attr( $post->ID ); ?>">
			<p>
				<label for="aismw-keyword"><?php esc_html_e( 'Focus keyword (optional)', 'ai-seo-meta-writer' ); ?></label>
				<input type="text" id="aismw-keyword" class="widefat" />
			</p>
			<p>
				<label for="aismw-text"><?php esc_html_e( 'Description', 'ai-seo-meta-writer' ); ?></label>
				<textarea id="aismw-text" class="widefat" rows="4"><?php echo esc_textarea( $current ); ?></textarea>
				<span class="aismw-count"></span>
			</p>
			<p class="aismw-actions">
				<button type="button" class="button" id="aismw-generate"><?php esc_html_e( 'Generate with AI', 'ai-seo-meta-writer' ); ?></button>
				<button type="button" class="button button-primary" id="aismw-save"><?php esc_html_e( 'Save', 'ai-seo-meta-writer' ); ?></button>
			</p>
			<p class="aismw-status" role="status" aria-live="polite"></p>
			<p class="description">
				<?php
				/* translators: %s: SEO plugin name. */
				echo esc_html( sprintf( __( 'Saves to %s.', 'ai-seo-meta-writer' ), $labels[ $provider ] ) );
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Validate the AJAX request and return the post.
	 *
	 * @return WP_Post
	 */
	private function verify_request() {
		check_ajax_referer( self::NONCE, 'nonce' );
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$post    = $post_id ? get_post( $post_id ) : null;
		if ( ! $post || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot edit this item.', 'ai-seo-meta-writer' ) ), 403 );
		}
		return $post;
	}

	/** AJAX: generate a suggestion (not saved yet). */
	public function ajax_generate() {
		$post    = $this->verify_request();
		$keyword = isset( $_POST['keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['keyword'] ) ) : '';
		$result  = $this->client->generate_for_post( $post, $keyword );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 502 );
		}
		wp_send_json_success( array( 'description' => $result ) );
	}

	/** AJAX: save the (possibly edited) description. */
	public function ajax_save() {
		$post        = $this->verify_request();
		$description = isset( $_POST['description'] ) ? sanitize_text_field( wp_unslash( $_POST['description'] ) ) : '';
		$this->bridge->save( $post->ID, $description );
		wp_send_json_success( array( 'description' => $this->bridge->get( $post->ID ) ) );
	}
}
