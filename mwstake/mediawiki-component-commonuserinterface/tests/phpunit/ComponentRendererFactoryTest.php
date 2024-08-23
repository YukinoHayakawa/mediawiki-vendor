<?php

namespace MWStake\MediaWiki\Component\CommonUserInterface\Tests;

use Exception;
use MWStake\MediaWiki\Component\CommonUserInterface\Component\Literal;
use MWStake\MediaWiki\Component\CommonUserInterface\ComponentRendererFactory;
use MWStake\MediaWiki\Component\CommonUserInterface\IButton;
use MWStake\MediaWiki\Component\CommonUserInterface\IComponentRenderer;
use MWStake\MediaWiki\Component\CommonUserInterface\IPanel;
use PHPUnit\Framework\TestCase;
use Wikimedia\ObjectFactory\ObjectFactory;

class ComponentRendererFactoryTest extends TestCase {

	/**
	 * @covers ComponentRendererFactory::getKey
	 *
	 * @return void
	 */
	public function testGetKey() {
		$componentRegistry = [
			'test1' => IPanel::class,
			'test2' => IButton::class
		];
		$mockObjectFactory = $this->createMock( ObjectFactory::class );
		$factory = new ComponentRendererFactory( [], $componentRegistry, '', $mockObjectFactory );

		$mockComponent1 = $this->createMock( IPanel::class );
		$key1 = $factory->getKey( $mockComponent1 );
		$this->assertEquals( 'test1', $key1 );

		$mockComponent2 = $this->createMock( IButton::class );
		$key2 = $factory->getKey( $mockComponent2 );
		$this->assertEquals( 'test2', $key2 );

		$this->expectException( Exception::class );
		$mockComponent3 = $this->createMock( Literal::class );
		$key2 = $factory->getKey( $mockComponent3 );
	}

	/**
	 * @covers ComponentRendererFactory::getRenderer
	 *
	 * @return void
	 */
	public function testGetRenderer() {
		$rendererRegistry = [
			'*' => [
				'key1' => 'GenericRenderer1',
				'key2' => 'GenericRenderer2'
			],
			'testType' => [
				'key1' => 'TestTypeRenderer1'
			]
		];

		$mockGenericRenderer1 = $this->createMock( IComponentRenderer::class );
		$mockGenericRenderer2 = $this->createMock( IComponentRenderer::class );
		$mockTestTypeRenderer1 = $this->createMock( IComponentRenderer::class );

		$mockObjectFactory = $this->createMock( ObjectFactory::class );
		$mockObjectFactory
			->expects( $this->any() )
			->method( 'createObject' )
			->willReturn( $this->returnValueMap( [
				[ [ 'class' => 'GenericRenderer1' ], [], $mockGenericRenderer1 ],
				[ [ 'class' => 'GenericRenderer2' ], [], $mockGenericRenderer2 ],
				[ [ 'class' => 'TestTypeRenderer1' ], [], $mockTestTypeRenderer1 ]
			] ) );
		$factory = new ComponentRendererFactory( $rendererRegistry, [], 'testType', $mockObjectFactory );
		$this->assertEquals( $mockTestTypeRenderer1, $factory->getRenderer( 'key1' ) );
		$this->assertEquals( $mockGenericRenderer2, $factory->getRenderer( 'key2' ) );

		$this->expectException( Exception::class );
		$factory->getRenderer( 'key3' );
	}
}
