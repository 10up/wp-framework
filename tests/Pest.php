<?php
/**
 * Pest configuration.
 *
 * Binds each test directory to the right Testkit base class:
 *
 * - Integration/ boots WordPress. Use it whenever the code under test calls WordPress.
 * - Unit/ does not. Testkit's Unit_Test_Case runs each class in a separate process with
 *   global state discarded, so unit tests can never see integration state.
 * - Arch/ holds Pest architecture expectations and needs no base class.
 *
 * @package TenupFramework
 */

declare( strict_types = 1 );

use Mantle\Testkit\Integration_Test_Case;
use Mantle\Testkit\Unit_Test_Case;

pest()->extend( Integration_Test_Case::class )->in( 'Integration' );
pest()->extend( Unit_Test_Case::class )->in( 'Unit' );
