<?php

namespace MWStake\MediaWiki\Component\Wikitext\Tests\Node;

use MWStake\MediaWiki\Component\Wikitext\Node\Header;
use PHPUnit\Framework\TestCase;

class HeaderTest extends TestCase {

	/**
	 * @param array $input
	 * @param array $mutate
	 * @param string $expected
	 * @dataProvider provideData
	 * @covers \MWStake\MediaWiki\Component\Wikitext\Node\Header::setHeaderText
	 * @covers \MWStake\MediaWiki\Component\Wikitext\Node\Header::setLevel
	 * @covers \MWStake\MediaWiki\Component\Wikitext\Node\Header::getCurrentData
	 */
	public function testPropertyMutation( $input, $mutate, $expected ) {
		$node = new Header( ...array_values( $input ) );

		foreach ( $mutate as $type => $value ) {
			switch ( $type ) {
				case 'level':
					$node->setLevel( $value );
					break;
				case 'text':
					$node->setHeaderText( $value );
					break;
			}
		}

		$this->assertSame( $expected, $node->getCurrentData() );
	}

	/**
	 * @return array[]
	 */
	public function provideData() {
		return [
			'h1' => [
				'input' => [
					'level' => 1,
					'text' => 'Foo',
					'wikitext' => "=Foo="
				],
				'mutate' => [
					'level' => 2,
					'text' => 'Bar'
				],
				'expected' => "==Bar=="
			],
			'h5' => [
				'input' => [
					'level' => 1,
					'text' => 'Dummy text',
					'wikitext' => "=====Dummy text====="
				],
				'mutate' => [
					'text' => 'Dummy'
				],
				'expected' => "=====Dummy====="
			]
		];
	}
}
