<?php
/**
 * TestMultiDirectoryBlockRegistrar
 *
 * @package TenupFrameworkTests
 */

declare(strict_types = 1);

namespace TenupFrameworkTests;

use TenupFramework\BlockRegistrar;

/**
 * Test implementation of BlockRegistrar with multiple directories for testing purposes.
 */
class TestMultiDirectoryBlockRegistrar extends BlockRegistrar {
	/**
	 * Get the blocks directory.
	 *
	 * @return array
	 */
	public function get_blocks_directory(): array {
		return [
			'/test/blocks/',
			'/test/custom-blocks/',
			'/test/vendor-blocks/',
		];
	}
}
