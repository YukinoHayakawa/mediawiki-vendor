<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Utils;

use Closure;
use Wikimedia\Assert\Assert;
use Wikimedia\Assert\UnreachableException;
use Wikimedia\Parsoid\Config\Env;
use Wikimedia\Parsoid\Core\DomSourceRange;
use Wikimedia\Parsoid\DOM\Document;
use Wikimedia\Parsoid\DOM\DocumentFragment;
use Wikimedia\Parsoid\DOM\Element;
use Wikimedia\Parsoid\DOM\Node;
use Wikimedia\Parsoid\Ext\ParsoidExtensionAPI;
use Wikimedia\Parsoid\Wt2Html\XMLSerializer;

/**
 * These utilities are for processing content that's generated
 * by parsing source input (ex: wikitext)
 */
class ContentUtils {

	/**
	 * XML Serializer.
	 *
	 * @param Node $node
	 * @param array $options XMLSerializer options.
	 * @return string
	 */
	public static function toXML( Node $node, array $options = [] ): string {
		return XMLSerializer::serialize( $node, $options )['html'];
	}

	/**
	 * dataobject aware XML serializer, to be used in the DOM post-processing phase.
	 *
	 * @param Node $node
	 * @param array $options
	 * @return string
	 */
	public static function ppToXML( Node $node, array $options = [] ): string {
		DOMDataUtils::visitAndStoreDataAttribs( $node, $options );
		return self::toXML( $node, $options );
	}

	/**
	 * XXX: Don't use this outside of testing.  It shouldn't be necessary
	 * to create new documents when parsing or serializing.  A document lives
	 * on the environment which can be used to create fragments.  The bag added
	 * as a dynamic property to the PHP wrapper around the libxml doc
	 * is at risk of being GC-ed.
	 *
	 * @param string $html
	 * @param bool $validateXMLNames
	 * @return Document
	 */
	public static function createDocument(
		string $html = '', bool $validateXMLNames = false
	): Document {
		$doc = DOMUtils::parseHTML( $html, $validateXMLNames );
		DOMDataUtils::prepareDoc( $doc );
		return $doc;
	}

	/**
	 * XXX: Don't use this outside of testing.  It shouldn't be necessary
	 * to create new documents when parsing or serializing.  A document lives
	 * on the environment which can be used to create fragments.  The bag added
	 * as a dynamic property to the PHP wrapper around the libxml doc
	 * is at risk of being GC-ed.
	 *
	 * @param string $html
	 * @param array $options
	 * @return Document
	 */
	public static function createAndLoadDocument(
		string $html, array $options = []
	): Document {
		$doc = self::createDocument( $html );
		DOMDataUtils::visitAndLoadDataAttribs(
			DOMCompat::getBody( $doc ), $options
		);
		return $doc;
	}

	/**
	 * @param Document $doc
	 * @param string $html
	 * @param array $options
	 * @return DocumentFragment
	 */
	public static function createAndLoadDocumentFragment(
		Document $doc, string $html, array $options = []
	): DocumentFragment {
		$domFragment = $doc->createDocumentFragment();
		DOMUtils::setFragmentInnerHTML( $domFragment, $html );
		DOMDataUtils::visitAndLoadDataAttribs( $domFragment, $options );
		return $domFragment;
	}

	/**
	 * Pull the data-parsoid script element out of the doc before serializing.
	 *
	 * @param Node $node
	 * @param array $options XMLSerializer options.
	 * @return array
	 */
	public static function extractDpAndSerialize( Node $node, array $options = [] ): array {
		$doc = DOMUtils::isBody( $node ) ? $node->ownerDocument : $node;
		$pb = DOMDataUtils::extractPageBundle( $doc );
		$out = XMLSerializer::serialize( $node, $options );
		$out['pb'] = $pb;
		return $out;
	}

