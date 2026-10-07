import { test, expect } from '@playwright/test';

test.describe('CCD critical paths', () => {
  test('home carrega com marca e nav', async ({ page }) => {
    const res = await page.goto('/');
    expect(res?.ok()).toBeTruthy();
    await expect(page.locator('body')).toBeVisible();
    await expect(page.locator('nav, .menu-item, .site-header').first()).toBeVisible();
  });

  test('/login mostra formulario', async ({ page }) => {
    const res = await page.goto('/login');
    expect(res?.status()).toBe(200);
    await expect(page.locator('#loginform, form[name="loginform"], input[name="log"]').first()).toBeVisible();
  });

  test('/diabetes/ e arquivo de categoria, nao post', async ({ page }) => {
    const res = await page.goto('/diabetes/', { waitUntil: 'domcontentloaded' });
    expect(res?.status()).toBe(200);
    expect(page.url()).toMatch(/\/diabetes\/?$/);
    expect(page.url()).not.toMatch(/diabetes-tipo-2/);
    const intro = page.locator('.ccd-category-intro');
    await expect(intro).toHaveCount(0);
    await expect(page.locator('.hero-title, h1').first()).toBeVisible();
  });

  test('post com comentario abre form', async ({ page }) => {
    const res = await page.goto('/hipoglicemia/');
    expect(res?.ok()).toBeTruthy();
    await expect(page.locator('#commentform, #respond, textarea#comment').first()).toBeVisible();
  });

  test('blog e contato respondem', async ({ page }) => {
    for (const path of ['/blog/', '/contato/']) {
      const res = await page.goto(path);
      expect(res?.ok()).toBeTruthy();
    }
  });
});
