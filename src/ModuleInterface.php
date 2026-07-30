<?php
/**
 * ModuleInterface
 *
 * @package TenupFramework
 */

declare(strict_types = 1);

namespace TenupFramework;

/**
 * Interface for the Module trait.
 */
interface ModuleInterface {

	/**
	 * Used to alter the order in which classes are initialized.
	 *
	 * Lower number will be initialized first.
	 *
	 * @note This has no correlation to the `init` priority. It's just a way to allow certain classes to be initialized before others.
	 *
	 * @return int The priority of the module.
	 */
	public function load_order();

	/**
	 * Checks whether the Module should run within the current context.
	 *
	 * @return bool
	 */
	public function can_register();

	/**
	 * Connects the Module with WordPress using Hooks and/or Filters.
	 *
	 * @return void
	 */
	public function register();
}
