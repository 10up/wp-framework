<?php
/**
 * Test Class
 *
 * @package TenupScaffold
 */

declare(strict_types = 1);

namespace TenupFrameworkTests;

use PHPUnit\Framework\TestCase;
use function Brain\Monkey\Functions\when;

/**
 * Test Class
 */
class ModuleInitializationTest extends TestCase {

	use FrameworkTestSetup;

	/**
	 * Ensure we can find the right classes.
	 *
	 * @return void
	 */
	public function test_it_can_find_classes() {
		$class   = \TenupFramework\ModuleInitialization::instance();
		$classes = $class->get_classes( dirname( __DIR__, 1 ) . '/src/' );

		// Check that we have the concrete classes we expect to see.
		$this->assertContains( 'TenupFramework\PostTypes\AbstractPostType', $classes );
		$this->assertContains( 'TenupFramework\PostTypes\AbstractCorePostType', $classes );
		$this->assertContains( 'TenupFramework\Taxonomies\AbstractTaxonomy', $classes );
		$this->assertContains( 'TenupFramework\ModuleInitialization', $classes );
	}

	/**
	 * Ensure we can find the right classes.
	 *
	 * @return void
	 */
	public function test_it_can_find_classes_to_register() {
		$class = \TenupFramework\ModuleInitialization::instance();
		$class->init_classes( dirname( __DIR__, 1 ) . '/fixtures/classes' );
		$classes = $class->get_all_classes();

		// The registered set is non-empty and contains only ModuleInterface implementations.
		$this->assertNotEmpty( $classes );
		foreach ( $classes as $registered ) {
			$this->assertInstanceOf( \TenupFramework\ModuleInterface::class, $registered );
		}
	}

	/**
	 * Ensure an exception is thrown when a directory does not exist.
	 *
	 * @return void
	 */
	public function test_that_an_exception_is_thrown_when_a_directory_does_not_exist() {
		$class = \TenupFramework\ModuleInitialization::instance();
		$this->expectException( \RuntimeException::class );
		$class->init_classes( dirname( __DIR__, 1 ) . '/src/does-not-exist-1234567/' );
	}

	/**
	 * Ensure an exception is thrown when a directory is not passed.
	 *
	 * @return void
	 */
	public function test_that_an_exception_is_thrown_when_a_directory_is_not_passed() {
		$class = \TenupFramework\ModuleInitialization::instance();
		$this->expectException( \RuntimeException::class );
		$class->init_classes();
	}

	/**
	 * Ensure the instance method returns the same instance.
	 *
	 * @return void
	 */
	public function test_instance_returns_same_instance() {
		$instance1 = \TenupFramework\ModuleInitialization::instance();
		$instance2 = \TenupFramework\ModuleInitialization::instance();
		$this->assertSame( $instance1, $instance2 );
	}

	/**
	 * Ensure the instance method returns the same instance.
	 *
	 * @return void
	 */
	public function test_get_classes_returns_classes_from_directory() {
		$module_init = \TenupFramework\ModuleInitialization::instance();
		$classes     = $module_init->get_classes( dirname( __DIR__, 1 ) . '/fixtures/classes' );
		$this->assertIsArray( $classes );
		$this->assertNotEmpty( $classes );
	}

	/**
	 * Ensure the instance method returns the same instance.
	 *
	 * @return void
	 */
	public function test_init_classes_initializes_classes_in_correct_order() {
		$module_init = \TenupFramework\ModuleInitialization::instance();
		$module_init->init_classes( dirname( __DIR__, 1 ) . '/fixtures/classes' );
		$classes = $module_init->get_all_classes();
		$this->assertNotEmpty( $classes );
		$this->assertNotContains( 'TenupFramework\Taxonomies\AbstractTaxonomy', $classes );
		$this->assertInstanceOf( \TenupFramework\ModuleInterface::class, reset( $classes ) );
	}

	/**
	 * Ensure that we can return an instantiated class vie get_module.
	 *
	 * @return void
	 */
	public function test_get_module_returns_instantiated_class() {
		$module_init = \TenupFramework\ModuleInitialization::instance();
		$module_init->init_classes( dirname( __DIR__, 1 ) . '/fixtures/classes' );
		$module = \TenupFramework\ModuleInitialization::get_module( 'TenupFrameworkTestClasses\PostTypes\Demo' );
		$this->assertInstanceOf( \TenupFrameworkTestClasses\PostTypes\Demo::class, $module );

		$module = \TenupFramework\ModuleInitialization::get_module( 'TenupFrameworkTestClasses\DoesntExist' );
		$this->assertFalse( $module );
	}

