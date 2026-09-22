<?php
/**
 * Unit tests for the validator rules and the compliance report.
 *
 * @package SidrenaCijena\Tests\Unit\Compliance
 */

namespace SidrenaCijena\Tests\Unit\Compliance;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Catalog\CatalogItem;
use SidrenaCijena\Compliance\ComplianceReport;
use SidrenaCijena\Compliance\Finding;
use SidrenaCijena\Compliance\Rules\AnchorRules;
use SidrenaCijena\Compliance\Rules\CatalogRules;
use SidrenaCijena\Compliance\Rules\PublicationRules;
use SidrenaCijena\Compliance\Rules\SaleRules;
use SidrenaCijena\Compliance\Validator;

/**
 * Covers every built-in rule with hand-built contexts.
 */
class ValidatorTest extends TestCase {

	/**
	 * Test the missing anchor rule and its severity.
	 *
	 * @return void
	 */
	public function test_missing_anchor_rule() {
		$rules    = $this->anchor_rules();
		$findings = $rules->missing_anchor_price( $this->item_context( array( $this->item( array( 'id' => 7, 'sku' => 'KAV-250' ) ) ) ) );

		$this->assertCount( 1, $findings );
		$this->assertSame( Finding::ERROR, $findings[0]->severity );
		$this->assertSame( 'missing_anchor_price', $findings[0]->code );
		$this->assertSame( 7, $findings[0]->entity_id );
		$this->assertFalse( $findings[0]->context['introduced_after'] );
	}

	/**
	 * An item introduced after the reference date is flagged as such.
	 *
	 * @return void
	 */
	public function test_missing_anchor_reports_introduced_after() {
		$rules    = $this->anchor_rules();
		$context  = $this->item_context( array( $this->item( array( 'id' => 8, 'first_seen' => '2026-10-01' ) ) ) );
		$findings = $rules->missing_anchor_price( $context );

		$this->assertCount( 1, $findings );
		$this->assertTrue( $findings[0]->context['introduced_after'] );
	}

	/**
	 * Items with an anchor record produce no missing-anchor finding.
	 *
	 * @return void
	 */
	public function test_anchor_present_skips_missing_rule() {
		$item    = $this->item( array( 'id' => 9 ) );
		$context = $this->item_context( array( $item ) );
		$context['anchors'][ $item->key() ] = array( 'id' => 3, 'price' => '7.49', 'reference_date' => '2026-09-10' );

		$rules = $this->anchor_rules();

		$this->assertSame( array(), $rules->missing_anchor_price( $context ) );
		$this->assertSame( array(), $rules->invalid_anchor_date( $context ) );
		$this->assertSame( array(), $rules->anchor_needs_verification( $context ) );
	}

	/**
	 * An anchor with an invalid reference date is an error.
	 *
	 * @return void
	 */
	public function test_invalid_anchor_date_rule() {
		$item    = $this->item( array( 'id' => 10 ) );
		$context = $this->item_context( array( $item ) );
		$context['anchors'][ $item->key() ] = array( 'id' => 4, 'price' => '7.49', 'reference_date' => '10.09.2026.' );

		$findings = $this->anchor_rules()->invalid_anchor_date( $context );

		$this->assertCount( 1, $findings );
		$this->assertSame( 'invalid_anchor_date', $findings[0]->code );
	}

	/**
	 * An anchor recorded for another reference date needs verification.
	 *
	 * @return void
	 */
	public function test_anchor_needs_verification_rule() {
		$item    = $this->item( array( 'id' => 11 ) );
		$context = $this->item_context( array( $item ) );
		$context['anchors'][ $item->key() ] = array( 'id' => 5, 'price' => '7.49', 'reference_date' => '2025-05-02' );

		$findings = $this->anchor_rules()->anchor_needs_verification( $context );

		$this->assertCount( 1, $findings );
		$this->assertSame( Finding::WARNING, $findings[0]->severity );
		$this->assertSame( 'anchor_needs_verification', $findings[0]->code );
	}

