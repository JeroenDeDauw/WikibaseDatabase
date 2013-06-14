<?php

namespace Wikibase\Database;

/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @since 0.1
 *
 * @file
 * @ingroup WikibaseDatabase
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class UpdateFailedException extends QueryInterfaceException {

	protected $tableName;
	protected $values;
	protected $conditions;

	public function __construct( $tableName, array $values, array $conditions, $message = '', \Exception $previous = null ) {
		parent::__construct( $message, 0, $previous );

		$this->tableName = $tableName;
		$this->conditions = $conditions;
		$this->values = $values;
	}

	/**
	 * @return string
	 */
	public function getTableName() {
		return $this->tableName;
	}

	/**
	 * @return array
	 */
	public function getConditions() {
		return $this->conditions;
	}

	/**
	 * @return array
	 */
	public function getValues() {
		return $this->values;
	}

}