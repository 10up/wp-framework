<?php
/**
 * Shared test helpers.
 *
 * Loaded via composer autoload-dev so both suites can use them.
 *
 * @package TenupFramework
 */

declare( strict_types = 1 );

/**
 * Recursively copy a directory.
 *
 * Used to work on a throwaway copy of the committed example directories, so a test that
 * writes a class cache never dirties the repository.
 *
 * @param string $source The source directory.
 * @param string $target The target directory.
 *
 * @return void
 */
function tenup_copy_dir( string $source, string $target ): void {
	mkdir( $target, 0777, true );

	$items = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $source, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::SELF_FIRST
	);

	foreach ( $items as $item ) {
		$destination = $target . '/' . $items->getSubPathname();

		if ( $item->isDir() ) {
			mkdir( $destination, 0777, true );
			continue;
		}

		copy( $item->getPathname(), $destination );
	}
}

/**
 * Recursively remove a directory.
 *
 * @param string $dir The directory to remove.
 *
 * @return void
 */
function tenup_remove_dir( string $dir ): void {
	if ( ! is_dir( $dir ) ) {
		return;
	}

	$items = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ( $items as $item ) {
		if ( $item->isDir() ) {
			rmdir( $item->getPathname() );
			continue;
		}

		unlink( $item->getPathname() );
	}

	rmdir( $dir );
}

/**
 * Copy one of the committed example directories to a fresh temporary directory.
 *
 * @param string $name The example directory name under tests/examples.
 *
 * @return string The path to the temporary copy.
 */
function tenup_example_copy( string $name ): string {
	$target = sys_get_temp_dir() . '/tenup_example_' . $name . '_' . uniqid( '', true );

	tenup_copy_dir( dirname( __DIR__ ) . '/tests/examples/' . $name, $target );

	return $target;
}

/**
 * The absolute path to the class cache file for a discovery directory.
 *
 * @param string $dir The discovery directory.
 *
 * @return string
 */
function tenup_cache_file_path( string $dir ): string {
	return $dir . '/' . TenupFramework\ModuleInitialization::CACHE_DIR_NAME
		. '/' . TenupFramework\ModuleInitialization::CACHE_FILENAME;
}

/**
 * Create a temporary directory containing a single discoverable class.
 *
 * @return string The created directory path.
 */
function tenup_temp_class_dir(): string {
	$dir = sys_get_temp_dir() . '/tenup_framework_test_' . uniqid( '', true );

	mkdir( $dir );
	file_put_contents( $dir . '/Widget.php', "<?php\nnamespace TenupTmp;\nclass Widget {}\n" );

	return $dir;
}

/**
 * Reset the framework's process-level state between tests.
 *
 * ModuleInitialization is a singleton and LoaderDebug keeps its records in static properties,
 * neither of which the database rollback between integration tests touches. Without this, a
 * registered module or a recorded loader leaks into the next test. Reflection is used because
 * this is deliberately not part of the public API.
 *
 * @return void
 */
function tenup_reset_framework_state(): void {
	$instance = new ReflectionProperty( TenupFramework\ModuleInitialization::class, 'instance' );
	$instance->setValue( null, null );

	foreach ( [
		'loaders' => [],
		'booted'  => false,
	] as $name => $value ) {
		$property = new ReflectionProperty( TenupFramework\Debug\LoaderDebug::class, $name );
		$property->setValue( null, $value );
	}

	// LoaderDebug registers its admin page once per request via this global.
	unset( $GLOBALS['tenup_framework_debug_page_registered'] );
}

/**
 * Reset BlockRegistrar's static registries between tests.
 *
 * They are process globals shared by every registrar instance, so without this a block
 * registered in one test looks like a conflict in the next.
 *
 * @return void
 */
function tenup_reset_block_registrar(): void {
	TenupFramework\BlockRegistrar::$registered_block_names = [];
	TenupFramework\BlockRegistrar::$block_sources          = [];
	TenupFramework\BlockRegistrar::$filter_registered      = false;
}

/**
 * The registry of temporary directories awaiting cleanup.
 *
 * A holder object rather than $this, because Pest's `test()` proxy cannot have array elements
 * appended to a property.
 *
 * @return object{dirs: array<int, string>}
 */
function tenup_temp_dir_registry(): object {
	static $registry = null;

	if ( null === $registry ) {
		$registry = new class() {
			/**
			 * Tracked directories.
			 *
			 * @var array<int, string>
			 */
			public array $dirs = [];
		};
	}

	return $registry;
}

/**
 * Remove every tracked temporary directory and empty the registry.
 *
 * @return void
 */
function tenup_cleanup_temp_dirs(): void {
	$registry = tenup_temp_dir_registry();

	foreach ( $registry->dirs as $dir ) {
		tenup_remove_dir( $dir );
	}

	$registry->dirs = [];
}

/**
 * Build a temporary blocks directory containing real block.json files.
 *
 * The directory is tracked for removal by tenup_cleanup_temp_dirs().
 *
 * @param array<string, array{json?: string, markup?: string}> $blocks Keyed by block name
 *        (namespace/name). `json` overrides the generated block.json; `markup` writes a
 *        markup.php alongside it.
 *
 * @return string The directory path, with a trailing slash (register_blocks() globs "*\/block.json").
 */
