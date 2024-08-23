<?php

namespace MWStake\MediaWiki\Component\CommonUserInterface\Tests;

use MWStake\MediaWiki\Component\CommonUserInterface\HtmlIdRegistry;
use PHPUnit\Framework\TestCase;

class HtmlIdRegistryTest extends TestCase {
	/**
	 * @covers HtmlIdRegistry::makeHtmlIdSafe
	 *
	 * @return void
	 */
	public function testMakeHtmlIdSafe() {
		$htmlIdRegistry = HtmlIdRegistry::singleton();

		$saveHtmlId = $htmlIdRegistry->makeHtmlIdSafe( 'first-test-id' );
		$this->assertEquals( 'first-test-id', $saveHtmlId );

		$saveHtmlId = $htmlIdRegistry->makeHtmlIdSafe( 'second-test-id' );
		$this->assertEquals( 'second-test-id', $saveHtmlId );
		$saveHtmlId = $htmlIdRegistry->makeHtmlIdSafe( 'second-test-id' );
		$this->assertEquals( 'second-test-id-1', $saveHtmlId );
		$saveHtmlId = $htmlIdRegistry->makeHtmlIdSafe( 'second-test-id' );
		$this->assertEquals( 'second-test-id-2', $saveHtmlId );

		// set one id with suffix 1 first!
		$saveHtmlId = $htmlIdRegistry->makeHtmlIdSafe( '3rd-test-id-1' );
		$this->assertEquals( '3rd-test-id-1', $saveHtmlId );
		// set id wihtout suffix
		$saveHtmlId = $htmlIdRegistry->makeHtmlIdSafe( '3rd-test-id' );
		$this->assertEquals( '3rd-test-id', $saveHtmlId );
		$saveHtmlId = $htmlIdRegistry->makeHtmlIdSafe( '3rd-test-id' );
		$this->assertEquals( '3rd-test-id-2', $saveHtmlId );
		$saveHtmlId = $htmlIdRegistry->makeHtmlIdSafe( '3rd-test-id' );
		$this->assertEquals( '3rd-test-id-3', $saveHtmlId );
	}
}
