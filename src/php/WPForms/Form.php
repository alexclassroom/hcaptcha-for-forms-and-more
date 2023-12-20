<?php
/**
 * Form class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WPForms;

use HCaptcha\Helpers\HCaptcha;

/**
 * Class Form.
 */
class Form {

	/**
	 * Nonce action.
	 */
	const ACTION = 'hcaptcha_wpforms';

	/**
	 * Nonce name.
	 */
	const NAME = 'hcaptcha_wpforms_nonce';

	/**
	 * Form constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	private function init_hooks() {
		add_action( 'wpforms_process', [ $this, 'verify' ], 10, 3 );
		add_action( 'wp_head', [ $this, 'print_inline_styles' ], 20 );
		add_filter( 'wpforms_setting', [ $this, 'wpforms_setting' ], 10, 4 );
		add_filter( 'wpforms_update_settings', [ $this, 'wpforms_update_settings' ] );
		add_filter( 'wpforms_settings_fields', [ $this, 'wpforms_settings_fields' ], 10, 2 );
		add_filter( 'hcap_print_hcaptcha_scripts', [ $this, 'hcap_print_hcaptcha_scripts' ] );
		add_filter( 'wpforms_admin_settings_captcha_enqueues_disable', [ $this, 'wpforms_admin_settings_captcha_enqueues_disable' ] );
		add_action( 'wpforms_wp_footer', [ $this, 'block_assets_recaptcha' ], 0 );
		add_action( 'wpforms_frontend_output', [ $this, 'wpforms_frontend_output' ], 19, 5 );
	}

	/**
	 * Action that fires during form entry processing after initial field validation.
	 *
	 * @link         https://wpforms.com/developers/wpforms_process/
	 *
	 * @param array $fields    Sanitized entry field: values/properties.
	 * @param array $entry     Original $_POST global.
	 * @param array $form_data Form data and settings.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function verify( array $fields, array $entry, array $form_data ) {
		$error_message = hcaptcha_get_verify_message(
			self::NAME,
			self::ACTION
		);

		if ( null !== $error_message ) {
			wpforms()->get( 'process' )->errors[ $form_data['id'] ]['footer'] = $error_message;
		}
	}

	/**
	 * Print inline styles.
	 *
	 * @return void
	 * @noinspection CssUnusedSymbol
	 */
	public function print_inline_styles() {
		$css = <<<CSS
	div.wpforms-container-full .wpforms-form .h-captcha {
		position: relative;
		display: block;
		margin-bottom: 0;
		padding: 0;
		clear: both;
	}

	div.wpforms-container-full .wpforms-form .h-captcha[data-size="normal"] {
		width: 303px;
		height: 78px;
	}
	
	div.wpforms-container-full .wpforms-form .h-captcha[data-size="compact"] {
		width: 164px;
		height: 144px;
	}
	
	div.wpforms-container-full .wpforms-form .h-captcha[data-size="invisible"] {
		display: none;
	}

	div.wpforms-container-full .wpforms-form .h-captcha iframe {
		position: relative;
	}
CSS;

		HCaptcha::css_display( $css );
	}

	/**
	 * Filter WPForms setting and return hCaptcha values.
	 *
	 * @param mixed  $value         Setting value.
	 * @param string $key           Setting key.
	 * @param mixed  $default_value Setting default value.
	 * @param string $option        Settings option name.
	 *
	 * @return mixed
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function wpforms_setting( $value, string $key, $default_value, string $option ) {
		if ( 'wpforms_settings' !== $option ) {
			return $value;
		}

		switch ( $key ) {
			case 'hcaptcha-site-key':
				return hcaptcha()->settings()->get_site_key();
			case 'hcaptcha-secret-key':
				return hcaptcha()->settings()->get_secret_key();
			case 'hcaptcha-theme-key':
				return hcaptcha()->settings()->get( 'theme' );
			case 'recaptcha-noconflict':
				return ! hcaptcha()->settings()->get( 'recaptcha_compat_off' );
			case 'hcaptcha-fail-msg':
				$error_messages = hcap_get_error_messages();

				if ( isset( $error_messages['fail'] ) ) {
					return $error_messages['fail'];
				}

				break;
			default:
				break;
		}

		return $value;
	}

	/**
	 * Update wpforms settings.
	 *
	 * @param array|mixed $settings Settings.
	 *
	 * @return array
	 */
	public function wpforms_update_settings( $settings ): array {
		$settings     = (array) $settings;
		$old_settings = (array) get_option( 'wpforms_settings', [] );

		$filtered_settings = [
			'hcaptcha-site-key',
			'hcaptcha-secret-key',
			'hcaptcha-fail-msg',
			'recaptcha-noconflict',
			'hcaptcha-theme-key',
		];

		// Do not save hCaptcha options filtered in wpforms_setting().
		foreach ( $filtered_settings as $filtered_setting ) {
			$settings[ $filtered_setting ] = $old_settings[ $filtered_setting ];
		}

		return $settings;
	}

