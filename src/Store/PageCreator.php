<?php

namespace MediaWiki\Extension\StructureSync\Store;

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use Title;
use WikiPage;
use CommentStoreComment;
use User;

/**
 * Helper class for creating and updating wiki pages
 */
class PageCreator {

	/** @var User */
	private $user;

	/**
	 * @param User|null $user User performing the edits (null = system user)
	 */
	public function __construct( User $user = null ) {
		if ( $user === null ) {
			// Use a system user for automated edits
			$user = User::newSystemUser( 'StructureSync', [ 'steal' => true ] );
		}
		$this->user = $user;
	}

	/**
	 * Create or update a wiki page
	 *
	 * @param Title $title Page title
	 * @param string $content Page content (wikitext)
	 * @param string $summary Edit summary
	 * @param int $flags Edit flags (EDIT_NEW, EDIT_UPDATE, etc.)
	 * @return bool True on success
	 */
	public function createOrUpdatePage( Title $title, string $content, string $summary, int $flags = 0 ): bool {
		try {
			$wikiPage = WikiPage::factory( $title );

			$pageUpdater = $wikiPage->newPageUpdater( $this->user );

			// Set content
			$contentObj = \ContentHandler::makeContent( $content, $title );
			$pageUpdater->setContent( SlotRecord::MAIN, $contentObj );

			// Set edit summary
			$pageUpdater->saveRevision(
				CommentStoreComment::newUnsavedComment( $summary ),
				$flags
			);

			return !$pageUpdater->wasSuccessful() ? false : true;
		} catch ( \Exception $e ) {
			wfLogWarning( "StructureSync: Failed to create/update page {$title->getPrefixedText()}: " . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Check if a page exists
	 *
	 * @param Title $title
	 * @return bool
	 */
	public function pageExists( Title $title ): bool {
		return $title->exists();
	}

	/**
	 * Get the content of a page
	 *
	 * @param Title $title
	 * @return string|null Page content or null if page doesn't exist
	 */
	public function getPageContent( Title $title ): ?string {
		if ( !$title->exists() ) {
			return null;
		}

		$wikiPage = WikiPage::factory( $title );
		$content = $wikiPage->getContent();

		if ( $content === null ) {
			return null;
		}

		return $content->getText();
	}

	/**
	 * Get or create a Title object safely
	 *
	 * @param string $titleText
	 * @param int $namespace
	 * @return Title|null
	 */
	public function makeTitle( string $titleText, int $namespace ): ?Title {
		try {
			return Title::makeTitleSafe( $namespace, $titleText );
		} catch ( \Exception $e ) {
			wfLogWarning( "StructureSync: Failed to create title for '$titleText': " . $e->getMessage() );
			return null;
		}
	}

	/**
	 * Delete a page
	 *
	 * @param Title $title
	 * @param string $reason Deletion reason
	 * @return bool True on success
	 */
	public function deletePage( Title $title, string $reason ): bool {
		if ( !$title->exists() ) {
			return true; // Already deleted
		}

		try {
			$wikiPage = WikiPage::factory( $title );
			$error = '';
			$status = $wikiPage->doDeleteArticleReal( $reason, $this->user, false, null, $error );

			return $status->isOK();
		} catch ( \Exception $e ) {
			wfLogWarning( "StructureSync: Failed to delete page {$title->getPrefixedText()}: " . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Update or insert text within markers in page content
	 *
	 * @param string $content Current page content
	 * @param string $newText New text to insert
	 * @param string $startMarker Start marker (e.g., "<!-- StructureSync Schema Start -->")
	 * @param string $endMarker End marker (e.g., "<!-- StructureSync Schema End -->")
	 * @return string Updated content
	 */
	public function updateWithinMarkers( string $content, string $newText, string $startMarker, string $endMarker ): string {
		// Check if markers exist
		$startPos = strpos( $content, $startMarker );
		$endPos = strpos( $content, $endMarker );

		if ( $startPos !== false && $endPos !== false && $endPos > $startPos ) {
			// Replace content between markers
			$before = substr( $content, 0, $startPos + strlen( $startMarker ) );
			$after = substr( $content, $endPos );
			return $before . "\n" . $newText . "\n" . $after;
		} else {
			// Append markers and content
			if ( !empty( $content ) && substr( $content, -1 ) !== "\n" ) {
				$content .= "\n";
			}
			return $content . "\n" . $startMarker . "\n" . $newText . "\n" . $endMarker . "\n";
		}
	}
}

