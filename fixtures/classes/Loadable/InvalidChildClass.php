<?php
/**
 * InvalidChildClass
 *
 * @package TenUpPlugin\Loadable
 */

declare(strict_types = 1);

namespace TenupFrameworkTestClasses\Loadable;

/**
 * Represents a class that attempts to extend a non-existent or invalid superclass.
 *
 * This class is invalid due to the parent class not being defined or available.
 * Ensure that the parent class exists and is properly imported or declared
 * in order to resolve this issue.
 */
class InvalidChildClass extends NonExistentClass {

}