	/**
	 * Test that only classes implementing ModuleInterface are initialized.
	 *
	 * @return void
	 */
	public function test_only_classes_implementing_module_interface_are_initialized() {
		$module_init = \TenupFramework\ModuleInitialization::instance();
		$module_init->init_classes( dirname( __DIR__, 1 ) . '/fixtures/classes' );

		$this->assertTrue( did_action( 'tenup_framework_module_init__tenupframeworktestclasses-posttypes-demo' ) > 0, 'Demo was not initialized.' );
		$this->assertFalse( did_action( 'tenup_framework_module_init__tenupframeworktestclasses-standalone-standalone' ) > 0, 'Standalone class was initialized.' );
	}

	/**
	 * Validate if the classes are fully loadable.
	 *
	 * @return void
	 */
	public function testIsClassFullyLoadable() {
		$module_init = \TenupFramework\ModuleInitialization::instance();

		$this->assertInstanceOf( 'ReflectionClass', $module_init->get_fully_loadable_class( '\TenupFrameworkTestClasses\Loadable\BaseClass' ) );
		$this->assertInstanceOf( 'ReflectionClass', $module_init->get_fully_loadable_class( '\TenupFrameworkTestClasses\Loadable\ChildClass' ) );
		$this->assertFalse( $module_init->get_fully_loadable_class( '\TenupFrameworkTestClasses\Loadable\InvalidChildClass' ) );
	}


	/**
	 * generate_cache() writes a readable cache file and returns the discovered classes.
	 *
	 * @return void
	 */
	public function test_generate_cache_writes_a_readable_cache_file() {
		$dir = $this->make_temp_class_dir();

		$module_init = \TenupFramework\ModuleInitialization::instance();
		$cached      = $module_init->generate_cache( $dir );

		$this->assertFileExists( $this->cache_file_path( $dir ) );
		$this->assertContains( 'TenupTmp\\Widget', $cached );

		$this->remove_temp_dir( $dir );
	}

	/**
	 * The runtime read path uses the cache file when one is present.
	 *
	 * @return void
	 */
	public function test_get_classes_reads_the_cache_file_when_present() {
		$dir = $this->make_temp_class_dir();

		$module_init = \TenupFramework\ModuleInitialization::instance();
		$module_init->generate_cache( $dir );

		// Tamper with the cache so we can prove the read path uses it rather than re-discovering.
		$this->write_file( $this->cache_file_path( $dir ), "<?php return array( 'TenupTmp\\\\Sentinel' );" );

		$read = $module_init->get_classes( $dir );

		$this->assertSame( [ 'TenupTmp\\Sentinel' ], array_values( $read ) );

		$this->remove_temp_dir( $dir );
	}

	/**
	 * With no cache present the runtime discovers live and writes nothing.
	 *
	 * @return void
	 */
	public function test_get_classes_creates_no_cache_when_none_exists() {
		$dir = $this->make_temp_class_dir();

		$module_init = \TenupFramework\ModuleInitialization::instance();
		$classes     = $module_init->get_classes( $dir );

		$this->assertContains( 'TenupTmp\\Widget', $classes );
		$this->assertDirectoryDoesNotExist( $dir . '/' . \TenupFramework\ModuleInitialization::CACHE_DIR_NAME );

		$this->remove_temp_dir( $dir );
	}

	/**
	 * A cache written by an older framework version (a different filename) is ignored,
	 * so an upgraded site never serves a stale cache it cannot rewrite.
	 *
	 * @return void
	 */
	public function test_get_classes_ignores_legacy_cache_file() {
		$dir       = $this->make_temp_class_dir();
		$cache_dir = $dir . '/' . \TenupFramework\ModuleInitialization::CACHE_DIR_NAME;
		mkdir( $cache_dir );

		// The previous version wrote `discoverer-cache-{id}` as a serialized file.
		$this->write_file( $cache_dir . '/discoverer-cache-TenupFramework', serialize( [ 'TenupTmp\\Legacy' ] ) );

		$module_init = \TenupFramework\ModuleInitialization::instance();
		$read        = $module_init->get_classes( $dir );

		$this->assertNotContains( 'TenupTmp\\Legacy', $read );
		$this->assertContains( 'TenupTmp\\Widget', $read );

		$this->remove_temp_dir( $dir );
	}

