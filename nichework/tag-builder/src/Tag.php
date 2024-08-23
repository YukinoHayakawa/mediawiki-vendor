<?php
/**
 * Copyright (C) 2021  NicheWork, LLC
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Mark A. Hershberger  <mah@nichework.com>
 */
namespace NicheWork\MW;

use Html;
use MWException;
use Parser;
use ParserOutput;
use PPFrame;
use RequestContext;
use Title;

class Tag {
	protected static ?string $name = null;
	protected Parser $parser;
	protected PPFrame $frame;
	protected ParserOutput $out;
	/** @var string[] */
	protected $error = [];

	/**
	 * We can set global attributes here.
	 * @see https://developer.mozilla.org/en-US/docs/Web/HTML/Global_attributes
	 *
	 * @var array<string, string>
	 */
	private $globalAttrMap = [
		"style" => "handleStyle"
	];

	/**
	 * Set per-element attributes here.
	 *
	 * @var array<string, string>
	 */
	protected ?array $attrMap = [];

	/**
	 * The parsed value of any attributes.
	 *
	 * @var array<string, string>
	 */
	protected $attrValue = [];

	/**
	 * Defaults that will be applied when no value is given.
	 *
	 * @var array<string, string>
	 */
	protected $default = [];

	/**
	 * Attributes that *must* be present.
	 *
	 * @var array<int, string>
	 */
	protected ?array $mandatoryAttributes = [];

	/**
	 * Whatever is inside the tag.
	 *
	 * @var ?string
	 */
	protected $body = null;

	/**
	 * Any tag that we've registered we will find here.
	 *
	 * @var array<string, string>
	 */
	private static $registeredTags = [];

	/**
	 * Set up the tag handling.
	 *
	 * @param Parser $parser
	 */
	public static function register( Parser $parser ): void {
		$class = static::class;
		if ( static::$name === null ) {
			static::$name = $class;
			$sub = strrchr( $class, "\\");
			if ( $sub !== false ) {
				static::$name = substr( $sub, 1 );
			}
		}
		if ( static::$name !== null && static::$name !== false ) {
			$name = static::$name;

			if (
				isset( self::$registeredTags[$name] ) &&
				self::$registeredTags[$name] !== $class
			) {
				$class = self::$registeredTags[$name];
				throw new MWException(
					sprintf(
						'%s is already handled by %s ... Cannot re-declare %s!',
						$name, $class, $class
					)
				);
			}
			$parser->setHook( $name ?? "MakePhanHappy", [ $class, 'handler' ] );
			self::$registeredTags[$name] = $class;
			return;
		}
		throw new MWException( sprintf( 'Need to set static name property for %s!', $class ) );
	}

	/**
	 * Parser hook for the tag.
	 *
	 * @param ?string $text Raw, untrimmed wikitext content of the tag, if any
	 * @param array<string, string> $argv
	 * @param Parser $parser
	 * @param PPFrame $frame
	 *
	 * @return string HTML
	 */
	public static function handler(
		$text,
		array $argv,
		Parser $parser,
		PPFrame $frame
	): string {
		$tag = new static( $parser, $frame );
		foreach ( $argv as $key => $val ) {
			$tag->handleAttribute( $key, $val );
		}
		$tag->handleBody( $text );

		return $tag->asHtml();
	}

	/**
	 * Constructor for the tag handler
	 *
	 * @param Parser $parser
	 * @param PPFrame $frame
	 */
	protected function __construct( Parser $parser, PPFrame $frame ) {
		$this->parser = $parser;
		$this->frame = $frame;
		$this->out = $this->parser->getOutput();
	}

	/**
	 * Use the parser to get the title or, as a last resort, RequestContext.
	 */
	protected function getTitle(): Title {
		return $this->parser->getTitle() ?? RequestContext::getMain()->getTitle();
	}

	/**
	 * Return "true" if the argument is true
	 *
	 * @param string $name
	 * @param ?string $origValue
	 */
	protected function isTrue( $name, $origValue ): ?string {
		$value = strtolower( $origValue ?? "" );
		// Attribute with no value is considered true
		if ( $value === "" || $value === "yes" || $value === "true" || intval( $origValue ) > 0 ) {
			return "true";
		}
		return null;
	}

