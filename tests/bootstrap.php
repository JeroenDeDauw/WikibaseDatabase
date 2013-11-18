<?php

/**
 * PHPUnit test bootstrap file for the Wikibase Database component.
 *
 * @since 0.1
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */

if ( !in_array( '--testsuite=WikibaseDatabaseStandalone', $GLOBALS['argv'] ) ) {
	require_once( __DIR__ . '/evilMediaWikiBootstrap.php' );
}

require_once( __DIR__ . '/../WikibaseDatabase.php' );

require_once( __DIR__ . '/testLoader.php' );
