<?php

namespace Wikibase\Database\Tests;

use Wikibase\Database\MediaWiki\MediaWikiQueryInterface;
use Wikibase\Database\MediaWiki\MWTableBuilderBuilder;
use Wikibase\Database\MediaWiki\MWTableDefinitionReaderBuilder;
use Wikibase\Database\Schema\Definitions\FieldDefinition;
use Wikibase\Database\Schema\Definitions\IndexDefinition;
use Wikibase\Database\LazyDBConnectionProvider;
use Wikibase\Database\Schema\Definitions\TableDefinition;

/**
 * @since 0.1
 *
 * @group Wikibase
 * @group WikibaseDatabase
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Adam Shorland
 */
class TableCreateReadDeleteTest extends \PHPUnit_Framework_TestCase {

	protected function tearDown() {
		parent::tearDown();

		$this->dropTablesIfStillThere();
	}

	protected function dropTablesIfStillThere() {
		$tableBuilder = $this->newTableBuilder();

		foreach ( array( 'different_field_types', 'default_field_values', 'not_null_fields' ) as $tableName ) {
			if ( $tableBuilder->tableExists( $tableName ) ) {
				$tableBuilder->dropTable( $tableName );
			}
		}
	}

	protected function newTableBuilder() {
		$connectionProvider = new LazyDBConnectionProvider( DB_MASTER );

		$tbBuilder = new MWTableBuilderBuilder();
		return $tbBuilder->setConnection( $connectionProvider )->getTableBuilder();
	}

	protected function newTableReader() {
		$connectionProvider = new LazyDBConnectionProvider( DB_MASTER );

		$trBuilder = new MWTableDefinitionReaderBuilder();
		return $trBuilder->setConnection( $connectionProvider )->getTableDefinitionReader( $this->newQueryInterface() );
	}

	protected function newQueryInterface() {
		$connectionProvider = new LazyDBConnectionProvider( DB_MASTER );

		return new MediaWikiQueryInterface( $connectionProvider );
	}

	public function tableProvider() {
		$tables = array();

		$tables[] = new TableDefinition( 'different_field_types', array(
			new FieldDefinition( 'intfield', FieldDefinition::TYPE_INTEGER ),
			new FieldDefinition( 'floatfield', FieldDefinition::TYPE_FLOAT ),
			new FieldDefinition( 'textfield', FieldDefinition::TYPE_TEXT ),
			new FieldDefinition( 'boolfield', FieldDefinition::TYPE_BOOLEAN ),
		) );

		$tables[] = new TableDefinition( 'default_field_values', array(
			new FieldDefinition( 'intfield', FieldDefinition::TYPE_INTEGER, FieldDefinition::NULL, 42 ),
		) );

		$tables[] = new TableDefinition( 'not_null_fields', array(
			new FieldDefinition( 'intfield', FieldDefinition::TYPE_INTEGER, FieldDefinition::NOT_NULL ),
			new FieldDefinition( 'textfield', FieldDefinition::TYPE_TEXT, FieldDefinition::NOT_NULL ),
		) );

		$tables[] = new TableDefinition( 'not_null_fields', array(
			new FieldDefinition( 'intfield', FieldDefinition::TYPE_INTEGER, FieldDefinition::NOT_NULL ),
			new FieldDefinition( 'textfield', FieldDefinition::TYPE_TEXT, FieldDefinition::NULL ),
		) );

		$tables[] = new TableDefinition( 'default_field_values', array(
				new FieldDefinition( 'textfield', FieldDefinition::TYPE_TEXT, FieldDefinition::NOT_NULL ),
				new FieldDefinition( 'intfield', FieldDefinition::TYPE_INTEGER, FieldDefinition::NULL, 3 ),
				new FieldDefinition( 'floatfield', FieldDefinition::TYPE_FLOAT, FieldDefinition::NOT_NULL ),
				new FieldDefinition( 'boolfield', FieldDefinition::TYPE_BOOLEAN, FieldDefinition::NOT_NULL, true ),
			),
			array(
				//TODO test PRIMARY key
				//TODO test UNIQUE INDEX
				new IndexDefinition( 'somename', array( 'intfield' => 0, 'floatfield' => 0 ) )
			)
		);

		$argLists = array();

		foreach ( $tables as $table ) {
			$argLists[] = array( $table );
		}

		return $argLists;
	}

	/**
	 * @dataProvider tableProvider
	 *
	 * @param TableDefinition $table
	 */
	public function testCreateAndDropTable( TableDefinition $table ) {
		$tableBuilder = $this->newTableBuilder();

		$this->assertFalse(
			$tableBuilder->tableExists( $table->getName() ),
			'Table should not exist before creation'
		);

		$tableBuilder->createTable( $table );

		$this->assertTrue(
			$tableBuilder->tableExists( $table->getName() ),
			'Table "' . $table->getName() . '" exists after creation'
		);

		$tableReader = $this->newTableReader();

		$this->assertEquals(
			$table,
			$tableReader->readDefinition( $table->getName() )
		);

		$tableBuilder->dropTable( $table->getName() );

		$this->assertFalse(
			$tableBuilder->tableExists( $table->getName() ),
			'Table should not exist after deletion'
		);
	}

}