	/**
	 * Strip Parsoid-inserted section wrappers, annotation wrappers, and synthetic nodes
	 * (fallback id spans with HTML4 ids for headings, auto-generated TOC metas
	 * and possibly other such in the future) from the DOM.
	 *
	 * @param Element $node
	 */
	public static function stripUnnecessaryWrappersAndSyntheticNodes( Element $node ): void {
		$n = $node->firstChild;
		while ( $n ) {
			$next = $n->nextSibling;
			if ( $n instanceof Element ) {
				if ( DOMCompat::nodeName( $n ) === 'meta' &&
					( DOMDataUtils::getDataMw( $n )->autoGenerated ?? false )
				) {
					// Strip auto-generated synthetic meta tags
					$n->parentNode->removeChild( $n );
				} elseif ( WTUtils::isFallbackIdSpan( $n ) ) {
					// Strip <span typeof='mw:FallbackId' ...></span>
					$n->parentNode->removeChild( $n );
				} else {
					// Recurse into subtree before stripping this
					self::stripUnnecessaryWrappersAndSyntheticNodes( $n );

					// Strip <section> tags and synthetic extended-annotation-region wrappers
					if ( WTUtils::isParsoidSectionTag( $n ) ||
						DOMUtils::hasTypeOf( $n, 'mw:ExtendedAnnRange' ) ) {
						DOMUtils::migrateChildren( $n, $n->parentNode, $n );
						$n->parentNode->removeChild( $n );
					}
				}
			}
			$n = $next;
		}
	}

	/**
	 * Extensions might be interested in examining their content embedded
	 * in data-mw attributes that don't otherwise show up in the DOM.
	 *
	 * Ex: inline media captions that aren't rendered, language variant markup,
	 *     attributes that are transcluded. More scenarios might be added later.
	 *
	 * @param ParsoidExtensionAPI $extAPI
	 * @param Element $elt The node whose data attributes need to be examined
	 * @param Closure $proc The processor that will process the embedded HTML
	 *        Signature: (string) -> string
	 *        This processor will be provided the HTML string as input
	 *        and is expected to return a possibly modified string.
	 */
	public static function processAttributeEmbeddedHTML(
		ParsoidExtensionAPI $extAPI, Element $elt, Closure $proc
	): void {
		if ( !$elt->hasAttribute( 'typeof' ) ) {
			return;
		}

		// Expanded attributes
		if ( DOMUtils::matchTypeOf( $elt, '/^mw:ExpandedAttrs$/' ) ) {
			$dmw = DOMDataUtils::getDataMw( $elt );
			if ( $dmw->attribs ?? null ) {
				foreach ( $dmw->attribs as &$a ) {
					foreach ( $a as $kOrV ) {
						if ( !is_string( $kOrV ) && isset( $kOrV->html ) ) {
							$kOrV->html = $proc( $kOrV->html );
						}
					}
				}
			}
		}

		// Language variant markup
		if ( DOMUtils::matchTypeOf( $elt, '/^mw:LanguageVariant$/' ) ) {
			$dmwv = DOMDataUtils::getJSONAttribute( $elt, 'data-mw-variant', null );
			if ( $dmwv ) {
				if ( isset( $dmwv->disabled ) ) {
					$dmwv->disabled->t = $proc( $dmwv->disabled->t );
				}
				if ( isset( $dmwv->twoway ) ) {
					foreach ( $dmwv->twoway as $l ) {
						$l->t = $proc( $l->t );
					}
				}
				if ( isset( $dmwv->oneway ) ) {
					foreach ( $dmwv->oneway as $l ) {
						$l->f = $proc( $l->f );
						$l->t = $proc( $l->t );
					}
				}
				if ( isset( $dmwv->filter ) ) {
					$dmwv->filter->t = $proc( $dmwv->filter->t );
				}
				DOMDataUtils::setJSONAttribute( $elt, 'data-mw-variant', $dmwv );
			}
		}

		// Inline media -- look inside the data-mw attribute
		if ( WTUtils::isInlineMedia( $elt ) ) {
			$dmw = DOMDataUtils::getDataMw( $elt );
			$caption = $dmw->caption ?? null;
			if ( $caption ) {
				$dmw->caption = $proc( $caption );
			}
		}

		// Process extension-specific embedded HTML
		$extTagName = WTUtils::getExtTagName( $elt );
		if ( $extTagName ) {
			$extConfig = $extAPI->getSiteConfig()->getExtTagConfig( $extTagName );
			if ( $extConfig['options']['wt2html']['embedsHTMLInAttributes'] ?? false ) {
				$tagHandler = $extAPI->getSiteConfig()->getExtTagImpl( $extTagName );
				$tagHandler->processAttributeEmbeddedHTML( $extAPI, $elt, $proc );
			}
		}
	}

