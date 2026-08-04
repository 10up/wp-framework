<?php
/**
 * A trait to set up the test environment.
 *
 * Handles setting up brainmonkey and mocking common WordPress functions.
 *
 * @package TenupFramework
 */

declare(strict_types = 1);

namespace TenupFrameworkTests;

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use function Brain\Monkey\Functions\stubEscapeFunctions;
use function Brain\Monkey\Functions\stubs;
use function Brain\Monkey\Functions\stubTranslationFunctions;

/**
 * Trait FrameworkTestSetup
 *
 * @runTestsInSeparateProcesses
 *
 * @package TenupFramework
 */
trait FrameworkTestSetup {
	use MockeryPHPUnitIntegration;

	/**
	 * Registered taxonomies.
	 *
	 * @var array<string,array>
	 */
	public static $registered_taxonomies = [];

	/**
	 * Registered post types.
	 *
	 * @var array<string,array>
	 */
	public static $registered_post_types = [];

	/**
	 * Set up the test.
	 *
	 * @return void
	 */
	protected function setUp(): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		parent::setUp();
		Monkey\setUp();

		stubs(
			[
				'wp_get_environment_type'           => 'local',
				// Default to the front end so existing tests don't trigger admin-only debug
				// recording; admin tests override this with their own stub.
				'is_admin'                          => false,
				'sanitize_title'                    => function ( $title ) {
					return str_replace( ' ', '-', strtolower( $title ) );
				},
				'register_post_type'                => function ( $slug, $args ) {
					self::$registered_post_types[ $slug ] = $args;
				},
				'register_taxonomy_for_object_type' => '__return_true',
				'register_taxonomy'                 => function ( $slug, $object_type, $args ) {
					self::$registered_taxonomies[ $slug ] = $args;
				},
			]
		);

		stubEscapeFunctions();
		stubTranslationFunctions();

		$this->reset_module_initialization();
		$this->reset_loader_debug();
	}

	/**
	 * Reset the ModuleInitialization singleton so its accumulated `$classes` do not leak
	 * between tests.
	 *
	 * Since here the `@runTestsInSeparateProcesses` annotation on this trait *is* in effect,
	 * this guards in case of removed trait annotation of multiple single test init_classes() calls.
	 *
	 * @return void
	 */
	protected function reset_module_initialization(): void {
		if ( ! class_exists( \TenupFramework\ModuleInitialization::class ) ) {
			return;
		}

		$instance = ( new \ReflectionClass( \TenupFramework\ModuleInitialization::class ) )->getProperty( 'instance' );
		$instance->setValue( null, null );
	}

	/**
	 * Reset the static state of the LoaderDebug registry so each test starts clean,
	 * independent of test execution order or process isolation.
	 *
	 * @return void
	 */
	protected function reset_loader_debug(): void {
		if ( ! class_exists( \TenupFramework\Debug\LoaderDebug::class ) ) {
			return;
		}

		$reflection = new \ReflectionClass( \TenupFramework\Debug\LoaderDebug::class );

		$reflection->getProperty( 'loaders' )->setValue( null, [] );
		$reflection->getProperty( 'booted' )->setValue( null, false );

		unset( $GLOBALS['tenup_framework_debug_page_registered'] );
	}

	/**
	 * Tear down the test.
	 *
	 * @return void
	 */
	protected function tearDown(): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		self::$registered_taxonomies = [];
		self::$registered_post_types = [];

		Monkey\tearDown();
		parent::tearDown();
	}
}
