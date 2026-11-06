<?php
/**
 * Actionable remediation proposals for compliance findings.
 *
 * @package SidrenaCijena\Compliance
 */

namespace SidrenaCijena\Compliance;

use SidrenaCijena\Compliance\RuleConfig;

defined( 'ABSPATH' ) || exit;

/**
 * Provides concrete, actionable proposals on what merchants need to change
 * when compliance errors or warnings are detected.
 */
class RemediationProposer {

	/**
	 * Map a finding code to an actionable proposal.
	 *
	 * @param string       $code    Finding code.
	 * @param array<mixed> $context Additional structured finding context.
	 * @return string Actionable proposal text.
	 */
	public static function propose( $code, array $context = array() ) {
		$code = (string) $code;

		switch ( $code ) {
			case 'missing_anchor_price':
				$ref_date = isset( $context['reference_date'] ) && '' !== $context['reference_date']
					? (string) $context['reference_date']
					: (string) RuleConfig::get( 'general_reference_date', RuleConfig::DEFAULT_GENERAL_DATE );

				return sprintf(
					/* translators: %s: reference date (YYYY-MM-DD) */
					__( 'Record an anchor price for this item for reference date %s. In the product editor under the "Sidrena cijena" box, enter the retail price valid on that date, or use CSV import to record anchors in bulk.', 'titanworks-sidrena-bazna-cijena' ),
					$ref_date
				);

			case 'invalid_anchor_date':
				$gen_date = (string) RuleConfig::get( 'general_reference_date', RuleConfig::DEFAULT_GENERAL_DATE );
				$leg_date = (string) RuleConfig::get( 'legacy_reference_date', RuleConfig::DEFAULT_LEGACY_DATE );

				return sprintf(
					/* translators: 1: general reference date, 2: legacy reference date */
					__( 'Update the anchor reference date to the statutory date (%1$s, or %2$s for legacy categories) with a mandatory correction reason.', 'titanworks-sidrena-bazna-cijena' ),
					$gen_date,
					$leg_date
				);

			case 'anchor_needs_verification':
				return __( 'Verify that the recorded anchor price matches your historical price records for the designated reference date.', 'titanworks-sidrena-bazna-cijena' );

			case 'invalid_retail_price':
				return __( 'Set a valid retail price greater than 0,00 € for this item in the product editor.', 'titanworks-sidrena-bazna-cijena' );

			case 'missing_sale_type':
				return __( 'Classify this promotional price by selecting a statutory sale type ("akcija", "sniženje", "sezonsko sniženje", or "rasprodaja") in the special sale metabox.', 'titanworks-sidrena-bazna-cijena' );

			case 'missing_30_day_price':
				return __( 'Record or calculate the lowest price applied during the 30 days prior to the start of this special sale.', 'titanworks-sidrena-bazna-cijena' );

			case 'sale_price_not_below_reference':
				return __( 'Reduce the promotional sale price so that it is strictly lower than the lowest price applied in the prior 30 days, or verify the 30-day reference price.', 'titanworks-sidrena-bazna-cijena' );

			case 'missing_store_identifier':
				return __( 'Set a unique facility identifier (e.g. "P1") for this sales location under Sidrena cijena → Prodajna mjesta.', 'titanworks-sidrena-bazna-cijena' );

			case 'missing_location_address':
				return __( 'Enter the official street address, house number, and city in Sidrena cijena → Prodajna mjesta or in WooCommerce → Settings → General.', 'titanworks-sidrena-bazna-cijena' );

			case 'invalid_filename':
				return __( 'Verify the sales location type, address, and facility identifier to ensure a compliant price-list filename can be formed.', 'titanworks-sidrena-bazna-cijena' );

			case 'missing_sku':
				return __( 'Assign a SKU (product code) in the product inventory settings.', 'titanworks-sidrena-bazna-cijena' );

			case 'missing_barcode':
				return __( 'Add a barcode (EAN/GTIN) if required for this product category.', 'titanworks-sidrena-bazna-cijena' );

			case 'missing_name':
				return __( 'Enter a valid product title/name in the product editor.', 'titanworks-sidrena-bazna-cijena' );

			case 'invalid_currency':
				return __( 'Set the store and product currency to EUR in WooCommerce or WordPress settings.', 'titanworks-sidrena-bazna-cijena' );

			case 'invalid_availability':
				return __( 'Set the stock availability status ("available" or "unavailable") for this item.', 'titanworks-sidrena-bazna-cijena' );

			case 'duplicate_product_records':
				return __( 'Consolidate duplicate catalog entries sharing the same SKU or identifier.', 'titanworks-sidrena-bazna-cijena' );

			case 'stale_price_list':
				return __( 'Generate a fresh daily price list (click "Generate now" or run `wp sidrena-cijena generate`) to maintain current daily compliance before 08:00.', 'titanworks-sidrena-bazna-cijena' );

			case 'missing_archived_price_list':
				return __( 'Generate a price list for this sales location to initiate the mandatory 30-day compliance archive.', 'titanworks-sidrena-bazna-cijena' );

			case 'malformed_csv':
				return __( 'Regenerate the CSV price list to ensure semicolon delimiters, UTF-8 encoding, and comma decimals.', 'titanworks-sidrena-bazna-cijena' );

			case 'malformed_xml':
				return __( 'Regenerate the XML price list to ensure valid root and item elements.', 'titanworks-sidrena-bazna-cijena' );

			default:
				return '';
		}
	}
}
