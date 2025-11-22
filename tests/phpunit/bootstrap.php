<?php

/**
 * PHPUnit bootstrap file for StructureSync extension
 */

// Attempt to find MediaWiki
if ( getenv( 'MW_INSTALL_PATH' ) !== false ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = __DIR__ . '/../../..';
}

// MediaWiki's PHPUnit bootstrap
if ( file_exists( "$IP/tests/phpunit/bootstrap.php" ) ) {
	require_once "$IP/tests/phpunit/bootstrap.php";
} else {
	// Fallback for standalone testing
	require_once __DIR__ . '/../../vendor/autoload.php';
}

