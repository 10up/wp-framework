<?php
/**
 * Tests BlockRegistrar against real block registration.
 *
 * The previous suite pointed at directories that did not exist, so most of it could only prove
 * register_blocks() returned without throwing. These build real block.json files on disk and
 * assert against WordPress's own block registry.
 *
 * @package TenupFramework
 */

declare( strict_types = 1 );

use TenupFramework\BlockRegistrar;
use TenupFramework\ModuleInterface;
use TenupFrameworkTests\Doubles\ConfigurableBlockRegistrar;
use TenupFrameworkTests\Doubles\SecondBlockRegistrar;

beforeEach(
	function (): void {
		tenup_reset_block_registrar();
	}
);

afterEach(
	function (): void {
		// Blocks live in a process-global registry, so they outlive the database rollback.
		foreach ( array_keys( WP_Block_Type_Registry::get_instance()->get_all_registered() ) as $name ) {
			if ( str_starts_with( (string) $name, 'tenup-test/' ) ) {
				unregister_block_type( (string) $name );
			}
		}

		tenup_cleanup_temp_dirs();
		tenup_reset_block_registrar();
	}
);

it(
	'is a module the loader will pick up',
	function (): void {
		$registrar = new ConfigurableBlockRegistrar();

		expect( $registrar )->toBeInstanceOf( ModuleInterface::class );
		expect( $registrar->can_register() )->toBeTrue();
	}
);

it(
	'defers block registration to the init action',
	function (): void {
		$registrar = new ConfigurableBlockRegistrar();

		$registrar->register();

		expect( has_action( 'init', [ $registrar, 'register_blocks' ] ) )->toBe( 10 );
	}
);

it(
	'registers a block from a directory containing block.json',
	function (): void {
		$dir = tenup_make_blocks_dir( [ 'tenup-test/alpha' => [] ] );

		( new ConfigurableBlockRegistrar( [ $dir ] ) )->register_blocks();

		$block = WP_Block_Type_Registry::get_instance()->get_registered( 'tenup-test/alpha' );

		expect( $block )->not->toBeNull();
		expect( $block->title )->toBe( 'Alpha' );
	}
);

it(
	'registers blocks found across several directories',
	function (): void {
		$first  = tenup_make_blocks_dir( [ 'tenup-test/alpha' => [] ] );
		$second = tenup_make_blocks_dir( [ 'tenup-test/beta' => [] ] );

		( new ConfigurableBlockRegistrar( [ $first, $second ] ) )->register_blocks();

		expect( WP_Block_Type_Registry::get_instance()->is_registered( 'tenup-test/alpha' ) )->toBeTrue();
		expect( WP_Block_Type_Registry::get_instance()->is_registered( 'tenup-test/beta' ) )->toBeTrue();
	}
);

it(
	'registers nothing when given no directories',
	function (): void {
		( new ConfigurableBlockRegistrar( [] ) )->register_blocks();

		expect( BlockRegistrar::$registered_block_names )->toBe( [] );
	}
);

it(
	'skips directories that do not exist',
	function (): void {
		$missing = sys_get_temp_dir() . '/tenup_blocks_missing_' . uniqid( '', true ) . '/';
		$real    = tenup_make_blocks_dir( [ 'tenup-test/alpha' => [] ] );

		( new ConfigurableBlockRegistrar( [ $missing, $real ] ) )->register_blocks();

		// The missing directory is passed first, to prove it does not abort the run.
		expect( WP_Block_Type_Registry::get_instance()->is_registered( 'tenup-test/alpha' ) )->toBeTrue();
	}
);

it(
	'skips a block whose block.json is malformed',
	function (): void {
		$dir = tenup_make_blocks_dir(
			[
				'tenup-test/broken' => [ 'json' => '{ not valid json' ],
				'tenup-test/alpha'  => [],
			]
		);

		( new ConfigurableBlockRegistrar( [ $dir ] ) )->register_blocks();

		expect( WP_Block_Type_Registry::get_instance()->is_registered( 'tenup-test/broken' ) )->toBeFalse();
		// The valid sibling still registers.
		expect( WP_Block_Type_Registry::get_instance()->is_registered( 'tenup-test/alpha' ) )->toBeTrue();
	}
);

it(
	'skips a block.json with no name',
	function (): void {
		$dir = tenup_make_blocks_dir(
			[ 'tenup-test/nameless' => [ 'json' => '{"apiVersion":3,"title":"Nameless"}' ] ]
		);

		( new ConfigurableBlockRegistrar( [ $dir ] ) )->register_blocks();

		expect( BlockRegistrar::$registered_block_names )->toBe( [] );
	}
);

