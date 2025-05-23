<?php

namespace SMW\Tests\SQLStore\TableBuilder;

use SMW\SQLStore\TableBuilder\Table;
use SMW\Tests\PHPUnitCompat;

/**
 * @covers \SMW\SQLStore\TableBuilder\Table
 * @group semantic-mediawiki
 *
 * @license GPL-2.0-or-later
 * @since 2.5
 *
 * @author mwjames
 */
class TableTest extends \PHPUnit\Framework\TestCase {

	use PHPUnitCompat;

	public function testCanConstruct() {
		$this->assertInstanceOf(
			Table::class,
			new Table( 'Foo' )
		);
	}

	public function testIsCoreTable() {
		$instance = new Table( 'Foo' );

		$this->assertTrue(
			$instance->isCoreTable()
		);

		$instance = new Table( 'Bar', false );

		$this->assertFalse(
			$instance->isCoreTable()
		);
	}

	public function testAddColumn() {
		$instance = new Table( 'Foo' );

		$instance->addColumn( 'b', 'integer' );

		$expected = [
			'fields' => [
				 'b' => 'integer'
			]
		];

		$this->assertEquals(
			$expected,
			$instance->getAttributes()
		);
	}

	public function testAddIndex() {
		$instance = new Table( 'Foo' );

		$instance->addIndex( 'bar' );

		$expected = [
			'indices' => [
				'bar'
			]
		];

		$this->assertEquals(
			$expected,
			$instance->getAttributes()
		);

		$this->assertIsString(

			$instance->getHash()
		);
	}

	public function testAddIndexWithKey() {
		$instance = new Table( 'Foo' );

		$instance->addIndex( [ 'foobar' ], 'bar' );

		$expected = [
			'indices' => [
				'bar' => [ 'foobar' ]
			]
		];

		$this->assertEquals(
			$expected,
			$instance->getAttributes()
		);
	}

	public function testSetPrimaryKey() {
		$instance = new Table( 'Foo' );
		$instance->setPrimaryKey( 'abc,def' );

		$expected = [
			'indices' => [
				'pri' => [ 'abc,def', 'PRIMARY KEY' ]
			]
		];

		$this->assertEquals(
			$expected,
			$instance->getAttributes()
		);
	}

	public function testAddIndexWithSpaceThrowsException() {
		$instance = new Table( 'Foo' );

		$this->expectException( 'RuntimeException' );
		$instance->addIndex( 'foobar, bar' );
	}

	public function testAddOption() {
		$instance = new Table( 'Foo' );

		$instance->addOption( 'bar', [ 'foobar' ] );

		$expected = [
			'bar' => [ 'foobar' ]
		];

		$this->assertEquals(
			$expected,
			$instance->getAttributes()
		);

		$this->assertEquals(
			[ 'foobar' ],
			$instance->get( 'bar' )
		);
	}

	public function testGetOnUnregsiteredKeyThrowsException() {
		$instance = new Table( 'Foo' );

		$this->expectException( 'RuntimeException' );
		$instance->get( 'bar' );
	}

	/**
	 * @dataProvider invalidOptionsProvider
	 */
	public function testAddOptionOnReservedOptionKeyThrowsException( $key ) {
		$instance = new Table( 'Foo' );

		$this->expectException( 'RuntimeException' );
		$instance->addOption( $key, [] );
	}

	public function invalidOptionsProvider() {
		$provider[] = [
			'fields'
		];

		$provider[] = [
			'indices'
		];

		$provider[] = [
			'defaults'
		];

		return $provider;
	}

}
