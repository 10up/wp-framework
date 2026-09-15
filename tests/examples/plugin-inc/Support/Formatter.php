<?php
/**
 * A plain support class that is discovered but never registered.
 *
 * @package TenupFramework
 */

declare(strict_types = 1);

namespace TenupFrameworkExamples\Support;

/**
 * A helper class that does not implement ModuleInterface. Discovery still finds it (it is a
 * class), but init_classes() skips it because it is not a module.
 */
class Formatter {

	/**
	 * Upper-case a string.
	 *
	 * @param string $value The value to format.
	 *
	 * @return string
	 */
	public function shout( string $value ): string {
		return strtoupper( $value );
	}
}
