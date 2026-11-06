<?php
/**
 * Activation routine.
 *
 * @package SidrenaCijena\Support
 */

namespace SidrenaCijena\Support;

use SidrenaCijena\Admin\WelcomeBanner;

use SidrenaCijena\Admin\Capabilities;
use SidrenaCijena\Catalog\StandalonePostType;
use SidrenaCijena\Compliance\RuleConfig;
use SidrenaCijena\Cron\Scheduler;
use SidrenaCijena\Database\LocationRepository;
use SidrenaCijena\Database\Migrator;
use SidrenaCijena\Logging\Logger;
use SidrenaCijena\WooCommerce\WooCommerceDetector;

defined( 'ABSPATH' ) || exit;

/**
 * Runs on plugin activation: tables, options, default location and schedules.
 */
final class Install {

	/**
	 * Activate the plugin.
	 *
	 * @return void
	 */
	public static function activate() {
		Capabilities::grant();

		Migrator::migrate();

		// Settings defaults are translatable strings, and `init` has not run
		// yet during activation, so the textdomain has to be loaded explicitly
		// or the seeded labels would be stored in English.
		$mo_file = file_exists( SIDRENA_CIJENA_DIR . 'languages/titanworks-bazna-cijena-hr.mo' )
			? SIDRENA_CIJENA_DIR . 'languages/titanworks-bazna-cijena-hr.mo'
			: SIDRENA_CIJENA_DIR . 'languages/titanworks-bazna-cijena-hr_HR.mo';
		load_textdomain( 'titanworks-bazna-cijena', $mo_file );

		self::seed_options();

		if ( ! WooCommerceDetector::is_active() ) {
			StandalonePostType::register();
			StandalonePostType::seed_terms();
		}

		$locations = new LocationRepository();
		$locations->ensure_default_webshop();

		Scheduler::schedule_all();

		update_option( WelcomeBanner::REDIRECT_OPTION, 1, false );

		// The public feed lives on a rewrite rule, so it only resolves once the rules are rebuilt.
		flush_rewrite_rules();

		Logger::info(
			'migration',
			__( 'Plugin activated.', 'titanworks-bazna-cijena' ),
			array(
				'version' => defined( 'SIDRENA_CIJENA_VERSION' ) ? SIDRENA_CIJENA_VERSION : '',
			)
		);

		/**
		 * Fires after the plugin has been activated.
		 */
		do_action( 'sidrena_cijena_activated' );
	}

	/**
	 * Seed default options without overwriting existing values.
	 *
	 * @return void
	 */
	public static function seed_options() {
		if ( false === get_option( Settings::OPTION, false ) ) {
			add_option( Settings::OPTION, Settings::defaults(), '', false );
		}

		if ( false === get_option( RuleConfig::OPTION, false ) ) {
			add_option( RuleConfig::OPTION, RuleConfig::defaults(), '', false );
		}

		if ( false === get_option( RuleConfig::HISTORY_OPTION, false ) ) {
			add_option( RuleConfig::HISTORY_OPTION, array(), '', false );
		}
	}
}
