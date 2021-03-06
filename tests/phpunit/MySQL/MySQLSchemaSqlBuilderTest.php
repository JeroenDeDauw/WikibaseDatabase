<?php

namespace Wikibase\Database\Tests\MySQL;

use Wikibase\Database\MySQL\MySQLSchemaSqlBuilder;
use Wikibase\Database\Schema\Definitions\FieldDefinition;
use Wikibase\Database\Schema\Definitions\IndexDefinition;

/**
 * @covers Wikibase\Database\MySQL\MySQLSchemaSqlBuilder
 *
 * @group Wikibase
 * @group WikibaseDatabase
 * @group WikibaseDatabaseMySQL
 * @group Database
 *
 * @licence GNU GPL v2+
 * @author Adam Shorland
 */
class MySQLSchemaSqlBuilderTest extends \PHPUnit_Framework_TestCase {

	private function newInstance() {
		$mockEscaper = $this->getMock( 'Wikibase\Database\Escaper' );

		$mockEscaper->expects( $this->any() )
			->method( 'getEscapedValue' )
			->will( $this->returnCallback( function( $input ) {
				return "|$input|";
			} ) );

		$mockEscaper->expects( $this->any() )
			->method( 'getEscapedIdentifier' )
			->will( $this->returnCallback( function( $value ) {
				return '-' . $value . '-';
			} ) );

		$mockTableNameFormatter = $this->getMock( 'Wikibase\Database\TableNameFormatter' );
		$mockTableNameFormatter->expects( $this->atLeastOnce() )
			->method( 'formatTableName' )
			->will( $this->returnCallback( function( $input ) {
				return "||$input||";
			} ) );

		return new MySQLSchemaSqlBuilder( $mockEscaper, $mockTableNameFormatter );
	}

	public function testGetRemoveFieldSql(){
		$instance = $this->newInstance();
		$sql = $instance->getRemoveFieldSql( 'tableName', 'fieldName' );
		$this->assertEquals( "ALTER TABLE -||tableName||- DROP -fieldName-", $sql );
	}

	public function testGetAddFieldSql(){
		$instance = $this->newInstance();
		$field = new FieldDefinition( 'intField', FieldDefinition::TYPE_INTEGER, FieldDefinition::NOT_NULL, 42 );
		$sql = $instance->getAddFieldSql( 'tableName', $field );
		$this->assertEquals( 'ALTER TABLE -||tableName||- ADD -intField- INT DEFAULT |42| NOT NULL', $sql );
	}

	public function testGetRemoveIndexSql(){
		$instance = $this->newInstance();
		$sql = $instance->getRemoveIndexSql( 'tableName', 'indexName' );
		$this->assertEquals( "DROP INDEX -indexName- ON -||tableName||-", $sql );
	}

	public function testGetAddIndexSql(){
		$instance = $this->newInstance();
		$sql = $instance->getAddIndexSql( 'tableName', new IndexDefinition( 'indexName', array( 'a' => 0, 'b' => 0 ), IndexDefinition::TYPE_INDEX ) );
		$this->assertEquals( "CREATE INDEX -indexName- ON -||tableName||- (-a-,-b-)", $sql );
	}

}