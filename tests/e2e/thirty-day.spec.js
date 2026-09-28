const { test, expect } = require('@playwright/test');
const { wpCli, wpEval, login } = require('./helpers');

test.describe('30-day lowest price display', () => {
  let productId = 0;
  let cartPageId = 0;
  let checkoutPageId = 0;
  let wooCartPageId = 0;

  test.beforeAll(async () => {
    wpCli(['plugin', 'activate', 'sidrena-cijena']);

    const id = wpEval(`
      $product = new WC_Product_Simple();
      $product->set_name('E2E 30-day proizvod');
      $product->set_regular_price('20.00');
      $product->set_sale_price('7.00');
      $product->set_sku('E2E-30D-1');
      $product->set_status('publish');
      $product->save();

      $plugin = \\SidrenaCijena\\Plugin::instance();
      $plugin->anchors()->record($plugin->catalog()->get_item($product->get_id()), 25.00, array('source' => 'manual'));

      $today = \\SidrenaCijena\\Support\\Dates::today();
      $plugin->history()->insert(array(
        'product_id' => $product->get_id(),
        'item_type' => 'product',
        'sku' => 'E2E-30D-1',
        'price_type' => 'historical',
        'price' => 9.00,
        'valid_from' => gmdate('Y-m-d', strtotime($today . ' -40 days')),
        'source' => 'manual',
      ));

      $plugin->special_sale_service()->create($plugin->catalog()->get_item($product->get_id()), array(
        'sale_type' => 'akcija',
        'sale_name' => 'E2E akcija',
        'start_date' => $today,
        'end_date' => gmdate('Y-m-d', strtotime($today . ' +5 days')),
        'promo_price' => 7.00,
      ));

      update_option('sidrena_cijena_settings', array_merge(\\SidrenaCijena\\Support\\Settings::all(), array(
        'display_enabled' => true,
        'display_location' => 'price_html',
      )), false);

      echo $product->get_id();
    `);

    productId = parseInt(id.trim(), 10);

    cartPageId = parseInt(wpCli([
      'post',
      'create',
      '--post_type=page',
      '--post_title=E2E Classic Cart',
      '--post_content=[woocommerce_cart]',
      '--post_status=publish',
      '--porcelain',
    ]).trim(), 10);

    checkoutPageId = parseInt(wpCli([
      'post',
      'create',
      '--post_type=page',
      '--post_title=E2E Classic Checkout',
      '--post_content=[woocommerce_checkout]',
      '--post_status=publish',
      '--porcelain',
    ]).trim(), 10);

    wooCartPageId = parseInt(wpEval('$id = wc_get_page_id("cart"); echo $id;').trim(), 10);
  });

  test.afterAll(async () => {
    if (productId) {
      wpEval(`
        $product = wc_get_product(${productId});
        if ($product) { $product->delete(true); }
        echo 'ok';
      `);
    }

    if (cartPageId) {
      wpCli(['post', 'delete', cartPageId.toString(), '--force']);
    }

    if (checkoutPageId) {
      wpCli(['post', 'delete', checkoutPageId.toString(), '--force']);
    }

    wpEval(`
      if (file_exists(WPMU_PLUGIN_DIR . '/test-fallback.php')) {
        @unlink(WPMU_PLUGIN_DIR . '/test-fallback.php');
      }
    `);
  });

  test('the 30-day price is displayed separately from the anchor price', async ({ page }) => {
    await page.goto(`/?p=${productId}`);

    const anchor = page.locator('.sidrena-cijena__anchor-value').first();
    await expect(anchor).toContainText('25,00');

    const block = page.locator('.sidrena-cijena--30-day').first();
    await expect(block).toBeVisible();
    await expect(block.locator('.sidrena-cijena__30-day-value')).toContainText('9,00');
    await expect(block.locator('.sidrena-cijena__label')).toContainText(/Lowest price in the last 30 days|Najniža cijena/i);

    await expect(anchor).toContainText('25,00');
  });

  test('the shortcode renders both blocks distinctly', async ({ page }) => {
    const pageId = wpCli([
      'post',
      'create',
      '--post_type=page',
      '--post_title=E2E 30-day shortcode',
      `--post_content=[sidrena_cijena product_id=${productId} show_30_day=yes show_current=yes]`,
      '--post_status=publish',
      '--porcelain',
    ]).trim();

    await page.goto(`/?page_id=${pageId}`);

    await expect(page.locator('.sidrena-cijena__anchor-value').first()).toContainText('25,00');
    await expect(page.locator('.sidrena-cijena--30-day .sidrena-cijena__30-day-value').first()).toContainText('9,00');

    wpCli(['post', 'delete', pageId, '--force']);
  });

  test('classic cart and checkout show labeled per-item reference prices', async ({ page }) => {
    await page.goto(`/?add-to-cart=${productId}`);
    await page.goto(`/?page_id=${cartPageId}`);

    const cartItem = page.locator('tr.cart_item').first();
    await expect(cartItem.locator('.product-price .woocommerce-Price-amount')).toContainText('7.00');
    await expect(cartItem.locator('.sidrena-cijena__cart-unit-label')).toContainText(/Referentne cijene po komadu|Reference prices per item/);
    await expect(cartItem.locator('.sidrena-cijena__anchor-value')).toContainText('25,00');
    await expect(cartItem).toContainText('Cijena na dan');
    await expect(cartItem.locator('.sidrena-cijena--30-day .sidrena-cijena__30-day-value')).toContainText('9,00');

    await page.goto(`/?page_id=${checkoutPageId}`);

    const checkoutItem = page.locator('tr.cart_item').first();
    await expect(checkoutItem.locator('.product-total .woocommerce-Price-amount')).toContainText('7.00');
    await expect(checkoutItem.locator('.sidrena-cijena__cart-unit-label')).toContainText(/Referentne cijene po komadu|Reference prices per item/);
    await expect(checkoutItem.locator('.sidrena-cijena__anchor-value')).toContainText('25,00');
    await expect(checkoutItem.locator('.sidrena-cijena--30-day .sidrena-cijena__30-day-value')).toContainText('9,00');
  });

  test('Store API cart data includes the same reference wording for Blocks', async ({ page }) => {
    await page.goto(`/?add-to-cart=${productId}`);

    const cartItem = await page.evaluate(async (expectedProductId) => {
      const response = await fetch('/wp-json/wc/store/v1/cart');
      const body = await response.json();
      return body.items.find((item) => item.id === expectedProductId);
    }, productId);
    const reference = cartItem.extensions['sidrena-cijena-eu'].cartPriceSuffix;

    expect(reference).toMatch(/Referentne cijene po komadu|Reference prices per item/);
    expect(reference).toContain('Cijena na dan');
    expect(reference).toContain('25,00');
    expect(reference).toContain('9,00');

    await page.setViewportSize({ width: 375, height: 812 });
    await page.goto(`/?page_id=${wooCartPageId}`);

    const blockCartItem = page.locator('.wc-block-cart-items__row').filter({ hasText: 'E2E 30-day proizvod' });
    await expect(blockCartItem).toContainText(/Referentne cijene po komadu|Reference prices per item/);
    await expect(blockCartItem).toContainText('Cijena na dan');
    await expect(blockCartItem).toContainText('Najniža cijena u zadnjih 30 dana');
  });

  test('reference prices remain visible within a mobile product layout', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 });
    await page.goto(`/?p=${productId}`);

    const reference = page.locator('.sidrena-cijena').first();
    await expect(reference).toBeVisible();
    await expect(reference.locator('.sidrena-cijena__anchor-value')).toContainText('25,00');

    const bounds = await reference.boundingBox();
    expect(bounds).not.toBeNull();
    expect(bounds.x).toBeGreaterThanOrEqual(0);
    expect(bounds.x + bounds.width).toBeLessThanOrEqual(375);
  });

  test('customer wording is unmistakably the lowest price and respects configurable labels', async ({ page }) => {
    await page.goto(`/?p=${productId}`);

    const block = page.locator('.sidrena-cijena--30-day').first();
    await expect(block).toBeVisible();

    const labelText = await block.locator('.sidrena-cijena__label').innerText();
    expect(labelText).toContain('Najniža cijena u zadnjih 30 dana');
    expect(labelText.toLowerCase()).not.toContain('redovna');
    expect(labelText.toLowerCase()).not.toContain('preporučena');
    expect(labelText.toLowerCase()).not.toContain('originalna');
    expect(labelText.toLowerCase()).not.toContain('uobičajena');

    // Test configurable label persistence and rendering
    wpEval(`
      $settings = \\SidrenaCijena\\Support\\Settings::all();
      $settings['thirty_day_label'] = 'Najniža cijena (zadnjih 30 dana)';
      update_option('sidrena_cijena_settings', $settings);
    `);

    await page.goto(`/?p=${productId}`);
    await expect(block.locator('.sidrena-cijena__label')).toHaveText('Najniža cijena (zadnjih 30 dana)');

    // Restore default label
    wpEval(`
      $settings = \\SidrenaCijena\\Support\\Settings::all();
      unset($settings['thirty_day_label']);
      update_option('sidrena_cijena_settings', $settings);
    `);
  });

  test('English fallback behavior renders English labels when Croatian default is disabled', async ({ page }) => {
    wpEval(`
      $settings = \\SidrenaCijena\\Support\\Settings::all();
      unset($settings['thirty_day_label']);
      update_option('sidrena_cijena_settings', $settings);
      if (!is_dir(WPMU_PLUGIN_DIR)) {
        wp_mkdir_p(WPMU_PLUGIN_DIR);
      }
      file_put_contents(
        WPMU_PLUGIN_DIR . '/test-fallback.php',
        '<?php
        add_filter("sidrena_cijena_load_croatian_catalog", "__return_false");
        add_filter("sidrena_cijena_thirty_day_label", function() {
          return "Lowest price in the last 30 days";
        });'
      );
    `);

    try {
      await page.goto(`/?p=${productId}`);

      const block = page.locator('.sidrena-cijena--30-day').first();
      await expect(block.locator('.sidrena-cijena__label')).toContainText('Lowest price in the last 30 days');
    } finally {
      wpEval(`
        if (file_exists(WPMU_PLUGIN_DIR . '/test-fallback.php')) {
          @unlink(WPMU_PLUGIN_DIR . '/test-fallback.php');
        }
      `);
    }
  });

  test('sale expiration or sale end removes the 30-day display while retaining anchor price', async ({ page }) => {
    wpEval(`
      $product = wc_get_product(${productId});
      $product->set_sale_price('');
      $product->save();

      global $wpdb;
      $wpdb->update(
        $wpdb->prefix . 'sidrena_special_sales',
        array('status' => 'ended'),
        array('product_id' => ${productId})
      );
      clean_post_cache(${productId});
      wc_delete_product_transients(${productId});
    `);

    try {
      await page.goto(`/?p=${productId}`);

      // The 30-day lowest price block must cleanly disappear
      const thirtyDayBlock = page.locator('.sidrena-cijena--30-day');
      await expect(thirtyDayBlock).toHaveCount(0);

      // The anchor price block must still be visible
      const anchorBlock = page.locator('.sidrena-cijena__anchor-value');
      await expect(anchorBlock).toBeVisible();
      await expect(anchorBlock).toContainText('25,00');
    } finally {
      wpEval(`
        $product = wc_get_product(${productId});
        $product->set_sale_price('7.00');
        $product->save();

        global $wpdb;
        $wpdb->update(
          $wpdb->prefix . 'sidrena_special_sales',
          array('status' => 'active'),
          array('product_id' => ${productId})
        );
        clean_post_cache(${productId});
        wc_delete_product_transients(${productId});
      `);
    }
  });
});

