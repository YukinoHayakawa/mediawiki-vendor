<?php

namespace MWStake\MediaWiki\Component\CommonUserInterface\Tests\Renderer;

use MWStake\MediaWiki\Component\CommonUserInterface\Component\Badge;
use MWStake\MediaWiki\Component\CommonUserInterface\IComponent;
use MWStake\MediaWiki\Component\CommonUserInterface\IComponentRenderer;
use MWStake\MediaWiki\Component\CommonUserInterface\Renderer\Badge as BadgeRenderer;
use PHPUnit\Framework\TestCase;
use RawMessage;

class BadgeRendererTest extends TestCase {

	/**
	 * This is not the regular call of getHtml. This test should confirm DataAttributeBuilder
	 * output and AriaAttributeBuilder output together with the renderer method
	 * getRendererDataTreeNode.
	 *
	 * The html is easier to compare.
	 *
	 * @covers BatchRenderer::getHtml
	 *
	 * @return void
	 */
	public function testGetHtml() {
		$renderer = $this->getRenderer();
		$component = $this->getComponent();
		$subComponentNodes = $this->getSubComponentNodes();
		$templateData = $renderer->getRendererDataTreeNode( $component, $subComponentNodes );

		$html = $renderer->getHtml( $templateData );
		$$expectedHtml = $this->getExpectedHtml();
		$this->assertEquals( $$expectedHtml, $html );
	}

	/**
	 * @return IComponentRenderer
	 */
	private function getRenderer(): IComponentRenderer {
		return new BadgeRenderer();
	}

	/**
	 * @return IComponent
	 */
	private function getComponent(): IComponent {
		return new Badge( [
			'id' => 'simple-badge',
			'classes' => [ 'renderer-test', 'badge-renderer' ],
			'text' => new RawMessage( 'Badge renderer' ),
		] );
	}

	/**
	 * @return array
	 */
	private function getSubComponentNodes(): array {
		return [];
	}

	/**
	 * @return string
	 */
	private function getExpectedHtml(): string {
		$expectedHtml = file_get_contents( __DIR__ . '/results/badge.html' );
		if ( !$expectedHtml ) {
			$expectedHtml = 'NO RESULT FILE';
		}
		return $expectedHtml;
	}
}