function tenup_make_blocks_dir( array $blocks ): string {
	$root = sys_get_temp_dir() . '/tenup_blocks_' . uniqid( '', true ) . '/';
	mkdir( $root, 0777, true );

	tenup_temp_dir_registry()->dirs[] = $root;

	foreach ( $blocks as $name => $options ) {
		$folder = $root . basename( $name );
		mkdir( $folder );

		$json = $options['json'] ?? (string) json_encode(
			[
				'apiVersion' => 3,
				'name'       => $name,
				'title'      => ucfirst( basename( $name ) ),
				'category'   => 'widgets',
			]
		);

		file_put_contents( $folder . '/block.json', $json );

		if ( isset( $options['markup'] ) ) {
			file_put_contents( $folder . '/markup.php', $options['markup'] );
		}
	}

	return $root;
}

/**
 * Invoke a protected static method on LoaderDebug.
 *
 * Its rendering helpers are deliberately not public API, but they carry the formatting logic
 * worth asserting directly rather than only through page output.
 *
 * @param string       $method The method name.
 * @param array<mixed> $args   Arguments to pass.
 *
 * @return mixed
 */
function tenup_invoke_loader_debug( string $method, array $args = [] ): mixed {
	return ( new ReflectionMethod( TenupFramework\Debug\LoaderDebug::class, $method ) )
		->invoke( null, ...$args );
}

/**
 * A representative loader record, as ModuleInitialization would hand to LoaderDebug::record().
 *
 * @param string $directory The loader directory.
 *
 * @return array<string, mixed>
 */
function tenup_sample_loader_record( string $directory = '/srv/site/wp-content/plugins/demo/inc' ): array {
	return [
		'directory'         => $directory,
		'cache_file'        => $directory . '/class-loader-cache/class-loader-cache-v2.php',
		'cache_exists'      => false,
		'cache_used'        => false,
		'cache_disabled'    => false,
		'cache_failed'      => false,
		'classes'           => [ 'TenupTmp\\Widget' ],
		'version'           => '1.3.0',
		'reference'         => 'abcdef1234567890',
		'discovery_seconds' => 0.0123,
		'lookup_seconds'    => 0.0456,
	];
}

/**
 * Create an administrator and switch to them.
 *
 * @throws RuntimeException If the user could not be created.
 *
 * @return int The new user ID.
 */
function tenup_acting_as_admin(): int {
	$user_id = wp_insert_user(
		[
			'user_login' => 'tenup_admin_' . uniqid( '', false ),
			'user_pass'  => wp_generate_password(),
			'role'       => 'administrator',
		]
	);

	if ( is_wp_error( $user_id ) ) {
		throw new RuntimeException( 'Could not create an administrator: ' . esc_html( $user_id->get_error_message() ) );
	}

	wp_set_current_user( $user_id );

	return $user_id;
}

/**
 * Run a command, returning its stdout, stderr and exit code.
 *
 * @param array<int, string> $command The command and its arguments, unescaped.
 *
 * @throws RuntimeException If the process could not be started.
 *
 * @return array{stdout: string, stderr: string, exit: int}
 */
function tenup_run_process( array $command ): array {
	$descriptors = [
		1 => [ 'pipe', 'w' ],
		2 => [ 'pipe', 'w' ],
	];

	$process = proc_open(
		implode( ' ', array_map( 'escapeshellarg', $command ) ),
		$descriptors,
		$pipes
	);

	if ( ! is_resource( $process ) ) {
		throw new RuntimeException( 'Could not start process: ' . esc_html( implode( ' ', $command ) ) );
	}

	$stdout = (string) stream_get_contents( $pipes[1] );
	$stderr = (string) stream_get_contents( $pipes[2] );

	fclose( $pipes[1] );
	fclose( $pipes[2] );

	return [
		'stdout' => $stdout,
		'stderr' => $stderr,
		'exit'   => proc_close( $process ),
	];
}

/**
 * Run the class-cache bin script, returning its stdout, stderr and exit code.
 *
 * Shelling out (rather than calling ModuleInitialization directly) is deliberate: it covers the
 * script's own autoloader resolution, argument handling and exit codes, the way CI invokes it.
 *
 * @param array<int, string> $args The arguments to pass after the script name.
 *
 * @return array{stdout: string, stderr: string, exit: int}
 */
function tenup_run_cache_bin( array $args ): array {
	$script = dirname( __DIR__ ) . '/bin/tenup-framework-generate-class-cache';

	return tenup_run_process( array_merge( [ PHP_BINARY, $script ], $args ) );
}

/**
 * Run one of the tests/scripts/ helper scripts in a fresh PHP process.
 *
 * @param string             $script The filename under tests/scripts/.
 * @param array<int, string> $args   Arguments to pass to the script.
 *
 * @return array{stdout: string, stderr: string, exit: int}
 */
function tenup_run_script( string $script, array $args = [] ): array {
	return tenup_run_process(
		array_merge( [ PHP_BINARY, __DIR__ . '/scripts/' . $script ], $args )
	);
}
