<?php

declare(strict_types=1);

namespace Ccd\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SeoHelpersTest extends TestCase {
	public function testTruncateShort(): void {
		$this->assertSame('Curto', ccd_seo_boost_truncate('Curto', 155));
	}

	public function testTruncateStripsTagsAndLimits(): void {
		$long = str_repeat('palavra ', 40);
		$out  = ccd_seo_boost_truncate('<p>' . $long . '</p>', 40);
		$this->assertLessThanOrEqual(42, mb_strlen($out));
		$this->assertStringEndsWith('…', $out);
		$this->assertStringNotContainsString('<p>', $out);
	}

	public function testSetContentImgAltInserts(): void {
		$html = '<p><img class="wp-image-99 size-full" src="/x.jpg" /></p>';
		$out  = ccd_seo_set_content_img_alt($html, 99, 'Alt CCD');
		$this->assertStringContainsString('alt="Alt CCD"', $out);
	}

	public function testSetContentImgAltReplaces(): void {
		$html = '<img class="wp-image-12" alt="velho" src="/x.jpg" />';
		$out  = ccd_seo_set_content_img_alt($html, 12, 'novo');
		$this->assertStringContainsString('alt="novo"', $out);
		$this->assertStringNotContainsString('alt="velho"', $out);
	}

	public function testHomeMetadescNotEmpty(): void {
		$this->assertNotSame('', ccd_seo_home_metadesc());
		$this->assertGreaterThan(40, strlen(ccd_seo_home_metadesc()));
	}
}
