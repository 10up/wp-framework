<?php
/**
 * ReadOnlyFileDiscoverCacheDriver
 *
 * @package TenupFramework
 */

declare( strict_types = 1 );

namespace TenupFramework\Cache;

use Spatie\StructureDiscoverer\Cache\FileDiscoverCacheDriver;

/**
 * A read-only variant of Spatie's FileDiscoverCacheDriver.
 *
 * At runtime the framework only ever reads a pre-built class cache; it must never
 * create or write one. A server that cannot write the cache cannot hold a stale one,
 * which is the failure mode this fixes (see GitHub issue #30).
 *
 * Reads are inherited unchanged from the parent (has(), get(), resolvePath()) so the
 * runtime reads exactly what the build-time generator wrote. Every write path is
 * neutralised: put() and forget() are no-ops, and unlike the parent the constructor
 * does not create the cache directory.
 *
 * @package TenupFramework
 */
class ReadOnlyFileDiscoverCacheDriver extends FileDiscoverCacheDriver {

	/**
	 * Set up the driver without creating the cache directory.
	 *
	 * @param string  $directory The directory the cache file lives in.
	 * @param bool    $serialize Whether the cache is serialized (true) or a PHP array file (false).
	 * @param ?string $filename  Optional explicit cache filename.
	 */
	public function __construct( string $directory, bool $serialize = true, ?string $filename = null ) {
		$this->directory = rtrim( $directory, '/' );
		$this->serialize = $serialize;
		$this->filename  = $filename;
	}

	/**
	 * No-op. Cache generation happens at build time only, never at runtime.
	 *
	 * @param string       $id         The cache identifier.
	 * @param array<mixed> $discovered The discovered structures.
	 *
	 * @return void
	 */
	public function put( string $id, array $discovered ): void {
		// Intentionally empty.
	}

	/**
	 * No-op. The runtime never deletes the cache.
	 *
	 * @param string $id The cache identifier.
	 *
	 * @return void
	 */
	public function forget( string $id ): void {
		// Intentionally empty.
	}
}
