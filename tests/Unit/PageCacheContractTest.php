<?php

declare(strict_types=1);

namespace Ccd\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Contrato: HTML com badge private nao envenena page cache;
 * visitante logado nunca recebe Cache-Control public/s-maxage.
 */
final class PageCacheContractTest extends TestCase {
	protected function setUp(): void {
		ccd_test_reset_state();
		$_GET    = array();
		$_SERVER = array(
			'REQUEST_METHOD' => 'GET',
			'HTTP_HOST'      => 'example.test',
			'REQUEST_URI'    => '/blog/',
		);
	}

	public function testPrivateBadgeDetectedInPtAndEn(): void {
		$this->assertTrue(ccd_page_cache_html_looks_private('<h2>Privado: Eu nao tenho mais diabetes.</h2>'));
		$this->assertTrue(ccd_page_cache_html_looks_private('<h2>Private: Secret post</h2>'));
		$this->assertTrue(ccd_page_cache_html_looks_private('<h2>Protegido: rascunho</h2>'));
		$this->assertTrue(ccd_page_cache_html_looks_private('<h2>Protected: draft</h2>'));
	}

	public function testPublicHtmlDoesNotLookPrivate(): void {
		$html = '<html><body><h2>Estudo premiado indica melhor metodo</h2></body></html>';
		$this->assertFalse(ccd_page_cache_html_looks_private($html));
		$this->assertFalse(ccd_page_cache_html_looks_private(''));
		// "private" solto no texto nao e o badge do WP.
		$this->assertFalse(ccd_page_cache_html_looks_private('<p>This is a private matter</p>'));
	}

	public function testCacheControlAnonymousIsPublicWithSMaxAge(): void {
		$cc = ccd_html_cache_control_value(false, false);
		$this->assertIsString($cc);
		$this->assertStringContainsString('public', $cc);
		$this->assertStringContainsString('s-maxage=', $cc);
		$this->assertStringNotContainsString('private', $cc);
	}

	public function testCacheControlUsesPerfConstantWhenDefined(): void {
		if ( ! defined( 'CCD_PERF_HTML_CACHE' ) ) {
			define( 'CCD_PERF_HTML_CACHE', 'public, max-age=0, s-maxage=99, must-revalidate' );
		}
		$cc = ccd_html_cache_control_value( false, false );
		$this->assertSame( (string) CCD_PERF_HTML_CACHE, $cc );
	}

	public function testCacheControlLoggedInIsPrivateNoStore(): void {
		$cc = ccd_html_cache_control_value(false, true);
		$this->assertIsString($cc);
		$this->assertStringContainsString('private', $cc);
		$this->assertStringContainsString('no-store', $cc);
		$this->assertStringNotContainsString('s-maxage=', $cc);
		$this->assertStringNotContainsString('public', $cc);
	}

	public function testCacheControlAdminIsNull(): void {
		$this->assertNull(ccd_html_cache_control_value(true, false));
		$this->assertNull(ccd_html_cache_control_value(true, true));
	}

	public function testPageCacheFileNullWhenLoggedIn(): void {
		$GLOBALS['ccd_test']['is_user_logged_in'] = true;
		$this->assertNull(ccd_page_cache_file());
	}

	public function testPageCacheFileNullOnLoginPath(): void {
		$_SERVER['REQUEST_URI'] = '/login';
		$this->assertNull(ccd_page_cache_file());
	}

	public function testPageCacheFileNullWithQueryString(): void {
		$_GET['nocache'] = '1';
		$this->assertNull(ccd_page_cache_file());
	}

	public function testPageCacheFilePathForAnonymousGet(): void {
		$path = ccd_page_cache_file();
		$this->assertIsString($path);
		$this->assertStringContainsString('/cache/ccd-page/', $path);
		$this->assertStringEndsWith('.html', $path);
		$expected = ccd_page_cache_dir() . '/' . md5('example.test|/blog/') . '.html';
		$this->assertSame($expected, $path);
	}
}
