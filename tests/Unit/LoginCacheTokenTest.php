<?php

declare(strict_types=1);

namespace Ccd\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class LoginCacheTokenTest extends TestCase {
	public function testTokenStableForSamePayload(): void {
		$a = ccd_login_clear_cache_token(array('convivendocomdiabetes.com'), 1700000000);
		$b = ccd_login_clear_cache_token(array('convivendocomdiabetes.com'), 1700000000);
		$this->assertSame($a, $b);
		$this->assertSame(64, strlen($a));
	}

	public function testTokenChangesWithHostOrExp(): void {
		$base = ccd_login_clear_cache_token(array('a.example'), 100);
		$this->assertNotSame($base, ccd_login_clear_cache_token(array('b.example'), 100));
		$this->assertNotSame($base, ccd_login_clear_cache_token(array('a.example'), 101));
	}

	public function testClearCacheUrlContainsSig(): void {
		$url = ccd_login_clear_cache_url(array('localhost'));
		$this->assertStringContainsString('action=ccd_clear_cache', $url);
		$this->assertStringContainsString('ccd_sig=', $url);
		$this->assertStringContainsString('/login', $url);
	}
}
