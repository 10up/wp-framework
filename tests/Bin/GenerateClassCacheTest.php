<?php
/**
 * Integration tests for the tenup-framework-generate-class-cache build command.
 *
 * These shell out to the actual bin script (the way CI would) rather than calling
 * ModuleInitialization directly, so the autoloader-resolution, argument-handling and
 * exit-code behaviour of the script itself is covered.
 *
 * @package TenupFramework
 */

declare(strict_types = 1);

namespace TenupFrameworkTests\Bin;

use PHPUnit\Framework\TestCase;
use TenupFramework\ModuleInitialization;

/**
 * GenerateClassCacheTest class.
 */
class GenerateClassCacheTest extends TestCase {

	/**
	 * Temporary directories created during a test, removed on teardown.
	 *
	 * @var array<int, string>
	 */
	private $temp_dirs = [];

	/**
	 * Remove any temporary directories created during the test.
	 *
	 * @return void
	 */
	protected function tearDown(): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		foreach ( $this->temp_dirs as $dir ) {
			$this->remove_dir( $dir );
		}
		$this->temp_dirs = [];

		parent::tearDown();
	}

	/**
	 * Running the command against a directory writes a readable cache of its classes.
	 *
	 * @return void
	 */
	public function test_generates_a_cache_for_a_directory() {
		$dir = $this->example_copy( 'plugin-inc' );

		$result = $this->run_bin( [ $dir ] );

		$this->assertSame( 0, $result['exit'], $result['stderr'] );
		$this->assertStringContainsString( 'Cached', $result['stdout'] );

		$cache_file = $this->cache_file_path( $dir );
		$this->assertFileExists( $cache_file );

		$cached = require $cache_file;
		$this->assertContains( 'TenupFrameworkExamples\\Modules\\GreetingModule', $cached );
		$this->assertContains( 'TenupFrameworkExamples\\Support\\Formatter', $cached );
	}

	/**
	 * With no arguments the command prints usage to stderr and exits non-zero.
	 *
	 * @return void
	 */
	public function test_reports_usage_and_fails_without_arguments() {
		$result = $this->run_bin( [] );

		$this->assertSame( 1, $result['exit'] );
		$this->assertStringContainsString( 'Usage:', $result['stderr'] );
	}

	/**
	 * A missing directory fails that directory (non-zero exit, error on stderr) but the command
	 * still processes the directories that are valid.
	 *
	 * @return void
	 */
	public function test_missing_directory_fails_but_valid_directories_still_cache() {
		$good    = $this->example_copy( 'plugin-inc' );
		$missing = sys_get_temp_dir() . '/tenup_bin_missing_' . uniqid( '', true );

		$result = $this->run_bin( [ $missing, $good ] );

		$this->assertSame( 1, $result['exit'] );
		$this->assertStringContainsString( $missing, $result['stderr'] );
		$this->assertStringContainsString( 'Failed to generate cache', $result['stderr'] );

		// The valid directory was still cached despite the earlier failure.
		$this->assertFileExists( $this->cache_file_path( $good ) );
	}

	/**
	 * Several directories can be cached in a single invocation.
	 *
	 * @return void
	 */
	public function test_caches_multiple_directories() {
		$first  = $this->example_copy( 'plugin-inc' );
		$second = $this->example_copy( 'second-inc' );

		$result = $this->run_bin( [ $first, $second ] );

		$this->assertSame( 0, $result['exit'], $result['stderr'] );
		$this->assertFileExists( $this->cache_file_path( $first ) );
		$this->assertFileExists( $this->cache_file_path( $second ) );

		$cached = require $this->cache_file_path( $second );
		$this->assertContains( 'TenupFrameworkExamples\\Widgets\\Card', $cached );
	}

	/**
	 * A cache that cannot be written fails loudly instead of reporting success.
	 *
	 * Spatie's file driver ignores the return values of mkdir()/file_put_contents(), so without
	 * an explicit check the command printed "Cached N class(es)" and exited 0 having written
	 * nothing — a broken build staying green.
	 *
	 * @return void
	 */
	public function test_unwritable_directory_fails_instead_of_reporting_success() {
		$dir = $this->example_copy( 'plugin-inc' );
		chmod( $dir, 0555 );

		$result = $this->run_bin( [ $dir ] );

		// Restore permissions first so teardown can always clean up.
		chmod( $dir, 0755 );

		$this->assertSame( 1, $result['exit'], 'An unwritable target must fail the build.' );
		$this->assertStringContainsString( 'Failed to write the class cache', $result['stderr'] );
		$this->assertStringNotContainsString( 'Cached', $result['stdout'] );
		$this->assertFileDoesNotExist( $this->cache_file_path( $dir ) );
	}

	/**
	 * A regenerate that cannot replace the previous build's cache fails the build rather than
	 * leaving the old file to be deployed as if it were freshly built (issue #30).
	 *
	 * The old file is unlinked before the write, so a write that fails for a reason unrelated to
	 * directory permissions (a full disk) leaves nothing behind. When the directory itself is
	 * unwritable the old file cannot be removed at all — so the guarantee that matters is the
	 * non-zero exit, which stops the pipeline before it can ship the stale cache.
	 *
	 * @return void
	 */
	public function test_regenerate_that_cannot_replace_a_stale_cache_fails_the_build() {
		$dir = $this->example_copy( 'plugin-inc' );

		// First build succeeds.
		$this->assertSame( 0, $this->run_bin( [ $dir ] )['exit'] );
		$cache_file = $this->cache_file_path( $dir );
		$this->assertFileExists( $cache_file );

		// Make only the cache directory unwritable, so the rewrite cannot happen.
		$cache_dir = dirname( $cache_file );
		chmod( $cache_dir, 0555 );

		$result = $this->run_bin( [ $dir ] );

		// Restore permissions first so teardown can always clean up.
		chmod( $cache_dir, 0755 );

		$this->assertSame( 1, $result['exit'], 'A cache that cannot be replaced must fail the build.' );
		$this->assertStringContainsString( 'Could not remove the existing class cache', $result['stderr'] );
		$this->assertStringNotContainsString( 'Cached', $result['stdout'] );
	}

	/**
	 * Run the bin script with the given arguments, returning its stdout, stderr and exit code.
	 *
	 * @param array<int, string> $args The arguments to pass after the script name.
	 *
	 * @return array{stdout: string, stderr: string, exit: int}
	 */
	private function run_bin( array $args ): array {
		$script  = dirname( __DIR__, 2 ) . '/bin/tenup-framework-generate-class-cache';
		$command = array_map( 'escapeshellarg', array_merge( [ PHP_BINARY, $script ], $args ) );

		$descriptors = [
			1 => [ 'pipe', 'w' ],
			2 => [ 'pipe', 'w' ],
		];

		$process = proc_open( implode( ' ', $command ), $descriptors, $pipes );
		$this->assertIsResource( $process );

		$stdout = (string) stream_get_contents( $pipes[1] );
		$stderr = (string) stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );

		$exit = proc_close( $process );

		return [
			'stdout' => $stdout,
			'stderr' => $stderr,
			'exit'   => $exit,
		];
	}

	/**
	 * Copy an example directory into a fresh temp directory so the command can write a cache
	 * into it without touching the committed examples.
	 *
	 * @param string $name The example directory name under tests/examples.
	 *
	 * @return string The path to the temp copy.
	 */
	private function example_copy( string $name ): string {
		$source = __DIR__ . '/../examples/' . $name;
		$target = sys_get_temp_dir() . '/tenup_bin_' . $name . '_' . uniqid( '', true );

		$this->copy_dir( $source, $target );
		$this->temp_dirs[] = $target;

		return $target;
	}

	/**
	 * The absolute path to the cache file the command writes for a directory.
	 *
	 * @param string $dir The discovery directory.
	 *
	 * @return string
	 */
	private function cache_file_path( string $dir ): string {
		return $dir . '/' . ModuleInitialization::CACHE_DIR_NAME . '/' . ModuleInitialization::CACHE_FILENAME;
	}

	/**
	 * Recursively copy a directory.
	 *
	 * @param string $source The source directory.
	 * @param string $target The target directory.
	 *
	 * @return void
	 */
	private function copy_dir( string $source, string $target ): void {
		mkdir( $target, 0777, true );

		$items = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $source, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $items as $item ) {
			$destination = $target . '/' . $items->getSubPathname();
			if ( $item->isDir() ) {
				mkdir( $destination, 0777, true );
			} else {
				copy( $item->getPathname(), $destination );
			}
		}
	}

	/**
	 * Recursively remove a directory.
	 *
	 * @param string $dir The directory to remove.
	 *
	 * @return void
	 */
	private function remove_dir( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$items = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $items as $item ) {
			if ( $item->isDir() ) {
				rmdir( $item->getPathname() );
			} else {
				unlink( $item->getPathname() );
			}
		}

		rmdir( $dir );
	}
}
