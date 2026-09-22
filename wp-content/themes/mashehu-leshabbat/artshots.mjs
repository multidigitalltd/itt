/* Every artwork, drawn full, so the new ones can be looked at. */
import { chromium } from 'playwright';
const shapes = process.argv.slice(2);
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' });
const ctx = await browser.newContext({ viewport: { width: 900, height: 900 } });
await ctx.route('**/msl/v1/**', r => r.fulfill({ contentType: 'application/json', body: '{}' }));
const page = await ctx.newPage();
page.on('pageerror', e => console.log('PAGE ERROR:', e.message));
await page.goto('http://127.0.0.1:8902/home.html');
await page.waitForTimeout(900);
await page.evaluate(() => { document.body.dataset.mslScreen = 'art'; const p = document.querySelector('[data-msl-screen-panel="art"]'); p.hidden = false; });
for (const shape of shapes) {
  const cells = await page.evaluate((s) => {
    window.MSLCanvas.setState({ artwork: s, count: 100, target: 100 });
    return window.MSLCanvas.cellCount();
  }, shape);
  await page.waitForTimeout(1100);
  await page.evaluate(() => window.MSLCanvas.setState({ count: 100, target: 100 }));
  await page.waitForTimeout(300);
  await page.screenshot({ path: `art-${shape}.png`, clip: { x: 130, y: 60, width: 640, height: 640 } });
  console.log(shape.padEnd(10), 'cells:', cells);
}
await browser.close();
