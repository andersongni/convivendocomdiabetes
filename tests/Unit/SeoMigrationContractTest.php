<?php

declare(strict_types=1);

namespace Ccd\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Contrato: migrações SEO nunca escrevem no front público;
 * claim da versão acontece antes do trabalho pesado.
 */
final class SeoMigrationContractTest extends TestCase {
	protected function setUp(): void {
		ccd_test_reset_state();
	}

	public function testEditorialApplyIsNoopOnPublicFront(): void {
		$GLOBALS['ccd_test']['is_admin'] = false;
		$GLOBALS['ccd_test']['doing_cron'] = false;

		ccd_seo_editorial_apply();

		$this->assertArrayNotHasKey('ccd_seo_editorial', $GLOBALS['ccd_test']['options']);
		$this->assertSame(0, $GLOBALS['ccd_test']['update_post_calls']);
		$this->assertSame(0, $GLOBALS['ccd_test']['update_term_calls']);
		$this->assertNotContains('ccd_seo_editorial', $GLOBALS['ccd_test']['update_option_log']);
	}

	public function testBoostApplyIsNoopOnPublicFront(): void {
		$GLOBALS['ccd_test']['is_admin'] = false;
		$GLOBALS['ccd_test']['doing_cron'] = false;

		ccd_seo_boost_apply();

		$this->assertArrayNotHasKey('ccd_seo_boost', $GLOBALS['ccd_test']['options']);
		$this->assertSame(0, $GLOBALS['ccd_test']['update_post_calls']);
		$this->assertNotContains('ccd_seo_boost', $GLOBALS['ccd_test']['update_option_log']);
	}

	public function testEditorialApplyNoopWhenAlreadyMigrated(): void {
		$GLOBALS['ccd_test']['is_admin'] = true;
		$GLOBALS['ccd_test']['options']['ccd_seo_editorial'] = CCD_SEO_EDITORIAL_VERSION;

		ccd_seo_editorial_apply();

		$this->assertSame(0, $GLOBALS['ccd_test']['update_post_calls']);
		$this->assertSame(array(), $GLOBALS['ccd_test']['update_option_log']);
	}

	public function testEditorialApplyClaimsVersionBeforeWorkOnAdmin(): void {
		$GLOBALS['ccd_test']['is_admin'] = true;

		ccd_seo_editorial_apply();

		$this->assertSame(CCD_SEO_EDITORIAL_VERSION, get_option('ccd_seo_editorial'));
		$this->assertContains('ccd_seo_editorial', $GLOBALS['ccd_test']['update_option_log']);
		// Claim é a primeira (e tipicamente única) escrita de option da migração.
		$this->assertSame('ccd_seo_editorial', $GLOBALS['ccd_test']['update_option_log'][0]);
	}

	public function testBoostApplyClaimsVersionBeforeWorkOnAdmin(): void {
		$GLOBALS['ccd_test']['is_admin'] = true;

		ccd_seo_boost_apply();

		$this->assertSame(CCD_SEO_BOOST_VERSION, get_option('ccd_seo_boost'));
		$this->assertContains('ccd_seo_boost', $GLOBALS['ccd_test']['update_option_log']);
		$this->assertSame('ccd_seo_boost', $GLOBALS['ccd_test']['update_option_log'][0]);
	}

	public function testEditorialApplyAllowedOnCron(): void {
		$GLOBALS['ccd_test']['doing_cron'] = true;

		ccd_seo_editorial_apply();

		$this->assertSame(CCD_SEO_EDITORIAL_VERSION, get_option('ccd_seo_editorial'));
	}

	public function testEditorialApplySkipsAjax(): void {
		$GLOBALS['ccd_test']['is_admin'] = true;
		$GLOBALS['ccd_test']['doing_ajax'] = true;

		ccd_seo_editorial_apply();

		$this->assertArrayNotHasKey('ccd_seo_editorial', $GLOBALS['ccd_test']['options']);
		$this->assertSame(0, $GLOBALS['ccd_test']['update_post_calls']);
	}

	public function testEditorialApplySkipsWhenMigratingLockHeld(): void {
		$GLOBALS['ccd_test']['is_admin'] = true;
		set_transient('ccd_seo_editorial_migrating', 1, 60);

		ccd_seo_editorial_apply();

		$this->assertArrayNotHasKey('ccd_seo_editorial', $GLOBALS['ccd_test']['options']);
		$this->assertSame(0, $GLOBALS['ccd_test']['update_post_calls']);
	}

	public function testEditorialApplyNoopWhenRedisCacheDriftsBehindMysql(): void {
		$GLOBALS['ccd_test']['is_admin'] = true;
		// Object cache (get_option) atrasado; MySQL já na versão atual.
		$GLOBALS['ccd_test']['options']['ccd_seo_editorial'] = '1';
		$GLOBALS['wpdb']->db_options = array(
			'ccd_seo_editorial' => CCD_SEO_EDITORIAL_VERSION,
		);

		ccd_seo_editorial_apply();

		$this->assertSame(0, $GLOBALS['ccd_test']['update_post_calls']);
		$this->assertSame(array(), $GLOBALS['ccd_test']['update_option_log']);
		// Cache corrigido para o valor do MySQL.
		$this->assertSame(CCD_SEO_EDITORIAL_VERSION, get_option('ccd_seo_editorial'));
	}
}
