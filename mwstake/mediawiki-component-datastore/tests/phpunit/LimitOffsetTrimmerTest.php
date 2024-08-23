<?php

namespace MWStake\MediaWiki\Component\DataStore\Tests;

use MWStake\MediaWiki\Component\DataStore\LimitOffsetTrimmer;

class LimitOffsetTrimmerTest extends \PHPUnit\Framework\TestCase {

	protected $testDataSets = [
		// Page 1
		'Zero',
		'One',
		'Two',
		'Three',
		'Four',

		// Page 2
		'Five',
		'Six',
		'Seven',
		'Eight',
		'Nine',

		// Page 3
		'Ten',
		'Eleven',
		'Twelve',
		'Thirteen'
	];

	/**
	 * @covers \MWStake\MediaWiki\Component\DataStore\LimitOffsetTrimmer::trim
	 */
	public function testNormalPage() {
		$trimmer = new LimitOffsetTrimmer( 5, 5 );
		$trimmedData = $trimmer->trim( $this->testDataSets );

		$this->assertEquals( count( $trimmedData ), 5 );
		$this->assertEquals( $trimmedData[0], 'Five' );
	}

	/**
	 * @covers \MWStake\MediaWiki\Component\DataStore\LimitOffsetTrimmer::trim
	 */
	public function testLastPage() {
		$trimmer = new LimitOffsetTrimmer( 5, 10 );
		$trimmedData = $trimmer->trim( $this->testDataSets );

		$this->assertEquals( count( $trimmedData ), 4 );
		$this->assertEquals( $trimmedData[0], 'Ten' );
	}
}
