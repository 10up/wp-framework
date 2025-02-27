<?php
/**
 * Test all the traits that are unused by the framework.
 *
 * @package TenupFramework
 */

declare( strict_types = 1 );

namespace TenupFrameworkTestClasses;

use ReflectionClass;
use Spatie\StructureDiscoverer\Cache\FileDiscoverCacheDriver;
use Spatie\StructureDiscoverer\Data\DiscoveredStructure;
use Spatie\StructureDiscoverer\Discover;
use TenupFramework\Assets\GetAssetInfo;

/**
 * TraitTests class.
 *
 * @package TenupFramework
 */
class TraitTests {

	use GetAssetInfo;
}