	/**
	 * Catalog data rules flag the documented problems.
	 *
	 * @return void
	 */
	public function test_catalog_rules() {
		$rules = $this->catalog_rules();

		$findings = $rules->missing_sku( $this->item_context( array( $this->item( array( 'sku' => '' ) ) ) ) );
		$this->assertSame( 'missing_sku', $findings[0]->code );

		$findings = $rules->missing_name( $this->item_context( array( $this->item( array( 'name' => '' ) ) ) ) );
		$this->assertSame( 'missing_name', $findings[0]->code );

		$findings = $rules->missing_barcode( $this->item_context( array( $this->item( array( 'barcode' => '' ) ) ) ) );
		$this->assertSame( Finding::WARNING, $findings[0]->severity );

		$findings = $rules->invalid_retail_price( $this->item_context( array( $this->item( array( 'regular_price' => null, 'sale_price' => null, 'current_price' => null ) ) ) ) );
		$this->assertSame( 'invalid_retail_price', $findings[0]->code );

		$findings = $rules->invalid_unit_price( $this->item_context( array( $this->item( array( 'unit_price' => -1.0 ) ) ) ) );
		$this->assertSame( 'invalid_unit_price', $findings[0]->code );

		$findings = $rules->invalid_availability( $this->item_context( array( $this->item( array( 'availability' => 'maybe' ) ) ) ) );
		$this->assertSame( 'invalid_availability', $findings[0]->code );

		$findings = $rules->invalid_currency( $this->item_context( array( $this->item( array( 'currency' => 'HRK' ) ) ) ) );
		$this->assertSame( 'invalid_currency', $findings[0]->code );
	}

	/**
	 * Duplicate SKUs inside a batch are warnings.
	 *
	 * @return void
	 */
	public function test_duplicate_product_records_rule() {
		$items = array(
			$this->item( array( 'id' => 20, 'sku' => 'DUP-1' ) ),
			$this->item( array( 'id' => 21, 'sku' => 'DUP-1' ) ),
		);

		$findings = $this->catalog_rules()->duplicate_product_records( $this->item_context( $items ) );

		$this->assertCount( 2, $findings );
		$this->assertSame( 'duplicate_product_records', $findings[0]->code );
	}

	/**
	 * An unclassified promotion is an error.
	 *
	 * @return void
	 */
	public function test_missing_sale_type_rule() {
		$item     = $this->item( array( 'id' => 30, 'regular_price' => 20.0, 'sale_price' => 15.0 ) );
		$findings = $this->sale_rules()->missing_sale_type( $this->item_context( array( $item ) ) );

		$this->assertCount( 1, $findings );
		$this->assertSame( 'missing_sale_type', $findings[0]->code );
		$this->assertSame( Finding::ERROR, $findings[0]->severity );
	}

	/**
	 * A classified sale without a 30-day price is an error.
	 *
	 * @return void
	 */
	public function test_missing_30_day_price_rule() {
		$item    = $this->item( array( 'id' => 31, 'regular_price' => 20.0, 'sale_price' => 15.0 ) );
		$context = $this->item_context( array( $item ) );
		$context['sales'][ $item->key() ] = array( 'id' => 9, 'sale_type' => 'akcija', 'lowest_30_day_price' => null );

		$findings = $this->sale_rules()->missing_30_day_price( $context );

		$this->assertCount( 1, $findings );
		$this->assertSame( 'missing_30_day_price', $findings[0]->code );

		$context['sales'][ $item->key() ]['lowest_30_day_price'] = 12.5;

		$this->assertSame( array(), $this->sale_rules()->missing_30_day_price( $context ) );
	}

	/**
	 * Publication rules cover identifiers, filenames and archives.
	 *
	 * @return void
	 */
	public function test_publication_rules() {
		$rules = $this->publication_rules();

		$context = $this->global_context( array( $this->location( array( 'identifier' => '' ) ) ) );

		$findings = $rules->missing_store_identifier( $context );
		$this->assertSame( 'missing_store_identifier', $findings[0]->code );

		$findings = $rules->missing_archived_price_list( $context );
		$this->assertCount( 2, $findings );
		$this->assertSame( 'missing_archived_price_list', $findings[0]->code );
	}

	/**
	 * An invalid filename is an error and a valid one is not.
	 *
	 * @return void
	 */
	public function test_invalid_filename_rule() {
		$rules   = $this->publication_rules();
		$context = $this->global_context( array( $this->location() ) );

		$context['publications']['1:csv'] = array(
			'id'          => 1,
			'format'      => 'csv',
			'filename'    => 'cjenik.csv',
			'location_id' => 1,
		);

		$findings = $rules->invalid_filename( $context );
		$this->assertCount( 1, $findings );
		$this->assertSame( 'invalid_filename', $findings[0]->code );

		$context['publications']['1:csv']['filename'] = 'PRODAVAONICA_VLASKA68_ZAGREB_P1_01_01102026_0750.CSV';

		$this->assertSame( array(), $rules->invalid_filename( $context ) );
	}

