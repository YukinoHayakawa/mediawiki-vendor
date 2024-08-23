<?php

namespace MWStake\MediaWiki\Component\Wikitext\Tests\Node;

use MWStake\MediaWiki\Component\Wikitext\Node\Transclusion;
use PHPUnit\Framework\TestCase;

class TransclusionTest extends TestCase {

	/**
	 * @param array $input
	 * @param array $mutate
	 * @param string $expected
	 * @dataProvider provideData
	 * @covers \MWStake\MediaWiki\Component\Wikitext\Node\Transclusion::setParam
	 * @covers \MWStake\MediaWiki\Component\Wikitext\Node\Transclusion::getCurrentData
	 */
	public function testPropertyMutation( $input, $mutate, $expected, $allowNew = false ) {
		$node = new Transclusion( ...array_values( $input ) );

		foreach ( $mutate as $index => $value ) {
			$node->setParam( $index, $value, $allowNew );
		}

		$this->assertSame( $expected, $node->getCurrentData() );
	}

	public function provideData() {
		return [
			'unnamed-params' => [
				'input' => [
					'target' => 'DummyTemplate',
					'params' => [ 1 => 'param1', 2 => 'param2' ],
					'wikitext' => "{{DummyTemplate\n|param1|param2}}"
				],
				'mutate' => [
					2 => 'mutated'
				],
				'expected' => "{{DummyTemplate\n|param1|mutated}}"
			],
			'named-params' => [
				'input' => [
					'target' => 'DummyTemplate',
					'params' => [ 'param1' => 'value1', 'param2' => 'value2' ],
					'wikitext' => "{{DummyTemplate\n|param1=value1\n|param2=value2}}"
				],
				'mutate' => [
					'param1' => 'mutated'
				],
				'expected' => "{{DummyTemplate\n|param1=mutated\n|param2=value2}}"
			],
			'new-param-unnamed' => [
				'input' => [
					'target' => 'DummyTemplate',
					'params' => [ 1 => 'param1', 2 => 'param2' ],
					'wikitext' => "{{DummyTemplate\n|param1|param2}}"
				],
				'mutate' => [
					3 => 'dummy'
				],
				'expected' => "{{DummyTemplate\n|param1|param2|dummy}}",
				'allowNew' => true
			],
			'new-param-named' => [
				'input' => [
					'target' => 'DummyTemplate',
					'params' => [ 'param1' => 'value1', 'param2' => 'value2' ],
					'wikitext' => "{{DummyTemplate\n|param1=value1|param2=value2\n}}"
				],
				'mutate' => [
					'param3' => 'dummy'
				],
				'expected' => "{{DummyTemplate\n|param1=value1|param2=value2\n|param3=dummy}}",
				'allowNew' => true
			],
		];
	}
}
