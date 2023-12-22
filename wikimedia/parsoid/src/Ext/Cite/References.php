<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Ext\Cite;

use Closure;
use stdClass;
use Wikimedia\Parsoid\Core\DomSourceRange;
use Wikimedia\Parsoid\DOM\DocumentFragment;
use Wikimedia\Parsoid\DOM\Element;
use Wikimedia\Parsoid\DOM\Node;
use Wikimedia\Parsoid\Ext\DOMDataUtils;
use Wikimedia\Parsoid\Ext\DOMUtils;
use Wikimedia\Parsoid\Ext\ExtensionTagHandler;
use Wikimedia\Parsoid\Ext\ParsoidExtensionAPI;
use Wikimedia\Parsoid\Ext\PHPUtils;
use Wikimedia\Parsoid\Ext\WTUtils;
use Wikimedia\Parsoid\NodeData\DataMw;
use Wikimedia\Parsoid\NodeData\DataParsoid;
use Wikimedia\Parsoid\Utils\DOMCompat;

class References extends ExtensionTagHandler {

	private static function hasRef( Node $node ): bool {
		$c = $node->firstChild;
		while ( $c ) {
			if ( $c instanceof Element ) {
				if ( WTUtils::isSealedFragmentOfType( $c, 'ref' ) ) {
					return true;
				}
				if ( self::hasRef( $c ) ) {
					return true;
				}
			}
			$c = $c->nextSibling;
		}
		return false;
	}

	private static function createReferences(
		ParsoidExtensionAPI $extApi, DocumentFragment $domFragment,
		array $refsOpts, ?callable $modifyDp, bool $autoGenerated = false
	): Element {
		$doc = $domFragment->ownerDocument;

		$ol = $doc->createElement( 'ol' );
		DOMCompat::getClassList( $ol )->add( 'mw-references' );
		DOMCompat::getClassList( $ol )->add( 'references' );

		DOMUtils::migrateChildren( $domFragment, $ol );

		// Support the `responsive` parameter
		if ( $refsOpts['responsive'] !== null ) {
			$responsiveWrap = $refsOpts['responsive'] !== '0';
		} else {
			$responsiveWrap = (bool)$extApi->getSiteConfig()->getMWConfigValue( 'CiteResponsiveReferences' );
		}

		if ( $responsiveWrap ) {
			$div = $doc->createElement( 'div' );
			DOMCompat::getClassList( $div )->add( 'mw-references-wrap' );
			$div->appendChild( $ol );
			$frag = $div;
		} else {
			$frag = $ol;
		}

		if ( $autoGenerated ) {
			// FIXME: This is very much trying to copy ExtensionHandler::onDocument
			DOMUtils::addAttributes( $frag, [
				'typeof' => 'mw:Extension/references',
				'about' => $extApi->newAboutId()
			] );
			$dataMw = new DataMw( [
				'name' => 'references',
				'attrs' => new stdClass
			] );
			// Dont emit empty keys
			if ( $refsOpts['group'] ) {
				$dataMw->attrs->group = $refsOpts['group'];
			}
			DOMDataUtils::setDataMw( $frag, $dataMw );
		}

		$dp = DOMDataUtils::getDataParsoid( $frag );
		if ( $refsOpts['group'] ) {  // No group for the empty string either
			$dp->group = $refsOpts['group'];
			$ol->setAttribute( 'data-mw-group', $refsOpts['group'] );
		}
		if ( $modifyDp ) {
			$modifyDp( $dp );
		}

		// These module namess are copied from Cite extension.
		// They are hardcoded there as well.
		$metadata = $extApi->getMetadata();
		$metadata->addModules( [ 'ext.cite.ux-enhancements' ] );
		$metadata->addModuleStyles( [ 'ext.cite.parsoid.styles', 'ext.cite.styles' ] );

		return $frag;
	}

