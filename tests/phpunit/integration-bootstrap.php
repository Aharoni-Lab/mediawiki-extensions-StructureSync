<?php

/**
 * PHPUnit bootstrap for SemanticSchemas integration tests.
 *
 * Uses MediaWiki's test framework from the labki-platform dev image.
 * Run via: ./tests/scripts/run-docker-tests.sh integration
 */

// Skip the composer.lock check since we're running in Docker
putenv( 'MW_SKIP_EXTERNAL_DEPENDENCIES=1' );

// Load MediaWiki's integration test bootstrap
require_once '/var/www/html/tests/phpunit/bootstrap.integration.php';
