<?php

namespace Wikibase\Database\MySQL;

use Wikibase\Database\QueryInterface\QueryInterface;
use Wikibase\Database\QueryInterface\QueryInterfaceException;
use Wikibase\Database\QueryInterface\ResultIterator;
use Wikibase\Database\Schema\Definitions\FieldDefinition;
use Wikibase\Database\Schema\Definitions\IndexDefinition;
use Wikibase\Database\Schema\Definitions\TableDefinition;
use Wikibase\Database\Schema\TableDefinitionReader;

/**
 * @since 0.1
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Adam Shorland
 */
class MySQLTableDefinitionReader implements TableDefinitionReader {

	protected $queryInterface;

	/**
	 * @param QueryInterface $queryInterface
	 */
	public function __construct( QueryInterface $queryInterface ) {
		$this->queryInterface = $queryInterface;
	}

	/**
	 * @see TableDefinitionReader::readDefinition
	 *
	 * @param string $tableName
	 *
	 * @throws QueryInterfaceException
	 * @return TableDefinition
	 */
	public function readDefinition( $tableName ) {
		if( !$this->queryInterface->tableExists( $tableName ) ){
			throw new QueryInterfaceException( "Unknown table {$tableName}" );
		}

		$fields = $this->getFields( $tableName );
		$indexes = $this->getIndexes( $tableName );
		return new TableDefinition( $tableName, $fields, $indexes );
	}

	/**
	 * @param $tableName string
	 * @return FieldDefinition[]
	 */
	private function getFields( $tableName ) {
		$fieldResults = $this->doColumnsQuery( $tableName );

		$fields = array();
		foreach( $fieldResults as $field ){

			$fields[] = new FieldDefinition(
				$field->name,
				$this->getDataType( $field->type ),
				$this->getNullable( $field->cannull ),
				$field->defaultvalue );
		}

		return $fields;
	}

	/**
	 * Performs a request to get information needed to construct FieldDefinitions
	 * @param string $tableName
	 * @return ResultIterator
	 */
	private function doColumnsQuery( $tableName ){
		return $this->queryInterface->select(
			'INFORMATION_SCHEMA.COLUMNS',
			array(
				'name' => 'COLUMN_NAME',
				'cannull' => 'IS_NULLABLE',
				'type' => 'DATA_TYPE',
				'defaultvalue' => 'COLUMN_DEFAULT', ),
			array( 'TABLE_NAME' => $tableName )
		);
	}

	/**
	 * Simplifies the datatype and returns something a FieldDefinition can expect
	 * @param $dataType string
	 * @return string
	 */
	private function getDataType( $dataType ) {
		if( stristr( $dataType, 'blob' ) ){
			return FieldDefinition::TYPE_TEXT;
		} else if ( stristr( $dataType, 'tinyint' ) ){
			return FieldDefinition::TYPE_BOOLEAN;
		} else if ( stristr( $dataType, 'int' ) ){
			return FieldDefinition::TYPE_INTEGER;
		} else if ( stristr( $dataType, 'float' ) ){
			return FieldDefinition::TYPE_FLOAT;
		} else {
			return $dataType;
		}
	}

	/**
	 * @param $nullable string
	 * @return bool
	 */
	private function getNullable( $nullable ) {
		if( $nullable === 'YES' ){
			return true;
		} else {
			return false;
		}
	}

	/**
	 * @param $tableName string
	 * @throws QueryInterfaceException
	 * @return IndexDefinition[]
	 * @TODO support currently don't notice FULLTEXT or SPATIAL indexes
	 * @TODO integration test that uses primarykey in MYSQL
	 */
	private function getIndexes( $tableName ) {
		$indexes = array();

		$constraintsResult =  $this->doConstraintsQuery( $tableName );
		$constraints = array();
		foreach( $constraintsResult as $constraint ) {
			//todo we should try to detect the index length here and use that instead of default 0
			$constraints[ $constraint->name ][ $constraint->columnName ] = 0;
		}
		foreach( $constraints as $name => $cols ){
			$indexes[] = $this->getConstraint( $name, $cols );
		}

		$indexesResult = $this->doIndexesQuery( $tableName );
		foreach( $indexesResult as $index ){
			$indexDef =  $this->getIndex( $index );
			//ignore any indexes we already have (primary and unique)
			if( !array_key_exists( $indexDef->getName(), $constraints ) ){
				$indexes[] = $indexDef;
			}
		}

		return $indexes;
	}

	private function getConstraint( $name, $columns ) {
		if( $name === 'PRIMARY' ){
			return new IndexDefinition( 'PRIMARY' , $columns , IndexDefinition::TYPE_PRIMARY );
		} else {
			return new IndexDefinition( $name , $columns , IndexDefinition::TYPE_UNIQUE );
		}
	}

	private function getIndex( $index ){
		$cols = array();
		foreach( explode( ',', $index->columns ) as $col ){
			$cols[ $col ] = 0;
		}
		return new IndexDefinition( $index->name, $cols , IndexDefinition::TYPE_INDEX );
	}

	/**
	 * Performs a request to get information needed to construct IndexDefinitions
	 * for Primary Keys and Unique Indexes from constraints
	 * @param string $tableName
	 * @return ResultIterator
	 */
	private function doConstraintsQuery( $tableName ) {
		return $this->queryInterface->select(
			'INFORMATION_SCHEMA.KEY_COLUMN_USAGE',
			array(
				'name' => 'CONSTRAINT_NAME',
				'columnName' => 'COLUMN_NAME'
			),
			array( 'TABLE_NAME' => $tableName )
		);
	}

	/**
	 * Performs a request to get information needed to construct IndexDefinitions
	 * that are not Primary Keys or Unique Indexes from statistics
	 * @param string $tableName
	 * @return ResultIterator
	 */
	private function doIndexesQuery( $tableName ){
		return $this->queryInterface->select(
			'INFORMATION_SCHEMA.STATISTICS',
			array(
				'COLUMN_NAME',
				'SEQ_IN_INDEX',
				'name' => 'INDEX_NAME',
				'columns' => 'GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX)' ),
			array( 'TABLE_NAME' => $tableName ),
			array( 'GROUP BY' => 'name' )
		);
	}

}
