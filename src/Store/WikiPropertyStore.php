<?php

namespace MediaWiki\Extension\StructureSync\Store;

use MediaWiki\Extension\StructureSync\Schema\PropertyModel;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;

/**
 * WikiPropertyStore
 * ------------------
 * Responsible for reading/writing SMW Property: pages and reconstructing
 * PropertyModel objects from the raw page content.
 *
 * This version provides:
 *   - Robust parsing of SMW property metadata
 *   - Canonical normalization of property names
 *   - Extraction of:
 *        * datatype            (from [[Has type::...]])
 *        * allowed values      (from [[Allows value::...]])
 *        * range categories    (from [[Has domain and range::Category:...]])
 *        * subproperty links   (from [[Subproperty of::...]])
 *        * human-readable descriptions
 *   - SMW-correct export
 */
class WikiPropertyStore {

    /** @var PageCreator */
    private $pageCreator;

    public function __construct( PageCreator $pageCreator = null ) {
        $this->pageCreator = $pageCreator ?? new PageCreator();
    }

    /* ---------------------------------------------------------------------
     * READ PROPERTY
     * --------------------------------------------------------------------- */

    /**
     * Read a property from the wiki and construct a PropertyModel.
     *
     * @param string $propertyName Canonical property (no "Property:" prefix)
     * @return PropertyModel|null
     */
    public function readProperty( string $propertyName ): ?PropertyModel {

        $canonical = $this->normalizePropertyName( $propertyName );

        $title = $this->pageCreator->makeTitle( $canonical, \SMW_NS_PROPERTY );
        if ( $title === null || !$this->pageCreator->pageExists( $title ) ) {
            return null;
        }

        $content = $this->pageCreator->getPageContent( $title );
        if ( $content === null ) {
            return null;
        }

        $data = $this->parsePropertyContent( $content );

        // Ensure label fallback
        if ( !isset( $data['label'] ) ) {
            $data['label'] = $canonical;
        }

        return new PropertyModel( $canonical, $data );
    }

    /* ---------------------------------------------------------------------
     * PROPERTY NAME NORMALIZATION
     * --------------------------------------------------------------------- */

    /**
     * Normalize property names to canonical SMW style ("Has advisor").
     */
    private function normalizePropertyName( string $name ): string {
        $name = trim( $name );
        $name = str_replace( '_', ' ', $name );
        return $name;
    }

    /* ---------------------------------------------------------------------
     * PARSE PROPERTY CONTENT
     * --------------------------------------------------------------------- */

    /**
     * Parse SMW property content and return structured metadata array.
     *
     * @param string $content Raw wiki text of Property: page
     * @return array
     */
    private function parsePropertyContent( string $content ): array {

        $data = [];

        // ------------------------------------------------------------------
        // 1. Datatype: [[Has type::Type]]
        // ------------------------------------------------------------------
        if ( preg_match( '/\[\[Has type::([^\|\]]+)/i', $content, $m ) ) {
            $data['datatype'] = trim( $m[1] );
        }

        // ------------------------------------------------------------------
        // 2. Allowed values: * [[Allows value::Foo]]
        // ------------------------------------------------------------------
        preg_match_all( '/\[\[Allows value::([^\|\]]+)/i', $content, $matches );
        if ( !empty( $matches[1] ) ) {
            $values = array_map( 'trim', $matches[1] );
            $data['allowedValues'] = array_values( array_unique( $values ) );
        }

        // ------------------------------------------------------------------
        // 3. Range category (Page-type restrictions): [[Has domain and range::Category:Foo]]
        // ------------------------------------------------------------------
        if ( preg_match( '/\[\[Has domain and range::Category:([^\|\]]+)/i', $content, $m ) ) {
            $data['rangeCategory'] = trim( $m[1] );
        }

        // ------------------------------------------------------------------
        // 4. Subproperty: [[Subproperty of::Has parent]]
        // ------------------------------------------------------------------
        if ( preg_match( '/\[\[Subproperty of::([^\|\]]+)/i', $content, $m ) ) {
            $data['subpropertyOf'] = trim( str_replace( '_', ' ', $m[1] ) );
        }

        // ------------------------------------------------------------------
        // 5. Description:
        //    First non-empty line that does not start with:
        //    * [[...]]
        //    * {{
        //    * <!
        // ------------------------------------------------------------------
        $lines = explode( "\n", $content );
        foreach ( $lines as $line ) {
            $line = trim( $line );
            if (
                $line !== '' &&
                !str_starts_with( $line, '[[' ) &&
                !str_starts_with( $line, '{{' ) &&
                !str_starts_with( $line, '<!' )
            ) {
                $data['description'] = $line;
                break;
            }
        }

        return $data;
    }

