<?php
/**
 * Price-list generation service.
 *
 * @package SidrenaCijena\Export
 */

namespace SidrenaCijena\Export;

use SidrenaCijena\Catalog\CatalogProvider;
use SidrenaCijena\Database\LocationRepository;
use SidrenaCijena\Database\PriceHistoryRepository;
use SidrenaCijena\Database\PublicationRepository;
use SidrenaCijena\Database\SpecialSaleRepository;
use SidrenaCijena\Logging\Logger;
use SidrenaCijena\Plugin;
use SidrenaCijena\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Builds and publishes the daily price lists per sales location and format.
 */
class GenerationService {

	/**
	 * Catalog provider.
	 *
	 * @var CatalogProvider
	 */
	protected $catalog;

	/**
	 * Location repository.
	 *
	 * @var LocationRepository
	 */
	protected $locations;

	/**
	 * Publication repository.
	 *
	 * @var PublicationRepository
	 */
	protected $publications;

	/**
	 * Price history repository.
	 *
	 * @var PriceHistoryRepository
	 */
	protected $history;

	/**
	 * Special sale repository.
	 *
	 * @var SpecialSaleRepository
	 */
	protected $sales;

	/**
	 * Constructor.
	 *
	 * @param CatalogProvider        $catalog      Catalog provider.
	 * @param LocationRepository     $locations    Location repository.
	 * @param PublicationRepository  $publications Publication repository.
	 * @param PriceHistoryRepository $history      Price history repository.
	 * @param SpecialSaleRepository  $sales        Special sale repository.
	 */
	public function __construct( CatalogProvider $catalog, LocationRepository $locations, PublicationRepository $publications, PriceHistoryRepository $history, SpecialSaleRepository $sales ) {
		$this->catalog      = $catalog;
		$this->locations    = $locations;
		$this->publications = $publications;
		$this->history      = $history;
		$this->sales        = $sales;
	}

	/**
	 * Generate price lists for every active location.
	 *
	 * @param array<mixed> $args Arguments: `source` (`scheduled|manual|cli`), `type`, `force`.
	 * @return array<mixed>
	 */
	public function generate_all( array $args = array() ) {
		$results = array();

		foreach ( $this->locations->all( true ) as $location ) {
			$results[ (int) $location['id'] ] = $this->generate_location( (int) $location['id'], $args );
		}

		return $results;
	}

	/**
	 * Generate all configured formats for one location.
	 *
	 * @param int          $location_id Location ID.
	 * @param array<mixed> $args        Arguments.
	 * @return array<mixed>|\WP_Error
	 */
	public function generate_location( $location_id, array $args = array() ) {
		$location = $this->locations->get( $location_id );

		if ( ! $location ) {
			return new \WP_Error( 'location_not_found', __( 'The sales location could not be found.', 'sidrena-cijena-eu' ) );
		}

		$results = array();

		foreach ( $this->formats( $location ) as $format ) {
			$results[ $format ] = $this->generate( $location_id, $format, $args );
		}

		return $results;
	}

	/**
	 * Generate one price list.
	 *
	 * @param int          $location_id Location ID.
	 * @param string       $format      Format (`csv` or `xml`).
	 * @param array<mixed> $args        Arguments.
	 * @return array<mixed>|\WP_Error
	 */
	public function generate( $location_id, $format, array $args = array() ) {
		$location = $this->locations->get( $location_id );

		if ( ! $location ) {
			return new \WP_Error( 'location_not_found', __( 'The sales location could not be found.', 'sidrena-cijena-eu' ) );
		}

		$format = strtolower( (string) $format );

		if ( ! in_array( $format, array( 'csv', 'xml' ), true ) ) {
			return new \WP_Error( 'invalid_format', __( 'The requested format is not supported.', 'sidrena-cijena-eu' ) );
		}

		$source = isset( $args['source'] ) ? (string) $args['source'] : 'manual';
		$event  = 'scheduled' === $source ? 'export_scheduled' : 'export_manual';

		if ( empty( $location['identifier'] ) ) {
			$findings = array(
				array(
					'code'     => 'missing_store_identifier',
					'severity' => 'ERROR',
					'message'  => __( 'The sales location has no facility identifier.', 'sidrena-cijena-eu' ),
				),
			);

			$this->record_failure( $location_id, $format, $findings );

			return new \WP_Error( 'missing_store_identifier', $findings[0]['message'], array( 'findings' => $findings ) );
		}

		$validation = $this->catalog_report( $location_id );
		$findings   = self::publication_blockers( $validation['errors'] );

		if ( ! empty( $findings ) ) {
			$this->record_failure( $location_id, $format, $findings );

			return new \WP_Error( 'validation_failed', __( 'The price list failed compliance validation and was not published.', 'sidrena-cijena-eu' ), array( 'findings' => $findings ) );
		}

		$build = $this->builder()->build( $location, $args );

		$items = $build['items'];
		$meta  = $build['meta'];

		$fingerprint = $this->builder()->fingerprint( $items, $location, $format, (string) $meta['rule_version'] );

		$content = 'xml' === $format
			? ( new XmlWriter() )->to_string( $items, $location, $meta )
			: ( new CsvWriter() )->to_string( $items );

		$result = $this->publisher()->publish(
			$location,
			$format,
			$content,
			array(
				'fingerprint'        => $fingerprint,
				'item_count'         => count( $items ),
				'rule_version'       => (string) $meta['rule_version'],
				'validation_summary' => array(
					'errors'   => 0,
					'warnings' => (int) $validation['warnings'] + count( $validation['errors'] ) - count( $findings ),
				),
				'force'              => ! empty( $args['force'] ),
			)
		);

		if ( is_wp_error( $result ) ) {
			Logger::error(
				'export_failed',
				'The price list could not be published.',
				array(
					'location_id' => (int) $location_id,
					'format'      => $format,
					'code'        => $result->get_error_code(),
				)
			);

			/**
			 * Fires when a price-list publication fails.
			 *
			 * @param int          $location_id Location ID.
			 * @param string       $format      Format.
			 * @param array<mixed> $findings    Failure findings.
			 */
			do_action( 'sidrena_cijena_publication_failed', $location_id, $format, array() );

			return $result;
		}

		Logger::info(
			$event,
			empty( $result['skipped'] ) ? 'Price list published.' : 'Price list unchanged; the current version was kept.',
			array(
				'location_id' => (int) $location_id,
				'format'      => $format,
				'filename'    => isset( $result['filename'] ) ? $result['filename'] : '',
				'items'       => count( $items ),
				'skipped'     => ! empty( $result['skipped'] ),
			)
		);

		return array(
			'location_id' => (int) $location_id,
			'format'      => $format,
			'filename'    => isset( $result['filename'] ) ? $result['filename'] : '',
			'item_count'  => count( $items ),
			'skipped'     => ! empty( $result['skipped'] ),
			'publication' => isset( $result['publication'] ) ? $result['publication'] : null,
		);
	}

