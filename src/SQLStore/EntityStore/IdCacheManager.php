<?php

namespace SMW\SQLStore\EntityStore;

use RuntimeException;
use SMW\DIWikiPage;

/**
 * @license GPL-2.0-or-later
 * @since 3.0
 *
 * @author mwjames
 */
class IdCacheManager {

	// InMemoryCacheManager

	/**
	 * Caching redirects, especially from prefetch
	 */
	const REDIRECT_SOURCE = 'redirect.source.lookup';
	const REDIRECT_TARGET = 'redirect.target.lookup';

	/**
	 * @var
	 */
	private $caches;

	/**
	 * @since 3.0
	 *
	 * @param array $caches
	 */
	public function __construct( array $caches ) {
		$this->caches = $caches;

		if ( !isset( $this->caches['entity.id'] ) ) {
			throw new RuntimeException( "Missing 'entity.id' instance." );
		}

		if ( !isset( $this->caches['entity.sort'] ) ) {
			throw new RuntimeException( "Missing 'entity.sort' instance." );
		}

		if ( !isset( $this->caches['entity.lookup'] ) ) {
			throw new RuntimeException( "Missing 'entity.lookup' instance." );
		}

		if ( !isset( $this->caches['propertytable.hash'] ) ) {
			throw new RuntimeException( "Missing 'propertytable.hash' instance." );
		}
	}

	/**
	 * @since 3.0
	 *
	 * @param string|array $args
	 *
	 * @return string
	 */
	public static function computeSha1( $args = '' ) {
		return sha1( json_encode( $args ) );
	}

	/**
	 * @since 3.0
	 *
	 * @param string $key
	 *
	 * @return bool
	 */
	public function get( $key ) {
		if ( !isset( $this->caches[$key] ) ) {
			throw new RuntimeException( "$key is unknown" );
		}

		return $this->caches[$key];
	}

	/**
	 * @since 3.0
	 *
	 * @param string $hash
	 *
	 * @return bool
	 */
	public function hasCache( $hash ) {
		if ( !is_string( $hash ) ) {
			return false;
		}

		return $this->caches['entity.id']->contains( $hash );
	}

	/**
	 * @since 3.0
	 *
	 * @param string $title
	 * @param int $namespace
	 * @param string $interwiki
	 * @param string $subobject
	 * @param int $id
	 * @param string $sortkey
	 */
	public function setCache( $title, $namespace, $interwiki, $subobject, $id, $sortkey ) {
		if ( is_array( $title ) ) {
			throw new RuntimeException( "Expected a string instead an array was detected!" );
		}

		if ( strpos( $title, ' ' ) !== false ) {
			throw new RuntimeException( "Somebody tried to use spaces in a cache title! ($title)" );
		}

		$hash = $this->computeSha1(
			[ $title, (int)$namespace, $interwiki, $subobject ]
		);

		$this->caches['entity.id']->save( $hash, $id );
		$this->caches['entity.sort']->save( $hash, $sortkey );

		$dataItem = new DIWikiPage( $title, $namespace, $interwiki, $subobject );
		$dataItem->setId( $id );
		$dataItem->setSortKey( $sortkey );

		$this->caches['entity.lookup']->save( $id, $dataItem );

		// Speed up detection of redirects when fetching IDs
		if ( $interwiki == SMW_SQL3_SMWREDIIW ) {
			$this->setCache( $title, $namespace, '', $subobject, 0, '' );
		}
	}

	/**
	 * @since 3.0
	 *
	 * @param string $title
	 * @param int $namespace
	 * @param string $interwiki
	 * @param string $subobject
	 */
	public function deleteCache( $title, $namespace, $interwiki, $subobject ) {
		$hash = $this->computeSha1(
			[ $title, (int)$namespace, $interwiki, $subobject ]
		);

		$this->caches['entity.id']->delete( $hash );
		$this->caches['entity.sort']->delete( $hash );

		if ( ( $id = $this->caches['entity.id']->fetch( $hash ) ) !== false ) {
			$this->caches['entity.lookup']->delete( $id );
		}
	}

	/**
	 * @since 3.0
	 *
	 * @param string $id
	 */
	public function deleteCacheById( $id ) {
		$dataItem = $this->caches['entity.lookup']->fetch( $id );

		if ( !$dataItem instanceof DIWikiPage ) {
			return;
		}

		$hash = $this->computeSha1(
			[
				$dataItem->getDBKey(),
				(int)$dataItem->getNamespace(),
				$dataItem->getInterwiki(),
				$dataItem->getSubobjectName()
			]
		);

		$this->caches['entity.id']->delete( $hash );
		$this->caches['entity.sort']->delete( $hash );
		$this->caches['entity.lookup']->delete( $id );
	}

	/**
	 * Get a cached SMW ID, or false if no cache entry is found.
	 *
	 * @since 3.0
	 *
	 * @param DIWikiPage|array $args
	 *
	 * @return int|bool
	 */
	public function getId( $args ) {
		if ( $args instanceof DIWikiPage ) {
			$args = [
				$args->getDBKey(),
				(int)$args->getNamespace(),
				$args->getInterwiki(),
				$args->getSubobjectName()
			];
		}

		if ( is_array( $args ) ) {
			$hash = $this->computeSha1( $args );
		} else {
			$hash = $args;
		}

		if ( ( $id = $this->caches['entity.id']->fetch( $hash ) ) !== false ) {
			return (int)$id;
		}

		return false;
	}

	/**
	 * Get a cached SMW sortkey, or false if no cache entry is found.
	 *
	 * @since 3.0
	 *
	 * @param $args
	 *
	 * @return string|bool
	 */
	public function getSort( $args ) {
		if ( is_array( $args ) ) {
			$hash = $this->computeSha1( $args );
		} else {
			$hash = $args;
		}

		if ( ( $sort = $this->caches['entity.sort']->fetch( $hash ) ) !== false ) {
			return $sort;
		}

		return false;
	}

}
