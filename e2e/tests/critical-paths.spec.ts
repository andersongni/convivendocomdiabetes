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
    await expect(page.locator('.header-wrapper .ccd-category-intro, .header-wrapper .ccd-hub-pillars')).toHaveCount(0);
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

  test('/contato/ WPForms com jQuery sincrono', async ({ page }) => {
    const pageErrors: string[] = [];
    page.on('pageerror', (err) => pageErrors.push(String(err)));

    const res = await page.goto('/contato/', { waitUntil: 'domcontentloaded' });
    expect(res?.status()).toBe(200);

    // jQuery nao pode estar defer nesta pagina (WPForms e sincrono).
    const jq = await page.evaluate(() => {
      const el = document.getElementById('jquery-core-js') as HTMLScriptElement | null;
      return {
        defer: !!el?.defer,
        hasJq: typeof (window as unknown as { jQuery?: unknown }).jQuery,
        hasWpforms: typeof (window as unknown as { wpforms?: unknown }).wpforms,
      };
    });
    expect(jq.defer).toBe(false);
    expect(jq.hasJq).toBe('function');

    await expect(page.locator('#wpforms-2765, .wpforms-form, .ccd-contact form').first()).toBeVisible();
    await expect(page.getByText(/WPForms detectou um problema/i)).toHaveCount(0);
    expect(pageErrors.filter((e) => /jQuery is not defined/i.test(e))).toEqual([]);
  });
});