	/**
	 * Return "false" if the argument is false.  Not needed because the absence of a boolean
	 * attribute is considered the same as setting that attribute false.
	 *
	 * @param string $name
	 * @param ?string $origValue
	 */
	protected function isFalse( $name, $origValue ): ?string {
		$value = strtolower( $origValue );
		if ( $value === "no" || $value === "false" || $origValue === "0" ) {
			return "";
		}
		return null;
	}

	/**
	 * Return "true" if the argument is true, null otherwise.
	 *
	 * @param string $name
	 * @param ?string $origValue
	 * @return ?string
	 */
	protected function handleBool( string $name, ?string $origValue ): ?string {
		return $this->isTrue( $name, $origValue );
	}

	/**
	 * Return the string from the value.  Can be overridden if needed.
	 *
	 * @param string $name
	 * @param ?string $origValue
	 * @return ?string
	 */
	protected function handleString( string $name, ?string $origValue ): ?string {
		return $origValue;
	}

	/**
	 * Return a string representation of the integer, null otherwise
	 *
	 * @param string $name
	 * @param ?string $origValue
	 * @return ?string
	 */
	protected function handleInt( $name, $origValue ): string {
		$value = intval( $origValue );
		if ( $value !== 0 || $origValue === "0" ) {
			return strval ( $value );
		}
		throw new AttrException( "Invalid $name: $origValue" );
	}

	/**
	 * Read and set up the attributes
	 *
	 * @param string $name of the attribute
	 * @param string $value of the attribute
	 * @return void
	 */
	protected function handleAttribute( string $name, string $value ): void {
		$method = $this->getAttributeMethod( $name );
		if ( $method ) {
			$this->setAttribute( $method, $name, $value );
		} else {
			$this->handleMethodError( $method, $name, $value );
		}
	}

	/**
	 * Return the method for handling this attribute.  Global attribute methods are checked first.
	 */
	protected function getAttributeMethod( $name ): ?string {
		return $this->globalAttrMap[$name] ?? $this->attrMap[$name] ?? null;
	}

	/**
	 * Handle this invalid name/value pair
	 *
	 * @param ?string $method to handle the attribute
	 * @param string $name of the attribute
	 * @param string $value of the attribute
	 * @return void
	 */
	protected function handleMethodError( $method, $name, $value ): void {
		$err = wfMessage( "tag-attr-name-not-exist", $name )->parse();
		if ( $method !== null ) {
			$err = wfMessage( "tag-attr-name-method-not-exist", $name, $method )->parse();
		}

		$this->error[] = $err;
	}

	/**
	 * Call the specified method for the value and return the result.  Handle any error.
	 *
	 * @param string $method to handle the attribute
	 * @param string $name of the attribute
	 * @param string $value of the attribute
	 * @return void
	 */
	protected function setAttribute( $method, $name, $value ): void {
		$ret = null;
		try {
			$ret = $this->$method( $name, $value );
		} catch ( AttrException $e ) {
			$this->error[] = (string)$e->getMessage();
		}
		if ( $ret !== null ) {
			$this->attrValue[$name] = $ret;
		}
	}

	protected function handleStyle( string $name, ?string $value ): ?string {
		return $value;
	}

	/**
	 * Store the body for future use.
	 *
	 * @param ?string $text of body
	 */
	protected function handleBody( $text = null ): void {
		$this->body = $text;
	}

	/**
	 * Convenience function for throwing errors when we need to
	 *
	 * @param array<string,string|int> $parsed the result of parse_url()
	 * @param string $part a key into $parsed
	 * @return string the part requested
	 */
	protected function getPart( array $parsed, $part ): string {
		return strval( $parsed[$part] ?? "" );
	}

	/**
	 * Convenience function for throwing errors when we need to
	 *
	 * @param array<string,string|int> $parsed the result of parse_url()
	 * @param string $part a key into $parsed
	 * @param string $url the whole url, used for error messages
	 * @return string the part requested
	 */
	protected function getPartOrError(
		array $parsed,
		$part,
		$url
	): string {
		if ( !isset( $parsed[$part] ) ) {
			throw new AttrException( "Part missing: $part not found in $url" );
		}
		return $this->getPart( $parsed, $part );
	}

