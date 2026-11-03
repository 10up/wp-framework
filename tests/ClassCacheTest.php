<?php
/**
 * Tests for the prebuilt class cache.
 *
 * @package TenupFramework
 */

declare(strict_types = 1);

namespace TenupFrameworkTests;

use PHPUnit\Framework\TestCase;
use TenupFramework\ClassCache;
use TenupFramework\ModuleInitialization;

/**
 * ClassCacheTest
 *
 * Each test works on a copy of fixtures/classes in a temporary directory, so
 * writing a cache never touches the fixtures.
 */
class ClassCacheTest extends TestCase {

	use FrameworkTestSetup;

	/**
	 * Temporary copy of the fixture classes.
	 *
	 * @var string
	 */
	private string $dir = '';

	/**
	 * Copy the fixtures to a temporary directory.
	 *
	 * @return void
	 */
	protected function setUp(): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		parent::setUp();

		$this->dir = sys_get_temp_dir() . '/wp-framework-class-cache-' . uniqid();
		$this->copy( dirname( __DIR__ ) . '/fixtures/classes', $this->dir );
	}

	/**
	 * Remove the temporary directory.
	 *
	 * @return void
	 */
	protected function tearDown(): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->remove( $this->dir );
		parent::tearDown();
	}

	/**
	 * Writing a cache lists the directory's classes and reading it back returns them.
	 *
	 * @return void
	 */
	public function test_write_then_read_returns_the_classes() {
		$written = ClassCache::write( $this->dir );

		$this->assertFileExists( ClassCache::path( $this->dir ) );
		$this->assertContains( 'TenupFrameworkTestClasses\PostTypes\Demo', $written );
		$this->assertSame( $written, ClassCache::read( $this->dir ) );
	}

	/**
	 * Without a cache file there is nothing to read.
	 *
	 * @return void
	 */
	public function test_read_returns_null_without_a_cache() {
		$this->assertNull( ClassCache::read( $this->dir ) );
	}

	/**
	 * A cache stops being used as soon as the code it lists changes.
	 *
	 * @return void
	 */
	public function test_read_returns_null_when_the_code_changed() {
		ClassCache::write( $this->dir );

		file_put_contents( $this->dir . '/Added.php', "<?php\nnamespace TenupFrameworkTestClasses;\nclass Added {}\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		$this->assertNull( ClassCache::read( $this->dir ) );
	}

	/**
	 * The hash ignores the cache folder itself, so writing a cache doesn't invalidate it.
	 *
	 * @return void
	 */
	public function test_hash_ignores_the_cache_folder() {
		$before = ClassCache::hash( $this->dir );
		ClassCache::write( $this->dir );

		$this->assertSame( $before, ClassCache::hash( $this->dir ) );
	}

	/**
	 * Discovery uses a matching prebuilt cache instead of scanning the directory.
	 *
	 * The cache is edited to list a class that doesn't exist, so only a read of
	 * the cache can return it.
	 *
	 * @return void
	 */
	public function test_get_classes_prefers_the_prebuilt_cache() {
		$this->write_marker_cache();

		$classes = ModuleInitialization::instance()->get_classes( $this->dir );

		$this->assertSame( [ 'TenupFrameworkTestClasses\FromTheCache' ], $classes );
	}

	/**
	 * On WordPress VIP, where the runtime cache is skipped, the prebuilt cache is still read.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @return void
	 */
	public function test_get_classes_reads_the_prebuilt_cache_on_vip() {
		define( 'VIP_GO_APP_ENVIRONMENT', 'production' );
		$this->write_marker_cache();

		$this->assertSame( [ 'TenupFrameworkTestClasses\FromTheCache' ], ModuleInitialization::instance()->get_classes( $this->dir ) );
	}

	/**
	 * TENUP_FRAMEWORK_DISABLE_CLASS_CACHE turns the prebuilt cache off too.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @return void
	 */
	public function test_disabling_the_cache_ignores_the_prebuilt_cache() {
		define( 'TENUP_FRAMEWORK_DISABLE_CLASS_CACHE', true );
		$this->write_marker_cache();

		$classes = ModuleInitialization::instance()->get_classes( $this->dir );

		$this->assertNotContains( 'TenupFrameworkTestClasses\FromTheCache', $classes );
		$this->assertContains( 'TenupFrameworkTestClasses\PostTypes\Demo', $classes );
	}

	/**
	 * Write a valid cache for the directory that lists a single marker class.
	 *
	 * @return void
	 */
	private function write_marker_cache(): void {
		ClassCache::write( $this->dir );

		$contents = "<?php\nreturn " . var_export( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
			[
				'hash'    => ClassCache::hash( $this->dir ),
				'classes' => [ 'TenupFrameworkTestClasses\FromTheCache' ],
			],
			true
		) . ";\n";

		file_put_contents( ClassCache::path( $this->dir ), $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	/**
	 * Copy a directory recursively.
	 *
	 * @param string $from Source.
	 * @param string $to   Destination.
	 *
	 * @return void
	 */
	private function copy( string $from, string $to ): void {
		mkdir( $to, 0755, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir

		foreach ( scandir( $from ) as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}

			if ( is_dir( "$from/$entry" ) ) {
				$this->copy( "$from/$entry", "$to/$entry" );
			} else {
				copy( "$from/$entry", "$to/$entry" );
			}
		}
	}

	/**
	 * Remove a directory recursively.
	 *
	 * @param string $dir Directory.
	 *
	 * @return void
	 */
	private function remove( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		foreach ( scandir( $dir ) as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}

			$path = "$dir/$entry";
			is_dir( $path ) ? $this->remove( $path ) : unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		}

		rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	}
}
