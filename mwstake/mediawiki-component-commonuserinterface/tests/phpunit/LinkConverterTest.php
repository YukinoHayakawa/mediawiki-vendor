<?php

namespace MWStake\MediaWiki\Component\CommonUserInterface\Tests;

use MWStake\MediaWiki\Component\CommonUserInterface\LinkConverter;
use PHPUnit\Framework\TestCase;
use RawMessage;

/**
 * @group Broken
 */
class LinkConverterTest extends TestCase {

	/**
	 * @param array $linkDescs
	 * @covers LinkConverter::getLinksFromArrayDescriptor
	 * @dataProvider provideGetLinksFromArrayDescriptorTestData
	 * @return void
	 */
	public function testGetLinksFromArrayDescriptor( $linkDescs ) {
		$linkConverter = new LinkConverter();
		$links = $linkConverter->getLinkComponents( $linkDescs );

		$this->assertEquals( count( $linkDescs ), count( $links ) );

		foreach ( $links as $link ) {
			$link->getId();
		}
	}

	/**
	 *
	 * @return array
	 */
	public function provideGetLinksFromArrayDescriptorTestData() {
		return [
			'personal_tools' => [
				[
					'userpage' =>
					[
						'text' => 'WikiSysop',
						'href' => '/wiki/User:WikiSysop',
						'class' => false,
						'exists' => true,
						'active' => false,
						'dir' => 'auto',
					],
					'notifications-alert' =>
					[
						'href' => '/wiki/Special:Notifications',
						'text' => new RawMessage( 'echo-notification-alert' ),
						'active' => false,
						'class' =>
						[
							0 => 'mw-echo-notifications-badge',
							1 => 'mw-echo-notification-badge-nojs',
							2 => 'oo-ui-icon-bell',
							3 => 'mw-echo-notifications-badge-all-read',
						],
						'data' =>
						[
							'counter-num' => 0,
							'counter-text' => '0',
						],
					],
					'notifications-notice' =>
					[
						'href' => '/wiki/Special:Notifications',
						'text' => new RawMessage( 'echo-notification-notice' ),
						'active' => false,
						'class' =>
						[
							0 => 'mw-echo-notifications-badge',
							1 => 'mw-echo-notification-badge-nojs',
							2 => 'oo-ui-icon-tray',
						],
						'data' =>
						[
							'counter-num' => 1,
							'counter-text' => '1',
						],
					],
					'mytalk' =>
					[
						'text' => 'Talk',
						'href' => '/wiki/User_talk:WikiSysop',
						'class' => 'new',
						'exists' => false,
						'active' => false,
					],
					'preferences' =>
					[
						'text' => 'Preferences',
						'href' => '/wiki/Special:Preferences',
						'active' => false,
					],
					'watchlist' =>
					[
						'text' => 'Watchlist',
						'href' => '/wiki/Special:Watchlist',
						'active' => false,
					],
					'mycontris' =>
					[
						'text' => 'Contributions',
						'href' => '/wiki/Special:Contributions/WikiSysop',
						'active' => false,
					],
					'logout' =>
					[
						'text' => 'Log out',
						'data-mw' => 'interface',
						'href' => '/wiki/Special:UserLogout&returnto=Main+Page',
						'active' => false,
					]
				]
			],
			'content_navigation' => [
				'namespaces' =>
					[
						'main' =>
						[
							'class' => 'selected',
							'text' => 'Main page',
							'href' => '/wiki/Main_Page',
							'exists' => true,
							'primary' => true,
							'context' => 'subject',
							'id' => 'ca-nstab-main',
						],
						'talk' =>
						[
							'class' => 'new',
							'text' => 'Discussion',
							'href' => '/wiki/Talk:Main_Page&action=edit&redlink=1',
							'exists' => false,
							'primary' => true,
							'context' => 'talk',
							'rel' => 'discussion',
							'id' => 'ca-talk',
						],
					],
					'views' =>
					[
						'view' =>
						[
							'class' => 'selected',
							'text' => 'Read',
							'href' => '/wiki/Main_Page',
							'exists' => true,
							'primary' => true,
							'redundant' => true,
							'id' => 'ca-view',
						],
						'edit' =>
						[
							'class' => '',
							'text' => 'Edit',
							'href' => '/wiki/Main_Page&action=edit',
							'primary' => true,
							'id' => 'ca-edit',
						],
						'history' =>
						[
							'class' => false,
							'text' => 'History',
							'href' => '/wiki/Main_Page&action=history',
							'id' => 'ca-history',
						],
					],
					'actions' =>
					[
						'delete' =>
						[
							'class' => false,
							'text' => 'Delete',
							'href' => '/wiki/Main_Page&action=delete',
							'id' => 'ca-delete',
						],
						'move' =>
						[
							'class' => false,
							'text' => 'Move',
							'href' => '/wiki/Special:MovePage/Main_Page',
							'id' => 'ca-move',
						],
						'protect' =>
						[
							'class' => false,
							'text' => 'Protect',
							'href' => '/wiki/Main_Page&action=protect',
							'id' => 'ca-protect',
						],
						'watch' =>
						[
							'class' => 'mw-watchlink ',
							'text' => 'Watch',
							'href' => '/wiki/Main_Page&action=watch',
							'data' =>
							[
								'mw' => 'interface',
							],
							'id' => 'ca-watch',
						],
					]
			],
			'content_actions' => [
					[
						'nstab-main' =>
						[
							'class' => 'selected',
							'text' => 'Main page',
							'href' => '/wiki/Main_Page',
							'exists' => true,
							'primary' => true,
							'context' => 'subject',
							'id' => 'ca-nstab-main',
						],
						'talk' =>
						[
							'class' => 'new',
							'text' => 'Discussion',
							'href' => '/wiki/Talk:Main_Page&action=edit&redlink=1',
							'exists' => false,
							'primary' => true,
							'context' => 'talk',
							'rel' => 'discussion',
							'id' => 'ca-talk',
						],
						'edit' =>
						[
							'class' => '',
							'text' => 'Edit',
							'href' => '/wiki/Main_Page&action=edit',
							'primary' => true,
							'id' => 'ca-edit',
						],
						'history' =>
						[
							'class' => false,
							'text' => 'History',
							'href' => '/wiki/Main_Page&action=history',
							'id' => 'ca-history',
						],
						'delete' =>
						[
							'class' => false,
							'text' => 'Delete',
							'href' => '/wiki/Main_Page&action=delete',
							'id' => 'ca-delete',
						],
						'move' =>
						[
							'class' => false,
							'text' => 'Move',
							'href' => '/wiki/Special:MovePage/Main_Page',
							'id' => 'ca-move',
						],
						'protect' =>
						[
							'class' => false,
							'text' => 'Protect',
							'href' => '/wiki/Main_Page&action=protect',
							'id' => 'ca-protect',
						],
						'watch' =>
						[
							'class' => 'mw-watchlink ',
							'text' => 'Watch',
							'href' => '/wiki/Main_Page&action=watch',
							'data' =>
							[
								'mw' => 'interface',
							],
							'id' => 'ca-watch',
						],
					]
			]
		];
	}
}
