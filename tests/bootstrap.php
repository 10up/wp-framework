<?php
/**
 * PHPUnit bootstrap file.
 *
 * Boots WordPress for the integration suite via Mantle's installer. Mantle downloads and
 * installs WordPress into a temporary directory on first run, so no WordPress checkout or
 * shell script is needed locally or in CI.
 *
 * @package TenupFramework
 */

declare( strict_types = 1 );

require dirname( __DIR__ ) . '/vendor/autoload.php';

/*
 * Mantle reads its database credentials from the environment (Utils::env() wraps getenv()).
 *
 * Default to a database named for this package rather than Mantle's own
 * `wordpress_unit_tests` default: the installer drops and recreates tables, and a shared
 * local MySQL instance frequently already has a `wordpress_unit_tests` belonging to another
 * project. Only fill in values that are not already set, so CI can override each one.
 */
const TENUP_FRAMEWORK_TEST_DB = 'wp_framework_tests';

/*
 * WP_CORE_DIR must be specific to this package. Mantle's default is a bare
 * `/tmp/wordpress`, and it *reuses a wp-tests-config.php it finds there* rather than
 * regenerating one from the environment. Any other project that has run its tests on this
 * machine will have left a config behind pointing at its own database, which the installer
 * then happily drops and recreates tables in. Keeping our own directory means our config is
 * always the one we generated.
 */
$tenup_framework_env_defaults = [
	'WP_CORE_DIR'    => sys_get_temp_dir() . '/wp-framework-tests-wordpress',
	'WP_DB_NAME'     => TENUP_FRAMEWORK_TEST_DB,
	'WP_DB_USER'     => 'root',
	'WP_DB_PASSWORD' => '',
	'WP_DB_HOST'     => '127.0.0.1',
];

foreach ( $tenup_framework_env_defaults as $tenup_framework_variable => $tenup_framework_default ) {
	if ( false === getenv( $tenup_framework_variable ) ) {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv
		putenv( "{$tenup_framework_variable}={$tenup_framework_default}" );
	}
}

unset( $tenup_framework_env_defaults, $tenup_framework_variable, $tenup_framework_default );

/*
 * Refuse to run against a database we were not pointed at. The installer is destructive, so
 * a config that has drifted (a stale cached file, a stray WP_DB_NAME) must stop the suite
 * rather than quietly rebuild someone else's tables.
 */
$tenup_framework_config = getenv( 'WP_CORE_DIR' ) . '/wp-tests-config.php';

if ( is_readable( $tenup_framework_config ) ) {
	$tenup_framework_contents = (string) file_get_contents( $tenup_framework_config ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$tenup_framework_expected = getenv( 'WP_DB_NAME' );

	if ( ! str_contains( $tenup_framework_contents, "'DB_NAME', '{$tenup_framework_expected}'" ) ) {
		fwrite(
			STDERR,
			PHP_EOL . 'Refusing to run: ' . $tenup_framework_config
			. ' does not target the expected test database (' . $tenup_framework_expected . ').'
			. PHP_EOL . 'Delete that file and re-run so it is regenerated.' . PHP_EOL
		);
		exit( 1 );
	}

	unset( $tenup_framework_contents, $tenup_framework_expected );
}

unset( $tenup_framework_config );

\Mantle\Testing\install();
