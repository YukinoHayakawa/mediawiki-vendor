<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Ext\Gallery;

use DOMDocument;
use DOMElement;

use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\Parsoid\Utils\PHPUtils;

class PackedMode extends TraditionalMode {
	/**
	 * Create a PackedMode singleton.
	 * @param string|null $mode Only used by subclasses.
	 */
	protected function __construct( string $mode = null ) {
		parent::__construct( $mode ?? 'packed' );
		$this->scale = 1.5;
		$this->padding = PHPUtils::arrayToObject( [ 'thumb' => 0, 'box' => 2, 'border' => 8 ] );
	}

	/** @inheritDoc */
	protected function perRow( Opts $opts, DOMElement $ul ): void {
		/* do nothing */
	}

	/** @inheritDoc */
	public function dimensions( Opts $opts ): string {
		$size = ceil( $opts->imageHeight * $this->scale );
		return "x{$size}px";
	}

	/** @inheritDoc */
	public function scaleMedia( Opts $opts, DOMElement $wrapper ) {
		$elt = $wrapper->firstChild->firstChild;
		DOMUtils::assertElt( $elt );
		$width = $elt->getAttribute( 'width' ) ?? '';
		if ( !is_numeric( $width ) ) {
			$width = $opts->imageWidth;
		} else {
			$width = intval( $width, 10 );
			$width /= $this->scale;
		}
		$elt->setAttribute( 'width', strval( ceil( $width ) ) );
		$elt->setAttribute( 'height', "$opts->imageHeight" );
		return $width;
	}

	protected function useTraditionalGalleryText(): bool {
		return true;
	}

	/** @inheritDoc */
	protected function galleryText(
		DOMDocument $doc, DOMElement $box, ?DOMElement $gallerytext, float $width
	): void {
		if ( $this->useTraditionalGalleryText() ) {
			parent::galleryText( $doc, $box, $gallerytext, $width );
			return;
		}
		if ( !$gallerytext ) {
			return;
		}
		$div = $doc->createElement( 'div' );
		$div->setAttribute( 'class', 'gallerytext' );
		DOMUtils::migrateChildrenBetweenDocs( $gallerytext, $div );
		if ( $gallerytext->hasAttribute( 'data-parsoid' ) ) {
			$div->setAttribute( 'data-parsoid', $gallerytext->getAttribute( 'data-parsoid' ) );
		}
		// The data-mw attribute *shouldn't* exist, since this gallerytext
		// should be a <figcaption>.  But let's be safe and copy it anyway.
		if ( $gallerytext->hasAttribute( 'data-mw' ) ) {
			$div->setAttribute( 'data-mw', $gallerytext->getAttribute( 'data-mw' ) );
		}
		$wrapper = $doc->createElement( 'div' );
		$wrapper->setAttribute( 'class', 'gallerytextwrapper' );
		$wrapper->setAttribute( 'style', 'width: ' . ceil( $width - 20 ) . 'px;' );
		$wrapper->appendChild( $div );
		$box->appendChild( $wrapper );
	}
}
