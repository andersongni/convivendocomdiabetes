import { test, expect } from '@playwright/test';

/**
 * Regressão: hero de categoria sem listas editoriais no banner,
 * e sem caixa de hub visual no conteúdo (removida — SEO não depende dela).
 */
test.describe('Category archive hero', () => {
  test('/diabetes/ hero limpo e sem hub visual', async ({ page }) => {
    const res = await page.goto('/diabetes/', { waitUntil: 'domcontentloaded' });
    expect(res?.status()).toBe(200);

    const header = page.locator('.header-wrapper').first();
    await expect(header).toBeVisible();

    const heroTitle = page.locator('.header .hero-title, .header-wrapper .hero-title').first();
    await expect(heroTitle).toBeVisible();
    await expect(heroTitle).toContainText(/diabetes/i);

    await expect(header.locator('nav.ccd-hub-pillars, .ccd-category-intro')).toHaveCount(0);
    await expect(page.locator('nav.ccd-hub-pillars')).toHaveCount(0);
    await expect(page.getByText('Pilares para começar')).toHaveCount(0);
    await expect(page.getByText('Guias para entender a condição')).toHaveCount(0);

    const inner = page.locator('.header .inner-header-description').first();
    if (await inner.count()) {
      await expect(inner).toBeVisible();
      const innerBox = await inner.boundingBox();
      expect(innerBox).toBeTruthy();
      expect(innerBox!.height).toBeLessThan(280);
    }
  });

  test('/receitas/ hero limpo e sem hub visual', async ({ page }) => {
    const res = await page.goto('/receitas/', { waitUntil: 'domcontentloaded' });
    if (res?.status() === 404) {
      test.skip();
      return;
    }
    expect(res?.ok()).toBeTruthy();
    const header = page.locator('.header-wrapper').first();
    await expect(header.locator('nav.ccd-hub-pillars, .ccd-category-intro')).toHaveCount(0);
    await expect(page.locator('nav.ccd-hub-pillars')).toHaveCount(0);
    await expect(page.getByText('Pilares para começar')).toHaveCount(0);
    await expect(page.getByText('Antes das receitas')).toHaveCount(0);
  });
});
