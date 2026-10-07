<?php

declare(strict_types=1);

namespace Ccd\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CategoryUrlsTest extends TestCase {
	protected function tearDown(): void {
		unset($_SERVER['REQUEST_URI']);
		parent::tearDown();
	}

	public function testRequestSlugSingleSegment(): void {
		$_SERVER['REQUEST_URI'] = '/diabetes/';
		$this->assertSame('diabetes', ccd_category_urls_request_slug());
	}

	public function testRequestSlugRejectsNested(): void {
		$_SERVER['REQUEST_URI'] = '/category/diabetes/';
		$this->assertSame('', ccd_category_urls_request_slug());
	}

	public function testRequestSlugEmpty(): void {
		$_SERVER['REQUEST_URI'] = '/';
		$this->assertSame('', ccd_category_urls_request_slug());
	}

	public function testBlockOldSlugWhenCategoryPath(): void {
		$_SERVER['REQUEST_URI'] = '/diabetes/';
		$this->assertSame('', ccd_category_urls_block_old_slug('https://example.com/diabetes-tipo-2-post/'));
	}

	public function testBlockOldSlugPassesThroughUnknown(): void {
		$_SERVER['REQUEST_URI'] = '/alguma-coisa/';
		$link = 'https://example.com/destino/';
		$this->assertSame($link, ccd_category_urls_block_old_slug($link));
	}

	public function testBlockGuess404WhenCategoryPath(): void {
		$_SERVER['REQUEST_URI'] = '/diabetes/';
		$this->assertFalse(ccd_category_urls_block_guess_404(null));
	}

	public function testBlockGuess404RespectsPrior(): void {
		$_SERVER['REQUEST_URI'] = '/diabetes/';
		$this->assertSame('keep', ccd_category_urls_block_guess_404('keep'));
	}
}
