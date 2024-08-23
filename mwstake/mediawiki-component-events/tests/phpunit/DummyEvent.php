<?php

namespace MWStake\MediaWiki\Component\Events\Tests;

use DateTime;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserIdentity;
use Message;
use MWStake\MediaWiki\Component\Events\INotificationEvent;
use RawMessage;

class DummyEvent implements INotificationEvent {
	/** @var int */
	private $id = 0;

	/**
	 * @inheritDoc
	 */
	public function getKey(): string {
		return 'dummy-event';
	}

	/**
	 * @inheritDoc
	 */
	public function getAgent(): UserIdentity {
		return MediaWikiServices::getInstance()->getUserFactory()->newFromId( 1 );
	}

	/**
	 * @inheritDoc
	 */
	public function getTime(): DateTime {
		return new DateTime();
	}

	/**
	 * @inheritDoc
	 */
	public function getPresetSubscribers(): array {
		return [];
	}

	/**
	 * @inheritDoc
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * @inheritDoc
	 */
	public function setId( $id ) {
		$this->id = $id;
	}

	/**
	 * @inheritDoc
	 */
	public function getMessage(): Message {
		return new RawMessage( 'dummy' );
	}

	/**
	 * @inheritDoc
	 */
	public function getIcon(): string {
		return '';
	}

	/**
	 * @inheritDoc
	 */
	public function getLinks(): array {
		return [];
	}

	/**
	 * @inheritDoc
	 */
	public function getKeyMessage(): Message {
		return new RawMessage( 'Dummy event' );
	}

	/**
	 * @inheritDoc
	 */
	public function getGroupMessage( int $count ): Message {
		return $this->getMessage();
	}

	/**
	 * @inheritDoc
	 */
	public function hasPriorityOver(): array {
		return [
			'dummy-sub-event'
		];
	}
}