	/**
	 * Stale publications are warnings.
	 *
	 * @return void
	 */
	public function test_stale_price_list_rule() {
		$rules   = $this->publication_rules();
		$context = $this->global_context( array( $this->location() ) );
		$context['today'] = '2026-10-02';
		$context['publications']['1:csv'] = array(
			'id'           => 2,
			'format'       => 'csv',
			'filename'     => 'PRODAVAONICA_VLASKA68_ZAGREB_P1_01_01102026_0750.CSV',
			'location_id'  => 1,
			'published_at' => '2026-10-01 06:00:00',
		);

		$findings = $rules->stale_price_list( $context );

		$this->assertCount( 1, $findings );
		$this->assertSame( 'stale_price_list', $findings[0]->code );
		$this->assertSame( Finding::WARNING, $findings[0]->severity );
	}

	/**
	 * Malformed CSV and XML files are detected.
	 *
	 * @return void
	 */
	public function test_malformed_files_rule() {
		$rules = $this->publication_rules();
		$dir   = sys_get_temp_dir() . '/sidrena-cijena-validator-' . uniqid();
		mkdir( $dir );

		$csv = $dir . '/broken.CSV';
		$xml = $dir . '/broken.XML';
		file_put_contents( $csv, "wrong;header\n1;2\n" );
		file_put_contents( $xml, '<cjenik><stavka></cjenik>' );

		$context = $this->global_context( array( $this->location() ) );
		$context['publications']['1:csv'] = array( 'id' => 3, 'format' => 'csv', 'filename' => 'BROKEN.CSV', 'location_id' => 1 );
		$context['publications']['1:xml'] = array( 'id' => 4, 'format' => 'xml', 'filename' => 'BROKEN.XML', 'location_id' => 1 );
		$context['publication_files']['1:csv'] = $csv;
		$context['publication_files']['1:xml'] = $xml;

		$csv_findings = $rules->malformed_csv( $context );
		$xml_findings = $rules->malformed_xml( $context );

		$this->assertSame( 'malformed_csv', $csv_findings[0]->code );
		$this->assertSame( 'malformed_xml', $xml_findings[0]->code );

		$valid = $dir . '/VALID.CSV';
		file_put_contents( $valid, "vrsta;naziv;sifra;marka;jedinica_mjere;cijena_za_jedinicu_mjere;maloprodajna_cijena;poseban_oblik_prodaje;naziv_posebnog_oblika_prodaje;sidrena_cijena;barkod;dostupnost\n" );
		$context['publication_files']['1:csv'] = $valid;

		$this->assertSame( array(), $rules->malformed_csv( $context ) );

		unlink( $csv );
		unlink( $xml );
		unlink( $valid );
		rmdir( $dir );
	}

	/**
	 * Severity filtering and counting.
	 *
	 * @return void
	 */
	public function test_counts_and_filtering() {
		$validator = $this->validator();
		$findings  = array(
			Finding::error( 'a', 'A' ),
			Finding::warning( 'b', 'B' ),
			Finding::info( 'c', 'C' ),
		);

		$counts = Validator::counts( $findings );

		$this->assertSame( 1, $counts[ Finding::ERROR ] );
		$this->assertSame( 1, $counts[ Finding::WARNING ] );
		$this->assertSame( 1, $counts[ Finding::INFO ] );
		$this->assertSame( 3, $counts['total'] );

		$this->assertCount( 3, $validator->filter_by_severity( $findings, '' ) );
		$this->assertCount( 1, $validator->filter_by_severity( $findings, Finding::ERROR ) );
		$this->assertCount( 2, $validator->filter_by_severity( $findings, Finding::WARNING ) );
	}

