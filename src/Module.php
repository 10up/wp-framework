<?php
/**
 * Module
 *
 * @package TenupFramework
 */

declare(strict_types = 1);

namespace TenupFramework;

/**
 * Module is any feature that conditionally activates based on the current context.
 */
trait Module {

	/**
	 * Used to alter the order in which classes are initialized.
	 *
	 * Lower number will be initialized first.
	 *
	 * @note This has no correlation to the `init` priority. It's just a way to allow certain classes to be initialized before others.
	 */
	public function load_order(): int {
		return 10;
	}

	/**
	 * Checks whether the Module should run within the current context.
	 */
	abstract public function can_register(): bool;

	/**
	 * Connects the Module with WordPress using Hooks and/or Filters.
	 */
	abstract public function register(): void;
}
