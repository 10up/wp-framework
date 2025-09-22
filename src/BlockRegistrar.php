<?php
/**
 * BlockRegistrar
 *
 * @package TenupFramework
 */

declare(strict_types = 1);

namespace TenupFramework;

/**
 * Abstract class for registering blocks from block.json files.
 */
abstract class BlockRegistrar implements ModuleInterface {

	use Module;

	/**
	 * Static array to track all registered block names across all instances.
	 *
	 * @var array<string>
	 */
	public static array $registered_block_names = [];

	/**
	 * Static array to track block registration sources for conflict detection.
	 *
	 * @var array<string, string> Block name => source class
	 */
	public static array $block_sources = [];

	/**
	 * Whether the allowed_block_types_all filter has been registered.
	 *
	 * @var bool
	 */
	public static bool $filter_registered = false;

	/**
	 * Get the blocks directory paths.
	 *
	 * @return array<string> Array of paths to the blocks directories.
	 */
	abstract public function get_blocks_directory(): array;

	/**
	 * Can this module be registered?
	 *
	 * @return bool
	 */
	public function can_register(): bool {
		return true;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'register_blocks' ], 10, 0 );
	}

	/**
	 * Automatically registers all blocks from the blocks directories.
	 *
	 * @return void
	 */
	public function register_blocks(): void {
		// Check if WordPress and block editor are available
		if ( ! function_exists( 'register_block_type_from_metadata' ) ) {
			error_log( 'BlockRegistrar: WordPress block editor not available' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return;
		}

		$blocks_dirs = $this->get_blocks_directory();
		$block_names = [];
		$errors      = [];

		foreach ( $blocks_dirs as $blocks_dir ) {
			// Validate directory path
			$validated_dir = $this->validate_directory_path( $blocks_dir );
			if ( ! $validated_dir ) {
				$errors[] = "Invalid directory path: {$blocks_dir}";
				continue;
			}

			if ( ! file_exists( $validated_dir ) ) {
				continue;
			}

			// Check if directory is readable
			if ( ! is_readable( $validated_dir ) ) {
				$errors[] = "Directory not readable: {$validated_dir}";
				continue;
			}

			$block_json_files = glob( $validated_dir . '*/block.json' );
			if ( empty( $block_json_files ) ) {
				continue;
			}

			foreach ( $block_json_files as $filename ) {
				$block_folder = dirname( $filename );

				// Validate block.json file
				$block_metadata = $this->validate_block_json( $filename );
				if ( ! $block_metadata ) {
					$errors[] = "Invalid block.json: {$filename}";
					continue;
				}

				$block_options = $this->get_block_options( $block_folder );

				$block = register_block_type_from_metadata( $block_folder, $block_options );
				if ( ! $block ) {
					$errors[] = "Failed to register block: {$block_folder}";
					continue;
				}

				// Check for block name conflicts
				$block_name    = $block->name;
				$current_class = get_class( $this );

				if ( isset( self::$block_sources[ $block_name ] ) ) {
					$existing_source = self::$block_sources[ $block_name ];

					// Log the conflict
					error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
						sprintf(
							'BlockRegistrar: Block name conflict detected. Block "%s" already registered by "%s", attempted to register by "%s"',
							$block_name,
							$existing_source,
							$current_class
						)
					);

					// Skip adding to allowed blocks to prevent conflicts
					continue;
				}

				// Track the block source
				self::$block_sources[ $block_name ] = $current_class;
				$block_names[]                      = $block_name;
			}
		}

		// Log any errors that occurred
		if ( ! empty( $errors ) ) {
			error_log( 'BlockRegistrar errors: ' . implode( '; ', $errors ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		if ( ! empty( $block_names ) ) {
			$this->register_allowed_block_types( $block_names );
		}
	}

	/**
	 * Get block registration options for a specific block folder.
	 *
	 * @param string $block_folder The path to the block folder.
	 * @return array Block registration options.
	 */
	protected function get_block_options( string $block_folder ): array {
		$block_options = [];

		$markup_file_path = $block_folder . '/markup.php';
		if ( file_exists( $markup_file_path ) ) {
			// Only add the render callback if the block has a file called markup.php in its directory
			$block_options['render_callback'] = function ( $attributes, $content, $block ) use ( $block_folder ) {
				// Create helpful variables that will be accessible in markup.php file
				$context = $block->context;

				// Get the actual markup from the markup.php file
				ob_start();
				include $block_folder . '/markup.php';
				return ob_get_clean();
			};
		}

		return $block_options;
	}

	/**
	 * Register blocks in allowed_block_types_all filter.
	 *
	 * @param array $block_names Array of block names to allow.
	 * @return void
	 */
	protected function register_allowed_block_types( array $block_names ): void {
		// Add new block names to the static registry, avoiding duplicates
		foreach ( $block_names as $block_name ) {
			if ( ! in_array( $block_name, self::$registered_block_names, true ) ) {
				self::$registered_block_names[] = $block_name;
			}
		}

		// Only register the filter once, regardless of how many instances exist
		if ( ! self::$filter_registered ) {
			add_filter(
				'allowed_block_types_all',
				[ self::class, 'filter_allowed_block_types' ]
			);
			self::$filter_registered = true;
		}
	}

	/**
	 * Static callback for the allowed_block_types_all filter.
	 *
	 * @param array|bool $allowed_blocks Current allowed blocks.
	 * @return array|bool Modified allowed blocks.
	 */
	public static function filter_allowed_block_types( array|bool $allowed_blocks ): array|bool {
		if ( ! is_array( $allowed_blocks ) ) {
			return $allowed_blocks;
		}

		return array_merge( $allowed_blocks, self::$registered_block_names );
	}

	/**
	 * Check if a block name has a conflict.
	 *
	 * @param string $block_name The block name to check.
	 * @return bool True if there's a conflict, false otherwise.
	 */
	public static function has_block_conflict( string $block_name ): bool {
		return isset( self::$block_sources[ $block_name ] );
	}

	/**
	 * Get the source class for a registered block.
	 *
	 * @param string $block_name The block name.
	 * @return string|null The source class name or null if not found.
	 */
	public static function get_block_source( string $block_name ): ?string {
		return self::$block_sources[ $block_name ] ?? null;
	}

	/**
	 * Get all registered block names and their sources.
	 *
	 * @return array<string, string> Block name => source class.
	 */
	public static function get_all_block_sources(): array {
		return self::$block_sources;
	}

	/**
	 * Validate directory path for security and correctness.
	 *
	 * @param string $path The directory path to validate.
	 * @return string|false Validated path or false if invalid.
	 */
	protected function validate_directory_path( string $path ): string|false {
		// Check for empty or null paths
		if ( empty( $path ) ) {
			return false;
		}

		// Check for directory traversal attacks
		if ( str_contains( $path, '..' ) || str_contains( $path, './' ) ) {
			return false;
		}

		// Normalize path separators
		$path = str_replace( '\\', '/', $path );

		// Ensure path ends with directory separator
		if ( ! str_ends_with( $path, '/' ) ) {
			$path .= '/';
		}

		// Check for reasonable path length (prevent excessive memory usage)
		if ( strlen( $path ) > 1000 ) {
			return false;
		}

		return $path;
	}

	/**
	 * Validate block.json file and return metadata.
	 *
	 * @param string $file_path Path to block.json file.
	 * @return array|false Block metadata or false if invalid.
	 */
	protected function validate_block_json( string $file_path ): array|false {
		// Check if file exists and is readable
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return false;
		}

		// Read and decode JSON
		// This approach avoids file_get_contents() which is a PHPCS issue
		ob_start();
		include $file_path;
		$json_content = ob_get_clean();

		if ( empty( $json_content ) ) {
			return false;
		}

		$metadata = json_decode( $json_content, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return false;
		}

		// Validate required fields
		if ( ! isset( $metadata['name'] ) || ! is_string( $metadata['name'] ) ) {
			return false;
		}

		// Validate block name format (namespace/name)
		if ( ! preg_match( '/^[a-z0-9][a-z0-9-]*\/[a-z0-9][a-z0-9-]*$/', $metadata['name'] ) ) {
			return false;
		}

		return $metadata;
	}
}
