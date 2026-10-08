import { test, expect } from '@playwright/test';

/**
 * Regressão: hero de categoria sem listas editoriais no banner.
 * Altura absoluta do .header varia com tema/separator no CI — o contrato
 * forte e "pilares fora do hero".
 */
test.describe('Category archive hero', () => {
  test('/diabetes/ hero sem pilares no banner', async ({ page }) => {
    const res = await page.goto('/diabetes/', { waitUntil: 'domcontentloaded' });
    expect(res?.status()).toBe(200);

    const header = page.locator('.header-wrapper').first();
    await expect(header).toBeVisible();

    const heroTitle = page.locator('.header .hero-title, .header-wrapper .hero-title').first();
    await expect(heroTitle).toBeVisible();
    await expect(heroTitle).toContainText(/diabetes/i);

    await expect(header.locator('nav.ccd-hub-pillars, .ccd-category-intro')).toHaveCount(0);
    await expect(header.getByText('Pilares para começar')).toHaveCount(0);

    const pillarsInContent = page.locator('#page-content nav.ccd-hub-pillars');
    const pillarsNav = page.locator('nav.ccd-hub-pillars');
    expect(await pillarsNav.count()).toBe(await pillarsInContent.count());
    if ((await pillarsInContent.count()) > 0) {
      await expect(pillarsInContent.first()).toBeVisible();
    }

    const inner = page.locator('.header .inner-header-description').first();
    if (await inner.count()) {
      await expect(inner).toBeVisible();
      const innerBox = await inner.boundingBox();
      expect(innerBox).toBeTruthy();
      // Inflado com lista de pilares >> 240px; titulo sozinho fica abaixo.
      expect(innerBox!.height).toBeLessThan(280);
    }
  });

  test('/receitas/ hero limpo', async ({ page }) => {
    const res = await page.goto('/receitas/', { waitUntil: 'domcontentloaded' });
    if (res?.status() === 404) {
      test.skip();
      return;
    }
    expect(res?.ok()).toBeTruthy();
    const header = page.locator('.header-wrapper').first();
    await expect(header.locator('nav.ccd-hub-pillars, .ccd-category-intro')).toHaveCount(0);
    await expect(header.getByText('Pilares para começar')).toHaveCount(0);
  });
});
