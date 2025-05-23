<?php

namespace SMW\Tests\Integration;

use SMW\DataValueFactory;
use SMW\Services\ServicesFactory as ApplicationFactory;
use SMW\Tests\SMWIntegrationTestCase;
use SMW\Tests\Utils\UtilityFactory;
use SMWQuery as Query;
use SMWQueryProcessor as QueryProcessor;

/**
 * @covers \SMW\Query\QueryResult
 *
 * @group SMW
 * @group SMWExtension
 *
 * @group mediawiki-database
 * @group Database
 * @group medium
 *
 * @license GPL-2.0-or-later
 * @since 2.0
 *
 * @author mwjames
 */
class QueryResultQueryProcessorIntegrationTest extends SMWIntegrationTestCase {

	private $subjects = [];
	private $semanticDataFactory;

	private $dataValueFactory;
	private $queryResultValidator;

	private $fixturesProvider;
	private $queryParser;

	protected function setUp(): void {
		parent::setUp();

		$utilityFactory = UtilityFactory::getInstance();

		$this->semanticDataFactory = $utilityFactory->newSemanticDataFactory();
		$this->queryResultValidator = $utilityFactory->newValidatorFactory()->newQueryResultValidator();

		$this->fixturesProvider = $utilityFactory->newFixturesFactory()->newFixturesProvider();
		$this->fixturesProvider->setupDependencies( $this->getStore() );

		$this->dataValueFactory = DataValueFactory::getInstance();
		$this->queryParser = ApplicationFactory::getInstance()->getQueryFactory()->newQueryParser();
	}

	protected function tearDown(): void {
		$fixturesCleaner = UtilityFactory::getInstance()->newFixturesFactory()->newFixturesCleaner();

		$fixturesCleaner
			->purgeSubjects( $this->subjects )
			->purgeAllKnownFacts();

		parent::tearDown();
	}

	public function testUriQueryFromRawParameters() {
		$property = $this->fixturesProvider->getProperty( 'url' );

		$semanticData = $this->semanticDataFactory->newEmptySemanticData( __METHOD__ );
		$this->subjects[] = $semanticData->getSubject();

		$dataValue = $this->dataValueFactory->newDataValueByProperty(
			$property,
			'http://example.org/api.php?action=Foo'
		);

		$semanticData->addDataValue( $dataValue );

		$dataValue = $this->dataValueFactory->newDataValueByProperty(
			$property,
			'http://example.org/Bar 42'
		);

		$semanticData->addDataValue( $dataValue );

		$this->getStore()->updateData( $semanticData );

		/**
		 * @query [[Url::http://example.org/api.php?action=Foo]][[Url::http://example.org/Bar 42]]
		 */
		$rawParams = [
			'[[Url::http://example.org/api.php?action=Foo]][[Url::http://example.org/Bar 42]]',
			'?Url',
			'limit=1'
		];

		[ $queryString, $parameters, $printouts ] = QueryProcessor::getComponentsFromFunctionParams(
			$rawParams,
			false
		);

		$description = $this->queryParser->getQueryDescription( $queryString );

		$query = new Query(
			$description,
			false,
			false
		);

		$queryResult = $this->getStore()->getQueryResult( $query );

		$this->queryResultValidator->assertThatQueryResultHasSubjects(
			$semanticData->getSubject(),
			$queryResult
		);
	}

	/**
	 * @dataProvider queryDataProvider
	 */
	public function testCanConstructor( array $test ) {
		$this->assertInstanceOf(
			'\SMW\Query\QueryResult',
			$this->getQueryResultFor( $test['query'] )
		);
	}

	/**
	 * @dataProvider queryDataProvider
	 */
	public function testToArray( array $test, array $expected ) {
		$instance = $this->getQueryResultFor( $test['query'] );
		$results  = $instance->toArray();

		$this->assertEquals( $expected[0], $results['printrequests'][0] );
		$this->assertEquals( $expected[1], $results['printrequests'][1] );
	}

	private function getQueryResultFor( $queryString ) {
		[ $query, $formattedParams ] = QueryProcessor::getQueryAndParamsFromFunctionParams(
			$queryString,
			SMW_OUTPUT_WIKI,
			QueryProcessor::INLINE_QUERY,
			false
		);

		return $this->getStore()->getQueryResult( $query );
	}

	public function queryDataProvider() {
		$provider = [];

		// #1 Standard query
		$provider[] = [
			[ 'query' => [
				'[[Modification date::+]]',
				'?Modification date',
				'limit=10'
				]
			],
			[
				[
					'label' => '',
					'typeid' => '_wpg',
					'mode' => 2,
					'format' => false,
					'key' => '',
					'redi' => ''
				],
				[
					'label' => 'Modification date',
					'typeid' => '_dat',
					'mode' => 1,
					'format' => '',
					'key' => '_MDAT',
					'redi' => ''
				]
			]
		];

		// #2 Query containing a printrequest formatting
		$provider[] = [
			[ 'query' => [
				'[[Modification date::+]]',
				'?Modification date#ISO',
				'limit=10'
				]
			],
			[
				[
					'label' => '',
					'typeid' => '_wpg',
					'mode' => 2,
					'format' => false,
					'key' => '',
					'redi' => ''
				],
				[
					'label' => 'Modification date',
					'typeid' => '_dat',
					'mode' => 1,
					'format' => 'ISO',
					'key' => '_MDAT',
					'redi' => ''
				]
			]
		];

		return $provider;
	}

}