	/**
	 * A corrupt or truncated cache file does not fatal the request: get_classes() catches the
	 * error and falls back to a live discovery, so a bad cache degrades to uncached rather than
	 * taking the site down.
	 *
	 * @return void
	 */
	public function test_get_classes_falls_back_to_live_when_cache_is_corrupt() {
		$dir       = $this->make_temp_class_dir();
		$cache_dir = $dir . '/' . \TenupFramework\ModuleInitialization::CACHE_DIR_NAME;
		mkdir( $cache_dir );

		// A truncated / syntactically broken cache file — `require` on this throws a ParseError.
		$this->write_file(
			$cache_dir . '/' . \TenupFramework\ModuleInitialization::CACHE_FILENAME,
			"<?php return array( 'TenupTmp\\\\Widget'"
		);

		$module_init = \TenupFramework\ModuleInitialization::instance();
		$read        = $module_init->get_classes( $dir );

		// Fell back to a live scan and still found the real class on disk.
		$this->assertContains( 'TenupTmp\\Widget', $read );

		$this->remove_temp_dir( $dir );
	}

	/**
	 * Defining TENUP_FRAMEWORK_DISABLE_CLASS_CACHE forces live discovery even when a
	 * cache file is present.
	 *
	 * @return void
	 */
	public function test_disable_constant_forces_live_discovery() {
		$dir = $this->make_temp_class_dir();

		$module_init = \TenupFramework\ModuleInitialization::instance();
		$module_init->generate_cache( $dir );

		// Tamper with the cache; with caching disabled this sentinel must not be read.
		$this->write_file( $this->cache_file_path( $dir ), "<?php return array( 'TenupTmp\\\\Sentinel' );" );

		define( 'TENUP_FRAMEWORK_DISABLE_CLASS_CACHE', true );

		$read = $module_init->get_classes( $dir );

		$this->assertNotContains( 'TenupTmp\\Sentinel', $read );
		$this->assertContains( 'TenupTmp\\Widget', $read );

		$this->remove_temp_dir( $dir );
	}

	/**
	 * In the admin, init_classes() hands a loader record to the debug registry.
	 *
	 * @return void
	 */
	public function test_init_classes_records_a_loader_in_admin() {
		when( 'is_admin' )->justReturn( true );
		when( 'add_action' )->justReturn( true );
		when( 'add_filter' )->justReturn( true );
		when( 'apply_filters' )->returnArg( 2 );

		$dir = $this->make_temp_class_dir();

		\TenupFramework\ModuleInitialization::instance()->init_classes( $dir );

		$loaders = \TenupFramework\Debug\LoaderDebug::get_loaders();
		$this->assertCount( 1, $loaders );
		$this->assertSame( $dir, $loaders[0]['directory'] );
		$this->assertContains( 'TenupTmp\\Widget', $loaders[0]['classes'] );

		$this->remove_temp_dir( $dir );
	}

	/**
	 * With no cache present, init_classes() records the time of a genuine live (uncached)
	 * discovery — a real filesystem scan, so the recorded duration is strictly positive.
	 *
	 * @return void
	 */
	public function test_init_classes_records_live_discovery_timing_in_admin() {
		when( 'is_admin' )->justReturn( true );
		when( 'add_action' )->justReturn( true );
		when( 'add_filter' )->justReturn( true );
		when( 'apply_filters' )->returnArg( 2 );

		$dir = $this->make_temp_class_dir();

		\TenupFramework\ModuleInitialization::instance()->init_classes( $dir );

		$loaders = \TenupFramework\Debug\LoaderDebug::get_loaders();
		$this->assertCount( 1, $loaders );
		$this->assertFalse( $loaders[0]['cache_used'], 'No cache exists, so discovery must be live.' );
		$this->assertIsFloat( $loaders[0]['discovery_seconds'] );
		$this->assertIsFloat( $loaders[0]['lookup_seconds'] );
		// A live filesystem scan and the reflection loop both take measurable time; the never-wired
		// default is 0.0, so asserting strictly-positive proves the instrumentation actually ran.
		$this->assertGreaterThan( 0.0, $loaders[0]['discovery_seconds'] );
		$this->assertGreaterThan( 0.0, $loaders[0]['lookup_seconds'] );

		$this->remove_temp_dir( $dir );
	}

	/**
	 * With a pre-built cache present, init_classes() reads it (cache_used) and still records a
	 * positive discovery duration — the cache-read cost rather than a live scan.
	 *
	 * @return void
	 */
	public function test_init_classes_records_cache_read_timing_in_admin() {
		when( 'is_admin' )->justReturn( true );
		when( 'add_action' )->justReturn( true );
		when( 'add_filter' )->justReturn( true );
		when( 'apply_filters' )->returnArg( 2 );

		$dir    = $this->make_temp_class_dir();
		$module = \TenupFramework\ModuleInitialization::instance();
		$module->generate_cache( $dir );

		$module->init_classes( $dir );

		$loaders = \TenupFramework\Debug\LoaderDebug::get_loaders();
		$this->assertCount( 1, $loaders );
		$this->assertTrue( $loaders[0]['cache_used'], 'A cache file exists, so it should be used.' );
		$this->assertGreaterThan( 0.0, $loaders[0]['discovery_seconds'] );

		$this->remove_temp_dir( $dir );
	}

