import { test, expect } from '@playwright/test';

/**
 * Regressão: hero de categoria deve ficar no padrão interno (faixa curta)
 * e não conter listas editoriais ("Pilares…") que inflam o banner.
 */
test.describe('Category archive hero', () => {
  test('/diabetes/ hero compacto e sem pilares no banner', async ({ page }) => {
    const res = await page.goto('/diabetes/', { waitUntil: 'domcontentloaded' });
    expect(res?.status()).toBe(200);

    const header = page.locator('.header-wrapper .header, .header-wrapper [class*="header"]').first();
    await expect(header).toBeVisible();

    const heroTitle = page.locator('.header .hero-title, .header-wrapper .hero-title').first();
    await expect(heroTitle).toBeVisible();
    await expect(heroTitle).toContainText(/diabetes/i);

    // Nada de pilares / intro editorial dentro do wrapper do hero.
    await expect(header.locator('.ccd-hub-pillars, .ccd-category-intro')).toHaveCount(0);
    await expect(header.getByText('Pilares para começar')).toHaveCount(0);

    // Pilares (se existirem) só em #page-content — nunca no banner.
    const pillarsInContent = page.locator('#page-content .ccd-hub-pillars');
    const pillarsAnywhere = page.locator('.ccd-hub-pillars');
    const contentCount = await pillarsInContent.count();
    const totalCount = await pillarsAnywhere.count();
    expect(totalCount).toBe(contentCount);
    if (contentCount > 0) {
      await expect(pillarsInContent.first()).toBeVisible();
    }

    const headerBox = await header.boundingBox();
    expect(headerBox).toBeTruthy();
    // Padrão a11y ~11.5rem de descrição; banner inflado com lista passa de ~420px.
    expect(headerBox!.height).toBeLessThan(360);
    expect(headerBox!.height).toBeGreaterThan(80);

    const inner = page.locator('.header .inner-header-description').first();
    await expect(inner).toBeVisible();
    const innerBox = await inner.boundingBox();
    expect(innerBox).toBeTruthy();
    expect(innerBox!.height).toBeLessThan(240);
    expect(innerBox!.height).toBeGreaterThan(120);
  });

  test('/receitas/ e categorias genéricas: hero limpo', async ({ page }) => {
    for (const path of ['/receitas/', '/diabetes/alimentacao/']) {
      const res = await page.goto(path, { waitUntil: 'domcontentloaded' });
      if (res?.status() === 404) {
        continue;
      }
      expect(res?.ok()).toBeTruthy();
      const header = page.locator('.header-wrapper').first();
      await expect(header.locator('.ccd-hub-pillars, .ccd-category-intro')).toHaveCount(0);
      await expect(header.getByText('Pilares para começar')).toHaveCount(0);
      const box = await header.boundingBox();
      expect(box).toBeTruthy();
      expect(box!.height).toBeLessThan(360);
    }
  });
});