	/**
	 * Return this scheme if it is safe.  Otherwise, throw an error.
	 *
	 * @param string $scheme to check
	 * @return string
	 * @todo Make schemes config var
	 */
	protected function isSafeScheme( $scheme ): string {
		$validSchemes = [ "http", "https", "ftp" ];
		$inv = array_flip( $validSchemes );
		if ( !isset( $inv[$scheme] ) ) {
			throw new AttrException(
				"Invalid scheme. '$scheme' is not one of "
				. implode( ", ", $validSchemes )
			);
		}
		return $scheme;
	}

	/**
	 * Return this host/domain if it is considereds safe.  Always returns. If you need to check
	 * hosts in your urls, override this.
	 *
	 * @param string $host to check
	 * @return string
	 * @todo Make hosts config var
	 */
	protected function isSafeHost( $host ): string {
		return $host;
	}

	/**
	 * Clean up the URL.  Could whitelist hosts, types, and such here.
	 *
	 * @param ?string $url to clean
	 * @return ?string
	 */
	protected function handleUrl( $name, $url ): string {
		$ret = null;
		$parsed = null;
		if ( $url ) {
			$url = trim( $url, '"\'' );
			$parsed = parse_url( $url );
		}
		if ( $url && $parsed ) {
			$ret = $this->isSafeScheme(
				strval( $this->getPartOrError( $parsed, 'scheme', $url ) )
			) . '://';
			// Whitelist hosts here
			$ret .= $this->isSafeHost(
				strval( $this->getPartOrError( $parsed, 'host', $url ) )
			);
			if ( isset( $parsed['port'] ) ) {
				$ret .= ':' . $parsed['port'];
			}
			$ret .= $this->getPart( $parsed, 'path', $url );
			if ( isset( $parsed['query'] ) ) {
				$ret .= '?' . $parsed['query'];
			}
			if ( isset( $parsed['fragment'] ) ) {
				$ret .= '#' . $parsed['fragment'];
			}
		}
		return $ret;
	}

	/**
	 * Apply any defaults that are not specified yet.
	 *
	 * @param array<string,string> $attribute
	 */
	protected function applyDefaults( array $attribute ): array {
		$ret = [];
		foreach ( array_merge( $this->attrMap, $this->globalAttrMap ) as $key => $val ) {
			if ( isset( $this->default[$key] ) || isset( $attribute[$key] ) ) {
				$ret[$key] = isset( $attribute[$key] ) ? $attribute[$key] : $this->default[$key];
			}
		}
		return $ret;
	}

	/**
	 * Do not show tag if mandatory attributes are not present
	 */
	protected function hasMandatoryAttributes(): bool {
		return array_reduce(
			$this->mandatoryAttributes,
			/**
			 * @param bool $previous
			 * @param string $key
			 */
			function ( $previous, $key ) {
				if ( !isset( $this->attrValue[$key] ) ) {
					$this->error[] = sprintf( "No %s attribute given for %s", $key, $this::$name );
					return false;
				}
				return $previous;
			}, true
		);
	}

	/**
	 * Give us what you want the tag to do here.
	 */
	public function getHtml(): string {
		if ( is_string( static::$name ) ) {
			return Html::element(
				static::$name ?? "MakePhanHappy", $this->attrValue, $this->body ?? ""
			);
		}
		/** Should not happen, but, you know, in case it does... */
		return sprintf( '<!-- The $name property of %s is not a string! -->', static::class );
	}

	/**
	 * Return the state of this tag as an HTML string.  Do not override this! Override getHtml()
	 * instead!
	 *
	 * @return string
	 */
	final protected function asHtml(): string {
		$valid = $this->hasMandatoryAttributes();
		/** @param string $error */
		$ret = implode( "\n", array_map( function ( $error ) {
			return sprintf( "<!-- %s -->", str_replace( "-->", "--/>", $error ) );
		}, $this->error ) );
		if ( $valid ) {
			$this->attrValue = $this->applyDefaults( $this->attrValue );
			$ret .= $this->getHtml();
		}
		return $ret;
	}
}
