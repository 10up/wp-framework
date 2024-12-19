<?php
/**
 * Test the AbstractTaxonomy class.
 *
 * @package Taxonomies
 */

declare(strict_types = 1);

namespace Taxonomies;

use PHPUnit\Framework\TestCase;
use TenupFrameworkTestClasses\Taxonomies\Demo;
use TenupFrameworkTests\FrameworkTestSetup;

/**
 * Class AbstractTaxonomyTest
 *
 * @package Taxonomies
 */
class AbstractTaxonomyTest extends TestCase {

	use FrameworkTestSetup;

	/**
	 * Test the taxonomy gets registered.
	 *
	 * @return void
	 */
	public function test_the_taxonomy_gets_registered() {
		$class = new Demo();
		$class->register();

		$this->assertArrayHasKey( $class->get_name(), self::$registered_taxonomies );
		$this->assertEquals( $class->get_plural_label(), self::$registered_taxonomies['tenup-tax-demo']['labels']['name'] );
		$this->assertEquals( $class->get_singular_label(), self::$registered_taxonomies['tenup-tax-demo']['labels']['singular_name'] );
	}
}