	/**
	 * Filter hCaptcha settings' fields and disable them.
	 *
	 * @param array  $fields Fields.
	 * @param string $view   View name.
	 *
	 * @return array
	 */
	public function wpforms_settings_fields( array $fields, string $view ): array {
		if ( 'captcha' !== $view ) {
			return $fields;
		}

		$inputs      = [
			'hcaptcha-site-key',
			'hcaptcha-secret-key',
			'hcaptcha-fail-msg',
			'recaptcha-noconflict',
		];
		$search      = [
			'class="wpforms-setting-field',
			'<input ',
		];
		$replace     = [
			'style="opacity: 0.4;" ' . $search[0],
			$search[1] . 'disabled ',
		];
		$notice      = HCaptcha::get_hcaptcha_plugin_notice();
		$label       = $notice['label'];
		$description = $notice['description'];

		foreach ( $inputs as $input ) {
			if ( ! isset( $fields[ $input ] ) ) {
				continue;
			}

			$fields[ $input ] = str_replace( $search, $replace, $fields[ $input ] );
		}

		if ( isset( $fields['hcaptcha-heading'] ) ) {
			$notice_content = <<<HTML
<div
		id="wpforms-setting-row-hcaptcha-heading"
		class="wpforms-setting-row wpforms-setting-row-content wpforms-clear section-heading specific-note">
	<span class="wpforms-setting-field">
		<div class="wpforms-specific-note-wrap">
			<div class="wpforms-specific-note-lightbulb">
				<svg viewBox="0 0 14 20">
					<path d="M3.75 17.97c0 .12 0 .23.08.35l.97 1.4c.12.2.32.28.51.28H8.4c.2 0 .39-.08.5-.27l.98-1.41c.04-.12.08-.23.08-.35v-1.72H3.75v1.72Zm3.13-5.47c.66 0 1.25-.55 1.25-1.25 0-.66-.6-1.25-1.26-1.25-.7 0-1.25.59-1.25 1.25 0 .7.55 1.25 1.25 1.25Zm0-12.5A6.83 6.83 0 0 0 0 6.88c0 1.75.63 3.32 1.68 4.53.66.74 1.68 2.3 2.03 3.59H5.6c0-.16 0-.35-.08-.55-.2-.7-.86-2.5-2.42-4.25a5.19 5.19 0 0 1-1.21-3.32c-.04-2.86 2.3-5 5-5 2.73 0 5 2.26 5 5 0 1.2-.47 2.38-1.26 3.32a11.72 11.72 0 0 0-2.42 4.25c-.07.2-.07.35-.07.55H10a10.56 10.56 0 0 1 2.03-3.6A6.85 6.85 0 0 0 6.88 0Zm-.4 8.75h.75c.3 0 .58-.23.62-.55l.5-3.75a.66.66 0 0 0-.62-.7H5.98a.66.66 0 0 0-.63.7l.5 3.75c.05.32.32.55.63.55Z"></path>
				</svg>
			</div>
			<div class="wpforms-specific-note-content">
				<p><strong>$label</strong></p>
				<p>$description</p>
			</div>
		</div>
	</span>
</div>
HTML;

			$fields['hcaptcha-heading'] .= $notice_content;
		}

		if ( isset( $fields['captcha-preview'] ) ) {
			$fields['captcha-preview'] = preg_replace(
				'#<div class="wpforms-captcha wpforms-captcha-hcaptcha".+?</div>#',
				HCaptcha::form(),
				$fields['captcha-preview']
			);
		}

		return $fields;
	}

	/**
	 * Filter whether to print hCaptcha scripts.
	 *
	 * @param bool|mixed $status Status.
	 *
	 * @return bool
	 */
	public function hcap_print_hcaptcha_scripts( $status ): bool {
		return $this->is_wpforms_hcaptcha_settings_page() || $status;
	}

