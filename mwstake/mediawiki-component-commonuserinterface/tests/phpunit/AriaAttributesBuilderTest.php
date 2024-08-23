<?php

namespace MWStake\MediaWiki\Component\CommonUserInterface\Tests;

use MWStake\MediaWiki\Component\CommonUserInterface\AriaAttributesBuilder;
use PHPUnit\Framework\TestCase;

class AriaAttributesBuilderTest extends TestCase {

	/**
	 * @covers AriaAttributesBuilder::build
	 *
	 * @return void
	 */
	public function testBuild() {
		$ariaAttributesBuilder = new AriaAttributesBuilder();

		$aria = [
			'bs-toggle' => 'toggle-id',
			'mw' => 'some value',
			'evil' => '" mouseover="alert(\'Should not work\');"'
		];

		$ariaAttributes = $ariaAttributesBuilder->build( $aria );
		$ariaAttributesExpected = [
			'aria-bs-toggle="toggle-id"',
			'aria-mw="some value"',
			'aria-evil="&quot; mouseover=&quot;alert(&#039;Should not work&#039;);&quot;"'
		];

		$this->assertEquals( $ariaAttributesExpected, $ariaAttributes );
	}
}