	/**
	 * Formats enabled for a location.
	 *
	 * @param array<mixed> $location Location row.
	 * @return string[]
	 */
	public function formats( array $location ) {
		$settings = (array) Settings::get( 'formats', array( 'csv', 'xml' ) );
		$config   = isset( $location['price_list_config'] ) && is_array( $location['price_list_config'] ) ? $location['price_list_config'] : array();

		if ( ! empty( $config['formats'] ) ) {
			$settings = array_intersect( $settings, (array) $config['formats'] );
		}

		$formats = array_values( array_intersect( array( 'csv', 'xml' ), $settings ) );

		return empty( $formats ) ? array( 'csv', 'xml' ) : $formats;
	}

	/**
	 * Catalog-level findings for a location, split by severity.
	 *
	 * @param int $location_id Location ID.
	 * @return array{errors:array<mixed>,warnings:int}
	 */
	protected function catalog_report( $location_id ) {
		$validator = Plugin::instance()->validator();

		$report = $validator->validate(
			array(
				'scope'       => 'catalog',
				'location_id' => (int) $location_id,
			)
		);

		$errors   = array();
		$warnings = 0;

		foreach ( $report['findings'] as $finding ) {
			if ( 'ERROR' === $finding->severity ) {
				$errors[] = $finding->to_array();
				continue;
			}

			++$warnings;
		}

		return array(
			'errors'   => $errors,
			'warnings' => $warnings,
		);
	}

	/**
	 * Missing retail/anchor evidence and unusable active-promotion references
	 * prevent a cjenik publication. Other findings remain warnings.
	 *
	 * @param array<mixed> $errors Error findings.
	 * @return array<mixed>
	 */
	public static function publication_blockers( array $errors ) {
		$required = array(
			'missing_anchor_price',
			'invalid_anchor_date',
			'invalid_retail_price',
			'missing_sale_type',
			'missing_30_day_price',
			'sale_price_not_below_reference',
		);

		return array_values(
			array_filter(
				$errors,
				static function ( $finding ) use ( $required ) {
					return isset( $finding['code'] ) && in_array( $finding['code'], $required, true );
				}
			)
		);
	}

	/**
	 * Store a failed publication row so the attempt stays auditable.
	 *
	 * @param int          $location_id Location ID.
	 * @param string       $format      Format.
	 * @param array<mixed> $findings    Findings.
	 * @return void
	 */
	protected function record_failure( $location_id, $format, array $findings ) {
		$id = $this->publications->insert(
			array(
				'location_id'        => (int) $location_id,
				'format'             => (string) $format,
				'status'             => 'failed',
				'rule_version'       => (string) \SidrenaCijena\Compliance\RuleConfig::get( 'rule_version', '' ),
				'validation_summary' => $findings,
			)
		);

		Logger::error(
			'export_failed',
			'Price-list generation was blocked by validation.',
			array(
				'location_id' => (int) $location_id,
				'format'      => (string) $format,
				'findings'    => count( $findings ),
			)
		);

		/**
		 * Fires when a price-list publication fails.
		 *
		 * @param int          $location_id Location ID.
		 * @param string       $format      Format.
		 * @param array<mixed> $findings    Failure findings.
		 */
		do_action( 'sidrena_cijena_publication_failed', $location_id, $format, $findings );
	}

	/**
	 * Row builder.
	 *
	 * @return PriceListBuilder
	 */
	protected function builder() {
		return new PriceListBuilder( $this->catalog, $this->history, $this->sales );
	}

	/**
	 * Publisher.
	 *
	 * @return Publisher
	 */
	protected function publisher() {
		return new Publisher( $this->locations, $this->publications, new FilenameGenerator() );
	}
}
