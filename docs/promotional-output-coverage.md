# Promotional output coverage

This inventory traces the price outputs produced by Sidrena Cijena. “Reduced
price” means the effective retail price while the item is in a classified
special sale. The fixed-date anchor and the preceding-30-day lowest price are
separate references.

| Output | Can show a reduced price? | References present for a classified sale |
| --- | --- | --- |
| Published CSV/XML (`/sidrena-cijena.csv`, `/sidrena-cijena.xml`) | Yes; `maloprodajna_cijena` is the effective price, accompanied by the special-sale flag and name. | The contract includes the fixed-date anchor and its date, populated when an anchor exists. The preceding-30-day lowest price is not a field in the documented contract. |
| REST `/prices.csv` and `/prices.xml` | Yes; these serialize the same published CSV/XML contract. | Same as the published files: anchor and date fields are present and populated when available; preceding-30-day lowest price is absent. |
| Public HTML `/sidrena-cjenik/` and `[sidrena-cjenik]` | Yes; the table shows the effective retail price and sale name. | The preceding-30-day lowest price is shown for a classified sale when calculated. The anchor and date are shown when an anchor exists. |
| REST `/prices` JSON | Yes, in `current_price`; a classified sale has a `special_sale` object. | Anchor and date are returned when an anchor exists. `special_sale.lowest_30_day_price` is present when the classified sale has a calculated value. |
| REST `/publications`, `/arhiva-sidrene-cijene/`, and `[sidrena-arhiva]` | No item prices are rendered; these expose publication metadata and download links. A linked historical CSV/XML file follows its format contract above. | No item reference prices in the metadata itself. |
| `[sidrena_cijena]` | Yes; current price is shown by default. | The anchor is shown when available. The separate preceding-30-day block is shown once by default. `show_30_day="no"` opts out; `show_30_day="yes"` shows it once. |
| PHP `sidrena_cijena_render_anchor_price()` | Yes, when the caller requests the current price. | The renderer includes an available anchor and, by default, the preceding-30-day block for a classified sale. |
| WooCommerce product pages, cards, supported blocks/builders, mini-cart, cart and checkout | Yes; these use the effective WooCommerce price. | The Task 5 display paths include an available anchor and the preceding-30-day price for classified sales. See [`storefront-price-coverage.md`](storefront-price-coverage.md) for path limits. |
| WooCommerce Store API cart-item schema extension | It contains reference text, not a retail price field. | The text includes the anchor and the preceding-30-day price where available. The plugin registers no product-price schema or JSON-LD output. |
| WordPress widgets and other feed integrations | No standalone Sidrena Cijena widget or third-party product-feed integration is registered. The WooCommerce mini-cart price filter is covered above. | Not applicable to plugin-generated output. External widgets, SEO schema and feed plugins are outside this plugin’s control. |
| WordPress sitemap | The plugin adds only the archive URL; it contains no prices. | Not applicable. |

## Feed contract and legal scope

The applicable rule distinguishes the mandatory machine-readable price list
from shopper-facing advertising. Article 19(1), (4), and (6) of the Consumer
Protection Act, as amended by NN 59/2026, makes the preceding-30-day low the
reference for a special sale and applies that reference-price duty when goods
are advertised. Article 19(5) excludes easily perishable and quickly expiring
goods from the preceding-low rule. The Ministry of Economy's clarification,
sections 1.4–1.5, explicitly says website advertising must show the additional
fixed-date price and that an advertised special sale shows three prices: the
current reduced price, the preceding-30-day low, and the additional price.
Accordingly the public HTML catalog and its shortcode are treated as shopper-
facing sale advertising; they now show the 30-day low when the active sale has
a calculated value, alongside the existing anchor and date.

The Act separately requires current price lists on the trader's website
(Article 19(9), with publication details under Article 19(12)). The Ministry
clarification, sections 2.2 and 2.5, specifies those machine-readable lists as
CSV or XML and enumerates their product fields: retail price, special-sale
status/name, and the additional fixed-date price, among others. It does not
include the preceding-30-day low. Decisions NN 101/2026-1212 and 1213 take
effect on 1 October 2026. Sidrena Cijena's CSV/XML endpoints implement that
statutory price-list contract and remain unchanged. The public HTML catalog is
an additional shopper-facing presentation, not the prescribed machine-
readable price list.

The sources do not expressly state whether a CSV/XML file that fulfills the
Article 19(9) price-list duty can also be an advertisement subject to paragraph
6 when it contains a special-sale price. The implementation keeps those files
within the enumerated machine-readable price-list contract and adds no
undocumented field. This is a narrow **LEGAL UNCERTAINTY** about overlap between
the two statutory roles; it does not affect the clear three-price rule for the
shopper-facing HTML presentation.

Sources:

- [Consumer Protection Act amendment, NN 59/2026, Article 10](https://narodne-novine.nn.hr/clanci/sluzbeni/2026_06_59_728.html)
- [Decision NN 101/2026-1212](https://narodne-novine.nn.hr/clanci/sluzbeni/2026_09_101_1212.html)
- [Decision NN 101/2026-1213](https://narodne-novine.nn.hr/clanci/sluzbeni/2026_09_101_1213.html)
- [Consumer Protection Act, NN 19/2022, Article 19](https://narodne-novine.nn.hr/clanci/sluzbeni/2022_02_19_203.html)
- [Ministry of Economy clarification, 22 September 2026](https://mingo.gov.hr/vijesti/pojasnjenja-za-primjenu-dodatne-cijene-i-objavu-cjenika-od-1-listopada/10440)
