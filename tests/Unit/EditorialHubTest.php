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
		$this->assertStringContainsString('<nav', $html);
		$this->assertStringContainsString('hipoglicemia', $html);
	}

	public function testCategoryPillarItemsWorksForAnyTerm(): void {
		$term = new \WP_Term();
		$term->term_id = 1;
		$term->slug = 'categoria-nova-qualquer';
		$items = ccd_seo_editorial_category_pillar_items($term);
		$this->assertNotEmpty($items);
		$this->assertArrayHasKey('url', $items[0]);
		$this->assertArrayHasKey('title', $items[0]);
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

	public function testHubPillarsMustNotNestInsidePostList(): void {
		$bad = <<<'HTML'
<main id="page-content" class="content blog-page">
  <div class="post-list row">
    <nav class="ccd-hub-pillars" aria-label="Pilares recomendados"><p>Pilares</p></nav>
    <div class="post-list-item"></div>
  </div>
</main>
HTML;
		$good = <<<'HTML'
<main id="page-content" class="content blog-page">
  <nav class="ccd-hub-pillars" aria-label="Pilares recomendados"><p>Pilares</p></nav>
  <div class="post-list row">
    <div class="post-list-item"></div>
  </div>
</main>
HTML;
		$this->assertTrue(ccd_seo_editorial_hub_pillars_nested_in_post_list($bad));
		$this->assertFalse(ccd_seo_editorial_hub_pillars_nested_in_post_list($good));
		$this->assertFalse(ccd_seo_editorial_hub_pillars_nested_in_post_list(''));
		$this->assertFalse(
			ccd_seo_editorial_hub_pillars_nested_in_post_list('<main id="page-content"><div class="post-list"></div></main>')
		);
	}

	public function testThemeIndexPrintsHubPillarsBeforePostList(): void {
		$root = dirname(__DIR__, 2);
		foreach (array('empowerwp', 'mesmerize') as $theme) {
			$path = $root . '/wordpress/wp-content/themes/' . $theme . '/index.php';
			$this->assertFileExists($path, $theme . '/index.php');
			$src = (string) file_get_contents($path);
			$printPos = strpos($src, 'ccd_seo_editorial_print_hub_pillars()');
			if (!preg_match('/class=["\'][^"\']*\bpost-list\b/', $src, $m, PREG_OFFSET_CAPTURE)) {
				$this->fail($theme . ': markup class="…post-list" ausente');
			}
			$postListPos = (int) $m[0][1];
			$this->assertNotFalse($printPos, $theme . ': deve chamar ccd_seo_editorial_print_hub_pillars()');
			$this->assertLessThan(
				$postListPos,
				$printPos,
				$theme . ': pilares devem ser impressos antes de .post-list (masonry)'
			);
		}
	}

	public function testMuPluginDoesNotHookPillarsOnLoopStart(): void {
		$path = dirname(__DIR__, 2) . '/wordpress/wp-content/mu-plugins/ccd-seo-editorial.php';
		$src = (string) file_get_contents($path);
		$this->assertStringNotContainsString(
			"add_action( 'loop_start'",
			$src,
			'loop_start injeta pilares dentro de .post-list e o masonry cobre o nav'
		);
		$this->assertStringNotContainsString('print_hub_pillars_in_loop', $src);
		$this->assertTrue(function_exists('ccd_seo_editorial_print_hub_pillars'));
		$this->assertTrue(function_exists('ccd_seo_editorial_hub_pillars_nested_in_post_list'));
	}
}