	private static function extractRefFromNode(
		ParsoidExtensionAPI $extApi, Element $node, ReferencesData $refsData
	): void {
		$doc = $node->ownerDocument;
		$errs = [];

		// This is data-parsoid from the dom fragment node that's gone through
		// dsr computation and template wrapping.
		$nodeDp = DOMDataUtils::getDataParsoid( $node );
		$isTplWrapper = DOMUtils::hasTypeOf( $node, 'mw:Transclusion' );
		$contentId = $nodeDp->html;
		$tplDmw = $isTplWrapper ? DOMDataUtils::getDataMw( $node ) : null;

		// This is the <sup> that's the meat of the sealed fragment
		$c = $extApi->getContentDOM( $contentId )->firstChild;
		DOMUtils::assertElt( $c );
		$cDp = DOMDataUtils::getDataParsoid( $c );
		$refDmw = DOMDataUtils::getDataMw( $c );

		// Use the about attribute on the wrapper with priority, since it's
		// only added when the wrapper is a template sibling.
		$about = DOMCompat::getAttribute( $node, 'about' ) ??
			DOMCompat::getAttribute( $c, 'about' );

		// FIXME(SSS): Need to clarify semantics here.
		// If both the containing <references> elt as well as the nested <ref>
		// elt has a group attribute, what takes precedence?
		$groupName = $refDmw->attrs->group ?? $refsData->referencesGroup;
		$group = $refsData->getRefGroup( $groupName );

		if (
			$refsData->inReferencesContent() &&
			$groupName !== $refsData->referencesGroup
		) {
			$errs[] = [ 'key' => 'cite_error_references_group_mismatch',
				'params' => [ $refDmw->attrs->group ] ];
		}

		// NOTE: This will have been trimmed in Utils::getExtArgInfo()'s call
		// to TokenUtils::kvToHash() and ExtensionHandler::normalizeExtOptions()
		$refName = $refDmw->attrs->name ?? '';
		$followName = $refDmw->attrs->follow ?? '';
		$refDir = strtolower( $refDmw->attrs->dir ?? '' );

		// Add ref-index linkback
		$linkBack = $doc->createElement( 'sup' );

		$ref = null;

		$hasRefName = strlen( $refName ) > 0;
		$hasFollow = strlen( $followName ) > 0;

		$validFollow = false;

		if ( $hasFollow ) {
			// Always wrap follows content so that there's no ambiguity
			// where to find it when roundtripping
			$span = $doc->createElement( 'span' );
			DOMUtils::addTypeOf( $span, 'mw:Cite/Follow' );
			$span->setAttribute( 'about', $about );
			$span->appendChild(
				$doc->createTextNode( ' ' )
			);
			DOMUtils::migrateChildren( $c, $span );
			$c->appendChild( $span );
		}

		$html = '';
		$contentDiffers = false;

		if ( $hasRefName ) {
			if ( $hasFollow ) {
				// Presumably, "name" has higher precedence
				$errs[] = [ 'key' => 'cite_error_ref_too_many_keys' ];
			}
			if ( isset( $group->indexByName[$refName] ) ) {
				$ref = $group->indexByName[$refName];
				// If there are multiple <ref>s with the same name, but different content,
				// the content of the first <ref> shows up in the <references> section.
				// in order to ensure lossless RT-ing for later <refs>, we have to record
				// HTML inline for all of them.
				if ( $ref->contentId ) {
					if ( $ref->cachedHtml === null ) {
						$refContent = $extApi->getContentDOM( $ref->contentId )->firstChild;
						$ref->cachedHtml = $extApi->domToHtml( $refContent, true, false );
					}
					// See the test, "Forward-referenced ref with magical follow edge case"
					// Ideally, we should strip the mw:Cite/Follow wrappers before comparing
					// But, we are going to ignore this edge case as not worth the complexity.
					$html = $extApi->domToHtml( $c, true, false );
					$contentDiffers = ( $html !== $ref->cachedHtml );
				}
			} else {
				if ( $refsData->inReferencesContent() ) {
					$errs[] = [
						'key' => 'cite_error_references_missing_key',
						'params' => [ $refDmw->attrs->name ]
					];
				}
			}
		} else {
			if ( $hasFollow ) {
				// This is a follows ref, so check that a named ref has already
				// been defined
				if ( isset( $group->indexByName[$followName] ) ) {
					$validFollow = true;
					$ref = $group->indexByName[$followName];
				} else {
					// FIXME: This key isn't exactly appropriate since this
					// is more general than just being in a <references>
					// section and it's the $followName we care about, but the
					// extension to the legacy parser doesn't have an
					// equivalent key and just outputs something wacky.
					$errs[] = [ 'key' => 'cite_error_references_missing_key',
						'params' => [ $refDmw->attrs->follow ] ];
				}
			} elseif ( $refsData->inReferencesContent() ) {
				$errs[] = [ 'key' => 'cite_error_references_no_key' ];
			}
		}

		// Process nested ref-in-ref
		//
		// Do this before possibly adding the a ref below or
		// migrating contents out of $c if we have a valid follow
		if ( empty( $cDp->empty ) && self::hasRef( $c ) ) {
			if ( $contentDiffers ) {
				$refsData->pushEmbeddedContentFlag();
			}
			self::processRefs( $extApi, $refsData, $c );
			if ( $contentDiffers ) {
				$refsData->popEmbeddedContentFlag();
				// If we have refs and the content differs, we need to
				// reserialize now that we processed the refs.  Unfortunately,
				// the cachedHtml we compared against already had its refs
				// processed so that would presumably never match and this will
				// always be considered a redefinition.  The implementation for
				// the legacy parser also considers this a redefinition so
				// there is likely little content out there like this :)
				$html = $extApi->domToHtml( $c, true, true );
			}
		}

		if ( $validFollow ) {
			// Migrate content from the follow to the ref
			if ( $ref->contentId ) {
				$refContent = $extApi->getContentDOM( $ref->contentId )->firstChild;
				DOMUtils::migrateChildren( $c, $refContent );
			} else {
				// Otherwise, we have a follow that comes after a named
				// ref without content so use the follow fragment as
				// the content
				// This will be set below with `$ref->contentId = $contentId;`
			}
		} else {
			// If we have !$ref, one might have been added in the call to
			// processRefs, ie. a self-referential ref.  We could try to look
			// it up again, but Parsoid is choosing not to support that.
			// Even worse would be if it tried to redefine itself!

			if ( !$ref ) {
				$ref = $refsData->add( $extApi, $groupName, $refName );
			}

			// Handle linkbacks
			if ( $refsData->inEmbeddedContent() ) {
				$ref->embeddedNodes[] = $about;
			} else {
				$ref->nodes[] = $linkBack;
				$ref->linkbacks[] = $ref->key . '-' . count( $ref->linkbacks );
			}
		}

		if ( isset( $refDmw->attrs->dir ) && $refDir !== 'rtl' && $refDir !== 'ltr' ) {
			$errs[] = [ 'key' => 'cite_error_ref_invalid_dir',
				'params' => [ $refDmw->attrs->dir ] ];
		}

		// FIXME: At some point this error message can be changed to a warning, as Parsoid Cite now
		// supports numerals as a name without it being an actual error, but core Cite does not.
		// Follow refs do not duplicate the error which can be correlated with the original ref.
		if ( ctype_digit( $refName ) ) {
			$errs[] = [ 'key' => 'cite_error_ref_numeric_key' ];
		}

		// Check for missing content, added ?? '' to fix T259676 crasher
		// FIXME: See T260082 for a more complete description of cause and deeper fix
		$missingContent = ( !empty( $cDp->empty ) || trim( $refDmw->body->extsrc ?? '' ) === '' );

		if ( $missingContent ) {
			// Check for missing name and content to generate error code
			//
			// In references content, refs should be used for definition so missing content
			// is an error.  It's possible that no name is present (!hasRefName), which also
			// gets the error "cite_error_references_no_key" above, so protect against that.
			if ( $refsData->inReferencesContent() ) {
				$errs[] = [ 'key' => 'cite_error_empty_references_define',
					'params' => [ $refDmw->attrs->name ?? '' ] ];
			} elseif ( !$hasRefName ) {
				if ( !empty( $cDp->selfClose ) ) {
					$errs[] = [ 'key' => 'cite_error_ref_no_key' ];
				} else {
					$errs[] = [ 'key' => 'cite_error_ref_no_input' ];
				}
			}

			if ( !empty( $cDp->selfClose ) ) {
				unset( $refDmw->body );
			} else {
				// Empty the <sup> since we've serialized its children and
				// removing it below asserts everything has been migrated out
				DOMCompat::replaceChildren( $c );
				$refDmw->body = (object)[ 'html' => $refDmw->body->extsrc ?? '' ];
			}
		} else {
			if ( $ref->contentId && !$validFollow ) {
				// Empty the <sup> since we've serialized its children and
				// removing it below asserts everything has been migrated out
				DOMCompat::replaceChildren( $c );
			}
			if ( $contentDiffers ) {
				// TODO: Since this error is being placed on the ref, the
				// key should arguably be "cite_error_ref_duplicate_key"
				$errs[] = [
					'key' => 'cite_error_references_duplicate_key',
					'params' => [ $refDmw->attrs->name ]
				];
				$refDmw->body = (object)[ 'html' => $html ];
			} else {
				$refDmw->body = (object)[ 'id' => 'mw-reference-text-' . $ref->target ];
			}
		}

		$class = 'mw-ref reference';
		if ( $validFollow ) {
			$class .= ' mw-ref-follow';
		}

		$lastLinkback = $ref->linkbacks[count( $ref->linkbacks ) - 1] ?? null;
		DOMUtils::addAttributes( $linkBack, [
				'about' => $about,
				'class' => $class,
				'id' => ( $refsData->inEmbeddedContent() || $validFollow ) ?
					null : ( $ref->name ? $lastLinkback : $ref->id ),
				'rel' => 'dc:references',
				'typeof' => DOMCompat::getAttribute( $node, 'typeof' ),
			]
		);
		DOMUtils::removeTypeOf( $linkBack, 'mw:DOMFragment/sealed/ref' );
		DOMUtils::addTypeOf( $linkBack, 'mw:Extension/ref' );

		$dataParsoid = new DataParsoid;
		if ( isset( $nodeDp->src ) ) {
			$dataParsoid->src = $nodeDp->src;
		}
		if ( isset( $nodeDp->dsr ) ) {
			$dataParsoid->dsr = $nodeDp->dsr;
		}
		if ( isset( $nodeDp->pi ) ) {
			$dataParsoid->pi = $nodeDp->pi;
		}
		DOMDataUtils::setDataParsoid( $linkBack, $dataParsoid );

		$dmw = $isTplWrapper ? $tplDmw : $refDmw;
		DOMDataUtils::setDataMw( $linkBack, $dmw );

		// FIXME(T214241): Should the errors be added to data-mw if
		// $isTplWrapper?  Here and other calls to addErrorsToNode.
		if ( count( $errs ) > 0 ) {
			self::addErrorsToNode( $linkBack, $errs );
		}

		// refLink is the link to the citation
		$refLink = $doc->createElement( 'a' );
		DOMUtils::addAttributes( $refLink, [
			'href' => $extApi->getPageUri() . '#' . $ref->target,
			'style' => 'counter-reset: mw-Ref ' . $ref->groupIndex . ';',
		] );
		if ( $ref->group ) {
			$refLink->setAttribute( 'data-mw-group', $ref->group );
		}

		// refLink-span which will contain a default rendering of the cite link
		// for browsers that don't support counters
		$refLinkSpan = $doc->createElement( 'span' );
		$refLinkSpan->setAttribute( 'class', 'mw-reflink-text' );
		$refLinkSpan->appendChild( $doc->createTextNode(
			'[' . ( $ref->group ? $ref->group . ' ' : '' ) . $ref->groupIndex . ']'
		) );

		$refLink->appendChild( $refLinkSpan );
		$linkBack->appendChild( $refLink );

		// Checking if the <ref> is nested in a link
		$aParent = DOMUtils::findAncestorOfName( $node, 'a' );
		if ( $aParent !== null ) {
			// If we find a parent link, we hoist the reference up, just after the link
			// But if there's multiple references in a single link, we want to insert in order -
			// so we look for other misnested references before inserting
			$insertionPoint = $aParent->nextSibling;
			while ( $insertionPoint instanceof Element &&
				DOMCompat::nodeName( $insertionPoint ) === 'sup' &&
				!empty( DOMDataUtils::getDataParsoid( $insertionPoint )->misnested )
			) {
				$insertionPoint = $insertionPoint->nextSibling;
			}
			$aParent->parentNode->insertBefore( $linkBack, $insertionPoint );
			// set misnested to true and DSR to zero-sized to avoid round-tripping issues
			$dsrOffset = DOMDataUtils::getDataParsoid( $aParent )->dsr->end ?? null;
			// we created that node hierarchy above, so we know that it only contains these nodes,
			// hence there's no need for a visitor
			self::setMisnested( $linkBack, $dsrOffset );
			self::setMisnested( $refLink, $dsrOffset );
			self::setMisnested( $refLinkSpan, $dsrOffset );
			$parentAbout = DOMCompat::getAttribute( $aParent, 'about' );
			if ( $parentAbout !== null ) {
				$linkBack->setAttribute( 'about', $parentAbout );
			}
			$node->parentNode->removeChild( $node );
		} else {
			// if not, we insert it where we planned in the first place
			$node->parentNode->replaceChild( $linkBack, $node );
		}

		// Keep the first content to compare multiple <ref>s with the same name.
		if ( $ref->contentId === null && !$missingContent ) {
			$ref->contentId = $contentId;
			$ref->dir = $refDir;
		} else {
			DOMCompat::remove( $c );
			$extApi->clearContentDOM( $contentId );
		}
	}