describe(
	'render callbacks',
	function (): void {
		it(
			'adds no render callback when the block has no markup.php',
			function (): void {
				$dir       = tenup_make_blocks_dir( [ 'tenup-test/alpha' => [] ] );
				$registrar = new ConfigurableBlockRegistrar( [ $dir ] );

				$options = ( new ReflectionMethod( $registrar, 'get_block_options' ) )
				->invoke( $registrar, $dir . 'alpha' );

				expect( $options )->toBe( [] );
			}
		);

		it(
			'adds a render callback when the block has markup.php',
			function (): void {
				$dir = tenup_make_blocks_dir(
					[ 'tenup-test/alpha' => [ 'markup' => '<?php echo "hello from markup"; ?>' ] ]
				);

				$registrar = new ConfigurableBlockRegistrar( [ $dir ] );

				$options = ( new ReflectionMethod( $registrar, 'get_block_options' ) )
				->invoke( $registrar, $dir . 'alpha' );

				expect( $options )->toHaveKey( 'render_callback' );
				expect( $options['render_callback'] )->toBeCallable();
			}
		);

		it(
			'renders the block through markup.php',
			function (): void {
				$dir = tenup_make_blocks_dir(
					[ 'tenup-test/alpha' => [ 'markup' => '<?php echo "hello from markup"; ?>' ] ]
				);

				( new ConfigurableBlockRegistrar( [ $dir ] ) )->register_blocks();

				expect(
					render_block(
						[
							'blockName'    => 'tenup-test/alpha',
							'attrs'        => [],
							'innerBlocks'  => [],
							'innerHTML'    => '',
							'innerContent' => [],
						]
					)
				)->toContain( 'hello from markup' );
			}
		);
	}
);

describe(
	'source tracking and conflicts',
	function (): void {
		it(
			'records which class registered each block',
			function (): void {
				$dir = tenup_make_blocks_dir( [ 'tenup-test/alpha' => [] ] );

				( new ConfigurableBlockRegistrar( [ $dir ] ) )->register_blocks();

				expect( BlockRegistrar::get_block_source( 'tenup-test/alpha' ) )
				->toBe( ConfigurableBlockRegistrar::class );
				expect( BlockRegistrar::get_all_block_sources() )->toHaveKey( 'tenup-test/alpha' );
			}
		);

		it(
			'returns null for a block it never registered',
			function (): void {
				expect( BlockRegistrar::get_block_source( 'tenup-test/nope' ) )->toBeNull();
			}
		);

		it(
			'detects a conflict once a block name is taken',
			function (): void {
				$dir = tenup_make_blocks_dir( [ 'tenup-test/alpha' => [] ] );

				expect( BlockRegistrar::has_block_conflict( 'tenup-test/alpha' ) )->toBeFalse();

				( new ConfigurableBlockRegistrar( [ $dir ] ) )->register_blocks();

				expect( BlockRegistrar::has_block_conflict( 'tenup-test/alpha' ) )->toBeTrue();
			}
		);

		it(
			'keeps the original source when a second registrar claims the same name',
			function (): void {
				/*
				* WordPress rejects the duplicate itself, via _doing_it_wrong from
				* WP_Block_Type_Registry::register. Mantle escalates those to failures unless
				* declared, so this states that the notice is the expected outcome.
				*
				* Worth noting: register_blocks() calls register_block_type_from_metadata() *before*
				* checking self::$block_sources, and bails on a falsy return. So for two registrars
				* competing in one request, WordPress's own rejection short-circuits the conflict
				* branch and its log line never runs. The end state is still correct - first
				* registrar wins, the name is recorded once - but the conflict logging is
				* effectively unreachable here.
				*/
				$this->setExpectedIncorrectUsage( 'WP_Block_Type_Registry::register' );

				$first  = tenup_make_blocks_dir( [ 'tenup-test/alpha' => [] ] );
				$second = tenup_make_blocks_dir( [ 'tenup-test/alpha' => [] ] );

				( new ConfigurableBlockRegistrar( [ $first ] ) )->register_blocks();
				( new SecondBlockRegistrar( [ $second ] ) )->register_blocks();

				expect( BlockRegistrar::get_block_source( 'tenup-test/alpha' ) )
				->toBe( ConfigurableBlockRegistrar::class );

				// Recorded once, so the conflicting registrar cannot double-add it to allowed blocks.
				expect( array_count_values( BlockRegistrar::$registered_block_names )['tenup-test/alpha'] )->toBe( 1 );
			}
		);
	}
);

describe(
	'allowed block types',
	function (): void {
		it(
			'adds registered blocks to the allowed list',
			function (): void {
				$dir = tenup_make_blocks_dir( [ 'tenup-test/alpha' => [] ] );

				( new ConfigurableBlockRegistrar( [ $dir ] ) )->register_blocks();

				expect( BlockRegistrar::filter_allowed_block_types( [ 'core/paragraph' ] ) )
				->toContain( 'core/paragraph' )
				->toContain( 'tenup-test/alpha' );
			}
		);

		it(
			'passes a boolean through untouched',
			function (): void {
				// `true` means "all blocks allowed"; narrowing it to a list would be a regression.
				expect( BlockRegistrar::filter_allowed_block_types( true ) )->toBeTrue();
				expect( BlockRegistrar::filter_allowed_block_types( false ) )->toBeFalse();
			}
		);

		it(
			'registers the allowed_block_types_all filter only once',
			function (): void {
				$first  = tenup_make_blocks_dir( [ 'tenup-test/alpha' => [] ] );
				$second = tenup_make_blocks_dir( [ 'tenup-test/beta' => [] ] );

				( new ConfigurableBlockRegistrar( [ $first ] ) )->register_blocks();
				( new SecondBlockRegistrar( [ $second ] ) )->register_blocks();

				$callbacks = $GLOBALS['wp_filter']['allowed_block_types_all'][10] ?? [];

				expect( $callbacks )->toHaveCount( 1 );
			}
		);
	}
);
