<?php
/**
 * A minimal example module.
 *
 * @package TenupFramework
 */

declare(strict_types = 1);

namespace TenupFrameworkExamples\Modules;

use TenupFramework\Module;
use TenupFramework\ModuleInterface;

/**
 * A tiny module that a plugin `inc/` directory might contain: it implements
 * ModuleInterface, so init_classes() would instantiate and register it.
 */
class GreetingModule implements ModuleInterface {

	use Module;

	/**
	 * Always registers in this example.
	 *
	 * @return bool
	 */
	public function can_register() {
		return true;
	}

	/**
	 * Connect to WordPress. A real module would add hooks here.
	 *
	 * @return void
	 */
	public function register() {
		// Intentionally empty for the example.
	}
}