	/**
	 * Sets a node as misnested and its DSR as zero-width.
	 */
	private static function setMisnested( Element $node, ?int $offset ) {
		$dataParsoid = DOMDataUtils::getDataParsoid( $node );
		$dataParsoid->misnested = true;
		$dataParsoid->dsr = new DomSourceRange( $offset, $offset, null, null );
	}

	private static function addErrorsToNode( Element $node, array $errs ): void {
		DOMUtils::addTypeOf( $node, 'mw:Error' );
		$dmw = DOMDataUtils::getDataMw( $node );
		$dmw->errors = is_array( $dmw->errors ?? null ) ?
			array_merge( $dmw->errors, $errs ) : $errs;
	}

	private static function insertReferencesIntoDOM(
		ParsoidExtensionAPI $extApi, Element $refsNode,
		ReferencesData $refsData, bool $autoGenerated = false
	): void {
		$isTplWrapper = DOMUtils::hasTypeOf( $refsNode, 'mw:Transclusion' );
		$dp = DOMDataUtils::getDataParsoid( $refsNode );
		$group = $dp->group ?? '';
		$refGroup = $refsData->getRefGroup( $group );

		// Iterate through the ref list to back-patch typeof and data-mw error
		// information into ref for errors only known at time of references
		// insertion.  Refs in the top level dom will be processed immediately,
		// whereas embedded refs will be gathered for batch processing, since
		// we need to parse embedded content to find them.
		if ( $refGroup ) {
			$autoGeneratedWithGroup = ( $autoGenerated && $group !== '' );
			foreach ( $refGroup->refs as $ref ) {
				$errs = [];
				// Mark all refs that are part of a group that is autogenerated
				if ( $autoGeneratedWithGroup ) {
					$errs[] = [ 'key' => 'cite_error_group_refs_without_references',
						'params' => [ $group ] ];
				}
				// Mark all refs that are named without content
				if ( ( $ref->name !== '' ) && $ref->contentId === null ) {
					// TODO: Since this error is being placed on the ref,
					// the key should arguably be "cite_error_ref_no_text"
					$errs[] = [ 'key' => 'cite_error_references_no_text' ];
				}
				if ( count( $errs ) > 0 ) {
					foreach ( $ref->nodes as $node ) {
						self::addErrorsToNode( $node, $errs );
					}
					foreach ( $ref->embeddedNodes as $about ) {
						$refsData->embeddedErrors[$about] = $errs;
					}
				}
			}
		}

		// Note that `$sup`s here are probably all we really need to check for
		// errors caught with `$refsData->inReferencesContent()` but it's
		// probably easier to just know that state while they're being
		// constructed.
		$nestedRefsHTML = array_map(
			static function ( Element $sup ) use ( $extApi ) {
				return $extApi->domToHtml( $sup, false, true ) . "\n";
			},
			PHPUtils::iterable_to_array( DOMCompat::querySelectorAll(
				$refsNode, 'sup[typeof~=\'mw:Extension/ref\']'
			) )
		);

		if ( !$isTplWrapper ) {
			$dataMw = DOMDataUtils::getDataMw( $refsNode );
			// Mark this auto-generated so that we can skip this during
			// html -> wt and so that clients can strip it if necessary.
			if ( $autoGenerated ) {
				$dataMw->autoGenerated = true;
			} elseif ( count( $nestedRefsHTML ) > 0 ) {
				$dataMw->body = (object)[ 'html' => "\n" . implode( $nestedRefsHTML ) ];
			} elseif ( empty( $dp->selfClose ) ) {
				$dataMw->body = (object)[ 'html' => '' ];
			} else {
				unset( $dataMw->body );
			}
			unset( $dp->selfClose );
		}

		// Deal with responsive wrapper
		if ( DOMUtils::hasClass( $refsNode, 'mw-references-wrap' ) ) {
			// NOTE: The default Cite implementation hardcodes this threshold to 10.
			// We use a configurable parameter here primarily for test coverage purposes.
			// See citeParserTests.txt where we set a threshold of 1 or 2.
			$rrThreshold = $extApi->getSiteConfig()->getMWConfigValue( 'CiteResponsiveReferencesThreshold' ) ?? 10;
			if ( $refGroup && count( $refGroup->refs ) > $rrThreshold ) {
				DOMCompat::getClassList( $refsNode )->add( 'mw-references-columns' );
			}
			$refsNode = $refsNode->firstChild;
		}

		// Remove all children from the references node
		//
		// Ex: When {{Reflist}} is reused from the cache, it comes with
		// a bunch of references as well. We have to remove all those cached
		// references before generating fresh references.
		DOMCompat::replaceChildren( $refsNode );

		if ( $refGroup ) {
			foreach ( $refGroup->refs as $ref ) {
				$refGroup->renderLine( $extApi, $refsNode, $ref );
			}
		}

		// Remove the group from refsData
		$refsData->removeRefGroup( $group );
	}

