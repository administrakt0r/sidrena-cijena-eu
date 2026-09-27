# Storefront price-display coverage

This inventory describes the WooCommerce surfaces where Sidrena Cijena can
display a reference price. “Covered” means the output contains the configured
anchor amount and its date, plus the separate preceding-30-day amount and
label when the product is classified as being on sale.

| Customer-visible surface | Coverage and limits |
| --- | --- |
| Single-product page | Covered through WooCommerce price HTML, the single-product summary hook, and product-price blocks. |
| Shop, category, related, and upsell product cards | Covered when archive display is enabled and the product has “show in cards” enabled. Standard WooCommerce loop prices and the supported price blocks use the product attached to that item. |
| WooCommerce product-price blocks | Covered for `woocommerce/product-price`, `woocommerce/price`, and `core/price` when the block has an exact product context. Ambiguous collection items are skipped to avoid showing another product’s reference price. |
| Supported builders | Divi, Avada/Fusion, Beaver Builder, Bricks, and Elementor self-rendering price elements are supported on identifiable single-product pages. Collection elements that do not identify their product are skipped. |
| Variation selection | WooCommerce variation AJAX responses carry the selected variation’s own reference prices. They do not inherit a parent variation’s price. |
| Quick view | Covered when the quick-view implementation uses WooCommerce `get_price_html()` or one of the supported builder hooks. Custom quick-view renderers that bypass those hooks are outside the plugin’s control. |
| Classic cart and mini-cart | Covered by WooCommerce cart-item price and mini-cart quantity/price filters. The reference values are labeled as per-item amounts. |
| Classic checkout | Covered beside the line subtotal, with an explicit per-item label so it is not confused with the quantity-based subtotal. |
| Cart and Checkout blocks | Both use the WooCommerce Store API cart-item extension and Blocks `cartItemPrice` filter. The Cart block is browser-tested; Checkout block output is not separately browser-tested. |
| Other third-party cards | Covered when they render WooCommerce price HTML or use a supported builder integration. Plugin-specific price markup that bypasses those paths is not controlled by Sidrena Cijena. |

Cart and checkout presentation is enabled whenever display is enabled. It does
not depend on the product-page or archive-card switches because those switches
do not describe cart prices.

## Legal classification of cart output

The Consumer Protection Act, Article 19(4)–(5), requires the current sale price
and the lowest price during the preceding 30 days during a special sale, and
extends that information to advertising the goods. Decision NN 101/2026,
paragraph II, requires the additional fixed-date price to be clearly displayed
alongside the retail price at the point of sale and in advertising that price
from 1 October 2026.
The Ministry of Economy’s September 2026 clarification applies the additional
price to web shops and says the prices should appear together; during a discount
it describes the current, preceding-30-day, and additional fixed-date prices.

The cited materials do not expressly name a cart or checkout line. This plugin
therefore includes those surfaces as a conservative implementation choice:
the customer still sees and uses the reduced offer price there. Whether the
legislation separately requires the reference display in every cart and
checkout presentation remains **LEGAL UNCERTAINTY** and should be confirmed by
Croatian counsel if a definitive legal interpretation is needed.

Sources:

- [Consumer Protection Act, Official Gazette 19/2022, Article 19](https://narodne-novine.nn.hr/clanci/sluzbeni/2022_02_19_203.html)
- [Official Gazette 101/2026, Decision II](https://narodne-novine.nn.hr/clanci/sluzbeni/2026_09_101_1212.html)
- [Ministry of Economy clarification, 22 September 2026](https://mingo.gov.hr/vijesti/pojasnjenja-za-primjenu-dodatne-cijene-i-objavu-cjenika-od-1-listopada/10440)

## Verification map

- `tests/e2e/anchor-display.spec.js` covers single-product and opted-in archive
  output, disabled output, and the shortcode.
- `tests/e2e/thirty-day.spec.js` covers the separate anchor and 30-day blocks,
  classic cart and checkout, visible Cart block output and Store API data, and
  375-pixel product and Cart block layouts.
- `tests/unit/WooCommerce/Display/PriceBlockDisplayTest.php` verifies exact
  product context in price blocks and skips ambiguous collection items.
- `tests/unit/WooCommerce/Display/VariationDisplayTest.php` covers variation
  AJAX data; `BuilderCompatTest.php` covers supported builder detection.
- Quick-view plugins and third-party custom price renderers are not installed
  in the isolated test site, so those integration-specific paths are not
  end-to-end verified.
