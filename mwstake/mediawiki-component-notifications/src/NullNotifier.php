<?php

namespace MWStake\MediaWiki\Component\Notifications;

/**
 * Generic no-op notifier
 */
class NullNotifier implements INotifier {
	public function init() {
	}

	/**
	 *
	 * @param INotification $notification
	 * @return \Status
	 */
	public function notify( $notification ) {
		return \Status::newGood();
	}

	/**
	 * @param string $key
	 * @param array $params
	 */
	public function registerNotificationCategory( $key, $params = [] ) {
	}

	/**
	 * @param string $key
	 * @param array $params
	 */
	public function registerNotification( $key, $params ) {
	}

	/**
	 * @param string $key
	 * @return bool
	 */
	public function isNotificationRegistered( $key ) {
		return true;
	}

	/**
	 * @param string $key
	 */
	public function unRegisterNotification( $key ) {
	}

	/**
	 * @param string $key
	 * @param array $params
	 * @return NullNotification
	 */
	public function getNotificationObject( $key, $params ) {
		return new NullNotification( $key, $params );
	}

}
