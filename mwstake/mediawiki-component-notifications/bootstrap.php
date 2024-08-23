<?php

if ( !defined( 'MEDIAWIKI' ) && !defined( 'MW_PHPUNIT_TEST' ) ) {
	return;
}

if ( defined( 'MWSTAKE_MEDIAWIKI_COMPONENT_NOTIFICATIONS_VERSION' ) ) {
	return;
}

define( 'MWSTAKE_MEDIAWIKI_COMPONENT_NOTIFICATIONS_VERSION', '2.0.2' );

MWStake\MediaWiki\ComponentLoader\Bootstrapper::getInstance()
->register( 'notifications', static function () {
	$GLOBALS['wgMessagesDirs']['MWStakeMediaWikiComponentNotifications'] = __DIR__ . '/i18n';

	$GLOBALS['mwsgNotificationsNotifierSpec'] = [
		'class' => \MWStake\MediaWiki\Component\Notifications\NullNotifier::class
	];

	$GLOBALS['wgServiceWiringFiles'][] = __DIR__ . '/includes/ServiceWiring.php';
} );
