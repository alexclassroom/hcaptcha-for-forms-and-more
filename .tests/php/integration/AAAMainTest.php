<?php
/**
 * MainTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
/** @noinspection CssUnresolvedCustomProperty */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration;

use HCaptcha\Admin\Privacy;
use HCaptcha\Admin\WhatsNew;
use HCaptcha\AutoVerify\AutoVerify;
use HCaptcha\BBPress\NewTopic;
use HCaptcha\BBPress\Reply;
use HCaptcha\BuddyPress\CreateGroup;
use HCaptcha\CF7\Admin;
use HCaptcha\CF7\CF7;
use HCaptcha\CF7\ReallySimpleCaptcha;
use HCaptcha\Divi\Contact;
use HCaptcha\Divi\EmailOptin;
use HCaptcha\DownloadManager\DownloadManager;
use HCaptcha\FluentForm\Form;
use HCaptcha\Main;
use HCaptcha\ElementorPro\HCaptchaHandler;
use HCaptcha\NF\NF;
use HCaptcha\Quform\Quform;
use HCaptcha\Sendinblue\Sendinblue;
use HCaptcha\Settings\Settings;
use HCaptcha\WC\Checkout;
use HCaptcha\WC\OrderTracking;
use HCaptcha\WCWishlists\CreateList;
use HCaptcha\WP\Comment;
use HCaptcha\WP\Login;
use HCaptcha\WP\LoginOut;
use HCaptcha\WP\LostPassword;
use HCaptcha\WP\PasswordProtected;
use HCaptcha\WP\Register;
use HCaptcha\WPDiscuz\Subscribe;
use Mockery;
use ReflectionException;
use stdClass;
use tad\FunctionMocker\FunctionMocker;
use HCaptcha\Admin\PluginStats;
use HCaptcha\Admin\Events\Events;
use WP_Textdomain_Registry;

/**
 * Test Main class.
 *
 * @group main
 *
 * @group bp
 * @group subscriber
 */
class AAAMainTest extends HCaptchaWPTestCase {

	/**
	 * Included components in test_load_modules().
	 *
	 * @var array
	 */
	private static $included_components = [];

	/**
	 * Tear down test.
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function tearDown(): void {
		$hcaptcha = hcaptcha();

		$loaded_classes = $this->get_protected_property( $hcaptcha, 'loaded_classes' );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		unset(
			$GLOBALS['current_user'],
			$GLOBALS['current_screen'],
			$loaded_classes[ HCaptchaHandler::class ],
			$_SERVER['REQUEST_URI'],
			$_GET['post'],
			$_GET['action'],
			$_GET['elementor-preview'],
			$_POST['action']
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing

		$this->set_protected_property( $hcaptcha, 'loaded_classes', $loaded_classes );

		delete_option( 'hcaptcha_settings' );

		wp_dequeue_script( 'hcaptcha' );
		wp_deregister_script( 'hcaptcha' );

		wp_dequeue_script( 'jquery' );
		wp_deregister_script( 'jquery' );

		$hcaptcha->form_shown = false;

		parent::tearDown();
	}

	/**
	 * Test init().
	 *
	 * @return void
	 */
	public function test_init(): void {
		$hcaptcha = hcaptcha();

		// The plugin was loaded by codeception.
		self::assertSame( Main::LOAD_PRIORITY, has_action( 'plugins_loaded', [ $hcaptcha, 'init_hooks' ] ) );

		remove_action( 'plugins_loaded', [ $hcaptcha, 'init_hooks' ], Main::LOAD_PRIORITY );

		self::assertFalse( has_action( 'plugins_loaded', [ $hcaptcha, 'init_hooks' ] ) );

		$hcaptcha->init();

		self::assertSame( Main::LOAD_PRIORITY, has_action( 'plugins_loaded', [ $hcaptcha, 'init_hooks' ] ) );
	}

	/**
	 * Test init() and init_hooks().
	 *
	 * @param boolean        $logged_in                   User is logged in.
	 * @param string         $hcaptcha_off_when_logged_in Option 'hcaptcha_off_when_logged_in' is set.
	 * @param boolean|string $allowlisted                 Whether IP is allowlisted.
	 * @param boolean        $hcaptcha_active             Plugin should be active.
	 *
	 * @dataProvider dp_test_init
	 * @throws ReflectionException ReflectionException.
	 * @noinspection PhpUnitTestsInspection
	 */
	public function test_init_and_init_hooks( bool $logged_in, string $hcaptcha_off_when_logged_in, $allowlisted, bool $hcaptcha_active ): void {
		global $current_user;

		$hcaptcha = hcaptcha();

		update_option(
			'hcaptcha_settings',
			[
				'site_key'           => 'some site key',
				'secret_key'         => 'some secret key',
				'off_when_logged_in' => 'on' === $hcaptcha_off_when_logged_in ? [ 'on' ] : [],
			]
		);

		// Init plugin to update settings.
		do_action( 'plugins_loaded' );

		add_filter(
			'hcap_whitelist_ip',
			static function () use ( $allowlisted ) {
				return $allowlisted;
			}
		);

		// The plugin was loaded by codeception.
		self::assertTrue(
			in_array(
				$this->normalize_path( ABSPATH . 'wp-includes/pluggable.php' ),
				$this->normalize_path( get_included_files() ),
				true
			)
		);

		self::assertSame( Main::LOAD_PRIORITY, has_action( 'plugins_loaded', [ $hcaptcha, 'init_hooks' ] ) );

		self::assertSame( Main::LOAD_PRIORITY + 1, has_action( 'plugins_loaded', [ $hcaptcha, 'load_modules' ] ) );

		self::assertSame( 10, has_filter( 'wp_resource_hints', [ $hcaptcha, 'prefetch_hcaptcha_dns' ] ) );
		self::assertSame( 10, has_filter( 'wp_headers', [ $hcaptcha, 'csp_headers' ] ) );
		self::assertSame( 10, has_action( 'wp_head', [ $hcaptcha, 'print_inline_styles' ] ) );
		self::assertSame( 10, has_action( 'login_head', [ $hcaptcha, 'login_head' ] ) );
		self::assertSame( 0, has_action( 'wp_print_footer_scripts', [ $hcaptcha, 'print_footer_scripts' ] ) );

		self::assertInstanceOf( AutoVerify::class, $this->get_protected_property( $hcaptcha, 'auto_verify' ) );

		unset( $current_user );

		if ( $logged_in ) {
			wp_set_current_user( 1 );
		}

		$subject = new Main();
		$subject->init_hooks();

		self::assertSame(
			Main::LOAD_PRIORITY + 1,
			has_action( 'plugins_loaded', [ $subject, 'load_modules' ] )
		);
		self::assertSame(
			-PHP_INT_MAX,
			has_filter(
				'hcap_whitelist_ip',
				[ $subject, 'allowlist_ip' ]
			)
		);

		if ( $hcaptcha_active ) {
			self::assertSame( 10, has_filter( 'wp_resource_hints', [ $subject, 'prefetch_hcaptcha_dns' ] ) );
			self::assertSame( 10, has_filter( 'wp_headers', [ $subject, 'csp_headers' ] ) );
			self::assertSame( 10, has_action( 'wp_head', [ $subject, 'print_inline_styles' ] ) );
			self::assertSame( 10, has_action( 'login_head', [ $subject, 'login_head' ] ) );
			self::assertSame( 0, has_action( 'wp_print_footer_scripts', [ $subject, 'print_footer_scripts' ] ) );

			self::assertInstanceOf( AutoVerify::class, $this->get_protected_property( $subject, 'auto_verify' ) );
		} else {
			self::assertFalse( has_filter( 'wp_resource_hints', [ $subject, 'prefetch_hcaptcha_dns' ] ) );
			self::assertFalse( has_filter( 'wp_headers', [ $subject, 'csp_headers' ] ) );
			self::assertFalse( has_action( 'wp_head', [ $subject, 'print_inline_styles' ] ) );
			self::assertFalse( has_action( 'login_head', [ $subject, 'login_head' ] ) );
			self::assertFalse( has_action( 'wp_print_footer_scripts', [ $subject, 'print_footer_scripts' ] ) );

			self::assertNull( $this->get_protected_property( $subject, 'auto_verify' ) );
		}
	}

