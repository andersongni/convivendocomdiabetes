import { test, expect } from '@playwright/test';

/**
 * Regressão: /blog/ deve iniciar o infinite scroll sem depender de jQuery
 * (ccd-perf defere jquery-core). Com 2+ páginas, carrega até o fim.
 */
test.describe('Blog infinite scroll', () => {
  test('/blog/ boot e carrega páginas quando houver mais', async ({ page }) => {
    const res = await page.goto('/blog/', { waitUntil: 'domcontentloaded' });
    expect(res?.status()).toBe(200);

    await expect
      .poll(async () =>
        page.evaluate(() => typeof (window as unknown as { ccdBlogInfiniteBoot?: unknown }).ccdBlogInfiniteBoot)
      )
      .toBe('function');

    const meta = await page.evaluate(() => {
      const cfg =
        (window as unknown as { ccdBlogInfinite?: { pages?: number; hasMore?: boolean; nextUrl?: string; active?: boolean } })
          .ccdBlogInfinite || {};
      const initial = document.querySelectorAll('.post-list.row > .post-list-item').length;
      return {
        initial,
        pages: Number(cfg.pages || 0),
        hasMore: !!cfg.hasMore,
        nextUrl: String(cfg.nextUrl || ''),
        active: !!cfg.active,
        bodyInfinite: document.body.classList.contains('ccd-blog-infinite'),
        sentinel: !!document.querySelector('.ccd-blog-infinite-sentinel'),
      };
    });

    expect(meta.bodyInfinite || meta.active).toBeTruthy();
    expect(meta.initial).toBeGreaterThan(0);

    if (!meta.hasMore || meta.pages <= 1) {
      // CI com poucos posts: sem sentinel e sem page/2 — boot ja validado.
      return;
    }

    expect(meta.sentinel).toBeTruthy();
    expect(meta.nextUrl).toMatch(/\/blog\/page\/2\/?/);

    await page.evaluate(async () => {
      const sleep = (ms: number) => new Promise((r) => setTimeout(r, ms));
      const w = window as unknown as { ccdBlogInfiniteLoadNext?: () => void };
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
      .poll(
        async () =>
          page.evaluate(() => {
            const status = document.querySelector('.ccd-blog-infinite-status');
            return (status?.textContent || '').trim();
          }),
        { timeout: 60000 }
      )
      .toMatch(/fim do blog/i);

    const finalCount = await page.evaluate(
      () => document.querySelectorAll('.post-list.row > .post-list-item').length
    );
    expect(finalCount).toBeGreaterThan(meta.initial);
  });
});