	/**
	 * Process `<ref>`s left behind after the DOM is fully processed.
	 * We process them as if there was an implicit `<references />` tag at
	 * the end of the DOM.
	 *
	 * @param ParsoidExtensionAPI $extApi
	 * @param ReferencesData $refsData
	 * @param Node $node
	 */
	public static function insertMissingReferencesIntoDOM(
		ParsoidExtensionAPI $extApi, ReferencesData $refsData, Node $node
	): void {
		$doc = $node->ownerDocument;
		foreach ( $refsData->getRefGroups() as $groupName => $refsGroup ) {
			$domFragment = $doc->createDocumentFragment();
			$frag = self::createReferences(
				$extApi,
				$domFragment,
				[
					// Force string cast here since in the foreach above, $groupName
					// is an array key. In that context, number-like strings are
					// silently converted to a numeric value!
					// Ex: In <ref group="2" />, the "2" becomes 2 in the foreach
					'group' => (string)$groupName,
					'responsive' => null,
				],
				static function ( $dp ) use ( $extApi ) {
					// The new references come out of "nowhere", so to make selser work
					// properly, add a zero-sized DSR pointing to the end of the document.
					$content = $extApi->getPageConfig()->getRevisionContent()->getContent( 'main' );
					$contentLength = strlen( $content );
					$dp->dsr = new DomSourceRange( $contentLength, $contentLength, 0, 0 );
				},
				true
			);

			// Add a \n before the <ol> so that when serialized to wikitext,
			// each <references /> tag appears on its own line.
			$node->appendChild( $doc->createTextNode( "\n" ) );
			$node->appendChild( $frag );

			self::insertReferencesIntoDOM( $extApi, $frag, $refsData, true );
		}
	}

