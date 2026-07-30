<?php
/**
 * A BlockRegistrar whose block directories are supplied at construction.
 *
 * Replaces the previous fixed-path doubles, which pointed at directories that never existed and
 * so could only ever prove that register_blocks() did not crash.
 *
 * @package TenupFrameworkTests
 */

declare( strict_types = 1 );

namespace TenupFrameworkTests\Doubles;

use TenupFramework\BlockRegistrar;

/**
 * Configurable block registrar.
 */
class ConfigurableBlockRegistrar extends BlockRegistrar {

	/**
	 * The block directories to scan.
	 *
	 * @var array<string>
	 */
	public array $directories;

	/**
	 * Constructor.
	 *
	 * @param array<string> $directories The block directories to scan.
	 */
	public function __construct( array $directories = [] ) {
		$this->directories = $directories;
	}

	/**
	 * Get the blocks directory paths.
	 *
	 * @return array<string>
	 */
	public function get_blocks_directory(): array {
		return $this->directories;
	}
}