	/**
	 * Data provider for test_init().
	 *
	 * @return array[]
	 */
	public function dp_test_init(): array {
		return [
			'not logged in, not set, not allowlisted' => [ false, 'off', false, true ],
			'not logged in, set, not allowlisted'     => [ false, 'on', false, true ],
			'logged in, not set, not allowlisted'     => [ true, 'off', false, true ],
			'logged in, set, not allowlisted'         => [ true, 'on', false, false ],
			'not logged in, not set, allowlisted'     => [ false, 'off', true, false ],
			'not logged in, set, allowlisted'         => [ false, 'on', true, false ],
			'logged in, not set, allowlisted'         => [ true, 'off', true, false ],
			'logged in, set, allowlisted'             => [ true, 'on', true, false ],
		];
	}

	/**
	 * Test init() and init_hooks() on Elementor Pro edit page.
	 *
	 * @param string  $elementor_pro_status Option 'elementor_pro_status' is set.
	 * @param array   $server               $_SERVER variable.
	 * @param array   $get                  $_GET variable.
	 * @param array   $post                 $_POST variable.
	 * @param boolean $hcaptcha_active      Plugin should be active.
	 *
	 * @dataProvider dp_test_init_and_init_hooks_on_elementor_pro_edit_page
	 * @noinspection PhpUnitTestsInspection
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_init_and_init_hooks_on_elementor_pro_edit_page(
		string $elementor_pro_status,
		array $server,
		array $get,
		array $post,
		bool $hcaptcha_active
	): void {
		global $current_user;

		add_filter(
			'hcap_whitelist_ip',
			static function () {
				return true;
			}
		);

		unset( $current_user );
		wp_set_current_user( 1 );

		if ( 'on' === $elementor_pro_status ) {
			update_option( 'hcaptcha_settings', [ 'elementor_pro_status' => [ 'on' ] ] );
		} else {
			update_option( 'hcaptcha_settings', [ 'elementor_pro_status' => [] ] );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		$_SERVER = array_merge( $_SERVER, $server );
		$_GET    = array_merge( $_GET, $get );
		$_POST   = array_merge( $_POST, $post );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing

		$subject = new Main();
		$subject->init_hooks();

		self::assertSame(
			Main::LOAD_PRIORITY + 1,
			has_action( 'plugins_loaded', [ $subject, 'load_modules' ] )
		);
		self::assertSame(
			-PHP_INT_MAX,
			has_filter(
				'hcap_whitelist_ip',
				[ $subject, 'allowlist_ip' ]
			)
		);

		if ( $hcaptcha_active ) {
			self::assertSame( 10, has_filter( 'wp_resource_hints', [ $subject, 'prefetch_hcaptcha_dns' ] ) );
			self::assertSame( 10, has_filter( 'wp_headers', [ $subject, 'csp_headers' ] ) );
			self::assertSame( 10, has_action( 'wp_head', [ $subject, 'print_inline_styles' ] ) );
			self::assertSame( 10, has_action( 'login_head', [ $subject, 'login_head' ] ) );
			self::assertSame( 0, has_action( 'wp_print_footer_scripts', [ $subject, 'print_footer_scripts' ] ) );

			self::assertInstanceOf( AutoVerify::class, $this->get_protected_property( $subject, 'auto_verify' ) );
		} else {
			self::assertFalse( has_filter( 'wp_resource_hints', [ $subject, 'prefetch_hcaptcha_dns' ] ) );
			self::assertFalse( has_filter( 'wp_headers', [ $subject, 'csp_headers' ] ) );
			self::assertFalse( has_action( 'wp_head', [ $subject, 'print_inline_styles' ] ) );
			self::assertFalse( has_action( 'login_head', [ $subject, 'login_head' ] ) );
			self::assertFalse( has_action( 'wp_print_footer_scripts', [ $subject, 'print_footer_scripts' ] ) );

			self::assertNull( $this->get_protected_property( $subject, 'auto_verify' ) );
		}
	}

	/**
	 * Data provider for test_init_and_init_hooks_on_elementor_pro_edit_page().
	 *
	 * @return array
	 */
	public function dp_test_init_and_init_hooks_on_elementor_pro_edit_page(): array {
		return [
			'elementor option off' => [
				'off',
				[],
				[],
				[],
				false,
			],
			'request1'             => [
				'on',
				[ 'REQUEST_URI' => '/wp-admin/post.php?post=23&action=elementor' ],
				[
					'post'   => 23,
					'action' => 'elementor',
				],
				[],
				true,
			],
			'request2'             => [
				'on',
				[],
				[
					'preview_id'    => 23,
					'preview_nonce' => 'some',
					'preview'       => true,
				],
				[],
				true,
			],
			'request3'             => [
				'on',
				[],
				[],
				[ 'action' => 'elementor_ajax' ],
				true,
			],
			'other request'        => [
				'on',
				[],
				[],
				[ 'action' => 'some_ajax' ],
				false,
			],
		];
	}

	/**
	 * Test init_hooks() on cron request.
	 *
	 * @return void
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_init_hooks_on_cron(): void {
		add_filter( 'wp_doing_cron', '__return_true' );

		$subject = new Main();

		$subject->init_hooks();

		$loaded_classes = $this->get_protected_property( $subject, 'loaded_classes' );

		self::assertTrue( class_exists( Settings::class, false ) );
		self::assertArrayNotHasKey( PluginStats::class, $loaded_classes );
	}

	/**
	 * Test prefetch_hcaptcha_dns().
	 */
	public function test_prefetch_hcaptcha_dns(): void {
		$urls     = [
			'//s.w.org',
		];
		$expected = [
			'//s.w.org',
			'https://hcaptcha.com',
		];

		$subject = new Main();

		self::assertSame( $urls, $subject->prefetch_hcaptcha_dns( $urls, 'some-type' ) );
		self::assertSame( $expected, $subject->prefetch_hcaptcha_dns( $urls, 'dns-prefetch' ) );

		add_filter( 'hcap_print_hcaptcha_scripts', '__return_false' );

		self::assertSame( $urls, $subject->prefetch_hcaptcha_dns( $urls, 'some-type' ) );
		self::assertSame( $urls, $subject->prefetch_hcaptcha_dns( $urls, 'dns-prefetch' ) );
	}