	private static function processEmbeddedRefs(
		ParsoidExtensionAPI $extApi, ReferencesData $refsData, string $str
	): string {
		$domFragment = $extApi->htmlToDom( $str );
		self::processRefs( $extApi, $refsData, $domFragment );
		return $extApi->domToHtml( $domFragment, true, true );
	}

	public static function processRefs(
		ParsoidExtensionAPI $extApi, ReferencesData $refsData, Node $node
	): void {
		$child = $node->firstChild;
		while ( $child !== null ) {
			$nextChild = $child->nextSibling;
			if ( $child instanceof Element ) {
				if ( WTUtils::isSealedFragmentOfType( $child, 'ref' ) ) {
					self::extractRefFromNode( $extApi, $child, $refsData );
				} elseif ( DOMUtils::hasTypeOf( $child, 'mw:Extension/references' ) ) {
					if ( !$refsData->inReferencesContent() ) {
						$refsData->referencesGroup =
							DOMDataUtils::getDataParsoid( $child )->group ?? '';
					}
					$refsData->pushEmbeddedContentFlag( 'references' );
					if ( $child->hasChildNodes() ) {
						self::processRefs( $extApi, $refsData, $child );
					}
					$refsData->popEmbeddedContentFlag();
					if ( !$refsData->inReferencesContent() ) {
						$refsData->referencesGroup = '';
						self::insertReferencesIntoDOM( $extApi, $child, $refsData, false );
					}
				} else {
					$refsData->pushEmbeddedContentFlag();
					// Look for <ref>s embedded in data attributes
					$extApi->processAttributeEmbeddedHTML( $child,
						function ( string $html ) use ( $extApi, $refsData ) {
							return self::processEmbeddedRefs( $extApi, $refsData, $html );
						}
					);
					$refsData->popEmbeddedContentFlag();
					if ( $child->hasChildNodes() ) {
						self::processRefs( $extApi, $refsData, $child );
					}
				}
			}
			$child = $nextChild;
		}
	}

