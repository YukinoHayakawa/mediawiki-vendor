<?php

namespace MWStake\MediaWiki\Component\CommonUserInterface\Tests;

use MWStake\MediaWiki\Component\CommonUserInterface\LinkFormatter;
use PHPUnit\Framework\TestCase;

class LinkFormatterTest extends TestCase {

	/**
	 * @param bool|string $externalLinkTarget
	 * @param bool $noFollowLinks
	 * @param array $unformattedLinks
	 * @param array $expectedLinks
	 * @covers LinkFormatter::formatLinks
	 * @return void
	 * @dataProvider provideFormatLinksData
	 */
	public function testFormatLinks( $externalLinkTarget, $noFollowLinks,
		$unformattedLinks, $expectedLinks ) {
		$linkFormatter = new LinkFormatter( $externalLinkTarget, $noFollowLinks );
		$actualLinks = $linkFormatter->formatLinks( $unformattedLinks );

		$this->assertEquals( $expectedLinks, $actualLinks );
	}

	/**
	 *
	 * @return array
	 */
	public function provideFormatLinksData() {
		return [
			'external-link-1' => [
				'_blank',
				true,
				[
					'link-1' => [
						'text' => 'Link 1',
						'class' => 'link-1-class',
						'href' => 'https://test.com/test-me'
					]
				],
				[
					[
						'text' => 'Link 1',
						'class' => 'link-1-class',
						'href' => 'https://test.com/test-me',
						'target' => '_blank',
						'rel' => 'nofollow noreferrer noopener'
					]
				]
			],
			'inernal-link-1' => [
				'_blank',
				true,
				[
					'link-2' => [
						'text' => 'Link 2',
						'href' => '/test-me'
					]
				],
				[
					[
						'text' => 'Link 2',
						'href' => '/test-me',
						'rel' => 'nofollow'
					]
				]
			],
			'internal-link-2' => [
				'_blank',
				true,
				[
					'link-3' => [
						'text' => 'Link 3',
						'href' => '/test-me',
						'class' => [
							0 => 'mw-echo-notifications-badge',
							1 => 'mw-echo-notification-badge-nojs'
						]
					]
				],
				[
					[
						'text' => 'Link 3',
						'href' => '/test-me',
						'class' => 'mw-echo-notifications-badge mw-echo-notification-badge-nojs',
						'rel' => 'nofollow'
					]
				]
			]
		];
	}
}
