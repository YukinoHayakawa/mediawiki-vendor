<?php

namespace MWStake\MediaWiki\Component\CommonUserInterface\Tests;

use MWStake\MediaWiki\Component\CommonUserInterface\DataAttributesBuilder;
use PHPUnit\Framework\TestCase;

class DataAttributesBuilderTest extends TestCase {

	/**
	 * @covers DataAttributesBuilder::build
	 *
	 * @return void
	 */
	public function testBuild() {
		$dataAttributesBuilder = new DataAttributesBuilder();

		$data = [
			'bs-toggle' => 'toggle-id',
			'mw' => 'some value',
			'evil' => '" mouseover="alert(\'Should not work\');"'
		];

		$dataAttributes = $dataAttributesBuilder->build( $data );
		$dataAttributesExpected = [
			'data-bs-toggle="toggle-id"',
			'data-mw="some value"',
			'data-evil="&quot; mouseover=&quot;alert(&#039;Should not work&#039;);&quot;"'
		];

		$this->assertEquals( $dataAttributesExpected, $dataAttributes );
	}
}