	/**
	 * Traverse into all the embedded content and mark up the refs in there
	 * that have errors that weren't known before the content was serialized.
	 *
	 * Some errors are only known at the time when we're inserting the
	 * references lists, at which point, embedded content has already been
	 * serialized and stored, so we no longer have live access to it.  We
	 * therefore map about ids to errors for a ref at that time, and then do
	 * one final walk of the dom to peak into all the embedded content and
	 * mark up the errors where necessary.
	 *
	 * @param ParsoidExtensionAPI $extApi
	 * @param ReferencesData $refsData
	 * @param Node $node
	 */
	public static function addEmbeddedErrors(
		ParsoidExtensionAPI $extApi, ReferencesData $refsData, Node $node
	): void {
		$processEmbeddedErrors = function ( string $html ) use ( $extApi, $refsData ) {
			// Similar to processEmbeddedRefs
			$domFragment = $extApi->htmlToDom( $html );
			self::addEmbeddedErrors( $extApi, $refsData, $domFragment );
			return $extApi->domToHtml( $domFragment, true, true );
		};
		$processBodyHtml = static function ( Element $n ) use ( $processEmbeddedErrors ) {
			$dataMw = DOMDataUtils::getDataMw( $n );
			if ( isset( $dataMw->body->html ) ) {
				$dataMw->body->html = $processEmbeddedErrors(
					$dataMw->body->html
				);
			}
		};
		$child = $node->firstChild;
		while ( $child !== null ) {
			$nextChild = $child->nextSibling;
			if ( $child instanceof Element ) {
				if ( DOMUtils::hasTypeOf( $child, 'mw:Extension/ref' ) ) {
					$processBodyHtml( $child );
					$about = DOMCompat::getAttribute( $child, 'about' );
					$errs = $refsData->embeddedErrors[$about] ?? null;
					if ( $errs ) {
						self::addErrorsToNode( $child, $errs );
					}
				} elseif ( DOMUtils::hasTypeOf( $child, 'mw:Extension/references' ) ) {
					$processBodyHtml( $child );
				} else {
					$extApi->processAttributeEmbeddedHTML(
						$child, $processEmbeddedErrors
					);
				}
				if ( $child->hasChildNodes() ) {
					self::addEmbeddedErrors( $extApi, $refsData, $child );
				}
			}
			$child = $nextChild;
		}
	}

