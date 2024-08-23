<?php

namespace MWStake\MediaWiki\Component\Notifications;

interface INotification {
	/**
	 * @return string
	 */
	public function getKey();

	/**
	 * @return array
	 */
	public function getParams();

	/**
	 * @return array
	 */
	public function getAudience();

	/**
	 * @return \User The user that initiated the notification
	 */
	public function getUser();

	/**
	 * Gets configuration for secondary links
	 * if any exist
	 *
	 * @return array
	 */
	public function getSecondaryLinks();

	/**
	 * Whether mail for this notification should
	 * be sent immediately regardless of user settings
	 *
	 * @return bool
	 */
	public function sendImmediateEmail();

	/**
	 * Whether job queue should be used
	 * to send this notification
	 *
	 * @return bool
	 */
	public function useJobQueue();

	/**
	 *
	 * @return \Title|null
	 */
	public function getTitle();
}
