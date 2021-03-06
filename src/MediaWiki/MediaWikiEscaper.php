<?php

namespace Wikibase\Database\MediaWiki;

use DatabaseBase;
use Wikibase\Database\Escaper;

/**
 * Adapter for MediaWiki's SQL value escaping functionality.
 *
 * @since 0.1
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Adam Shorland
 */
class MediaWikiEscaper implements Escaper {

	protected $dbConnection;

	public function __construct( DatabaseBase $dbConnection ) {
		$this->dbConnection = $dbConnection;
	}

	/**
	 * @see Escaper::getEscapedValue
	 *
	 * @param mixed $value
	 *
	 * @return string The escaped value
	 */
	public function getEscapedValue( $value ) {
		return $this->dbConnection->addQuotes( $value );
	}

	/**
	 * @see Escaper::getEscapedIdentifier
	 *
	 * @param mixed $identifier
	 *
	 * @return string The escaped identifier
	 */
	public function getEscapedIdentifier( $identifier ) {
		return $this->dbConnection->addIdentifierQuotes( $identifier );
	}

}