	/**
	 * Test csp_headers().
	 *
	 * @return void
	 */
	public function test_csp_headers(): void {
		$headers  = [
			'some_header' => 'some header content',
		];
		$expected = $headers;
		$hcap_csp = "'self' 'unsafe-inline' 'unsafe-eval' https://hcaptcha.com https://*.hcaptcha.com";

		$expected['Content-Security-Policy'] =
			"script-src $hcap_csp; " .
			"frame-src $hcap_csp; " .
			"style-src $hcap_csp; " .
			"connect-src $hcap_csp; " .
			"default-src 'self'";

		$subject = new Main();

		// The 'hcap_add_csp_headers' filter is not added.
		self::assertSame( $headers, $subject->csp_headers( $headers ) );

		add_filter(
			'hcap_add_csp_headers',
			static function ( $add, $h ) use ( &$headers ) {
				return $h === $headers;
			},
			10,
			2
		);

		// The 'Content-Security-Policy' key is not in headers.
		self::assertSame( $headers, $subject->csp_headers( $headers ) );

		$headers = [
			'some_header'             => 'some header content',
			'Content-Security-Policy' => "default-src 'self'",
		];

		// The 'Content-Security-Policy' key is in headers.
		self::assertSame( $expected, $subject->csp_headers( $headers ) );
	}

