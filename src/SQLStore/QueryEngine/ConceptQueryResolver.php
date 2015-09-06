<?php

namespace SMW\SQLStore\QueryEngine;

use SMW\SQLStore\QueryEngine\QuerySegment;
use SMWQueryParser as QueryParser;
use SMWQuery as Query;

/**
 * @license GNU GPL v2+
 * @since 2.2
 *
 * @author mwjames
 */
class ConceptQueryResolver {

	/**
	 * @var QueryEngine
	 */
	private $queryEngine;

	/**
	 * @var integer
	 */
	private $conceptFeatures;

	/**
	 * @since 2.2
	 *
	 * @param QueryEngine $queryEngine
	 */
	public function __construct( QueryEngine $queryEngine ) {
		$this->queryEngine = $queryEngine;
	}

	/**
	 * @since 2.2
	 *
	 * @param integer $conceptFeatures
	 */
	public function setConceptFeatures( $conceptFeatures ) {
		$this->conceptFeatures = $conceptFeatures;
	}

	/**
	 * @param string $conceptDescriptionText
	 *
	 * @return QuerySegment|null
	 */
	public function prepareQuerySegmentFor( $conceptDescriptionText ) {

		$querySegements = array();
		QuerySegment::$qnum = 0;

		$querySegmentListBuilder = $this->queryEngine->getQuerySegmentListBuilder();
		$querySegmentListBuilder->setSortKeys( array() );

		$qp = new QueryParser( $this->conceptFeatures );

		$querySegmentListBuilder->buildQuerySegmentFor(
			$qp->getQueryDescription( $conceptDescriptionText )
		);

		$qid = $querySegmentListBuilder->getLastQuerySegmentId();
		$querySegements = $querySegmentListBuilder->getQuerySegmentList();

		if ( $qid < 0 ) {
			return null;
		}

		// execute query tree, resolve all dependencies
		$querySegmentListItemResolver = $this->queryEngine->getQuerySegmentListItemResolver();

		$querySegmentListItemResolver->setQueryMode( Query::MODE_INSTANCES );
		$querySegmentListItemResolver->setQuerySegmentList( $querySegements );
		$querySegmentListItemResolver->resolveForSegmentItem( $qid );

		return $querySegements[$qid];
	}

	/**
	 * @since 2.2
	 */
	public function cleanUp() {
		$this->queryEngine->getQuerySegmentListItemResolver()->setQueryMode( Query::MODE_INSTANCES );
		$this->queryEngine->getQuerySegmentListItemResolver()->cleanUp();
	}

	/**
	 * @since 2.2
	 *
	 * @return array
	 */
	public function getErrors() {
		return $this->queryEngine->getQuerySegmentListBuilder()->getErrors();
	}

}
