<?php
/**
 * Activation routine.
 *
 * @package Titanworks\SidrenaCijena\Support
 */

namespace Titanworks\SidrenaCijena\Support;

use Titanworks\SidrenaCijena\Admin\WelcomeBanner;

use Titanworks\SidrenaCijena\Admin\Capabilities;
use Titanworks\SidrenaCijena\Catalog\StandalonePostType;
use Titanworks\SidrenaCijena\Compliance\RuleConfig;
use Titanworks\SidrenaCijena\Cron\Scheduler;
use Titanworks\SidrenaCijena\Database\LocationRepository;
use Titanworks\SidrenaCijena\Database\Migrator;
use Titanworks\SidrenaCijena\Logging\Logger;
use Titanworks\SidrenaCijena\WooCommerce\WooCommerceDetector;

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
		IdentifierMigration::run();
		Capabilities::grant();

		Migrator::migrate();

		// Settings defaults are translatable strings, and `init` has not run
		// yet during activation, so the textdomain has to be loaded explicitly
		// or the seeded labels would be stored in English.
		$mo_file = file_exists( TWBCE_CIJENA_DIR . 'languages/sidrena-cijena-eu-hr.mo' )
			? TWBCE_CIJENA_DIR . 'languages/sidrena-cijena-eu-hr.mo'
			: TWBCE_CIJENA_DIR . 'languages/sidrena-cijena-eu-hr_HR.mo';
		load_textdomain( 'sidrena-cijena-eu', $mo_file );

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
			__( 'Plugin activated.', 'sidrena-cijena-eu' ),
			array(
				'version' => defined( 'TWBCE_CIJENA_VERSION' ) ? TWBCE_CIJENA_VERSION : '',
			)
		);

		/**
		 * Fires after the plugin has been activated.
		 */
		do_action( 'twbce_cijena_activated' );
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