	/**
	 * Test print_inline_styles().
	 *
	 * @param string|false $custom_themes Custom themes option value.
	 *
	 * @dataProvider dp_test_print_inline_styles
	 * @noinspection CssUnusedSymbol
	 */
	public function test_print_inline_styles( $custom_themes ): void {
		$license       = 'pro';
		$bg            = 'on' === $custom_themes
			? '#f0f0f0'
			: 'initial';
		$config_params = 'on' === $custom_themes
			? [
				'theme' => [
					'component' => [
						'checkbox' => [
							'main' => [
								'fill' => $bg,
							],
						],
					],
				],
			]
			: [];

		update_option(
			'hcaptcha_settings',
			[
				'custom_themes' => $custom_themes ? [ $custom_themes ] : [],
				// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
				'config_params' => json_encode( $config_params ),
				'license'       => $license,
			]
		);

		$div_logo_url       = HCAPTCHA_URL . '/assets/images/hcaptcha-div-logo.svg';
		$div_logo_url_white = HCAPTCHA_URL . '/assets/images/hcaptcha-div-logo-white.svg';

		FunctionMocker::replace(
			'defined',
			static function ( $constant_name ) {
				return 'SCRIPT_DEBUG' === $constant_name;
			}
		);

		FunctionMocker::replace(
			'constant',
			static function ( $name ) {
				return 'SCRIPT_DEBUG' === $name;
			}
		);

		$expected = <<<CSS
	.h-captcha {
		position: relative;
		display: block;
		margin-bottom: 2rem;
		padding: 0;
		clear: both;
	}

	.h-captcha[data-size="normal"] {
		width: 303px;
		height: 78px;
	}

	.h-captcha[data-size="compact"] {
		width: 164px;
		height: 144px;
	}

	.h-captcha[data-size="invisible"] {
		display: none;
	}

	.h-captcha::before {
		content: '';
		display: block;
		position: absolute;
		top: 0;
		left: 0;
		background: url( $div_logo_url ) no-repeat;
		border: 1px solid transparent;
		border-radius: 4px;
	}

	.h-captcha[data-size="normal"]::before {
		width: 300px;
		height: 74px;
		background-position: 94% 28%;
	}

	.h-captcha[data-size="compact"]::before {
		width: 156px;
		height: 136px;
		background-position: 50% 79%;
	}

	.h-captcha[data-theme="light"]::before,
	body.is-light-theme .h-captcha[data-theme="auto"]::before,
	.h-captcha[data-theme="auto"]::before {
		background-color: #fafafa;
		border: 1px solid #e0e0e0;
	}

	.h-captcha[data-theme="dark"]::before,
	body.is-dark-theme .h-captcha[data-theme="auto"]::before,
	html.wp-dark-mode-active .h-captcha[data-theme="auto"]::before,
	html.drdt-dark-mode .h-captcha[data-theme="auto"]::before {
		background-image: url( $div_logo_url_white );
		background-repeat: no-repeat;
		background-color: #333;
		border: 1px solid #f5f5f5;
	}

	@media (prefers-color-scheme: dark) {
		.h-captcha[data-theme="auto"]::before {
			background-image: url( http://test.test/wp-content/plugins/hcaptcha-wordpress-plugin/assets/images/hcaptcha-div-logo-white.svg );
			background-repeat: no-repeat;
			background-color: #333;
			border: 1px solid #f5f5f5;			
		}
	}

	.h-captcha[data-theme="custom"]::before {
		background-color: $bg;
	}

	.h-captcha[data-size="invisible"]::before {
		display: none;
	}

	.h-captcha iframe {
		position: relative;
	}

	div[style*="z-index: 2147483647"] div[style*="border-width: 11px"][style*="position: absolute"][style*="pointer-events: none"] {
		border-style: none;
	}
CSS;

		$expected = "<style>\n$expected\n</style>\n";

		$subject = new Main();

		$subject->init_hooks();

		ob_start();

		$subject->print_inline_styles();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Data provider for test_print_inline_styles().
	 *
	 * @return array
	 */
	public function dp_test_print_inline_styles(): array {
		return [
			[ false ],
			[ 'on' ],
		];
	}

	/**
	 * Test print_inline_styles() when prohibited.
	 *
	 * @return void
	 */
	public function test_print_inline_styles_when_prohibited(): void {
		add_filter( 'hcap_print_hcaptcha_scripts', '__return_false' );

		$subject = new Main();

		$subject->init_hooks();

		ob_start();

		$subject->print_inline_styles();

		self::assertSame( '', ob_get_clean() );
	}

	/**
	 * Test login_head().
	 *
	 * @noinspection CssUnusedSymbol
	 */
	public function test_login_head(): void {
		FunctionMocker::replace(
			'defined',
			static function ( $constant_name ) {
				return 'SCRIPT_DEBUG' === $constant_name;
			}
		);

		FunctionMocker::replace(
			'constant',
			static function ( $name ) {
				return 'SCRIPT_DEBUG' === $name;
			}
		);

		$expected = <<<CSS
	@media (max-width: 349px) {
		.h-captcha {
			display: flex;
			justify-content: center;
		}
		.h-captcha[data-size="normal"] {
			scale: calc(270 / 303);
		    transform: translate(-20px, 0);
		}
	}

	@media (min-width: 350px) {
		body #login {
			width: 350px;
			box-sizing: content-box;
		}
	}
CSS;

		$expected = "<style>\n$expected\n</style>\n";

		$subject = new Main();

		ob_start();

		$subject->login_head();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test login_head() when prohibited.
	 *
	 * @return void
	 */
	public function test_login_head_when_prohibited(): void {
		add_filter( 'hcap_print_hcaptcha_scripts', '__return_false' );

		$subject = new Main();

		ob_start();

		$subject->login_head();

		self::assertSame( '', ob_get_clean() );
	}

	/**
	 * Test get_api_url().
	 *
	 * @param string $api_host API host.
	 * @param string $expected Expected value.
	 *
	 * @return void
	 * @dataProvider dp_test_get_api_url
	 */
	public function test_get_api_url( string $api_host, string $expected ): void {
		update_option(
			'hcaptcha_settings',
			[
				'api_host' => $api_host,
			]
		);

		$subject = new Main();

		$subject->init_hooks();

		self::assertSame( $expected, $subject->get_api_url() );
	}

	/**
	 * Data provider for test_get_api_url().
	 *
	 * @return array
	 */
	public function dp_test_get_api_url(): array {
		return [
			[ '', 'https://js.hcaptcha.com/1/api.js' ],
			[ ' ', 'https://js.hcaptcha.com/1/api.js' ],
			[ 'cn1.hcaptcha.com', 'https://cn1.hcaptcha.com/1/api.js' ],
			[ 'http://cn1.hcaptcha.com', 'https://cn1.hcaptcha.com/1/api.js' ],
			[ 'https://cn1.hcaptcha.com', 'https://cn1.hcaptcha.com/1/api.js' ],
		];
	}

	/**
	 * Test get_api_src().
	 *
	 * @return void
	 */
	public function test_get_api_src(): void {
		update_option(
			'hcaptcha_settings',
			[
				'recaptcha_compat_off' => [ 'on' ],
				'custom_themes'        => [ 'on' ],
				'asset_host'           => 'assets-cn1.hcaptcha.com',
				'endpoint'             => 'cn1.hcaptcha.com',
				'host'                 => 'cn1.hcaptcha.com',
				'image_host'           => 'imgs-cn1.hcaptcha.com',
				'report_api'           => 'reportapi-cn1.hcaptcha.com',
				'sentry'               => 'cn1.hcaptcha.com',
				'license'              => 'pro',
			]
		);

		$expected = 'https://js.hcaptcha.com/1/api.js?onload=hCaptchaOnLoad&render=explicit&recaptchacompat=off&custom=true&assethost=https%3A%2F%2Fassets-cn1.hcaptcha.com&endpoint=https%3A%2F%2Fcn1.hcaptcha.com&host=https%3A%2F%2Fcn1.hcaptcha.com&imghost=https%3A%2F%2Fimgs-cn1.hcaptcha.com&reportapi=https%3A%2F%2Freportapi-cn1.hcaptcha.com&sentry=https%3A%2F%2Fcn1.hcaptcha.com';

		$subject = new Main();

		$subject->init_hooks();

		self::assertSame( $expected, $subject->get_api_src() );
	}

	/**
	 * Test get_verify_url().
	 *
	 * @param string $backend  Backend.
	 * @param string $expected Expected value.
	 *
	 * @return void
	 * @dataProvider dp_test_get_verify_url
	 */
	public function test_get_verify_url( string $backend, string $expected ): void {
		update_option(
			'hcaptcha_settings',
			[
				'backend' => $backend,
			]
		);

		$subject = new Main();

		$subject->init_hooks();

		self::assertSame( $expected, $subject->get_verify_url() );
	}

	/**
	 * Data provider for test_get_verify_url().
	 *
	 * @return array
	 */
	public function dp_test_get_verify_url(): array {
		return [
			[ '', 'https://api.hcaptcha.com/siteverify' ],
			[ ' ', 'https://api.hcaptcha.com/siteverify' ],
			[ 'cn1.hcaptcha.com', 'https://cn1.hcaptcha.com/siteverify' ],
			[ 'http://cn1.hcaptcha.com', 'https://cn1.hcaptcha.com/siteverify' ],
			[ 'https://cn1.hcaptcha.com', 'https://cn1.hcaptcha.com/siteverify' ],
		];
	}

	/**
	 * Test get_check_site_config_url().
	 *
	 * @param string $backend  Backend.
	 * @param string $expected Expected value.
	 *
	 * @return void
	 * @dataProvider dp_test_get_check_site_config_url
	 */
	public function test_get_check_site_config_url( string $backend, string $expected ): void {
		update_option(
			'hcaptcha_settings',
			[
				'backend' => $backend,
			]
		);

		$subject = new Main();

		$subject->init_hooks();

		self::assertSame( $expected, $subject->get_check_site_config_url() );
	}

	/**
	 * Data provider for test_get_check_site_config_url().
	 *
	 * @return array
	 */
	public function dp_test_get_check_site_config_url(): array {
		return [
			[ '', 'https://api.hcaptcha.com/checksiteconfig' ],
			[ ' ', 'https://api.hcaptcha.com/checksiteconfig' ],
			[ 'cn1.hcaptcha.com', 'https://cn1.hcaptcha.com/checksiteconfig' ],
			[ 'http://cn1.hcaptcha.com', 'https://cn1.hcaptcha.com/checksiteconfig' ],
			[ 'https://cn1.hcaptcha.com', 'https://cn1.hcaptcha.com/checksiteconfig' ],
		];
	}

	/**
	 * Test print_footer_scripts().
	 *
	 * @param string|false $compat              Compat option value.
	 * @param string|false $language            Language option value.
	 * @param string|false $custom_themes       Compat option value.
	 * @param string       $expected_script_src Expected script source.
	 *
	 * @dataProvider dp_test_print_footer_scripts
	 * @throws ReflectionException ReflectionException.
	 * @noinspection BadExpressionStatementJS
	 */
	public function test_print_footer_scripts( $compat, $language, $custom_themes, string $expected_script_src ): void {
		FunctionMocker::replace(
			'defined',
			static function ( $constant_name ) {
				return 'SCRIPT_DEBUG' === $constant_name;
			}
		);

		FunctionMocker::replace(
			'constant',
			static function ( $name ) {
				return 'SCRIPT_DEBUG' === $name;
			}
		);

		$hcaptcha = hcaptcha();

		$hcaptcha->form_shown = true;

		$expected_scripts = "
	( () => {
		'use strict';

		let loaded = false,
			scrolled = false,
			timerId;

		function load() {
			if ( loaded ) {
				return;
			}

			loaded = true;
			clearTimeout( timerId );

			window.removeEventListener( 'touchstart', load );
			document.body.removeEventListener( 'mouseenter', load );
			document.body.removeEventListener( 'click', load );
			window.removeEventListener( 'keydown', load );
			window.removeEventListener( 'scroll', scrollHandler );

			const t = document.getElementsByTagName( 'script' )[0];
			const s = document.createElement( 'script' );
			s.type  = 'text/javascript';
			s.id = 'hcaptcha-api';
			s['src'] = '$expected_script_src';
			s.async = true;
			t.parentNode.insertBefore( s, t );
		}

		function scrollHandler() {
			if ( ! scrolled ) {
				// Ignore first scroll event, which can be on page load.
				scrolled = true;
				return;
			}

			load();
		}

		document.addEventListener( 'hCaptchaBeforeAPI', function() {
			// noinspection JSAnnotator
			const delay = -100;

			if ( delay >= 0 ) {
				timerId = setTimeout( load, delay );
			}

			window.addEventListener( 'touchstart', load );
			document.body.addEventListener( 'mouseenter', load );
			document.body.addEventListener( 'click', load );
			window.addEventListener( 'keydown', load );
			window.addEventListener( 'scroll', scrollHandler );
		} );
	} )();
";

		$expected_scripts = "<script>$expected_scripts</script>\n";

		$site_key       = 'some site key';
		$secret_key     = 'some secret key';
		$theme          = 'light';
		$size           = 'normal';
		$language       = $language ?: 'en';
		$params         = [
			'sitekey' => $site_key,
			'theme'   => $theme,
			'size'    => $size,
			'hl'      => $language,
		];
		$config_params  = 'on' === $custom_themes ? [ 'theme' => [ 'some theme' ] ] : [];
		$params         = array_merge( $params, $config_params );
		$expected_extra = [
			'group' => 1,
			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
			'data'  => 'var HCaptchaMainObject = {"params":"' . addslashes( json_encode( $params ) ) . '"};',
		];

		update_option(
			'hcaptcha_settings',
			[
				'recaptcha_compat_off' => $compat ? [ $compat ] : [],
				'language'             => $language,
				'site_key'             => $site_key,
				'secret_key'           => $secret_key,
				'mode'                 => 'live',
				'theme'                => $theme,
				'size'                 => $size,
				'custom_themes'        => $custom_themes ? [ $custom_themes ] : [],
				// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
				'config_params'        => json_encode( $config_params ),
				'delay'                => -100,
				'license'              => 'pro',
			]
		);

		$hcaptcha->init_hooks();

		// Test when Elementor Pro is not loaded.
		self::assertFalse( wp_script_is( 'hcaptcha' ) );

		ob_start();
		do_action( 'wp_print_footer_scripts' );
		$scripts = ob_get_clean();

		self::assertTrue( wp_script_is( 'hcaptcha' ) );

		$script = wp_scripts()->registered['hcaptcha'];
		self::assertSame( HCAPTCHA_URL . '/assets/js/apps/hcaptcha.js', $script->src );
		self::assertSame( [ 'wp-hooks' ], $script->deps );
		self::assertSame( HCAPTCHA_VERSION, $script->ver );
		self::assertSame( $expected_extra, $script->extra );

		self::assertSame( 0, strpos( $scripts, $expected_scripts ) );

		// Test when Elementor Pro is loaded.
		wp_dequeue_script( 'hcaptcha' );
		wp_deregister_script( 'hcaptcha' );

		wp_dequeue_script( 'jquery' );
		wp_deregister_script( 'jquery' );

		$loaded_classes = $this->get_protected_property( $hcaptcha, 'loaded_classes' );

		$loaded_classes[ HCaptchaHandler::class ] = new stdClass();

		$this->set_protected_property( $hcaptcha, 'loaded_classes', $loaded_classes );

		self::assertFalse( wp_script_is( 'hcaptcha' ) );

		ob_start();
		do_action( 'wp_print_footer_scripts' );
		$scripts = ob_get_clean();

		self::assertTrue( wp_script_is( 'hcaptcha' ) );

		$script = wp_scripts()->registered['hcaptcha'];
		self::assertSame( HCAPTCHA_URL . '/assets/js/apps/hcaptcha.js', $script->src );
		self::assertSame( [ 'wp-hooks' ], $script->deps );
		self::assertSame( HCAPTCHA_VERSION, $script->ver );
		self::assertSame( $expected_extra, $script->extra );

		self::assertNotFalse( strpos( $scripts, $expected_scripts ) );
	}

	/**
	 * Test print_footer_scripts when blocked by filter.
	 *
	 * @return void
	 */
	public function test_print_footer_script_when_blocked_by_filter(): void {
		add_filter( 'hcap_activate', '__return_true' );
		add_filter( 'hcap_print_hcaptcha_scripts', '__return_false' );

		$subject = new Main();

		$subject->init_hooks();

		self::assertFalse( wp_script_is( 'hcaptcha' ) );

		ob_start();
		do_action( 'wp_print_footer_scripts' );
		$scripts = ob_get_clean();

		self::assertFalse( wp_script_is( 'hcaptcha' ) );
		self::assertSame( '', $scripts );
	}

	/**
	 * Data provider for test_print_footer_scripts().
	 *
	 * @return array
	 */
	public function dp_test_print_footer_scripts(): array {
		return [
			'no options'         => [
				false,
				false,
				false,
				'https://js.hcaptcha.com/1/api.js?onload=hCaptchaOnLoad&render=explicit',
			],
			'empty options'      => [
				'',
				'',
				'',
				'https://js.hcaptcha.com/1/api.js?onload=hCaptchaOnLoad&render=explicit',
			],
			'compat only'        => [
				'on',
				false,
				false,
				'https://js.hcaptcha.com/1/api.js?onload=hCaptchaOnLoad&render=explicit&recaptchacompat=off',
			],
			'language only'      => [
				false,
				'ru',
				false,
				'https://js.hcaptcha.com/1/api.js?onload=hCaptchaOnLoad&render=explicit',
			],
			'custom themes only' => [
				false,
				false,
				'on',
				'https://js.hcaptcha.com/1/api.js?onload=hCaptchaOnLoad&render=explicit&custom=true',
			],
			'all options'        => [
				'on',
				'ru',
				'on',
				'https://js.hcaptcha.com/1/api.js?onload=hCaptchaOnLoad&render=explicit&recaptchacompat=off&custom=true',
			],
		];
	}

	/**
	 * Test allowlist_ip().
	 *
	 * @param mixed        $allowlisted_ips Settings.
	 * @param string|false $client_ip       Client IP.
	 * @param bool         $expected        Expected result.
	 *
	 * @dataProvider dp_test_allowlist_ip
	 * @return void
	 */
	public function test_allowlist_ip( $allowlisted_ips, $client_ip, bool $expected ): void {
		update_option( 'hcaptcha_settings', [ 'whitelisted_ips' => $allowlisted_ips ] );

		$subject = new Main();

		$subject->init_hooks();

		self::assertSame( $expected, $subject->allowlist_ip( false, $client_ip ) );
	}

	/**
	 * Data provider for test_allowlist_ip().
	 *
	 * @return array
	 */
	public function dp_test_allowlist_ip(): array {
		return [
			'no settings, local ip'       => [ '', false, false ],
			'some ips, local ip'          => [ " 4444444.777.2 \r\n 220.45.45.1 \r\n", false, false ],
			'some ips, not matching ip'   => [ " 4444444.777.2 \r\n 220.45.45.1 \r\n", '220.45.45.2', false ],
			'some ips, matching ip'       => [ " 4444444.777.2 \r\n 220.45.45.1 \r\n", '220.45.45.1', true ],
			'some ips, matching wrong ip' => [ " 4444444.777.2 \r\n 220.45.45.1 \r\n", '4444444.777.2', false ],
			'with local, local ip'        => [ " 4444444.777.2 \r\n 220.45.45.1 \r\n127.0.0.1\r\n", '127.0.0.1', true ],
		];
	}

	/**
	 * Test print_footer_scripts() when form NOT shown.
	 */
	public function test_print_footer_scripts_when_form_NOT_shown(): void {
		self::assertFalse( wp_script_is( 'hcaptcha' ) );

		$site_key = 'some key';

		update_option( 'hcaptcha_settings', [ 'site_key' => $site_key ] );

		$hcaptcha = hcaptcha();

		$hcaptcha->init_hooks();

		ob_start();
		do_action( 'wp_print_footer_scripts' );
		$scripts = ob_get_clean();

		self::assertFalse( strpos( $scripts, '<style>' ) );
		self::assertFalse( strpos( $scripts, 'api.js' ) );

		self::assertFalse( wp_script_is( 'hcaptcha' ) );
	}

	/**
	 * Test load_modules().
	 *
	 * @param array $module Module to load.
	 *
	 * @dataProvider dp_test_load_modules
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_load_modules( array $module ): void {
		[ $option_name, $option_value ] = $module[0];

		update_option(
			'hcaptcha_settings',
			[ $option_name => [ $option_value ] ]
		);

		$activate = false;

		add_filter(
			'hcap_activate',
			static function () use ( &$activate ) {
				return $activate;
			}
		);

		$subject = new Main();
		$subject->init_hooks();

		// Test with hCaptcha plugin not active.
		$subject->load_modules();

		$expected_loaded_classes = [
			PluginStats::class,
			Events::class,
			Privacy::class,
			WhatsNew::class,
		];
		$loaded_classes          = $this->get_protected_property( $subject, 'loaded_classes' );

		self::assertSame( $expected_loaded_classes, array_keys( $loaded_classes ) );

		// Activate hCaptcha.
		$activate = true;
		$subject->init_hooks();

		$plugin_path = '';
		$template    = '';
		$module1_arr = (array) $module[1];

		if (
			$module[1] &&
			false !== strpos( implode( ' ', $module1_arr ), '.php' )
		) {
			$plugin_path = $module1_arr[0];
		}

		if (
			$module[1] &&
			false === strpos( implode( ' ', $module1_arr ), '.php' )
		) {
			$template = $module1_arr[0];
		}

		$component = (array) $module[2];
		$component = isset( $module[3] ) ? (array) $module[3] : $component;

		$loaded_classes = $this->get_protected_property( $subject, 'loaded_classes' );

		self::assertSame( $expected_loaded_classes, array_keys( $loaded_classes ) );

		array_walk(
			$component,
			function ( &$value ) use ( &$expected_loaded_classes ) {
				if ( false === strpos( $value, '.php' ) ) {
					if ( ! class_exists( $value, false ) ) {
						$expected_loaded_classes[] = $value;
					}

					$value = str_replace( 'HCaptcha\\', HCAPTCHA_PATH . '/src/php/', $value );

					$value .= '.php';
				} else {
					$value = HCAPTCHA_INC . '/' . $value;
				}

				$value = $this->normalize_path( $value );
			}
		);

		$this->check_component_loaded( $component );

		// Test with supported plugin not active.
		$subject->load_modules();

		if ( ! $module[1] ) {
			self::$included_components = array_unique( array_merge( self::$included_components, $component ) );
		}

		$this->check_component_loaded( $component );

		if ( $plugin_path ) {
			add_filter(
				'pre_option_active_plugins',
				static function () use ( &$plugin_path ) {
					return [ $plugin_path ];
				},
				10,
				3
			);
		}

		if ( $template ) {
			add_filter(
				'template',
				static function () use ( $template ) {
					return $template;
				},
				20
			);
		}

		// Test with supported plugin active.
		$subject->load_modules();

		self::$included_components = array_unique( array_merge( self::$included_components, $component ) );
		$this->check_component_loaded( $component );

		$loaded_classes = $this->get_protected_property( $subject, 'loaded_classes' );

		self::assertSame( $expected_loaded_classes, array_keys( $loaded_classes ) );

		foreach ( $loaded_classes as $class_name => $loaded_class ) {
			self::assertInstanceOf( $class_name, $loaded_class );
			self::assertSame( $loaded_class, $subject->get( $class_name ) );
		}
	}

	/**
	 * Data provider for test_load_modules().
	 *
	 * @return array
	 * @noinspection PhpFullyQualifiedNameUsageInspection
	 */
	public function dp_test_load_modules(): array {
		// Return modules similar to those defined in the Main class.
		// If $module[3] is set, it contains the expected value.
		$modules = [
			'Comment Form'                      => [
				[ 'wp_status', 'comment' ],
				'',
				Comment::class,
			],
			'Login Form'                        => [
				[ 'wp_status', 'login' ],
				'',
				[ Login::class, LoginOut::class ],
			],
			'Lost Password Form'                => [
				[ 'wp_status', 'lost_pass' ],
				'',
				LostPassword::class,
			],
			'Post/Page Password Form'           => [
				[ 'wp_status', 'password_protected' ],
				'',
				PasswordProtected::class,
			],
			'Register Form'                     => [
				[ 'wp_status', 'register' ],
				'',
				Register::class,
			],
			'ACF Extended Form'                 => [
				[ 'acfe_status', 'form' ],
				[ 'acf-extended-pro/acf-extended.php', 'acf-extended/acf-extended.php' ],
				\HCaptcha\ACFE\Form::class,
			],
			'Asgaros Form'                      => [
				[ 'asgaros_status', 'form' ],
				'asgaros-forum/asgaros-forum.php',
				\HCaptcha\Asgaros\Form::class,
			],
			'Avada Form'                        => [
				[ 'avada_status', 'form' ],
				'Avada',
				[ \HCaptcha\Avada\Form::class ],
			],
			'Back In Stock Notifier Form'       => [
				[ 'back_in_stock_notifier_status', 'form' ],
				'back-in-stock-notifier-for-woocommerce/cwginstocknotifier.php',
				\HCaptcha\BackInStockNotifier\Form::class,
			],
			'bbPress Login Form'                => [
				[ 'bbp_status', 'login' ],
				'bbpress/bbpress.php',
				[
					\HCaptcha\BBPress\Login::class,
					\HCaptcha\BBPress\LostPassword::class,
					\HCaptcha\BBPress\Register::class,
				],
			],
			'bbPress Lost Password Form'        => [
				[ 'bbp_status', 'lost_pass' ],
				'bbpress/bbpress.php',
				[
					\HCaptcha\BBPress\Login::class,
					\HCaptcha\BBPress\LostPassword::class,
					\HCaptcha\BBPress\Register::class,
				],
			],
			'bbPress New Topic'                 => [
				[ 'bbp_status', 'new_topic' ],
				'bbpress/bbpress.php',
				NewTopic::class,
			],
			'bbPress Register Form'             => [
				[ 'bbp_status', 'register' ],
				'bbpress/bbpress.php',
				[
					\HCaptcha\BBPress\Login::class,
					\HCaptcha\BBPress\LostPassword::class,
					\HCaptcha\BBPress\Register::class,
				],
			],
			'bbPress Reply'                     => [
				[ 'bbp_status', 'reply' ],
				'bbpress/bbpress.php',
				Reply::class,
			],
			'Beaver Builder Contact Form'       => [
				[ 'beaver_builder_status', 'contact' ],
				'bb-plugin/fl-builder.php',
				\HCaptcha\BeaverBuilder\Contact::class,
			],
			'Beaver Builder Login Form'         => [
				[ 'beaver_builder_status', 'login' ],
				'bb-plugin/fl-builder.php',
				[ \HCaptcha\BeaverBuilder\Login::class, Login::class ],
			],
			'Brizy Form'                        => [
				[ 'brizy_status', 'form' ],
				'brizy/brizy.php',
				[ \HCaptcha\Brizy\Form::class ],
			],
			'BuddyPress Create Group'           => [
				[ 'bp_status', 'create_group' ],
				'buddypress/bp-loader.php',
				CreateGroup::class,
			],
			'BuddyPress Register'               => [
				[ 'bp_status', 'registration' ],
				'buddypress/bp-loader.php',
				\HCaptcha\BuddyPress\Register::class,
			],
			'Classified Listing Contact'        => [
				[ 'classified_listing_status', 'contact' ],
				'classified-listing/classified-listing.php',
				\HCaptcha\ClassifiedListing\Contact::class,
			],
			'Classified Listing Login'          => [
				[ 'classified_listing_status', 'login' ],
				'classified-listing/classified-listing.php',
				\HCaptcha\ClassifiedListing\Login::class,
			],
			'Classified Listing Lost Password'  => [
				[ 'classified_listing_status', 'lost_pass' ],
				'classified-listing/classified-listing.php',
				\HCaptcha\ClassifiedListing\LostPassword::class,
			],
			'Classified Listing Register'       => [
				[ 'classified_listing_status', 'register' ],
				'classified-listing/classified-listing.php',
				\HCaptcha\ClassifiedListing\Register::class,
			],
			'Colorlib Customizer Login'         => [
				[ 'colorlib_customizer_status', 'login' ],
				'colorlib-login-customizer/colorlib-login-customizer.php',
				\HCaptcha\ColorlibCustomizer\Login::class,
			],
			'Colorlib Customizer Lost Password' => [
				[ 'colorlib_customizer_status', 'lost_pass' ],
				'colorlib-login-customizer/colorlib-login-customizer.php',
				\HCaptcha\ColorlibCustomizer\LostPassword::class,
			],
			'Colorlib Customizer Register'      => [
				[ 'colorlib_customizer_status', 'register' ],
				'colorlib-login-customizer/colorlib-login-customizer.php',
				\HCaptcha\ColorlibCustomizer\Register::class,
			],
			'Contact Form 7'                    => [
				[ 'cf7_status', 'form' ],
				'contact-form-7/wp-contact-form-7.php',
				[ CF7::class, Admin::class, ReallySimpleCaptcha::class ],
			],
			'Divi Comment Form'                 => [
				[ 'divi_status', 'comment' ],
				'Divi',
				[ \HCaptcha\Divi\Comment::class, Comment::class ],
				[ \HCaptcha\Divi\Comment::class, \HCaptcha\Divi\Login::class ],
			],
			'Divi Contact Form'                 => [
				[ 'divi_status', 'contact' ],
				'Divi',
				Contact::class,
			],
			'Divi Email Optin Form'             => [
				[ 'divi_status', 'email_optin' ],
				'Divi',
				EmailOptin::class,
			],
			'Divi Login Form'                   => [
				[ 'divi_status', 'login' ],
				'Divi',
				\HCaptcha\Divi\Login::class,
			],
			'Download Manager'                  => [
				[ 'download_manager_status', 'button' ],
				'download-manager/download-manager.php',
				DownloadManager::class,
			],
			'Easy Digital Downloads Checkout'   => [
				[ 'easy_digital_downloads_status', 'checkout' ],
				'easy-digital-downloads/easy-digital-downloads.php',
				\HCaptcha\EasyDigitalDownloads\Checkout::class,
			],
			'Elementor Pro Form'                => [
				[ 'elementor_pro_status', 'form' ],
				'elementor-pro/elementor-pro.php',
				[ HCaptchaHandler::class, \HCaptcha\ElementorPro\Login::class ],
			],
			'Elementor Pro Login'               => [
				[ 'elementor_pro_status', null ],
				'elementor-pro/elementor-pro.php',
				\HCaptcha\ElementorPro\Login::class,
			],
			'Fluent Forms'                      => [
				[ 'fluent_status', 'form' ],
				'fluentform/fluentform.php',
				Form::class,
			],
			'Formidable Forms'                  => [
				[ 'formidable_forms_status', 'form' ],
				'formidable/formidable.php',
				\HCaptcha\FormidableForms\Form::class,
			],
			'Forminator'                        => [
				[ 'forminator_status', 'form' ],
				'forminator/forminator.php',
				\HCaptcha\Forminator\Form::class,
			],
			'GiveWP'                            => [
				[ 'give_wp_status', 'form' ],
				'give/give.php',
				\HCaptcha\GiveWP\Form::class,
			],
			'Gravity Forms'                     => [
				[ 'gravity_status', null ],
				'gravityforms/gravityforms.php',
				[ \HCaptcha\GravityForms\Form::class, \HCaptcha\GravityForms\Field::class ],
			],
			'Jetpack'                           => [
				[ 'jetpack_status', 'contact' ],
				'jetpack/jetpack.php',
				\HCaptcha\Jetpack\Form::class,
			],
			'Kadence Form'                      => [
				[ 'kadence_status', 'form' ],
				'kadence-blocks/kadence-blocks.php',
				\HCaptcha\Kadence\Form::class,
			],
			'Kadence Advanced Form'             => [
				[ 'kadence_status', 'advanced_form' ],
				'kadence-blocks/kadence-blocks.php',
				\HCaptcha\Kadence\AdvancedForm::class,
			],
			'LearnDash Login Form'              => [
				[ 'learn_dash_status', 'login' ],
				'sfwd-lms/sfwd_lms.php',
				\HCaptcha\LearnDash\Login::class,
			],
			'LearnDash Lost Password Form'      => [
				[ 'learn_dash_status', 'lost_pass' ],
				'sfwd-lms/sfwd_lms.php',
				\HCaptcha\LearnDash\LostPassword::class,
			],
			'LearnDash Registration Form'       => [
				[ 'learn_dash_status', 'register' ],
				'sfwd-lms/sfwd_lms.php',
				\HCaptcha\LearnDash\Register::class,
			],
			'MailChimp'                         => [
				[ 'mailchimp_status', 'form' ],
				'mailchimp-for-wp/mailchimp-for-wp.php',
				\HCaptcha\Mailchimp\Form::class,
			],
			'MemberPress Login'                 => [
				[ 'memberpress_status', 'login' ],
				'memberpress/memberpress.php',
				[ \HCaptcha\MemberPress\Login::class, Login::class ],
			],
			'MemberPress Register'              => [
				[ 'memberpress_status', 'register' ],
				'memberpress/memberpress.php',
				\HCaptcha\MemberPress\Register::class,
			],
			'Ninja Forms'                       => [
				[ 'ninja_status', 'form' ],
				'ninja-forms/ninja-forms.php',
				NF::class,
			],
			'Otter Blocks'                      => [
				[ 'otter_status', 'form' ],
				'otter-blocks/otter-blocks.php',
				\HCaptcha\Otter\Form::class,
			],
			'Paid Memberships Pro Checkout'     => [
				[ 'paid_memberships_pro_status', 'checkout' ],
				'paid-memberships-pro/paid-memberships-pro.php',
				[ \HCaptcha\PaidMembershipsPro\Checkout::class, \HCaptcha\PaidMembershipsPro\Login::class ],
			],
			'Paid Memberships Pro Login'        => [
				[ 'paid_memberships_pro_status', 'login' ],
				'paid-memberships-pro/paid-memberships-pro.php',
				\HCaptcha\PaidMembershipsPro\Login::class,
			],
			'Passster Protect'                  => [
				[ 'passster_status', 'protect' ],
				'content-protector/content-protector.php',
				\HCaptcha\Passster\Protect::class,
			],
			'Profile Builder Login'             => [
				[ 'profile_builder_status', 'login' ],
				'profile-builder/index.php',
				\HCaptcha\ProfileBuilder\Login::class,
			],
			'Profile Builder Register'          => [
				[ 'profile_builder_status', 'register' ],
				'profile-builder/index.php',
				\HCaptcha\ProfileBuilder\Register::class,
			],
			'Profile Builder Recover Password'  => [
				[ 'profile_builder_status', 'lost_pass' ],
				'profile-builder/index.php',
				\HCaptcha\ProfileBuilder\LostPassword::class,
			],
			'Quform'                            => [
				[ 'quform_status', 'form' ],
				'quform/quform.php',
				Quform::class,
			],
			'Sendinblue'                        => [
				[ 'sendinblue_status', 'form' ],
				'mailin/sendinblue.php',
				Sendinblue::class,
			],
			'Subscriber'                        => [
				[ 'subscriber_status', 'form' ],
				'subscriber/subscriber.php',
				\HCaptcha\Subscriber\Form::class,
			],
			'Support Candy Form'                => [
				[ 'supportcandy_status', 'form' ],
				'supportcandy/supportcandy.php',
				\HCaptcha\SupportCandy\Form::class,
			],
			'Theme My Login Login'              => [
				[ 'theme_my_login_status', 'login' ],
				'theme-my-login/theme-my-login.php',
				\HCaptcha\ThemeMyLogin\Login::class,
			],
			'Theme My Login LostPassword'       => [
				[ 'theme_my_login_status', 'lost_pass' ],
				'theme-my-login/theme-my-login.php',
				\HCaptcha\ThemeMyLogin\LostPassword::class,
			],
			'Theme My Login Register'           => [
				[ 'theme_my_login_status', 'register' ],
				'theme-my-login/theme-my-login.php',
				\HCaptcha\ThemeMyLogin\Register::class,
			],
			'Ultimate Member Login'             => [
				[ 'ultimate_member_status', 'login' ],
				'ultimate-member/ultimate-member.php',
				\HCaptcha\UM\Login::class,
			],
			'Ultimate Member LostPassword'      => [
				[ 'ultimate_member_status', 'lost_pass' ],
				'ultimate-member/ultimate-member.php',
				\HCaptcha\UM\LostPassword::class,
			],
			'Ultimate Member Register'          => [
				[ 'ultimate_member_status', 'register' ],
				'ultimate-member/ultimate-member.php',
				\HCaptcha\UM\Register::class,
			],
			'UsersWP Forgot Password'           => [
				[ 'users_wp_status', 'forgot' ],
				'userswp/userswp.php',
				\HCaptcha\UsersWP\ForgotPassword::class,
			],
			'UsersWP Login'                     => [
				[ 'users_wp_status', 'login' ],
				'userswp/userswp.php',
				\HCaptcha\UsersWP\Login::class,
			],
			'UsersWP Register'                  => [
				[ 'users_wp_status', 'register' ],
				'userswp/userswp.php',
				\HCaptcha\UsersWP\Register::class,
			],
			'WooCommerce Checkout'              => [
				[ 'woocommerce_status', 'checkout' ],
				'woocommerce/woocommerce.php',
				Checkout::class,
			],
			'WooCommerce Login'                 => [
				[ 'woocommerce_status', 'login' ],
				'woocommerce/woocommerce.php',
				\HCaptcha\WC\Login::class,
			],
			'WooCommerce Lost Password'         => [
				[ 'woocommerce_status', 'lost_pass' ],
				'woocommerce/woocommerce.php',
				[ LostPassword::class, \HCaptcha\WC\LostPassword::class ],
			],
			'WooCommerce Order Tracking'        => [
				[ 'woocommerce_status', 'order_tracking' ],
				'woocommerce/woocommerce.php',
				OrderTracking::class,
			],
			'WooCommerce Register'              => [
				[ 'woocommerce_status', 'register' ],
				'woocommerce/woocommerce.php',
				\HCaptcha\WC\Register::class,
			],
			'WooCommerce Wishlists'             => [
				[ 'woocommerce_wishlists_status', 'create_list' ],
				'woocommerce-wishlists/woocommerce-wishlists.php',
				CreateList::class,
			],
			'Wordfence Login'                   => [
				[ 'wordfence_status', null ],
				[ 'wordfence/wordfence.php', 'wordfence-login-security/wordfence-login-security.php' ],
				\HCaptcha\Wordfence\General::class,
			],
			'WPForms Lite'                      => [
				[ 'wpforms_status', 'lite' ],
				'wpforms-lite/wpforms.php',
				\HCaptcha\WPForms\Form::class,
			],
			'WPForms Pro'                       => [
				[ 'wpforms_status', 'pro' ],
				'wpforms/wpforms.php',
				\HCaptcha\WPForms\Form::class,
			],
			'wpDiscuz Comment'                  => [
				[ 'wpdiscuz_status', 'comment_form' ],
				'wpdiscuz/class.WpdiscuzCore.php',
				\HCaptcha\WPDiscuz\Comment::class,
			],
			'wpDiscuz Subscribe'                => [
				[ 'wpdiscuz_status', 'subscribe_form' ],
				'wpdiscuz/class.WpdiscuzCore.php',
				Subscribe::class,
			],
			'wpForo New Topic'                  => [
				[ 'wpforo_status', 'new_topic' ],
				'wpforo/wpforo.php',
				\HCaptcha\WPForo\NewTopic::class,
			],
			'wpForo Reply'                      => [
				[ 'wpforo_status', 'reply' ],
				'wpforo/wpforo.php',
				\HCaptcha\WPForo\Reply::class,
			],
		];

		array_walk(
			$modules,
			static function ( &$value ) {
				$value = [ $value ];
			}
		);

		return $modules;
	}

	/**
	 * Test is_plugin_active().
	 *
	 * @return void
	 */
	public function test_is_plugin_active(): void {
		$plugin = 'some-plugin/some-plugin.php';

		add_filter(
			'pre_option_active_plugins',
			static function () use ( $plugin, &$active ) {
				return $active ? [ $plugin ] : false;
			}
		);

		$subject = new Main();

		$active = false;

		// Single site, not active.
		self::assertFalse( $subject->is_plugin_active( $plugin ) );

		$active = true;

		// Single site, active.
		self::assertTrue( $subject->is_plugin_active( $plugin ) );
	}

	/**
	 * Test load_textdomain().
	 */
	public function test_load_textdomain(): void {
		$subject = new Main();
		$subject->init_hooks();

		$locale = 'en_US';

		$default_domain = 'default';
		$default_mofile = WP_LANG_DIR . "/$locale.mo";

		$override_filter_params = [];

		add_filter(
			'override_load_textdomain',
			static function ( $override, $domain, $mofile ) use ( &$override_filter_params ) {
				$override_filter_params[] = [ $override, $domain, $mofile ];

				return $override;
			},
			Main::LOAD_PRIORITY,
			3
		);

		$saved_wp_textdomain_registry      = $GLOBALS['wp_textdomain_registry'] ?? null;
		$GLOBALS['wp_textdomain_registry'] = new WP_Textdomain_Registry();

		self::assertFalse( $GLOBALS['wp_textdomain_registry']->get( 'hcaptcha-for-forms-and-more', 'ko_KR' ) );

		$GLOBALS['wp_textdomain_registry'] = new WP_Textdomain_Registry();

		$subject->load_textdomain();

		self::assertNotEmpty( $GLOBALS['wp_textdomain_registry']->get( 'hcaptcha-for-forms-and-more', 'ko_KR' ) );

		$GLOBALS['wp_textdomain_registry'] = $saved_wp_textdomain_registry;

		self::assertFalse( $override_filter_params[0][0] );
		self::assertSame( $default_domain, $override_filter_params[0][1] );
		self::assertSame( $default_mofile, $override_filter_params[0][2] );
	}

	/**
	 * Convert a Windows path to Linux style to make tests OS-independent.
	 *
	 * @param string|string[] $path Path.
	 *
	 * @return string|string[]
	 */
	private function normalize_path( $path ) {
		return str_replace( '\\', '/', $path );
	}

	/**
	 * Check that component is loaded.
	 *
	 * @param array $component Component.
	 */
	public function check_component_loaded( array $component ): void {
		$intersect = array_intersect( $component, $this->normalize_path( get_included_files() ) );
		$included  = array_intersect( $component, self::$included_components );
		self::assertSame( $included, $intersect );
	}
}
