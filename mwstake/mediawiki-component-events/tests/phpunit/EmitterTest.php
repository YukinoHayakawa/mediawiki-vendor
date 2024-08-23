<?php

namespace MWStake\MediaWiki\Component\Events\Tests;

use MWStake\MediaWiki\Component\Events\Notifier;
use PHPUnit\Framework\TestCase;

class EmitterTest extends TestCase {

	/**
	 * @dataProvider provideOverrideData
	 * @covers \MWStake\MediaWiki\Component\Events\Emitter::emit
	 * @covers \MWStake\MediaWiki\Component\Events\Emitter::__destruct
	 */
	public function testOverrides( $main, $sub, $expectedCalls ) {
		$consumer = $this->getMockBuilder( DummyConsumer::class )
			->onlyMethods( [ 'consume', 'isInterested' ] )
			->getMock();
		$consumer->expects( $this->exactly( $expectedCalls ) )
			->method( 'consume' );
		$consumer->expects( $this->exactly( $expectedCalls ) )
			->method( 'isInterested' )
			->willReturn( true );

		$emitter = new Notifier( [ $consumer ] );
		$emitter->emit( $main );
		$emitter->emit( $sub );
	}

	/**
	 * @return array[]
	 */
	public function provideOverrideData() {
		return [
			'different-events-same-topic' => [
				new DummyEvent(),
				new DummySubEvent(),
				1
			],
			'opposite-order' => [
				new DummySubEvent(),
				new DummyEvent(),
				1
			],
			'different-agent' => [
				new DummyEvent(),
				new DummySubEvent( 2 ),
				2
			]
		];
	}

	/**
	 * @covers \MWStake\MediaWiki\Component\Events\Emitter::emit
	 */
	public function testEmit() {
		$event = new DummyEvent();

		$consumer = $this->getMockBuilder( DummyConsumer::class )
			->onlyMethods( [ 'consume', 'isInterested' ] )
			->getMock();
		$consumer->expects( $this->once() )
			->method( 'consume' )
			->with( $event );
		$consumer->expects( $this->once() )
			->method( 'isInterested' )
			->with( $event )
			->willReturn( true );

		$emitter = new Notifier( [ $consumer ] );
		$emitter->emit( $event );
	}

	/**
	 * @covers \MWStake\MediaWiki\Component\Events\Emitter::emit
	 */
	public function testEmitNotInterested() {
		$event = new DummyEvent();

		$consumer = $this->getMockBuilder( DummyConsumer::class )
			->onlyMethods( [ 'consume', 'isInterested' ] )
			->getMock();
		$consumer->expects( $this->never() )
			->method( 'consume' );
		$consumer->expects( $this->once() )
			->method( 'isInterested' )
			->with( $event )
			->willReturn( false );

		$emitter = new Notifier( [ $consumer ] );
		$emitter->emit( $event );
	}

	/**
	 * @covers \MWStake\MediaWiki\Component\Events\Emitter::emit
	 */
	public function testEmitMultipleConsumers() {
		$event = new DummyEvent();

		$consumer1 = $this->getMockBuilder( DummyConsumer::class )
			->onlyMethods( [ 'consume', 'isInterested' ] )->getMock();
		$consumer1->expects( $this->once() )->method( 'consume' )->with( $event );
		$consumer1->expects( $this->once() )
			->method( 'isInterested' )
			->with( $event
			)->willReturn( true );

		$consumer2 = $this->getMockBuilder( DummyConsumer::class )
			->onlyMethods( [ 'consume', 'isInterested' ] )->getMock();
		$consumer2->expects( $this->once() )->method( 'consume' )->with( $event );
		$consumer2->expects( $this->once() )
			->method( 'isInterested' )
			->with( $event )
			->willReturn( true );

		$emitter = new Notifier( [ $consumer1, $consumer2 ] );
		$emitter->emit( $event );
	}
}
