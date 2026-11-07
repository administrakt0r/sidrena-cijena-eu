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

namespace Titanworks\SidrenaCijena;

use Titanworks\SidrenaCijena\Admin\AdminMenu;
use Titanworks\SidrenaCijena\Admin\AnchorMetaBox;
use Titanworks\SidrenaCijena\Admin\BulkAnchorEntryPage;
use Titanworks\SidrenaCijena\Admin\CompliancePage;
use Titanworks\SidrenaCijena\Admin\ImportPage;
use Titanworks\SidrenaCijena\Admin\LocationsPage;
use Titanworks\SidrenaCijena\Admin\PriceHistoryPage;
use Titanworks\SidrenaCijena\Admin\PublicationsPage;
use Titanworks\SidrenaCijena\Admin\SettingsPage;
use Titanworks\SidrenaCijena\Admin\SetupGuidePage;
use Titanworks\SidrenaCijena\Admin\SpecialSaleMetaBox;
use Titanworks\SidrenaCijena\Admin\WelcomeBanner;
use Titanworks\SidrenaCijena\Api\PriceListController;
use Titanworks\SidrenaCijena\Api\PublicFeed;
use Titanworks\SidrenaCijena\Api\PublicPage;
use Titanworks\SidrenaCijena\Api\TemplateFunctions;
use Titanworks\SidrenaCijena\Catalog\CatalogProvider;
use Titanworks\SidrenaCijena\Catalog\StandaloneCatalogProvider;
use Titanworks\SidrenaCijena\Catalog\StandalonePostType;
use Titanworks\SidrenaCijena\Cli\Cli;
use Titanworks\SidrenaCijena\Compliance\Validator;
use Titanworks\SidrenaCijena\Cron\Scheduler;
use Titanworks\SidrenaCijena\Database\LocationRepository;
use Titanworks\SidrenaCijena\Database\Migrator;
use Titanworks\SidrenaCijena\Database\PriceHistoryRepository;
use Titanworks\SidrenaCijena\Database\PublicationRepository;
use Titanworks\SidrenaCijena\Database\SpecialSaleRepository;
use Titanworks\SidrenaCijena\Support\Settings;
use Titanworks\SidrenaCijena\WooCommerce\PriceHistoryRecorder;
use Titanworks\SidrenaCijena\WooCommerce\WooCommerceCatalogProvider;
use Titanworks\SidrenaCijena\WooCommerce\WooCommerceDetector;
use Titanworks\SidrenaCijena\WooCommerce\Display\BuilderCompat;
use Titanworks\SidrenaCijena\WooCommerce\Display\PriceDisplay;
use Titanworks\SidrenaCijena\WooCommerce\Display\CardVisibility;
use Titanworks\SidrenaCijena\WooCommerce\Display\VariationDisplay;

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
	 * @var \Titanworks\SidrenaCijena\Pricing\AnchorPriceService|null
	 */
	private $anchors = null;

	/**
	 * Anchor cache.
	 *
	 * @var \Titanworks\SidrenaCijena\Pricing\AnchorCache|null
	 */
	private $anchor_cache = null;

	/**
	 * 30-day lowest price calculator.
	 *
	 * @var \Titanworks\SidrenaCijena\Pricing\LowestPrice30DayCalculator|null
	 */
	private $lowest_price = null;

	/**
	 * 30-day lowest price explainer.
	 *
	 * @var \Titanworks\SidrenaCijena\Pricing\LowestPriceExplainer|null
	 */
	private $lowest_price_explainer = null;

	/**
	 * Special sale service.
	 *
	 * @var \Titanworks\SidrenaCijena\Pricing\SpecialSaleService|null
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
	 * @var \Titanworks\SidrenaCijena\Export\GenerationService|null
	 */
	private $generation = null;

	/**
	 * REST cache.
	 *
	 * @var \Titanworks\SidrenaCijena\Api\RestCache|null
	 */
	private $rest_cache = null;

	/**
	 * Import service.
	 *
	 * @var \Titanworks\SidrenaCijena\Import\ImportService|null
	 */
	private $import_service = null;

	/**
	 * Log repository.
	 *
	 * @var \Titanworks\SidrenaCijena\Database\LogRepository|null
	 */
	private $logs = null;

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
		\Titanworks\SidrenaCijena\Support\IdentifierMigration::run();
		self::instance()->register_hooks();
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		( new \Titanworks\SidrenaCijena\Pricing\FirstOfferRecorder() )->register();
		add_action( 'init', array( $this, 'register_standalone_catalog' ) );
		add_action( 'admin_init', array( Migrator::class, 'maybe_upgrade' ) );
		add_action( 'admin_menu', array( AdminMenu::class, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( AdminMenu::class, 'enqueue_assets' ) );
		WelcomeBanner::register();
		SetupGuidePage::register();
		add_action( 'save_post_' . StandalonePostType::POST_TYPE, array( StandalonePostType::class, 'save' ) );
		add_action( 'add_meta_boxes', array( StandalonePostType::class, 'register_meta_box' ) );
		add_action( 'init', array( TemplateFunctions::class, 'register' ) );
		add_action( 'init', array( $this, 'register_public_feed' ) );
		add_action( 'init', array( $this, 'register_public_pages' ), 0 );
		Settings::register();
		add_action( 'admin_init', array( SettingsPage::class, 'register_settings' ) );
		add_action( 'add_meta_boxes', array( AnchorMetaBox::class, 'register' ) );
		add_action( 'add_meta_boxes', array( SpecialSaleMetaBox::class, 'register' ) );
		add_filter( 'woocommerce_product_data_tabs', array( AnchorMetaBox::class, 'product_data_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( AnchorMetaBox::class, 'render_product_data_panel' ) );
		add_action( 'admin_post_' . AnchorMetaBox::ACTION, array( AnchorMetaBox::class, 'handle_record' ) );
		add_action( 'admin_post_' . SpecialSaleMetaBox::ACTION, array( SpecialSaleMetaBox::class, 'handle_save' ) );
		add_action( 'admin_notices', array( AnchorMetaBox::class, 'maybe_notice' ) );
		add_action( 'twbce_cijena_settings_changed', array( $this, 'flush_caches' ) );
		add_action( 'twbce_cijena_rule_config_changed', array( $this, 'flush_caches' ) );
		add_action( 'twbce_cijena_price_changed', array( $this, 'flush_rest_cache' ) );
		add_action( 'twbce_cijena_price_changed', array( $this, 'queue_service_regeneration' ), 10, 2 );
		add_action( 'twbce_cijena_anchor_recorded', array( $this, 'invalidate_anchor_cache' ), 5, 3 );
		add_action( 'twbce_cijena_anchor_recorded', array( $this, 'flush_rest_cache' ) );
		add_action( 'twbce_cijena_special_sale_changed', array( $this, 'on_special_sale_changed' ), 10, 3 );
		add_action( 'twbce_cijena_publication_published', array( $this, 'flush_rest_cache' ) );
		add_action( 'transition_post_status', array( $this, 'flush_on_catalog_status_change' ), 10, 3 );
		add_action( 'before_delete_post', array( $this, 'flush_on_catalog_item_deleted' ), 10, 2 );
		add_action( 'rest_api_init', array( PriceListController::class, 'register' ) );
		add_action( 'admin_post_' . PublicationsPage::ACTION, array( PublicationsPage::class, 'handle_generate' ) );
		add_action( 'admin_post_' . CompliancePage::ACTION_RUN, array( CompliancePage::class, 'handle_run' ) );
		add_action( 'admin_post_' . CompliancePage::ACTION_EXPORT, array( CompliancePage::class, 'handle_export' ) );
		add_action( 'admin_post_' . ImportPage::ACTION_PREVIEW, array( ImportPage::class, 'handle_preview' ) );
		add_action( 'admin_post_' . ImportPage::ACTION_COMMIT, array( ImportPage::class, 'handle_commit' ) );
		add_action( 'admin_post_' . ImportPage::ACTION_REPORT, array( ImportPage::class, 'handle_report' ) );
		add_action( 'admin_post_' . BulkAnchorEntryPage::ACTION, array( BulkAnchorEntryPage::class, 'handle_save' ) );
		add_action( 'admin_post_' . BulkAnchorEntryPage::ACTION_ALL_CURRENT, array( BulkAnchorEntryPage::class, 'handle_set_all_current' ) );
		add_action( 'admin_post_' . LocationsPage::ACTION_SAVE, array( LocationsPage::class, 'handle_save' ) );
		add_action( 'admin_post_' . LocationsPage::ACTION_DELETE, array( LocationsPage::class, 'handle_delete' ) );
		add_action( 'admin_post_' . PriceHistoryPage::ACTION, array( PriceHistoryPage::class, 'handle_correct' ) );

		Scheduler::register();

		add_action( 'cli_init', array( Cli::class, 'register' ) );

		add_action( 'init', array( $this, 'register_woocommerce_integration' ), 10 );
	}

	/**
	 * Serve the price list at its public, human-readable URL.
	 *
	 * @return void
	 */
	public function register_public_feed() {
		( new PublicFeed() )->register();
	}

	/**
	 * Serve the price list and its archive as public HTML pages.
	 *
	 * @return void
	 */
	public function register_public_pages() {
		( new PublicPage() )->register();
	}

	/**
	 * Register the WooCommerce integrations after translations can load.
	 *
	 * @return void
	 */
	public function register_woocommerce_integration() {
		if ( ! WooCommerceDetector::is_active() ) {
			return;
		}

		$display = new PriceDisplay( $this->anchors() );
		$display->register();
		add_action( 'woocommerce_admin_process_product_object', array( CardVisibility::class, 'save' ) );
		( new VariationDisplay( $this->anchors() ) )->register();
		( new BuilderCompat( $display ) )->register();
		( new PriceHistoryRecorder() )->register();
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
	 * Flush cached REST and product caches when a special sale changes or transitions.
	 *
	 * @param int $sale_id      Sale ID.
	 * @param int $product_id   Product ID.
	 * @param int $variation_id Variation ID.
	 * @return void
	 */
	public function on_special_sale_changed( $sale_id = 0, $product_id = 0, $variation_id = 0 ) {
		unset( $sale_id );

		$this->flush_rest_cache();

		if ( (int) $product_id > 0 ) {
			if ( function_exists( 'wc_delete_product_transients' ) ) {
				wc_delete_product_transients( (int) $product_id );
			}

			if ( function_exists( 'clean_post_cache' ) ) {
				clean_post_cache( (int) $product_id );

				if ( (int) $variation_id > 0 ) {
					clean_post_cache( (int) $variation_id );
				}
			}
		}
	}

	/**
	 * Post types that make up the active catalog.
	 *
	 * @return string[]
	 */
	public static function catalog_post_types() {
		return array(
			StandalonePostType::POST_TYPE,
			'product',
			'product_variation',
		);
	}

	/**
	 * Flush cached price data when a catalog item enters or leaves the catalog.
	 *
	 * Public pages and REST responses are cached for up to five minutes, so a
	 * trashed or unpublished item must disappear immediately and a newly
	 * published item must appear immediately. Content edits of published items
	 * do not change catalog membership and stay cached.
	 *
	 * @param string   $new_status New post status.
	 * @param string   $old_status Old post status.
	 * @param \WP_Post $post       Post in transition.
	 * @return void
	 */
	public function flush_on_catalog_status_change( $new_status, $old_status, $post ) {
		if ( ! in_array( (string) $post->post_type, self::catalog_post_types(), true ) ) {
			return;
		}

		if ( ( 'publish' === $new_status ) === ( 'publish' === $old_status ) ) {
			return;
		}

		$this->flush_caches();
	}

	/**
	 * Flush cached price data when a catalog item is deleted.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post being deleted.
	 * @return void
	 */
	public function flush_on_catalog_item_deleted( $post_id, $post ) {
		unset( $post_id );

		if ( ! in_array( (string) $post->post_type, self::catalog_post_types(), true ) ) {
			return;
		}

		$this->flush_caches();
	}

	/**
	 * Forget one cached anchor after it was (re)recorded.
	 *
	 * AnchorPriceService::record() already invalidates through its own cache
	 * handle; this hook covers anchor writes that reach the history table
	 * through any other path.
	 *
	 * @param int $record_id    Record ID.
	 * @param int $product_id   Product ID.
	 * @param int $variation_id Variation ID.
	 * @return void
	 */
	public function invalidate_anchor_cache( $record_id, $product_id, $variation_id = 0 ) {
		unset( $record_id );

		$this->anchor_cache()->invalidate( (int) $product_id, (int) $variation_id );
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
		if ( apply_filters( 'twbce_cijena_enable_standalone_catalog', false ) ) {
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
	 * Shares the anchor cache so storefront lookups reuse a record until the
	 * next anchor invalidation or flush.
	 *
	 * @return \Titanworks\SidrenaCijena\Pricing\AnchorPriceService
	 */
	public function anchors() {
		if ( null === $this->anchors ) {
			$this->anchors = new \Titanworks\SidrenaCijena\Pricing\AnchorPriceService( $this->history(), $this->anchor_cache() );
		}

		return $this->anchors;
	}

	/**
	 * Anchor cache.
	 *
	 * @return \Titanworks\SidrenaCijena\Pricing\AnchorCache
	 */
	public function anchor_cache() {
		if ( null === $this->anchor_cache ) {
			$this->anchor_cache = new \Titanworks\SidrenaCijena\Pricing\AnchorCache();
		}

		return $this->anchor_cache;
	}

	/**
	 * 30-day lowest price calculator.
	 *
	 * @return \Titanworks\SidrenaCijena\Pricing\LowestPrice30DayCalculator
	 */
	public function lowest_price() {
		if ( null === $this->lowest_price ) {
			$this->lowest_price = new \Titanworks\SidrenaCijena\Pricing\LowestPrice30DayCalculator();
		}

		return $this->lowest_price;
	}

	/**
	 * 30-day lowest price explainer.
	 *
	 * @return \Titanworks\SidrenaCijena\Pricing\LowestPriceExplainer
	 */
	public function lowest_price_explainer() {
		if ( null === $this->lowest_price_explainer ) {
			$this->lowest_price_explainer = new \Titanworks\SidrenaCijena\Pricing\LowestPriceExplainer(
				$this->history(),
				$this->lowest_price()
			);
		}

		return $this->lowest_price_explainer;
	}

	/**
	 * Special sale service.
	 *
	 * @return \Titanworks\SidrenaCijena\Pricing\SpecialSaleService
	 */
	public function special_sale_service() {
		if ( null === $this->special_sale_service ) {
			$this->special_sale_service = new \Titanworks\SidrenaCijena\Pricing\SpecialSaleService( $this->special_sales(), $this->history(), $this->lowest_price() );
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
	 * @return \Titanworks\SidrenaCijena\Export\GenerationService
	 */
	public function generation() {
		if ( null === $this->generation ) {
			$this->generation = new \Titanworks\SidrenaCijena\Export\GenerationService( $this->catalog(), $this->locations(), $this->publications(), $this->history(), $this->special_sales() );
		}

		return $this->generation;
	}

	/**
	 * REST cache.
	 *
	 * @return \Titanworks\SidrenaCijena\Api\RestCache
	 */
	public function rest_cache() {
		if ( null === $this->rest_cache ) {
			$this->rest_cache = new \Titanworks\SidrenaCijena\Api\RestCache();
		}

		return $this->rest_cache;
	}

	/**
	 * Import service.
	 *
	 * @return \Titanworks\SidrenaCijena\Import\ImportService
	 */
	public function import_service() {
		if ( null === $this->import_service ) {
			$parser    = new \Titanworks\SidrenaCijena\Import\CsvParser();
			$validator = new \Titanworks\SidrenaCijena\Import\RowValidator( $this->catalog(), $this->history() );

			$this->import_service = new \Titanworks\SidrenaCijena\Import\ImportService( $parser, $validator, $this->anchors() );
		}

		return $this->import_service;
	}

	/**
	 * Log repository.
	 *
	 * @return \Titanworks\SidrenaCijena\Database\LogRepository
	 */
	public function logs() {
		if ( null === $this->logs ) {
			$this->logs = new \Titanworks\SidrenaCijena\Database\LogRepository();
		}

		return $this->logs;
	}
}
