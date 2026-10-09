<?php
/**
 * TestEmptyDirectoryBlockRegistrar
 *
 * @package TenupFrameworkTests
 */

declare(strict_types = 1);

namespace TenupFrameworkTests;

use TenupFramework\BlockRegistrar;

/**
 * Test implementation of BlockRegistrar with empty directories for testing purposes.
 */
class TestEmptyDirectoryBlockRegistrar extends BlockRegistrar {
	/**
	 * Get the blocks directory.
	 *
	 * @return array
	 */
	public function get_blocks_directory(): array {
		return [];
	}
}