	/**
	 * Shift the DOM Source Range (DSR) of a DOM fragment.
	 * @param Env $env
	 * @param Node $rootNode
	 * @param callable $dsrFunc
	 * @param ParsoidExtensionAPI $extAPI
	 * @return Node Returns the $rootNode passed in to allow chaining.
	 */
	public static function shiftDSR(
		Env $env, Node $rootNode, callable $dsrFunc, ParsoidExtensionAPI $extAPI
	): Node {
		$doc = $rootNode->ownerDocument;
		$convertString = static function ( $str ) {
			// Stub $convertString out to allow definition of a pair of
			// mutually-recursive functions.
			return $str;
		};
		$convertNode = static function ( Node $node ) use (
			$env, $extAPI, $dsrFunc, &$convertString, &$convertNode
		) {
			if ( !( $node instanceof Element ) ) {
				return;
			}
			$dp = DOMDataUtils::getDataParsoid( $node );
			if ( isset( $dp->dsr ) ) {
				$dp->dsr = $dsrFunc( clone $dp->dsr );
				// We don't need to setDataParsoid because dp is not a copy

				// This is a bit of a hack, but we use this function to
				// clear DSR properties as well.  See below as well.
				if ( $dp->dsr === null ) {
					unset( $dp->dsr );
				}
			}
			$tmp = $dp->getTemp();
			if ( isset( $tmp->origDSR ) ) {
				// Even though tmp shouldn't escape Parsoid, go ahead and
				// convert to enable hybrid testing.
				$tmp->origDSR = $dsrFunc( clone $tmp->origDSR );
				if ( $tmp->origDSR === null ) {
					unset( $tmp->origDSR );
				}
			}
			if ( isset( $dp->extTagOffsets ) ) {
				$dp->extTagOffsets = $dsrFunc( clone $dp->extTagOffsets );
				if ( $dp->extTagOffsets === null ) {
					unset( $dp->extTagOffsets );
				}
			}

			// Handle embedded HTML in attributes
			self::processAttributeEmbeddedHTML( $extAPI, $node, $convertString );

			// DOMFragments will have already been unpacked when DSR shifting is run
			if ( DOMUtils::hasTypeOf( $node, 'mw:DOMFragment' ) ) {
				throw new UnreachableException( "Shouldn't encounter these nodes here." );
			}

			// However, extensions can choose to handle sealed fragments whenever
			// they want and so may be returned in subpipelines which could
			// subsequently be shifted
			if ( DOMUtils::matchTypeOf( $node, '#^mw:DOMFragment/sealed/\w+$#D' ) ) {
				$dp = DOMDataUtils::getDataParsoid( $node );
				if ( $dp->html ?? null ) {
					$domFragment = $env->getDOMFragment( $dp->html );
					DOMPostOrder::traverse( $domFragment, $convertNode );
				}
			}
		};
		$convertString = function ( string $str ) use ( $doc, $env, $convertNode ): string {
			$node = self::createAndLoadDocumentFragment( $doc, $str );
			DOMPostOrder::traverse( $node, $convertNode );
			return self::ppToXML( $node, [ 'innerXML' => true ] );
		};
		DOMPostOrder::traverse( $rootNode, $convertNode );
		return $rootNode; // chainable
	}

