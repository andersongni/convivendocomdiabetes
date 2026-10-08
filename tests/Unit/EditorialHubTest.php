<?php

declare(strict_types=1);

namespace Ccd\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class EditorialHubTest extends TestCase {
	public function testHubPillarsHtmlAlwaysEmpty(): void {
		$this->assertSame('', ccd_seo_editorial_hub_pillars_html('categoria-inexistente-xyz'));
		$this->assertSame('', ccd_seo_editorial_hub_pillars_html('diabetes'));
		$this->assertSame('', ccd_seo_editorial_hub_pillars_html('receitas'));
	}

	public function testCategoryPillarItemsOnlyCuratedGuides(): void {
		$term = new \WP_Term();
		$term->term_id = 1;
		$term->slug = 'categoria-nova-qualquer';
		$this->assertSame(array(), ccd_seo_editorial_category_pillar_items($term));

		$diabetes = new \WP_Term();
		$diabetes->term_id = 1;
		$diabetes->slug = 'diabetes';
		$items = ccd_seo_editorial_category_pillar_items($diabetes);
		$this->assertNotEmpty($items);
		foreach ($items as $item) {
			$this->assertArrayHasKey('url', $item);
			$this->assertArrayHasKey('title', $item);
			$this->assertStringNotContainsString('Post recente da categoria', (string) $item['title']);
		}
	}

	public function testHubHeadingHelpersStillExist(): void {
		$this->assertSame('Antes das receitas', ccd_seo_editorial_hub_heading('receitas'));
		$this->assertSame('Guias para entender a condição', ccd_seo_editorial_hub_heading('diabetes'));
		$this->assertSame('Leitura recomendada', ccd_seo_editorial_hub_heading('categoria-sem-mapa'));
	}

	public function testHeroForbiddenMarkers(): void {
		$markers = ccd_seo_editorial_hero_forbidden_markers();
		$this->assertContains('ccd-hub-pillars', $markers);
		$this->assertContains('ccd-category-intro', $markers);
		$this->assertContains('Pilares para começar', $markers);
		$this->assertContains('Leitura recomendada', $markers);
		$this->assertContains('Antes das receitas', $markers);
	}

	public function testHeroChunkMustNotContainHubMarkers(): void {
		$hero = '<div class="header-wrapper"><div class="header"><h1 class="hero-title">Diabetes</h1></div><div class="header-separator"></div></div>';
		foreach (ccd_seo_editorial_hero_forbidden_markers() as $marker) {
			$this->assertStringNotContainsString($marker, $hero);
		}
	}

	public function testHubPillarsMustNotNestInsidePostList(): void {
		$bad = <<<'HTML'
<main id="page-content" class="content blog-page">
  <div class="post-list row">
    <nav class="ccd-hub-pillars" aria-label="Leitura recomendada"><p>Leitura</p></nav>
    <div class="post-list-item"></div>
  </div>
</main>
HTML;
		$good = <<<'HTML'
<main id="page-content" class="content blog-page">
  <div class="post-list row">
    <div class="post-list-item"></div>
  </div>
</main>
HTML;
		$this->assertTrue(ccd_seo_editorial_hub_pillars_nested_in_post_list($bad));
		$this->assertFalse(ccd_seo_editorial_hub_pillars_nested_in_post_list($good));
		$this->assertFalse(ccd_seo_editorial_hub_pillars_nested_in_post_list(''));
	}

	public function testThemeIndexDoesNotPrintHubPillars(): void {
		$root = dirname(__DIR__, 2);
		foreach (array('empowerwp', 'mesmerize') as $theme) {
			$path = $root . '/wordpress/wp-content/themes/' . $theme . '/index.php';
			$this->assertFileExists($path, $theme . '/index.php');
			$src = (string) file_get_contents($path);
			$this->assertStringNotContainsString(
				'ccd_seo_editorial_print_hub_pillars',
				$src,
				$theme . ': hub visual removido — não chamar no template'
			);
		}
	}

	public function testMuPluginDoesNotHookPillarsOnLoopStart(): void {
		$path = dirname(__DIR__, 2) . '/wordpress/wp-content/mu-plugins/ccd-seo-editorial.php';
		$src = (string) file_get_contents($path);
		$this->assertStringNotContainsString(
			"add_action( 'loop_start'",
			$src,
			'loop_start injeta hub dentro de .post-list'
		);
		$this->assertStringNotContainsString('print_hub_pillars_in_loop', $src);
		$this->assertTrue(function_exists('ccd_seo_editorial_print_hub_pillars'));
		$this->assertTrue(function_exists('ccd_seo_editorial_hub_pillars_nested_in_post_list'));
	}
}
