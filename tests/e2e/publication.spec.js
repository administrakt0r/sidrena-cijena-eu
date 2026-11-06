const { test, expect } = require('@playwright/test');
const { wpCli, wpEval, login } = require('./helpers');

test.describe('Price-list publication', () => {
  let productId = 0;

  test.beforeAll(async () => {
    wpCli(['plugin', 'activate', 'titanworks-sidrena-bazna-cijena']);

    const id = wpEval(`
      $product = new WC_Product_Simple();
      $product->set_name('E2E cjenik proizvod');
      $product->set_regular_price('12.50');
      $product->set_sku('E2E-PUB-1');
      $product->set_status('publish');
      $product->save();
      $item = \\SidrenaCijena\\Plugin::instance()->catalog()->get_item($product->get_id());
      if ($item) {
        \\SidrenaCijena\\Plugin::instance()->anchors()->record($item, 12.50, array('source' => 'manual'));
      }
      $locations = new \\SidrenaCijena\\Database\\LocationRepository();
      $has_store = false;
      foreach ($locations->all(true) as $location) {
        if ('prodavaonica' === $location['type']) {
          $has_store = true;
          break;
        }
      }
      if (!$has_store) {
        $locations->insert(array('name' => 'Prodavaonica E2E', 'type' => 'prodavaonica', 'address' => 'Vlaška 68', 'city' => 'Zagreb', 'identifier' => 'E2E1', 'active' => true));
      }
      echo $product->get_id();
    `);

    productId = parseInt(id.trim(), 10);
  });

  test.afterAll(async () => {
    if (productId) {
      wpEval(`
        $product = wc_get_product(${productId});
        if ($product) { $product->delete(true); }
        echo 'ok';
      `);
    }
  });

  test('manual generation publishes a file listed in the archive', async ({ page }) => {
    await login(page);

    await page.goto('/wp-admin/admin.php?page=sidrena-cijena-publications');
    await expect(page.locator('h1')).toContainText(/Price lists|Cjenici/i);

    await page.locator('input[type="submit"], button[type="submit"]').first().click();
    await expect(page.locator('.sidrena-cijena-notice')).toBeVisible();

    await expect(page.locator('table.widefat tbody tr').first()).toContainText('PRODAVAONICA_');
  });

  test('CSV and XML download routes serve the documented formats', async ({ request }) => {
    // Several locations are active on the test site, and the route correctly
    // refuses to guess between them, so ask for the webshop explicitly.
    const locationId = parseInt(
      wpEval(`
        foreach ( \\SidrenaCijena\\Plugin::instance()->locations()->all( true ) as $location ) {
          if ( "webshop" === $location["type"] ) { echo (int) $location["id"]; return; }
        }
        foreach ( \\SidrenaCijena\\Plugin::instance()->locations()->all( true ) as $location ) {
          echo (int) $location["id"]; return;
        }
      `).trim(),
      10
    );

    expect(locationId).toBeGreaterThan(0);

    const csv = await request.get(
      `/wp-json/sidrena-cijena/v1/prices.csv?location=${locationId}`
    );
    expect(csv.status()).toBe(200);

    const csvBody = await csv.text();
    expect(csvBody.startsWith('vrsta;naziv;sifra;')).toBeTruthy();
    expect(csvBody).toContain('E2E-PUB-1');

    const xml = await request.get(
      `/wp-json/sidrena-cijena/v1/prices.xml?location=${locationId}`
    );
    expect(xml.status()).toBe(200);

    const xmlBody = await xml.text();
    expect(xmlBody).toContain('<?xml version="1.0" encoding="UTF-8"?>');
    expect(xmlBody).toContain('<cjenik');
  });

  test('the public prices endpoint returns paginated JSON', async ({ request }) => {
    const response = await request.get('/wp-json/sidrena-cijena/v1/prices?per_page=5');
    expect(response.status()).toBe(200);

    const body = await response.json();
    expect(body).toHaveProperty('data');
    expect(body).toHaveProperty('meta');
    expect(body.meta.per_page).toBe(5);
    expect(body.meta.rule_version).toBeTruthy();
  });

  test('the publications index is machine-readable', async ({ request }) => {
    const response = await request.get('/wp-json/sidrena-cijena/v1/publications');
    expect(response.status()).toBe(200);

    const body = await response.json();
    expect(body).toHaveProperty('data');
    expect(body).toHaveProperty('meta');
  });
});
