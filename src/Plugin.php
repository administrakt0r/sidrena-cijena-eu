<?php
/**
 * Plugin service container and hook wiring.
 *
 * The container selects the active catalog provider at runtime: WooCommerce
 * when it is available, otherwise the standalone catalog. No service ever
 * fatals when WooCommerce is absent.
 *
 * @package SidrenaCijena
 */

namespace SidrenaCijena;

use SidrenaCijena\Admin\AdminMenu;
use SidrenaCijena\Admin\AnchorMetaBox;
use SidrenaCijena\Admin\PublicationsPage;
use SidrenaCijena\Admin\SettingsPage;
use SidrenaCijena\Admin\SpecialSaleMetaBox;
use SidrenaCijena\Api\PriceListController;
use SidrenaCijena\Api\TemplateFunctions;
use SidrenaCijena\Catalog\CatalogProvider;
use SidrenaCijena\Catalog\StandaloneCatalogProvider;
use SidrenaCijena\Catalog\StandalonePostType;
use SidrenaCijena\Compliance\Validator;
use SidrenaCijena\Cron\Scheduler;
use SidrenaCijena\Database\LocationRepository;
use SidrenaCijena\Database\Migrator;
use SidrenaCijena\Database\PriceHistoryRepository;
use SidrenaCijena\Database\PublicationRepository;
use SidrenaCijena\Database\SpecialSaleRepository;
use SidrenaCijena\WooCommerce\WooCommerceCatalogProvider;
use SidrenaCijena\WooCommerce\WooCommerceDetector;
use SidrenaCijena\WooCommerce\Display\PriceDisplay;
use SidrenaCijena\WooCommerce\Display\VariationDisplay;

defined( 'ABSPATH' ) || exit;

