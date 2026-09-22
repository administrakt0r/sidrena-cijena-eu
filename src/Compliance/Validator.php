<?php
/**
 * Central compliance validator.
 *
 * Findings are computed on demand and never modify data (FR-058 to FR-061).
 * Rule callbacks receive a pre-loaded context so rules stay pure and testable.
 *
 * @package SidrenaCijena\Compliance
 */

namespace SidrenaCijena\Compliance;

use SidrenaCijena\Catalog\CatalogItem;
use SidrenaCijena\Catalog\CatalogProvider;
use SidrenaCijena\Compliance\Rules\AnchorRules;
use SidrenaCijena\Compliance\Rules\CatalogRules;
use SidrenaCijena\Compliance\Rules\PublicationRules;
use SidrenaCijena\Compliance\Rules\SaleRules;
use SidrenaCijena\Database\LocationRepository;
use SidrenaCijena\Database\PriceHistoryRepository;
use SidrenaCijena\Database\PublicationRepository;
use SidrenaCijena\Database\SpecialSaleRepository;
use SidrenaCijena\Plugin;
use SidrenaCijena\Support\Currency;
use SidrenaCijena\Support\Dates;
use SidrenaCijena\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Runs the registered validation rules.
 */
class Validator {

	/**
	 * Severity order.
	 *
	 * @var string[]
	 */
	const SEVERITIES = array( Finding::ERROR, Finding::WARNING, Finding::INFO );

	/**
	 * Catalog provider.
	 *
	 * @var CatalogProvider
	 */
	protected $catalog;

	/**
	 * Price history repository.
	 *
	 * @var PriceHistoryRepository
	 */
	protected $history;

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
	 * Special sale repository.
	 *
	 * @var SpecialSaleRepository
	 */
	protected $special_sales;

	/**
	 * Item-scoped rules: code => callable.
	 *
	 * @var array<string,callable>
	 */
	protected $item_rules = array();

	/**
	 * Global (location/publication) rules: code => callable.
	 *
	 * @var array<string,callable>
	 */
	protected $global_rules = array();

	/**
	 * Constructor.
	 *
	 * @param CatalogProvider|null        $catalog       Catalog provider.
	 * @param PriceHistoryRepository|null $history       History repository.
	 * @param LocationRepository|null     $locations     Location repository.
	 * @param PublicationRepository|null  $publications  Publication repository.
	 * @param SpecialSaleRepository|null  $special_sales Special sale repository.
	 */
	public function __construct( $catalog = null, $history = null, $locations = null, $publications = null, $special_sales = null ) {
		$plugin = Plugin::instance();

		$this->catalog       = $catalog ? $catalog : $plugin->catalog();
		$this->history       = $history ? $history : $plugin->history();
		$this->locations     = $locations ? $locations : $plugin->locations();
		$this->publications  = $publications ? $publications : $plugin->publications();
		$this->special_sales = $special_sales ? $special_sales : $plugin->special_sales();
	}

	/**
	 * Register a rule.
	 *
	 * @param string   $code     Stable finding code.
	 * @param callable $callback Callback receiving the validation context.
	 * @param string   $scope    `item` or `global`.
	 * @return void
	 */
	public function register( $code, $callback, $scope = 'item' ) {
		if ( 'global' === $scope ) {
			$this->global_rules[ $code ] = $callback;

			return;
		}

		$this->item_rules[ $code ] = $callback;
	}

	/**
	 * Register the built-in rules.
	 *
	 * @return void
	 */
	public function register_default_rules() {
		$rule_sets = array(
			new AnchorRules( $this ),
			new CatalogRules( $this ),
			new SaleRules( $this ),
			new PublicationRules( $this ),
		);

		foreach ( $rule_sets as $rules ) {
			$rules->register();
		}
	}

	/**
	 * Registered item rule codes.
	 *
	 * @return string[]
	 */
	public function item_rule_codes() {
		return array_keys( $this->item_rules );
	}

	/**
	 * Registered global rule codes.
	 *
	 * @return string[]
	 */
	public function global_rule_codes() {
		return array_keys( $this->global_rules );
	}

