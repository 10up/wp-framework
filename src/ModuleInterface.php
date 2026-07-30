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
	 */
	public function load_order(): int;

	/**
	 * Checks whether the Module should run within the current context.
	 */
	public function can_register(): bool;

	/**
	 * Connects the Module with WordPress using Hooks and/or Filters.
	 */
	public function register(): void;
}
