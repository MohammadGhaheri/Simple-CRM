'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const { chromium } = require('playwright');

async function main() {
  const [port, operationsSession, salesSession, financeSession] = process.argv.slice(2);
  assert(port && operationsSession && salesSession && financeSession, 'Missing synthetic HTTP sessions');
  const base = 'http://127.0.0.1:' + port;
  const destination = process.env.MAMMUT_SCREENSHOT_DIR ||
    path.join(os.tmpdir(), 'mammut-browser-screenshots');
  fs.mkdirSync(destination, { recursive: true });
  const browser = await chromium.launch({ headless: true });
  let screenshots = 0;
  try {
    const scenarios = [
      ['desktop-operations', operationsSession, 1365, 850],
      ['mobile-operations', operationsSession, 390, 844],
      ['desktop-sales', salesSession, 1365, 850],
      ['mobile-finance', financeSession, 390, 844],
    ];
    for (const [label, session, width, height] of scenarios) {
      const context = await browser.newContext({
        viewport: { width, height }, deviceScaleFactor: 1,
        locale: 'fa-IR', reducedMotion: 'reduce',
      });
      await context.addCookies([{ name: 'PHPSESSID', value: session, url: base }]);
      const page = await context.newPage();
      const uncaught = [];
      page.on('pageerror', error => uncaught.push(String(error.message)));
      const response = await page.goto(base + '/index.php?page=installations',
        { waitUntil: 'domcontentloaded' });
      assert.equal(response.status(), 200, label + ': queue did not render');
      assert(await page.locator('h2').filter({ hasText: 'تعهدات نصب' }).count() > 0,
        label + ': heading is missing');
      const dimensions = await page.evaluate(() => ({
        direction: getComputedStyle(document.documentElement).direction,
        documentWidth: document.documentElement.scrollWidth,
        viewportWidth: window.innerWidth,
        tables: document.querySelectorAll('.table-wrap').length,
      }));
      assert.equal(dimensions.direction, 'rtl', label + ': not right-to-left');
      assert(dimensions.documentWidth <= dimensions.viewportWidth + 24,
        label + ': horizontal overflow: ' + dimensions.documentWidth + ' / ' + dimensions.viewportWidth);
      assert(dimensions.tables > 0, label + ': list table missing');

      if (label.includes('operations')) {
        assert(await page.getByText('HTTP Owned Customer').count() > 0,
          label + ': own customer missing');
        await page.screenshot({ path: path.join(destination, label + '-queue.png'), fullPage: true });
        screenshots++;
        const detail = await page.goto(base + '/index.php?page=installations&action=show&id=1',
          { waitUntil: 'domcontentloaded' });
        assert.equal(detail.status(), 200, label + ': detail unavailable');
        assert.equal(await page.locator('input[name="imei"]').count(), 1,
          label + ': installation form missing');
        assert.equal(await page.locator('input[name="receipt_number"]').count(), 1,
          label + ': delivery form missing');
        const detailDimensions = await page.evaluate(() => ({
          width: document.documentElement.scrollWidth, viewport: window.innerWidth,
          direction: getComputedStyle(document.documentElement).direction,
        }));
        assert.equal(detailDimensions.direction, 'rtl', label + ': detail is not RTL');
        assert(detailDimensions.width <= detailDimensions.viewport + 24,
          label + ': detail horizontal overflow: ' + detailDimensions.width + ' / ' + detailDimensions.viewport);
        await page.screenshot({ path: path.join(destination, label + '-detail.png'), fullPage: true });
        screenshots++;
      } else if (label.includes('finance')) {
        const detail = await page.goto(base + '/index.php?page=installations&action=show&id=1',
          { waitUntil: 'domcontentloaded' });
        assert.equal(detail.status(), 200, label + ': finance detail unavailable');
        assert.equal(await page.locator('input[name="imei"]').count(), 0,
          label + ': finance can edit installation');
        await page.screenshot({ path: path.join(destination, label + '-read-only.png'), fullPage: true });
        screenshots++;
      } else {
        assert(await page.getByText('HTTP Owned Customer').count() > 0,
          label + ': own customer not listed');
        assert.equal(await page.getByText('HTTP Other Customer').count(), 0,
          label + ': foreign customer is visible');
        await page.screenshot({ path: path.join(destination, label + '-queue.png'), fullPage: true });
        screenshots++;
      }
      assert.deepEqual(uncaught, [], label + ': browser exceptions: ' + uncaught.join('; '));
      await context.close();
    }
    console.log('Headless visual/RTL/mobile/browser checks passed; saved ' + screenshots + ' synthetic screenshots.');
  } finally {
    await browser.close();
  }
}

main().catch(error => { console.error(error.stack || error.message); process.exitCode = 1; });
