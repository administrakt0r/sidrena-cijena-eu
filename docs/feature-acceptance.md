# Requested feature acceptance

| Requested behavior | Implementation and requirements |
|--------------------|---------------------------------|
| 10.09.2026 reference price; 02.05.2025 for food, beverages, cosmetics, cleaning, toiletries and household goods | Both defaults exist. Merchants must map their actual legacy category slugs in legal-rule settings. Names alone cannot reliably classify products. Existing historical prices require evidenced manual entry or import. |
| Lowest price in the preceding 30 days during reductions | Regular/sale history is recorded; the separate minimum is displayed for a **classified special sale**. Classify the sale in Sidrena settings; a WooCommerce sale-price field alone is not a legal classification. History must have been collected or evidenced/imported. |
| Daily price list and 30+ day archive | CSV/XML generation, location-specific names and minimum 30-day retention exist. Configure complete locations and reliable cron. See filename-profile.md for the retained format and unresolved legal-format review. Deadline verification remains tracked in REL-06. |
| Products, variations and services, unlimited count | No licence/product-count cap. Providers iterate in batches; WooCommerce variable parents expand to their individually priced variations. Services are supported by the standalone catalog and `_sidrena_item_type=service` for WooCommerce. Hosting resources still limit throughput. |
| Automatic reference price/date for newly published products | From 1.0.2, prospective first-offer capture observes new entries created with the plugin active, waits for publication and a price, and records the actual offered price plus observed site date when on/after the applicable baseline. Drafts and variable parent ranges are excluded; each variation has separate evidence. |

## First-offer boundaries

This is contemporaneous evidence of an offer witnessed by this shop. It is not
proof that the merchant never sold the item offline or elsewhere. Existing goods
migrated into a new WordPress entry must be imported as historical catalog data,
not presented as newly introduced offers. WooCommerce's product importer and
WordPress imports declaring `WP_IMPORTING` are excluded. Custom migration code
must define `WP_IMPORTING` before inserting products. Existing entries without a
witnessed insertion are never backfilled from their current price or creation date.

Publication before the applicable baseline is observed but does not create an
anchor for a future date. Later editing does not turn that old offer into a new
one. Existing evidenced anchors always take precedence. Later price edits,
unpublishing and republishing cannot replace the first observation. Corrections
use the normal append-only workflow with a reason. Failed recording is logged;
the validator continues to show a missing anchor rather than guessing on retry.

Standalone automatic capture requires a tax-inclusive price; WooCommerce capture
uses its tax-inclusive retail-price calculation. Merchant category mapping,
currency and tax settings must be correct before publishing an offer.

Verification includes integration tests for prospective capture, preservation,
services, zero prices, missing eligibility, copied metadata and pre-baseline offers;
E2E exercises real WooCommerce simple/variation saves and importer exclusion.
