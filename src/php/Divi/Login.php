<?php
/**
 * Login class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Divi;

use HCaptcha\Abstracts\LoginBase;
use Patchwork\Exceptions\NonNullToVoid;

/**
 * Class Login.
 */
class Login extends LoginBase {

	/**
	 * Login form shortcode tag.
	 */
	public const TAG = 'et_pb_login';

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	protected function init_hooks(): void {
		parent::init_hooks();

		add_filter( self::TAG . '_shortcode_output', [ $this, 'add_divi_captcha' ], 10, 2 );
	}

	/**
	 * Add hCaptcha to the login form.
	 *
	 * @param string|mixed $output      Module output.
	 * @param string       $module_slug Module slug.
	 *
	 * @return string|mixed
	 * @noinspection PhpUnusedParameterInspection
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function add_divi_captcha( $output, string $module_slug ) {
		if ( ! is_string( $output ) || et_core_is_fb_enabled() ) {
			// Do not add captcha in frontend builder.

			return $output;
		}

		if ( ! $this->is_login_limit_exceeded() ) {
			return $output;
		}

		$hcaptcha = '';

		// Check the login status, because class is always loading when Divi theme is active.
		if ( hcaptcha()->settings()->is( 'divi_status', 'login' ) ) {
			ob_start();

			$this->add_captcha();
			$hcaptcha = (string) ob_get_clean();
		}

		ob_start();

		/**
		 * Display hCaptcha signature.
		 */
		do_action( 'hcap_signature' );

		$signatures = (string) ob_get_clean();

		$pattern     = '/(<p>[\s]*?<button)/';
		$replacement = $hcaptcha . $signatures . "\n$1";

		// Insert hCaptcha.
		return (string) preg_replace( $pattern, $replacement, $output );
	}
}