	/** @inheritDoc */
	public function sourceToDom(
		ParsoidExtensionAPI $extApi, string $txt, array $extArgs
	): DocumentFragment {
		$domFragment = $extApi->extTagToDOM(
			$extArgs,
			$txt,
			[
				'parseOpts' => [ 'extTag' => 'references' ],
			]
		);

		$refsOpts = $extApi->extArgsToArray( $extArgs ) + [
			'group' => null,
			'responsive' => null,
		];

		// Detect invalid parameters on the references tag
		$knownAttributes = [ 'group', 'responsive' ];
		foreach ( $refsOpts as $key => $value ) {
			if ( !in_array( strtolower( (string)$key ), $knownAttributes, true ) ) {
				$extApi->pushError( 'cite_error_references_invalid_parameters' );
				break;
			}
		}

		$frag = self::createReferences(
			$extApi,
			$domFragment,
			$refsOpts,
			static function ( $dp ) use ( $extApi ) {
				$dp->src = $extApi->extTag->getSource();
				// Setting redundant info on fragment.
				// $docBody->firstChild info feels cumbersome to use downstream.
				if ( $extApi->extTag->isSelfClosed() ) {
					$dp->selfClose = true;
				}
			}
		);
		$domFragment->appendChild( $frag );
		return $domFragment;
	}

