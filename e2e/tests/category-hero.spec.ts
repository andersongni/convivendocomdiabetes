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

    // Pilares devem existir na página, mas no conteúdo (fora do hero).
    const pillars = page.locator('.ccd-hub-pillars');
    await expect(pillars).toHaveCount(1);
    await expect(pillars).toBeVisible();

    const headerBox = await header.boundingBox();
    expect(headerBox).toBeTruthy();
    // Padrão a11y ~11.5rem de descrição + nav/topo; banner inflado com lista passa de ~420px.
    expect(headerBox!.height).toBeLessThan(360);
    expect(headerBox!.height).toBeGreaterThan(80);

    const inner = page.locator('.header .inner-header-description').first();
    await expect(inner).toBeVisible();
    const innerBox = await inner.boundingBox();
    expect(innerBox).toBeTruthy();
    // ccd-a11y fixa 11.5rem (~184px em root 16px); margem para zoom/font.
    expect(innerBox!.height).toBeLessThan(240);
    expect(innerBox!.height).toBeGreaterThan(120);
  });

  test('/receitas/ segue o mesmo contrato de hero', async ({ page }) => {
    const res = await page.goto('/receitas/', { waitUntil: 'domcontentloaded' });
    expect(res?.status()).toBe(200);

    const header = page.locator('.header-wrapper').first();
    await expect(header.locator('.ccd-hub-pillars, .ccd-category-intro')).toHaveCount(0);

    const box = await header.boundingBox();
    expect(box).toBeTruthy();
    expect(box!.height).toBeLessThan(360);
  });
});