	/**
	 * The report exports stable CSV and JSON.
	 *
	 * @return void
	 */
	public function test_compliance_report_exports() {
		$report = new ComplianceReport(
			array(
				Finding::error( 'missing_anchor_price', 'No anchor; for "item".', array( 'entity_id' => 5 ) ),
				Finding::warning( 'missing_barcode', 'No barcode.', array( 'entity_id' => 6 ) ),
			),
			array( 'rule_version' => '2026-10-01' )
		);

		$this->assertSame( 1, $report->counts()[ Finding::ERROR ] );
		$this->assertArrayHasKey( 'missing_anchor_price', $report->counts_by_code() );

		$csv   = $report->to_csv();
		$lines = explode( "\n", trim( $csv ) );

		$this->assertSame( 'severity;code;entity_type;entity_id;message;rule_reference', $lines[0] );
		$this->assertStringContainsString( '"No anchor; for ""item""."', $csv );

		$json = json_decode( $report->to_json(), true );

		$this->assertSame( 2, $json['counts']['total'] );
		$this->assertSame( '2026-10-01', $json['rule_version'] );

		$entities = $report->group_by_entity();

		$this->assertCount( 2, $entities );
	}

	/**
	 * Validator instance with dummy dependencies.
	 *
	 * @return Validator
	 */
	protected function validator() {
		return new Validator( (object) array(), (object) array(), (object) array(), (object) array(), (object) array() );
	}

	/**
	 * Anchor rules instance.
	 *
	 * @return AnchorRules
	 */
	protected function anchor_rules() {
		return new AnchorRules( $this->validator() );
	}

	/**
	 * Catalog rules instance.
	 *
	 * @return CatalogRules
	 */
	protected function catalog_rules() {
		return new CatalogRules( $this->validator() );
	}

	/**
	 * Sale rules instance.
	 *
	 * @return SaleRules
	 */
	protected function sale_rules() {
		return new SaleRules( $this->validator() );
	}

	/**
	 * Publication rules instance.
	 *
	 * @return PublicationRules
	 */
	protected function publication_rules() {
		return new PublicationRules( $this->validator() );
	}

	/**
	 * Build an item context.
	 *
	 * @param CatalogItem[] $items Items.
	 * @return array<mixed>
	 */
	protected function item_context( array $items ) {
		$reference_dates = array();

		foreach ( $items as $item ) {
			$reference_dates[ $item->key() ] = array(
				'date'   => '2026-09-10',
				'rule'   => 'general',
				'legacy' => false,
			);
		}

		return array(
			'args'              => array(),
			'items'             => $items,
			'anchors'           => array(),
			'reference_dates'   => $reference_dates,
			'sales'             => array(),
			'expected_currency' => 'EUR',
			'now'               => '2026-09-21 08:00:00',
			'today'             => '2026-09-21',
		);
	}

	/**
	 * Build a global context.
	 *
	 * @param array<mixed> $locations Locations.
	 * @return array<mixed>
	 */
	protected function global_context( array $locations ) {
		return array(
			'args'              => array(),
			'locations'         => $locations,
			'publications'      => array(),
			'publication_files' => array(),
			'last_successful'   => null,
			'expected_currency' => 'EUR',
			'now'               => '2026-09-21 08:00:00',
			'today'             => '2026-09-21',
			'deadline'          => '08:00',
			'is_working_day'    => true,
		);
	}

	/**
	 * Build a location row.
	 *
	 * @param array<mixed> $overrides Overrides.
	 * @return array<mixed>
	 */
	protected function location( array $overrides = array() ) {
		return array_merge(
			array(
				'id'                => 1,
				'name'              => 'Prodavaonica Vlaška 68',
				'type'              => 'prodavaonica',
				'address'           => 'Vlaška 68',
				'city'              => 'Zagreb',
				'identifier'        => 'P1',
				'active'            => 1,
				'price_list_config' => array(),
			),
			$overrides
		);
	}

	/**
	 * Build a catalog item.
	 *
	 * @param array<mixed> $overrides Overrides.
	 * @return CatalogItem
	 */
	protected function item( array $overrides = array() ) {
		return new CatalogItem(
			array_merge(
				array(
					'id'            => 1,
					'type'          => 'product',
					'name'          => 'Kava mljevena 250 g',
					'sku'           => 'KAV-250',
					'brand'         => 'Franck',
					'barcode'       => '3851234567890',
					'unit'          => 'kg',
					'unit_price'    => 28.0,
					'currency'      => 'EUR',
					'availability'  => 'available',
					'regular_price' => 6.99,
					'sale_price'    => null,
					'current_price' => 6.99,
					'tax_inclusive' => true,
					'first_seen'    => '2025-01-10',
					'categories'    => array(),
					'source'        => 'standalone',
				),
				$overrides
			)
		);
	}
}
