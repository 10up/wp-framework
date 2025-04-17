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
