import { test, expect } from '@playwright/test';

/**
 * Regressão: /blog/ deve carregar todas as páginas via scroll infinito
 * mesmo com jQuery deferido (ccd-perf).
 */
test.describe('Blog infinite scroll', () => {
  test('/blog/ carrega todas as páginas até o fim', async ({ page }) => {
    const res = await page.goto('/blog/', { waitUntil: 'domcontentloaded' });
    expect(res?.status()).toBe(200);

    // Boot sem depender de jQuery (ReferenceError com jquery defer do ccd-perf).
    await expect
      .poll(async () =>
        page.evaluate(() => typeof (window as unknown as { ccdBlogInfiniteBoot?: unknown }).ccdBlogInfiniteBoot)
      )
      .toBe('function');
    await expect
      .poll(async () =>
        page.evaluate(() => !!document.querySelector('.ccd-blog-infinite-sentinel'))
      )
      .toBeTruthy();

    const meta = await page.evaluate(() => {
      const cfg = (window as unknown as { ccdBlogInfinite?: { pages?: number; hasMore?: boolean; nextUrl?: string } }).ccdBlogInfinite || {};
      const initial = document.querySelectorAll('.post-list.row > .post-list-item').length;
      return {
        initial,
        pages: Number(cfg.pages || 0),
        hasMore: !!cfg.hasMore,
        nextUrl: String(cfg.nextUrl || ''),
        bodyInfinite: document.body.classList.contains('ccd-blog-infinite'),
      };
    });

    expect(meta.bodyInfinite).toBeTruthy();
    expect(meta.initial).toBeGreaterThan(0);

    if (!meta.hasMore || meta.pages <= 1) {
      // Ambiente com uma página só — ainda assim o boot precisa existir.
      return;
    }

    expect(meta.nextUrl).toMatch(/\/blog\/page\/2\/?/);

    // Força cargas até o fim (IntersectionObserver pode não disparar em headless sem scroll real).
    await page.evaluate(async () => {
      const sleep = (ms: number) => new Promise((r) => setTimeout(r, ms));
      const w = window as unknown as {
        ccdBlogInfiniteLoadNext?: () => void;
      };
      for (let i = 0; i < 30; i++) {
        const status = document.querySelector('.ccd-blog-infinite-status');
        const text = (status?.textContent || '').trim();
        if (/fim do blog/i.test(text)) {
          break;
        }
        if (typeof w.ccdBlogInfiniteLoadNext === 'function') {
          w.ccdBlogInfiniteLoadNext();
        } else {
          window.scrollTo(0, document.body.scrollHeight);
          document.querySelector('.ccd-blog-infinite-sentinel')?.scrollIntoView({ block: 'end' });
        }
        await sleep(700);
      }
    });

    await expect
      .poll(async () =>
        page.evaluate(() => {
          const status = document.querySelector('.ccd-blog-infinite-status');
          return (status?.textContent || '').trim();
        })
      , { timeout: 60000 })
      .toMatch(/fim do blog/i);

    const finalCount = await page.evaluate(
      () => document.querySelectorAll('.post-list.row > .post-list-item').length
    );
    // Mais que a primeira página e perto do total esperado (pages * per_page, última incompleta).
    expect(finalCount).toBeGreaterThan(meta.initial);
    if (meta.pages >= 2) {
      expect(finalCount).toBeGreaterThanOrEqual(meta.initial * Math.min(meta.pages, 2));
    }
  });
});