    /* ---------------------------------------------------------------------
     * WRITE PROPERTY CONTENT
     * --------------------------------------------------------------------- */

    /**
     * Write/update an SMW Property: page
     */
    public function writeProperty( PropertyModel $property ): bool {

        $title = $this->pageCreator->makeTitle( $property->getName(), \SMW_NS_PROPERTY );
        if ( $title === null ) {
            return false;
        }

        $content = $this->generatePropertyContent( $property );
        $summary = "StructureSync: Update property metadata";

        return $this->pageCreator->createOrUpdatePage( $title, $content, $summary );
    }

    /**
     * Generate SMW property page content.
     *
     * @param PropertyModel $property
     * @return string
     */
    private function generatePropertyContent( PropertyModel $property ): string {

        $lines = [];

        // Description
        if ( $property->getDescription() !== '' ) {
            $lines[] = $property->getDescription();
            $lines[] = '';
        }

        // Datatype
        $lines[] = '[[Has type::' . $property->getSMWType() . ']]';

        // Allowed values (enum)
        if ( $property->hasAllowedValues() ) {
            $lines[] = '';
            $lines[] = '== Allowed values ==';
            foreach ( $property->getAllowedValues() as $value ) {
                $value = str_replace( '|', ' ', $value ); // Prevent SMW parse issues
                $lines[] = "* [[Allows value::$value]]";
            }
        }

        // Range category
        if ( $property->getRangeCategory() !== null ) {
            $cat = $property->getRangeCategory();
            $lines[] = '';
            $lines[] = '[[Has domain and range::Category:' . $cat . ']]';
        }

        // Subproperty
        if ( $property->getSubpropertyOf() !== null ) {
            $parent = $property->getSubpropertyOf();
            $lines[] = '';
            $lines[] = '[[Subproperty of::' . $parent . ']]';
        }

        // Category for organization
        $lines[] = '';
        $lines[] = '[[Category:Properties]]';

        return implode( "\n", $lines );
    }

    /* ---------------------------------------------------------------------
     * STORES & QUERIES
     * --------------------------------------------------------------------- */

    public function getAllProperties(): array {

        $properties = [];

        $lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
        $dbr = $lb->getConnection( DB_REPLICA );

        $res = $dbr->newSelectQueryBuilder()
            ->select( 'page_title' )
            ->from( 'page' )
            ->where( [ 'page_namespace' => \SMW_NS_PROPERTY ] )
            ->caller( __METHOD__ )
            ->fetchResultSet();

        foreach ( $res as $row ) {

            $name = str_replace( '_', ' ', $row->page_title );
            $property = $this->readProperty( $name );

            if ( $property !== null ) {
                $properties[$name] = $property;
            }
        }

        return $properties;
    }

    public function propertyExists( string $propertyName ): bool {
        $canonical = $this->normalizePropertyName( $propertyName );
        $title = $this->pageCreator->makeTitle( $canonical, \SMW_NS_PROPERTY );
        return $title !== null && $this->pageCreator->pageExists( $title );
    }
}
