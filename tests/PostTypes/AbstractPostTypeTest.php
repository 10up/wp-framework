<?php
/**
 * Test Class
 *
 * @package TenupFramework
 */

declare(strict_types = 1);

namespace PostTypes;

use PHPUnit\Framework\TestCase;
use TenupFrameworkTests\FrameworkTestSetup;
use TenupFrameworkTestClasses\PostTypes\Demo;

/**
 * Test Class
 *
 * @package TenupFramework
 */
class AbstractPostTypeTest extends TestCase {

	use FrameworkTestSetup;

	/**
	 * Test the post_type gets registered.
	 *
	 * @return void
	 */
	public function test_the_post_type_gets_registered() {
		$class = new Demo();
		$class->register();

		$this->assertArrayHasKey( $class->get_name(), self::$registered_post_types );
		$this->assertEquals( $class->get_plural_label(), self::$registered_post_types['tenup-demo']['labels']['name'] );
		$this->assertEquals( $class->get_singular_label(), self::$registered_post_types['tenup-demo']['labels']['singular_name'] );
	}
}