	/**
	 * Convert DSR offsets in a Document between utf-8/ucs2/codepoint
	 * indices.
	 *
	 * Offset types are:
	 *  - 'byte': Bytes (UTF-8 encoding), e.g. PHP `substr()` or `strlen()`.
	 *  - 'char': Unicode code points (encoding irrelevant), e.g. PHP `mb_substr()` or `mb_strlen()`.
	 *  - 'ucs2': 16-bit code units (UTF-16 encoding), e.g. JavaScript `.substring()` or `.length`.
	 *
	 * @see TokenUtils::convertTokenOffsets for a related function on tokens.
	 *
	 * @param Env $env
	 * @param Document $doc The document to convert
	 * @param string $from Offset type to convert from.
	 * @param string $to Offset type to convert to.
	 */
	public static function convertOffsets(
		Env $env,
		Document $doc,
		string $from,
		string $to
	): void {
		$env->setCurrentOffsetType( $to );
		if ( $from === $to ) {
			return; // Hey, that was easy!
		}
		$offsetMap = [];
		$offsets = [];
		$collect = static function ( int $n ) use ( &$offsetMap, &$offsets ) {
			if ( !array_key_exists( $n, $offsetMap ) ) {
				$box = (object)[ 'value' => $n ];
				$offsetMap[$n] = $box;
				$offsets[] =& $box->value;
			}
		};
		// Collect DSR offsets throughout the document
		$collectDSR = static function ( DomSourceRange $dsr ) use ( $collect ) {
			if ( $dsr->start !== null ) {
				$collect( $dsr->start );
				$collect( $dsr->innerStart() );
			}
			if ( $dsr->end !== null ) {
				$collect( $dsr->innerEnd() );
				$collect( $dsr->end );
			}
			return $dsr;
		};
		$body = DOMCompat::getBody( $doc );
		$extAPI = new ParsoidExtensionAPI( $env );
		self::shiftDSR( $env, $body, $collectDSR, $extAPI );
		if ( count( $offsets ) === 0 ) {
			return; /* nothing to do (shouldn't really happen) */
		}
		// Now convert these offsets
		TokenUtils::convertOffsets(
			$env->topFrame->getSrcText(), $from, $to, $offsets
		);
		// Apply converted offsets
		$applyDSR = static function ( DomSourceRange $dsr ) use ( $offsetMap ) {
			$start = $dsr->start;
			$openWidth = $dsr->openWidth;
			if ( $start !== null ) {
				$start = $offsetMap[$start]->value;
				$openWidth = $offsetMap[$dsr->innerStart()]->value - $start;
			}
			$end = $dsr->end;
			$closeWidth = $dsr->closeWidth;
			if ( $end !== null ) {
				$end = $offsetMap[$end]->value;
				$closeWidth = $end - $offsetMap[$dsr->innerEnd()]->value;
			}
			return new DomSourceRange(
				$start, $end, $openWidth, $closeWidth
			);
		};
		self::shiftDSR( $env, $body, $applyDSR, $extAPI );
	}

	/**
	 * @param Node $node
	 * @param array $options
	 * @return string
	 */
	private static function dumpNode( Node $node, array $options ): string {
		return self::toXML( $node, $options + [ 'saveData' => true ] );
	}

	/**
	 * Dump the DOM with attributes.
	 *
	 * @param Node $rootNode
	 * @param string $title
	 * @param array $options Associative array of options:
	 *   - dumpFragmentMap: Dump the fragment map from env
	 *   - quiet: Suppress separators
	 *
	 * storeDataAttribs options:
	 *   - discardDataParsoid
	 *   - keepTmp
	 *   - storeInPageBundle
	 *   - storeDiffMark
	 *   - env
	 *   - idIndex
	 *
	 * XMLSerializer options:
	 *   - smartQuote
	 *   - innerXML
	 *   - captureOffsets
	 *   - addDoctype
	 * @return string The dump result
	 */
	public static function dumpDOM(
		Node $rootNode, string $title = '', array $options = []
	): string {
		if ( !empty( $options['dumpFragmentMap'] ) ) {
			Assert::invariant( isset( $options['env'] ), "env should be set" );
		}

		$buf = '';
		if ( empty( $options['quiet'] ) ) {
			$buf .= "----- {$title} -----\n";
		}
		$buf .= self::dumpNode( $rootNode, $options ) . "\n";

		// Dump cached fragments
		if ( !empty( $options['dumpFragmentMap'] ) ) {
			foreach ( $options['env']->getDOMFragmentMap() as $k => $fragment ) {
				$buf .= str_repeat( '=', 15 ) . "\n";
				$buf .= "FRAGMENT {$k}\n";
				$buf .= self::dumpNode(
					is_array( $fragment ) ? $fragment[0] : $fragment,
					$options
				) . "\n";
			}
		}

		if ( empty( $options['quiet'] ) ) {
			$buf .= str_repeat( '-', mb_strlen( $title ) + 12 ) . "\n";
		}
		return $buf;
	}

}
