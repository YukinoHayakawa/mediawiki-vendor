<?php

namespace MWStake\MediaWiki\Component\ManifestRegistry\Test;

use ExtensionRegistry;
use MWStake\MediaWiki\Component\ManifestRegistry\ManifestAttributeBasedRegistry;
use PHPUnit\Framework\TestCase;

/**
 * @covers ManifestAttributeBasedRegistry
 */
class ManifestAttributeBasedRegistryTest extends TestCase {

	/**
	 * @param string $attributeName
	 * @param array $dummyExtensionAttributes
	 * @param array $overrides
	 * @param array $expectedRegistry
	 * @param string $message
	 *
	 * @dataProvider provideGenericTestData
	 * @covers ManifestAttributeBasedRegistry::getAllKeys
	 */
	public function testGetAllKeys( $attributeName, $dummyExtensionAttributes, $overrides,
		$expectedRegistry, $message ) {
		$mockExtensionRegistry = $this->makeMockExtensionRegistry( $dummyExtensionAttributes );
		$registry = new ManifestAttributeBasedRegistry(
			$attributeName,
			$mockExtensionRegistry,
			$overrides
		);

		$expectedKeys = array_keys( $expectedRegistry );
		$actualKeys = $registry->getAllKeys();

		$this->assertEquals( $expectedKeys, $actualKeys, "$message: Keys should match" );
	}

	/**
	 * @param string $attributeName
	 * @param array $dummyExtensionAttributes
	 * @param array $overrides
	 * @param array $expectedRegistry
	 * @param string $message
	 *
	 * @dataProvider provideGenericTestData
	 * @covers ManifestAttributeBasedRegistry::getAllValues
	 */
	public function testGetAllValues( $attributeName, $dummyExtensionAttributes, $overrides,
		$expectedRegistry, $message ) {
		$mockExtensionRegistry = $this->makeMockExtensionRegistry( $dummyExtensionAttributes );
		$registry = new ManifestAttributeBasedRegistry(
			$attributeName,
			$mockExtensionRegistry,
			$overrides
		);

		$actualRegistry = $registry->getAllValues();

		$this->assertEquals( $expectedRegistry, $actualRegistry, "$message: Array should match" );
	}

	/**
	 * @param string $attributeName
	 * @param array $dummyExtensionAttributes
	 * @param array $overrides
	 * @param array $expectedRegistry
	 * @param string $message
	 *
	 * @dataProvider provideGenericTestData
	 * @covers ManifestAttributeBasedRegistry::getValue
	 */
	public function testGetKeys( $attributeName, $dummyExtensionAttributes, $overrides,
		$expectedRegistry, $message ) {
		$mockExtensionRegistry = $this->makeMockExtensionRegistry( $dummyExtensionAttributes );
		$registry = new ManifestAttributeBasedRegistry(
			$attributeName,
			$mockExtensionRegistry,
			$overrides
		);

		foreach ( $expectedRegistry as $regKey => $expectedValue ) {
			$actualValue = $registry->getValue( $regKey );
			$this->assertEquals( $expectedValue, $actualValue, "$message: Values should match" );
		}
	}

	/**
	 * @return array
	 */
	public function provideGenericTestData() {
		$dummyExtensionAttributes = [
			'BlueSpiceFoundationLessVarsRegistry' => [
				'@bs-color-primary' => 'BLUE',
				'@bs-color-secondary' => 'ORANGE',
				'@bs-color-tertiary' => 'RED'
			],
			'BlueSpiceFoundationRendererRegistry' => [
				"list" => "LIST_FACTORY_CALLBACK",
				"linklist" => "LINKLIST_FACTORY_CALLBACK",
				"userimage" => "USERIMAGE_FACTORY_CALLBACK"
			]
		];

		return [
			[
				'BlueSpiceFoundationLessVarsRegistry',
				$dummyExtensionAttributes,
				[],
				$dummyExtensionAttributes['BlueSpiceFoundationLessVarsRegistry'],
				'No overrides'
			],
			[
				'BlueSpiceFoundationLessVarsRegistry',
				$dummyExtensionAttributes,
				[
					'set' => [
						'@bs-color-primary' => 'GREEN',
					]
				],
				[
					'@bs-color-primary' => 'GREEN',
				],
				"Only `set` override"
			],
			[
				'BlueSpiceFoundationLessVarsRegistry',
				$dummyExtensionAttributes,
				[
					'merge' => [
						'@bs-color-primary' => 'GREEN',
					]
				],
				[
					'@bs-color-primary' => 'GREEN',
					'@bs-color-secondary' => 'ORANGE',
					'@bs-color-tertiary' => 'RED'
				],
				'Only `merge` ovrride'
			],
			[
				'BlueSpiceFoundationLessVarsRegistry',
				$dummyExtensionAttributes,
				[
					'remove' => [ '@bs-color-primary', '@bs-color-tertiary' ]
				],
				[
					'@bs-color-secondary' => 'ORANGE'
				],
				'Only `remove` override'
			],
			[
				'BlueSpiceFoundationLessVarsRegistry',
				$dummyExtensionAttributes,
				[
					'merge' => [
						'@bs-color-primary' => 'GREEN',
					],
					'remove' => [ '@bs-color-tertiary' ]
				],
				[
					'@bs-color-primary' => 'GREEN',
					'@bs-color-secondary' => 'ORANGE'
				],
				'`merge` and `remove` override'
			],
			[
				'BlueSpiceFoundationLessVarsRegistry',
				$dummyExtensionAttributes,
				[
					'set' => [
						'@bs-color-tertiary' => 'PURPLE',
					],
					'merge' => [
						'@bs-color-primary' => 'GREEN',
					],
					'remove' => [ '@bs-color-tertiary' ]
				],
				[
					'@bs-color-tertiary' => 'PURPLE'
				],
				'`set`, `merge` and `remove` override'
			]
		];
	}

	/**
	 *
	 * @param array $dummyExtensionAttributes
	 * @return ExtensionRegistry
	 */
	private function makeMockExtensionRegistry( $dummyExtensionAttributes ) {
		$mock = $this->createMock( ExtensionRegistry::class );

		$valueMap = [];
		foreach ( $dummyExtensionAttributes as $dummyAttrName => $dummyValues ) {
			$valueMap[] = [ $dummyAttrName,	$dummyValues ];
		}

		$mock
			->expects( $this->any() )
			->method( 'getAttribute' )
			->will( $this->returnCallback( function ( $attrName ) use ( $dummyExtensionAttributes ) {
				return $dummyExtensionAttributes[ $attrName ];
			} ) );

		return $mock;
	}

}
