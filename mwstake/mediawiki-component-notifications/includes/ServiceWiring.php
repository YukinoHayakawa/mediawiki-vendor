<?php

use MediaWiki\MediaWikiServices;
use MWStake\MediaWiki\Component\Notifications\INotifier;
use MWStake\MediaWiki\Component\Notifications\NullNotifier;

return [
	'MWStakeNotificationsNotifier' => static function ( MediaWikiServices $services ) {
		$spec = $GLOBALS['mwsgNotificationsNotifierSpec'];
		$instance = $services->getObjectFactory()->createObject( $spec );
		if ( !$instance instanceof INotifier ) {
			return new NullNotifier();
		}
		return $instance;
	},
];