/**
 * Service container.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Catalog provider.
	 *
	 * @var CatalogProvider|null
	 */
	private $catalog = null;

	/**
	 * Price history repository.
	 *
	 * @var PriceHistoryRepository|null
	 */
	private $history = null;

	/**
	 * Location repository.
	 *
	 * @var LocationRepository|null
	 */
	private $locations = null;

	/**
	 * Publication repository.
	 *
	 * @var PublicationRepository|null
	 */
	private $publications = null;

	/**
	 * Special sale repository.
	 *
	 * @var SpecialSaleRepository|null
	 */
	private $special_sales = null;

	/**
	 * Anchor price service.
	 *
	 * @var \SidrenaCijena\Pricing\AnchorPriceService|null
	 */
	private $anchors = null;

	/**
	 * Anchor cache.
	 *
	 * @var \SidrenaCijena\Pricing\AnchorCache|null
	 */
	private $anchor_cache = null;

	/**
	 * 30-day lowest price calculator.
	 *
	 * @var \SidrenaCijena\Pricing\LowestPrice30DayCalculator|null
	 */
	private $lowest_price = null;

	/**
	 * Special sale service.
	 *
	 * @var \SidrenaCijena\Pricing\SpecialSaleService|null
	 */
	private $special_sale_service = null;

	/**
	 * Validator.
	 *
	 * @var Validator|null
	 */
	private $validator = null;

	/**
	 * Generation service.
	 *
	 * @var \SidrenaCijena\Export\GenerationService|null
	 */
	private $generation = null;

	/**
	 * REST cache.
	 *
	 * @var \SidrenaCijena\Api\RestCache|null
	 */
	private $rest_cache = null;

	/**
	 * Singleton accessor.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Boot the plugin.
	 *
	 * @return void
	 */
	public static function boot() {
		self::instance()->register_hooks();
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register_standalone_catalog' ) );
		add_action( 'admin_init', array( Migrator::class, 'maybe_upgrade' ) );
		add_action( 'admin_menu', array( AdminMenu::class, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( AdminMenu::class, 'enqueue_assets' ) );
		add_action( 'save_post_' . StandalonePostType::POST_TYPE, array( StandalonePostType::class, 'save' ) );
		add_action( 'add_meta_boxes', array( StandalonePostType::class, 'register_meta_box' ) );
		add_action( 'init', array( TemplateFunctions::class, 'register' ) );
		add_action( 'admin_init', array( SettingsPage::class, 'register_settings' ) );
		add_action( 'add_meta_boxes', array( AnchorMetaBox::class, 'register' ) );
		add_action( 'add_meta_boxes', array( SpecialSaleMetaBox::class, 'register' ) );
		add_action( 'admin_post_' . AnchorMetaBox::ACTION, array( AnchorMetaBox::class, 'handle_record' ) );
		add_action( 'admin_post_' . SpecialSaleMetaBox::ACTION, array( SpecialSaleMetaBox::class, 'handle_save' ) );
		add_action( 'admin_notices', array( AnchorMetaBox::class, 'maybe_notice' ) );
		add_action( 'sidrena_cijena_settings_changed', array( $this, 'flush_caches' ) );
		add_action( 'sidrena_cijena_rule_config_changed', array( $this, 'flush_caches' ) );
		add_action( 'sidrena_cijena_price_changed', array( $this, 'flush_rest_cache' ) );
		add_action( 'sidrena_cijena_price_changed', array( $this, 'queue_service_regeneration' ), 10, 2 );
		add_action( 'sidrena_cijena_anchor_recorded', array( $this, 'flush_rest_cache' ) );
		add_action( 'sidrena_cijena_special_sale_changed', array( $this, 'flush_rest_cache' ) );
		add_action( 'sidrena_cijena_publication_published', array( $this, 'flush_rest_cache' ) );
		add_action( 'rest_api_init', array( PriceListController::class, 'register' ) );
		add_action( 'admin_post_' . PublicationsPage::ACTION, array( PublicationsPage::class, 'handle_generate' ) );

		Scheduler::register();

		if ( WooCommerceDetector::is_active() ) {
			( new PriceDisplay( $this->anchors() ) )->register();
			( new VariationDisplay( $this->anchors() ) )->register();
		}
	}

	/**
	 * Flush cached anchor data after settings or rule changes.
	 *
	 * @return void
	 */
	public function flush_caches() {
		$this->anchor_cache()->flush();
		$this->rest_cache()->flush();
	}

	/**
	 * Flush cached REST responses after price data changes.
	 *
	 * @return void
	 */
	public function flush_rest_cache() {
		$this->rest_cache()->flush();
	}

	/**
	 * Queue a prompt price-list regeneration when a service price changed.
	 *
	 * Product changes are covered by the daily run; service changes must be
	 * published no later than 08:00 on the day of the change (FR-041).
	 *
	 * @param int $product_id   Product ID.
	 * @param int $variation_id Variation ID.
	 * @return void
	 */
	public function queue_service_regeneration( $product_id, $variation_id = 0 ) {
		$item = (int) $variation_id > 0
			? $this->catalog()->get_variation( (int) $product_id, (int) $variation_id )
			: $this->catalog()->get_item( (int) $product_id );

		if ( ! $item || ! $item->is_service() ) {
			return;
		}

		Scheduler::queue_service_regeneration(
			array(
				'product_id'   => (int) $product_id,
				'variation_id' => (int) $variation_id,
			)
		);
	}

	/**
	 * Register the standalone catalog when it is the active source.
	 *
	 * @return void
	 */
	public function register_standalone_catalog() {
		if ( ! WooCommerceDetector::is_active() ) {
			StandalonePostType::register();

			return;
		}

		/**
		 * Filters whether the standalone catalog is also registered while
		 * WooCommerce is active.
		 *
		 * @param bool $enabled Default false.
		 */
		if ( apply_filters( 'sidrena_cijena_enable_standalone_catalog', false ) ) {
			StandalonePostType::register();
		}
	}

	/**
	 * Active catalog provider.
	 *
	 * @return CatalogProvider
	 */
	public function catalog() {
		if ( null === $this->catalog ) {
			$this->catalog = WooCommerceDetector::is_active()
				? new WooCommerceCatalogProvider()
				: new StandaloneCatalogProvider();
		}

		return $this->catalog;
	}

	/**
	 * Replace the catalog provider (tests and integrations).
	 *
	 * @param CatalogProvider $provider Provider.
	 * @return void
	 */
	public function set_catalog( CatalogProvider $provider ) {
		$this->catalog = $provider;
	}

	/**
	 * Price history repository.
	 *
	 * @return PriceHistoryRepository
	 */
	public function history() {
		if ( null === $this->history ) {
			$this->history = new PriceHistoryRepository();
		}

		return $this->history;
	}

	/**
	 * Location repository.
	 *
	 * @return LocationRepository
	 */
	public function locations() {
		if ( null === $this->locations ) {
			$this->locations = new LocationRepository();
		}

		return $this->locations;
	}

	/**
	 * Publication repository.
	 *
	 * @return PublicationRepository
	 */
	public function publications() {
		if ( null === $this->publications ) {
			$this->publications = new PublicationRepository();
		}

		return $this->publications;
	}

	/**
	 * Special sale repository.
	 *
	 * @return SpecialSaleRepository
	 */
	public function special_sales() {
		if ( null === $this->special_sales ) {
			$this->special_sales = new SpecialSaleRepository();
		}

		return $this->special_sales;
	}

	/**
	 * Anchor price service.
	 *
	 * @return \SidrenaCijena\Pricing\AnchorPriceService
	 */
	public function anchors() {
		if ( null === $this->anchors ) {
			$this->anchors = new \SidrenaCijena\Pricing\AnchorPriceService( $this->history() );
		}

		return $this->anchors;
	}

	/**
	 * Anchor cache.
	 *
	 * @return \SidrenaCijena\Pricing\AnchorCache
	 */
	public function anchor_cache() {
		if ( null === $this->anchor_cache ) {
			$this->anchor_cache = new \SidrenaCijena\Pricing\AnchorCache();
		}

		return $this->anchor_cache;
	}

	/**
	 * 30-day lowest price calculator.
	 *
	 * @return \SidrenaCijena\Pricing\LowestPrice30DayCalculator
	 */
	public function lowest_price() {
		if ( null === $this->lowest_price ) {
			$this->lowest_price = new \SidrenaCijena\Pricing\LowestPrice30DayCalculator();
		}

		return $this->lowest_price;
	}

	/**
	 * Special sale service.
	 *
	 * @return \SidrenaCijena\Pricing\SpecialSaleService
	 */
	public function special_sale_service() {
		if ( null === $this->special_sale_service ) {
			$this->special_sale_service = new \SidrenaCijena\Pricing\SpecialSaleService( $this->special_sales(), $this->history(), $this->lowest_price() );
		}

		return $this->special_sale_service;
	}

	/**
	 * Validator.
	 *
	 * @return Validator
	 */
	public function validator() {
		if ( null === $this->validator ) {
			$this->validator = new Validator( $this->catalog(), $this->history(), $this->locations(), $this->publications(), $this->special_sales() );
		}

		return $this->validator;
	}

	/**
	 * Generation service.
	 *
	 * @return \SidrenaCijena\Export\GenerationService
	 */
	public function generation() {
		if ( null === $this->generation ) {
			$this->generation = new \SidrenaCijena\Export\GenerationService( $this->catalog(), $this->locations(), $this->publications(), $this->history(), $this->special_sales() );
		}

		return $this->generation;
	}

	/**
	 * REST cache.
	 *
	 * @return \SidrenaCijena\Api\RestCache
	 */
	public function rest_cache() {
		if ( null === $this->rest_cache ) {
			$this->rest_cache = new \SidrenaCijena\Api\RestCache();
		}

		return $this->rest_cache;
	}
}
