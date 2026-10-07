<?php

declare(strict_types=1);

namespace Ccd\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class EditorialHubTest extends TestCase {
	public function testHubPillarsHtmlEmptyForUnknownCategory(): void {
		$this->assertSame('', ccd_seo_editorial_hub_pillars_html('categoria-inexistente-xyz'));
	}

	public function testHubPillarsHtmlContainsNavAndLinks(): void {
		$html = ccd_seo_editorial_hub_pillars_html('diabetes');
		$this->assertStringContainsString('ccd-hub-pillars', $html);
		$this->assertStringContainsString('Pilares para começar', $html);
		$this->assertStringContainsString('hipoglicemia', $html);
		$this->assertStringContainsString('<nav', $html);
	}

	public function testHeroForbiddenMarkers(): void {
		$markers = ccd_seo_editorial_hero_forbidden_markers();
		$this->assertContains('ccd-hub-pillars', $markers);
		$this->assertContains('ccd-category-intro', $markers);
		$this->assertContains('Pilares para começar', $markers);
	}

	public function testHeroChunkMustNotContainPillars(): void {
		$hero = '<div class="header-wrapper"><div class="header"><h1 class="hero-title">Diabetes</h1></div><div class="header-separator"></div></div>';
		$content = $hero . '<nav class="ccd-hub-pillars"><p>Pilares para começar</p></nav>';
		foreach (ccd_seo_editorial_hero_forbidden_markers() as $marker) {
			$this->assertStringNotContainsString($marker, $hero);
		}
		$this->assertStringContainsString('ccd-hub-pillars', $content);
	}
}