	/**
	 * Run a compliance check.
	 *
	 * @param array<mixed> $args `product_id`, `variation_id`, `location_id`, `severity`, `scope`, `max_items`, `per_page`.
	 * @return array{findings:Finding[],counts:array<string,int>}
	 */
	public function validate( array $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'product_id'   => 0,
				'variation_id' => 0,
				'location_id'  => 0,
				'severity'     => '',
				'scope'        => 'all',
				'max_items'    => 0,
				'per_page'     => 200,
			)
		);

		if ( empty( $this->item_rules ) && empty( $this->global_rules ) ) {
			$this->register_default_rules();
		}

		$findings = array();

		if ( in_array( $args['scope'], array( 'all', 'publications' ), true ) ) {
			$findings = array_merge( $findings, $this->validate_global( $args ) );
		}

		if ( in_array( $args['scope'], array( 'all', 'catalog' ), true ) ) {
			$count = 0;
			$batch = array();

			foreach ( $this->catalog->iterate( array( 'per_page' => max( 1, (int) $args['per_page'] ) ) ) as $item ) {
				if ( ! empty( $args['product_id'] ) && (int) $item->id !== (int) $args['product_id'] ) {
					continue;
				}

				if ( ! empty( $args['variation_id'] ) && (int) $item->variation_id !== (int) $args['variation_id'] ) {
					continue;
				}

				$batch[] = $item;
				++$count;

				if ( count( $batch ) >= max( 1, (int) $args['per_page'] ) ) {
					$findings = array_merge( $findings, $this->validate_items( $batch, $args ) );
					$batch    = array();
				}

				if ( ! empty( $args['max_items'] ) && $count >= (int) $args['max_items'] ) {
					break;
				}
			}

			if ( ! empty( $batch ) ) {
				$findings = array_merge( $findings, $this->validate_items( $batch, $args ) );
			}
		}

		$findings = $this->filter_by_severity( $findings, $args['severity'] );

		return array(
			'findings' => $findings,
			'counts'   => self::counts( $findings ),
		);
	}

	/**
	 * Run only the global (location/publication) rules.
	 *
	 * @param array<mixed> $args Arguments.
	 * @return Finding[]
	 */
	public function validate_global( array $args = array() ) {
		if ( empty( $this->item_rules ) && empty( $this->global_rules ) ) {
			$this->register_default_rules();
		}

		$context  = $this->build_global_context( $args );
		$findings = array();

		foreach ( $this->global_rules as $callback ) {
			$findings = array_merge( $findings, (array) call_user_func( $callback, $context ) );
		}

		return $findings;
	}

	/**
	 * Validate a batch of items (used for batched admin checks and tests).
	 *
	 * @param CatalogItem[] $items Items.
	 * @param array         $args  Arguments.
	 * @return Finding[]
	 */
	public function validate_items( array $items, array $args = array() ) {
		if ( empty( $this->item_rules ) && empty( $this->global_rules ) ) {
			$this->register_default_rules();
		}

		$context  = $this->build_item_context( $items, $args );
		$findings = array();

		foreach ( $this->item_rules as $callback ) {
			$findings = array_merge( $findings, (array) call_user_func( $callback, $context ) );
		}

		return $findings;
	}

	/**
	 * Build the context passed to item-scoped rules.
	 *
	 * @param CatalogItem[] $items Items.
	 * @param array         $args  Arguments.
	 * @return array<mixed>
	 */
	public function build_item_context( array $items, array $args = array() ) {
		$product_ids = array();

		foreach ( $items as $item ) {
			$product_ids[] = (int) $item->id;
		}

		$anchors = $this->history->latest_map( $product_ids, 'anchor' );

		$reference_dates = array();

		foreach ( $items as $item ) {
			$reference_dates[ $item->key() ] = ReferenceDateResolver::resolve( $item );
		}

		return array(
			'args'              => $args,
			'items'             => $items,
			'anchors'           => $anchors,
			'reference_dates'   => $reference_dates,
			'sales'             => $this->special_sales->active_map(),
			'expected_currency' => $this->expected_currency(),
			'now'               => Dates::now_site()->format( 'Y-m-d H:i:s' ),
			'today'             => Dates::today(),
		);
	}

	/**
	 * Build the context passed to global rules.
	 *
	 * @param array<mixed> $args Arguments.
	 * @return array<mixed>
	 */
	public function build_global_context( array $args = array() ) {
		$locations = $this->locations->all( true );

		if ( ! empty( $args['location_id'] ) ) {
			$locations = array_values(
				array_filter(
					$locations,
					function ( $location ) use ( $args ) {
						return (int) $location['id'] === (int) $args['location_id'];
					}
				)
			);
		}

		$latest = array();
		$files  = array();

		foreach ( $locations as $location ) {
			foreach ( array( 'csv', 'xml' ) as $format ) {
				$row = $this->publications->latest( (int) $location['id'], $format );

				if ( $row ) {
					$latest[ (int) $location['id'] . ':' . $format ] = $row;

					if ( ! empty( $row['path'] ) && is_readable( $row['path'] ) ) {
						$files[ (int) $location['id'] . ':' . $format ] = $row['path'];
					}
				}
			}
		}

		return array(
			'args'              => $args,
			'locations'         => $locations,
			'publications'      => $latest,
			'publication_files' => $files,
			'last_successful'   => $this->publications->last_successful(),
			'expected_currency' => $this->expected_currency(),
			'now'               => Dates::now_site()->format( 'Y-m-d H:i:s' ),
			'today'             => Dates::today(),
			'deadline'          => (string) Settings::get( 'deadline', '08:00' ),
			'is_working_day'    => Dates::is_working_day( Dates::today() ),
		);
	}

	/**
	 * Expected catalog currency.
	 *
	 * @return string
	 */
	protected function expected_currency() {
		/**
		 * Filters the currency expected in price records.
		 *
		 * @param string $currency Currency code.
		 */
		return (string) apply_filters( 'sidrena_cijena_expected_currency', Currency::DEFAULT_CURRENCY );
	}

	/**
	 * Filter findings by severity.
	 *
	 * @param Finding[] $findings Findings.
	 * @param string    $severity Minimum severity or empty for all.
	 * @return Finding[]
	 */
	public function filter_by_severity( array $findings, $severity ) {
		if ( ! in_array( $severity, self::SEVERITIES, true ) ) {
			return $findings;
		}

		$order = array_flip( self::SEVERITIES );
		$limit = $order[ $severity ];

		return array_values(
			array_filter(
				$findings,
				function ( $finding ) use ( $order, $limit ) {
					return $order[ $finding->severity ] <= $limit;
				}
			)
		);
	}

	/**
	 * Severity counts.
	 *
	 * @param Finding[] $findings Findings.
	 * @return array<string,int>
	 */
	public static function counts( array $findings ) {
		$counts = array(
			Finding::ERROR   => 0,
			Finding::WARNING => 0,
			Finding::INFO    => 0,
			'total'          => 0,
		);

		foreach ( $findings as $finding ) {
			if ( isset( $counts[ $finding->severity ] ) ) {
				++$counts[ $finding->severity ];
			}

			++$counts['total'];
		}

		return $counts;
	}
}