	/**
	 * Disable enqueuing wpforms hCaptcha.
	 *
	 * @param bool|mixed $status Status.
	 *
	 * @return bool
	 */
	public function wpforms_admin_settings_captcha_enqueues_disable( $status ): bool {
		return $this->is_wpforms_hcaptcha_settings_page() || $status;
	}

	/**
	 * Block recaptcha assets on frontend.
	 *
	 * @return void
	 */
	public function block_assets_recaptcha() {
		if ( ! $this->is_wpforms_provider_hcaptcha() ) {
			return;
		}

		$captcha = wpforms()->get( 'captcha' );

		if ( ! $captcha ) {
			return;
		}

		remove_action( 'wpforms_wp_footer', [ $captcha, 'assets_recaptcha' ] );
	}

	/**
	 * Output embedded hCaptcha.
	 *
	 * @param array|mixed $form_data   Form data and settings.
	 * @param null        $deprecated  Deprecated in v1.3.7, previously was $form object.
	 * @param bool        $title       Whether to display form title.
	 * @param bool        $description Whether to display form description.
	 * @param array       $errors      List of all errors filled in WPForms_Process::process().
	 *
	 * @noinspection HtmlUnknownAttribute
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function wpforms_frontend_output( $form_data, $deprecated, bool $title, bool $description, array $errors ) {
		$form_data = (array) $form_data;

		$form_captcha_settings = $this->get_form_captcha_settings( $form_data );
		$our_wpforms_status    = hcaptcha()->settings()->is_on( 'wpforms_status' );

		if ( ! ( $form_captcha_settings || $our_wpforms_status ) ) {
			return;
		}

		$id = (int) $form_data['id'];

		if ( $form_captcha_settings ) {
			$captcha = wpforms()->get( 'captcha' );

			if ( ! $captcha ) {
				return;
			}

			remove_action( 'wpforms_frontend_output', [ $captcha, 'recaptcha' ], 20 );

			$this->show_hcaptcha( $id );

			return;
		}

		if ( $our_wpforms_status ) {
			$this->show_hcaptcha( $id );
		}
	}

	/**
	 * Show hCaptcha.
	 *
	 * @param int $id Form id.
	 *
	 * @return void
	 */
	private function show_hcaptcha( int $id ) {
		$frontend_obj = wpforms()->get( 'frontend' );

		if ( ! $frontend_obj ) {
			return;
		}

		$args = [
			'action' => self::ACTION,
			'name'   => self::NAME,
			'id'     => [
				'source'  => HCaptcha::get_class_source( static::class ),
				'form_id' => $id,
			],
		];

		printf(
			'<div class="wpforms-recaptcha-container wpforms-is-hcaptcha" %s>',
			$frontend_obj->pages ? 'style="display:none;"' : ''
		);

		HCaptcha::form_display( $args );

		echo '</div>';
	}

	/**
	 * Get captcha settings for form output.
	 * Return null if captcha is disabled.
	 *
	 * @param array $form_data Form data and settings.
	 *
	 * @return array|null
	 */
	private function get_form_captcha_settings( array $form_data ) {
		$captcha_settings = wpforms_get_captcha_settings();
		$provider         = $captcha_settings['provider'] ?? '';

		if ( 'hcaptcha' !== $provider ) {
			return null;
		}

		// Check that the CAPTCHA is configured for the specific form.
		$recaptcha = $form_data['settings']['recaptcha'] ?? '';

		if ( '1' !== $recaptcha ) {
			return null;
		}

		return $captcha_settings;
	}

	/**
	 * Check if the current page is wpforms captcha settings page and the current provider is hCaptcha.
	 *
	 * @return bool
	 */
	private function is_wpforms_hcaptcha_settings_page(): bool {
		if ( ! function_exists( 'get_current_screen' ) || ! is_admin() ) {
			return false;
		}

		$screen = get_current_screen();
		$id     = $screen->id ?? '';

		if ( 'wpforms_page_wpforms-settings' !== $id ) {
			return false;
		}

		return $this->is_wpforms_provider_hcaptcha();
	}

	/**
	 * Check if the current captcha provider is hCaptcha.
	 *
	 * @return bool
	 */
	private function is_wpforms_provider_hcaptcha(): bool {
		$captcha_settings = wpforms_get_captcha_settings();
		$provider         = $captcha_settings['provider'] ?? '';

		return 'hcaptcha' === $provider;
	}
}
