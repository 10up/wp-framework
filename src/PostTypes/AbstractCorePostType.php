<?php
/**
 * AbstractCorePostType
 *
 * @package TenupFramework
 */

declare(strict_types = 1);

namespace TenupFramework\PostTypes;

/**
 * Abstract class for core post types.
 *
 * This class is intended to be extended by post types that are part of core WordPress functionality.
 * This allows for a more common interface for core post types and custom post types.
 * It's unlikely that this class will need to be used directly.
 */
abstract class AbstractCorePostType extends AbstractPostType {

	/**
	 * Get the singular post type label.
	 *
	 * No-op for core post types since they are already registered by WordPress.
	 */
	public function get_singular_label(): string {
		return '';
	}

	/**
	 * Get the plural post type label.
	 *
	 * No-op for core post types since they are already registered by WordPress.
	 */
	public function get_plural_label(): string {
		return '';
	}

	/**
	 * Get the menu icon for the post type.
	 *
	 * No-op for core post types since they are already registered by WordPress.
	 */
	public function get_menu_icon(): string {
		return '';
	}

	/**
	 * Checks whether the Module should run within the current context.
	 *
	 * True for core post types since they are already registered by WordPress.
	 */
	public function can_register(): bool {
		return true;
	}

	/**
	 * Registers a post type and associates its taxonomies.
	 */
	public function register(): void {
		$this->register_taxonomies();
		$this->after_register();
	}
}
