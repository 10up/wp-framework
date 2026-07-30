<?php
/**
 * Exercises TENUP_FRAMEWORK_DISABLE_CLASS_CACHE in a dedicated process.
 *
 * A constant cannot be undefined, so defining it inside the test run would leak into every
 * later test sharing the process. Running it here guarantees isolation without relying on
 * PHPUnit's process-isolation attributes surviving Pest's class generation.
 *
 * Usage: php disable-class-cache.php <directory>
 * Prints the discovered class list as JSON.
 *
 * @package TenupFramework
 */

declare( strict_types = 1 );

use TenupFramework\ModuleInitialization;

require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

if ( ! isset( $argv[1] ) ) {
	fwrite( STDERR, 'Usage: disable-class-cache.php <directory>' . PHP_EOL );
	exit( 1 );
}

$dir         = $argv[1];
$module_init = ModuleInitialization::instance();

// Build a real cache, then tamper with it: the sentinel is what a cache read would return.
$module_init->generate_cache( $dir );

file_put_contents(
	$dir . '/' . ModuleInitialization::CACHE_DIR_NAME . '/' . ModuleInitialization::CACHE_FILENAME,
	"<?php return array( 'TenupTmp\\\\Sentinel' );"
);

define( 'TENUP_FRAMEWORK_DISABLE_CLASS_CACHE', true );

echo json_encode( array_values( $module_init->get_classes( $dir ) ) );
