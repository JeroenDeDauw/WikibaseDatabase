<?php

namespace Wikibase\Database\SQLite;

use RuntimeException;
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
class SQLiteTableDefinitionReader implements TableDefinitionReader {

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
		$keys = $this->getPrimaryKeys( $tableName );
		return new TableDefinition( $tableName, $fields, array_merge( $indexes, $keys ) );
	}

	/**
	 * Returns an array of all fields in the given table
	 * @param string $tableName
	 * @throws QueryInterfaceException
	 * @return FieldDefinition[]
	 */
	private function getFields( $tableName ) {
		$results = $this->doCreateQuery( $tableName );
		if( iterator_count( $results ) > 1 ){
			throw new QueryInterfaceException( "More than one set of fields returned for {$tableName}" );
		}
		$fields = array();

		foreach( $results as $result ){
			/** $createParts,  1 => tableName, 2 => fieldParts (fields, keys, etc.) */
			preg_match( '/CREATE TABLE ([^ ]+) \(([^\)]+)\)/', $result->sql, $createParts );

			foreach( explode( ',', $createParts[2] ) as $fieldSql ){
				if( preg_match( '/([^ ]+) ([^ ]+)( DEFAULT ([^ ]+))?( ((NOT )?NULL))?/', $fieldSql, $fieldParts )
					&& $fieldParts[0] !== 'PRIMARY KEY' ) {
					$fields[] = $this->getField( $fieldParts );
				}
			}
		}

		return $fields;
	}

	/**
	 * Performs a request to get the SQL needed to create the given table
	 * @param string $tableName
	 * @return ResultIterator
	 */
	private function doCreateQuery( $tableName ){
		return $this->queryInterface->select(
			'sqlite_master',
			array( 'sql' ),
			array( 'type' => 'table', 'tbl_name' => $tableName ) );
	}

	private function getField( $fieldParts ) {
		$type = $this->getFieldType( $fieldParts[2] );
		$default = $this->getFieldDefault( $fieldParts[4] );
		$null = $this->getFieldCanNull( $fieldParts[6] );
		return new FieldDefinition( $fieldParts[1], $type, $null, $default );
	}

	private function getFieldType( $type ) {
		switch ( $type ) {
			case 'TINYINT':
				return 'bool';
				break;
			case 'BLOB':
				return 'str';
				break;
			case 'INT':
				return 'int';
				break;
			case 'FLOAT':
				return 'float';
				break;
			default:
				throw new RuntimeException( __CLASS__ . ' does not support db fields of type ' . $type );
		}
	}

	private function getFieldDefault( $default ) {
		if( !empty( $default ) ){
			return $default;
		} else {
			return null;
		}
	}

	private function getFieldCanNull( $canNull ) {
		if( $canNull === 'NOT NULL' ){
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Returns an array of all indexes for a given table (excluding Primary Keys)
	 * @param string $tableName
	 * @return IndexDefinition[]
	 */
	private function getIndexes( $tableName ) {
		$results = $this->doIndexQuery( $tableName );
		$indexes = array();

		foreach( $results as $result ){
			$indexes[] = $this->getIndex( $result->sql );
		}

		return $indexes;
	}

	private function getIndex( $sql ){
		preg_match( '/CREATE (INDEX|UNIQUE INDEX) ([^ ]+) ON ([^ ]+) \((.+)\)\z/', $sql, $createParts );
		$parsedColumns = explode( ',', $createParts[4] );
		$columns = array();
		foreach( $parsedColumns as $columnName ){
			//default unrestricted index size limit
			$columns[ $columnName ] = 0;
		}
		$type = $this->getIndexType( $createParts[1] );
		return new IndexDefinition( $createParts[2], $columns , $type );
	}

	/**
	 * Performs a request to get the SQL needed to create all indexes for a table
	 * @param string $tableName
	 * @return ResultIterator
	 */
	private function doIndexQuery( $tableName ){
		return $this->queryInterface->select(
			'sqlite_master',
			array( 'sql' ),
			array( 'type' => 'index', 'tbl_name' => $tableName )
		);
	}

	private function getIndexType( $type ) {
		switch ( $type ) {
			case 'INDEX':
				return IndexDefinition::TYPE_INDEX;
				break;
			case 'UNIQUE INDEX':
				return IndexDefinition::TYPE_UNIQUE;
				break;
			default:
				throw new RuntimeException( __CLASS__ . ' does not support db indexes of type ' . $type );
		}
	}

	/**
	 * Returns an array of all primary keys for a given table
	 * @param string $tableName
	 * @return IndexDefinition[]
	 */
	private function getPrimaryKeys( $tableName ) {
		$keys = array();
		$results = $this->doPrimaryKeyQuery( $tableName );

		foreach( $results as $result ){
			if( preg_match( '/PRIMARY KEY \(([^\)]+)\)/', $result->sql, $createParts ) ){
				/**  0 => PRIMARY KEY (column1, column2), 1 => column1, column2 */
				$parsedColumns = explode( ',', $createParts[1] );
				$columns = array();
				foreach( $parsedColumns as $columnName ){
					//default unrestricted index size limit
					$columns[ trim( $columnName ) ] = 0;
				}
				$keys[] = new IndexDefinition( 'PRIMARY', $columns , IndexDefinition::TYPE_PRIMARY );
			}
		}

		return $keys;
	}

	/**
	 * Performs a request to get the SQL needed to create the primary key for a given table
	 * @param string $tableName
	 * @return ResultIterator
	 */
	private function doPrimaryKeyQuery( $tableName ){
		return $this->queryInterface->select(
			'sqlite_master',
			array( 'sql' ),
			array( 'type' => 'table', 'tbl_name' => $tableName, "sql LIKE '%PRIMARY KEY%'" )
		);
	}

}
