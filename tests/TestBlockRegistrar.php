<?php
/**
 * TestBlockRegistrar
 *
 * @package TenupFrameworkTests
 */

declare(strict_types = 1);

namespace TenupFrameworkTests;

use TenupFramework\BlockRegistrar;

/**
 * Test implementation of BlockRegistrar for testing purposes.
 */
class TestBlockRegistrar extends BlockRegistrar {
	/**
	 * Get the blocks directory.
	 *
	 * @return array
	 */
	public function get_blocks_directory(): array {
		return [ '/test/blocks/' ];
	}
}