	/**
	 * On the front end, init_classes() records nothing (the data is only viewable in the admin).
	 *
	 * @return void
	 */
	public function test_init_classes_records_nothing_on_the_front_end() {
		when( 'is_admin' )->justReturn( false );

		$dir = $this->make_temp_class_dir();

		\TenupFramework\ModuleInitialization::instance()->init_classes( $dir );

		$this->assertSame( [], \TenupFramework\Debug\LoaderDebug::get_loaders() );

		$this->remove_temp_dir( $dir );
	}

	/**
	 * discover_live() ignores any cache file and returns the real on-disk classes.
	 *
	 * @return void
	 */
	public function test_discover_live_ignores_the_cache() {
		$dir         = $this->make_temp_class_dir();
		$module_init = \TenupFramework\ModuleInitialization::instance();
		$module_init->generate_cache( $dir );

		// Tamper with the cache; discover_live() must not read it.
		$this->write_file( $this->cache_file_path( $dir ), "<?php return array( 'TenupTmp\\\\Sentinel' );" );

		$live = $module_init->discover_live( $dir );

		$this->assertContains( 'TenupTmp\\Widget', $live );
		$this->assertNotContains( 'TenupTmp\\Sentinel', $live );

		$this->remove_temp_dir( $dir );
	}

	/**
	 * A corrupt or truncated cache file falls back to a live scan instead of fataling.
	 *
	 * @return void
	 */
	public function test_get_classes_falls_back_when_the_cache_is_corrupt() {
		$dir         = $this->make_temp_class_dir();
		$module_init = \TenupFramework\ModuleInitialization::instance();
		$module_init->generate_cache( $dir );

		// Truncated PHP: require() raises a ParseError, which the read path must catch.
		$this->write_file( $this->cache_file_path( $dir ), '<?php return array( ' );

		$classes = $module_init->get_classes( $dir );

		$this->assertContains( 'TenupTmp\\Widget', $classes );

		$this->remove_temp_dir( $dir );
	}

	/**
	 * When the read path falls back, the loader record flags the failure so the debug page can
	 * show it rather than reporting the cache as in use.
	 *
	 * @return void
	 */
	public function test_corrupt_cache_records_a_failed_state() {
		when( 'is_admin' )->justReturn( true );
		when( 'add_action' )->justReturn( true );
		when( 'add_filter' )->justReturn( true );
		when( 'apply_filters' )->returnArg( 2 );

		$dir         = $this->make_temp_class_dir();
		$module_init = \TenupFramework\ModuleInitialization::instance();
		$module_init->generate_cache( $dir );
		$this->write_file( $this->cache_file_path( $dir ), '<?php return array( ' );

		$module_init->init_classes( $dir );

		$loaders = \TenupFramework\Debug\LoaderDebug::get_loaders();
		$this->assertNotEmpty( $loaders );
		$this->assertTrue( $loaders[0]['cache_failed'] );
		$this->assertFalse( $loaders[0]['cache_used'] );
		$this->assertContains( 'TenupTmp\\Widget', $loaders[0]['classes'] );

		$this->remove_temp_dir( $dir );
	}

	/**
	 * Build the absolute path to the cache file for a discovery directory.
	 *
	 * @param string $dir The discovery directory.
	 *
	 * @return string
	 */
	private function cache_file_path( string $dir ): string {
		return $dir . '/' . \TenupFramework\ModuleInitialization::CACHE_DIR_NAME
			. '/' . \TenupFramework\ModuleInitialization::CACHE_FILENAME;
	}

	/**
	 * Create a temporary directory containing a single discoverable class.
	 *
	 * @return string The created directory path.
	 */
	private function make_temp_class_dir(): string {
		$dir = sys_get_temp_dir() . '/tenup_framework_test_' . uniqid( '', true );
		mkdir( $dir );
		$this->write_file( $dir . '/Widget.php', "<?php\nnamespace TenupTmp;\nclass Widget {}\n" );

		return $dir;
	}

	/**
	 * Write a file, asserting the write succeeded.
	 *
	 * @param string $path     The file path.
	 * @param string $contents The contents to write.
	 *
	 * @return void
	 */
	private function write_file( string $path, string $contents ): void {
		$this->assertNotFalse( file_put_contents( $path, $contents ) );
	}

	/**
	 * Recursively remove a temporary directory.
	 *
	 * @param string $dir The directory to remove.
	 *
	 * @return void
	 */
	private function remove_temp_dir( string $dir ): void {
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
