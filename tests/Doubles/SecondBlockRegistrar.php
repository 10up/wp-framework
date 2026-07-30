<?php
/**
 * A second, distinctly-named block registrar.
 *
 * Conflict detection keys off get_class( $this ), so proving a conflict between two registrars
 * needs two different class names rather than two instances of one class.
 *
 * @package TenupFrameworkTests
 */

declare( strict_types = 1 );

namespace TenupFrameworkTests\Doubles;

/**
 * Second block registrar, identical behaviour but a different class name.
 */
class SecondBlockRegistrar extends ConfigurableBlockRegistrar {

}
