<?php
/**
 * A second-directory example class.
 *
 * @package TenupFramework
 */

declare(strict_types = 1);

namespace TenupFrameworkExamples\Widgets;

/**
 * A trivial class living in a second discovery directory, used to prove the build command
 * caches several directories in a single run.
 */
class Card {

	/**
	 * The card title.
	 *
	 * @return string
	 */
	public function title(): string {
		return 'Example card';
	}
}
