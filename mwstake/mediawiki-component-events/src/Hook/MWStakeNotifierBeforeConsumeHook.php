<?php

namespace MWStake\MediaWiki\Component\Events\Hook;

use MWStake\MediaWiki\Component\Events\INotificationEventConsumer;
use MWStake\MediaWiki\Component\Events\INotificationEvent;

interface MWStakeNotifierBeforeConsumeHook {
	/**
	 * Fired just before a consumer is about to consume the event
	 *
	 * @param INotificationEventConsumer $consumer
	 * @param INotificationEvent &$event
	 * @param bool &$isInterested
	 */
	public function onMWStakeNotifierBeforeConsume(
		INotificationEventConsumer $consumer, INotificationEvent &$event, bool &$isInterested
	): void;
}