	/** @inheritDoc */
	public function processAttributeEmbeddedHTML(
		ParsoidExtensionAPI $extApi, Element $elt, Closure $proc
	): void {
		$dataMw = DOMDataUtils::getDataMw( $elt );
		if ( isset( $dataMw->body->html ) ) {
			$dataMw->body->html = $proc( $dataMw->body->html );
		}
	}

	/** @inheritDoc */
	public function domToWikitext(
		ParsoidExtensionAPI $extApi, Element $node, bool $wrapperUnmodified
	) {
		$dataMw = DOMDataUtils::getDataMw( $node );
		// Autogenerated references aren't considered erroneous (the extension to the legacy
		// parser also generates them) and are not suppressed when serializing because apparently
		// that's the behaviour Parsoid clients want.  However, autogenerated references *with
		// group attributes* are errors (the legacy extension doesn't generate them at all) and
		// are suppressed when serialized since we considered them an error while parsing and
		// don't want them to persist in the content.
		if ( !empty( $dataMw->autoGenerated ) && ( $dataMw->attrs->group ?? '' ) !== '' ) {
			return '';
		} else {
			$startTagSrc = $extApi->extStartTagToWikitext( $node );
			if ( empty( $dataMw->body ) ) {
				return $startTagSrc; // We self-closed this already.
			} else {
				if ( isset( $dataMw->body->html ) ) {
					$src = $extApi->htmlToWikitext(
						[ 'extName' => $dataMw->name ],
						$dataMw->body->html
					);
					return $startTagSrc . $src . '</' . $dataMw->name . '>';
				} else {
					$extApi->log( 'error',
						'References body unavailable for: ' . DOMCompat::getOuterHTML( $node )
					);
					return ''; // Drop it!
				}
			}
		}
	}

	/** @inheritDoc */
	public function lintHandler(
		ParsoidExtensionAPI $extApi, Element $refs, callable $defaultHandler
	): ?Node {
		$dataMw = DOMDataUtils::getDataMw( $refs );
		if ( isset( $dataMw->body->html ) ) {
			$fragment = $extApi->htmlToDom( $dataMw->body->html );
			$defaultHandler( $fragment );
		}
		return $refs->nextSibling;
	}

	/** @inheritDoc */
	public function diffHandler(
		ParsoidExtensionAPI $extApi, callable $domDiff, Element $origNode,
		Element $editedNode
	): bool {
		$origDataMw = DOMDataUtils::getDataMw( $origNode );
		$editedDataMw = DOMDataUtils::getDataMw( $editedNode );

		if ( isset( $origDataMw->body->html ) && isset( $editedDataMw->body->html ) ) {
			$origFragment = $extApi->htmlToDom(
				$origDataMw->body->html, $origNode->ownerDocument,
				[ 'markNew' => true ]
			);
			$editedFragment = $extApi->htmlToDom(
				$editedDataMw->body->html, $editedNode->ownerDocument,
				[ 'markNew' => true ]
			);
			return call_user_func( $domDiff, $origFragment, $editedFragment );
		}

		// FIXME: Similar to DOMDiff::subtreeDiffers, maybe $editNode should
		// be marked as inserted to avoid losing any edits, at the cost of
		// more normalization

		return false;
	}
}
