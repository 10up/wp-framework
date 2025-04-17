<?php
/**
 * Standalone Class
 *
 * @package TenupFrameworkTestClasses\Standalone
 */

declare(strict_types = 1);

namespace TenupFrameworkTestClasses\Standalone;

/**
 * Standalone Class
 *
 * @package TenupFrameworkTestClasses\Standalone
 */
class Standalone {
	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'init' ] );
	}

	/**
	 * Init method.
	 *
	 * @return void
	 */
	public function init() {
		echo 'Hello from the Standalone class!';
	}
}
