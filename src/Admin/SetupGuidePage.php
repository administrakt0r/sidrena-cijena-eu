<?php
/**
 * First-run setup guide.
 *
 * @package SidrenaCijena\Admin
 */

namespace SidrenaCijena\Admin;

use SidrenaCijena\Compliance\RuleConfig;
use SidrenaCijena\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Single-screen checklist that configures the plugin in the right order.
 */
final class SetupGuidePage {

	/**
	 * Page slug.
	 */
	const SLUG = 'sidrena-cijena-setup';

	/**
	 * Completion flag.
	 */
	const COMPLETE_OPTION = 'sidrena_cijena_setup_complete';

	/**
	 * Actions.
	 */
	const COMPLETE_ACTION = 'sidrena_cijena_setup_complete';
	const REOPEN_ACTION   = 'sidrena_cijena_setup_reopen';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'admin_post_' . self::COMPLETE_ACTION, array( __CLASS__, 'handle_complete' ) );
		add_action( 'admin_post_' . self::REOPEN_ACTION, array( __CLASS__, 'handle_reopen' ) );
	}

	/**
	 * Ordered setup steps (pure, unit-testable).
	 *
	 * @return array<int,array{key:string,title:string,description:string}>
	 */
	public static function steps() {
		return array(
			array(
				'key'         => 'locations',
				'title'       => __( '1. Add sales locations', 'sidrena-cijena' ),
				'description' => __( 'Every location needs a name, facility form, street address, city and a unique facility identifier. The address and identifier build the price-list filename.', 'sidrena-cijena' ),
			),
			array(
				'key'         => 'rules',
				'title'       => __( '2. Check legal rules', 'sidrena-cijena' ),
				'description' => __( 'General reference date is 10 September 2026. Legacy categories (food, beverages, cosmetics, cleaning, toiletries, household) use 2 May 2025 — map your real category slugs, the plugin never guesses them.', 'sidrena-cijena' ),
			),
			array(
				'key'         => 'anchors',
				'title'       => __( '3. Record anchor prices', 'sidrena-cijena' ),
				'description' => __( 'Enter evidenced prices for existing products or import them by CSV. New products published with this plugin active capture their first offer automatically.', 'sidrena-cijena' ),
			),
			array(
				'key'         => 'lists',
				'title'       => __( '4. Generate the price list', 'sidrena-cijena' ),
				'description' => __( 'Generate CSV/XML per location, keep at least 30 days of archive. Products regenerate daily before 08:00; service price changes regenerate immediately.', 'sidrena-cijena' ),
			),
			array(
				'key'         => 'check',
				'title'       => __( '5. Run the compliance check', 'sidrena-cijena' ),
				'description' => __( 'Fix ERROR findings before publishing: missing anchors, missing addresses and unclassified promotions block the price list.', 'sidrena-cijena' ),
			),
		);
	}

	/**
	 * Whether setup was marked complete.
	 *
	 * @return bool
	 */
	public static function is_complete() {
		return (bool) get_option( self::COMPLETE_OPTION, false );
	}

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! Capabilities::current_user_can() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'sidrena-cijena' ) );
		}

		$plugin       = Plugin::instance();
		$locations    = $plugin->locations()->all();
		$rule         = RuleConfig::all();
		$total        = (int) $plugin->catalog()->count_items();
		$last         = $plugin->publications()->last_successful();
		$anchor_count = (int) $plugin->history()->count( array( 'price_type' => 'anchor' ) );
		$report       = CompliancePage::stored_report();
		$complete     = self::is_complete();

		$legacy_slugs = isset( $rule['legacy_category_slugs'] ) ? array_filter( array_map( 'strval', (array) $rule['legacy_category_slugs'] ) ) : array();

		$anchor_badge = $anchor_count > 0
			? sprintf( /* translators: %d: count of recorded anchors */ esc_html__( '(done: %d recorded)', 'sidrena-cijena' ), $anchor_count )
			: esc_html__( '(none recorded)', 'sidrena-cijena' );

		$check_badge = esc_html__( '(not run yet)', 'sidrena-cijena' );
		if ( is_array( $report ) ) {
			$error_count = isset( $report['counts']['ERROR'] ) ? (int) $report['counts']['ERROR'] : 0;
			$check_badge = $error_count > 0 ? esc_html__( '(errors found)', 'sidrena-cijena' ) : esc_html__( '(clean)', 'sidrena-cijena' );
		}

		echo '<div class="wrap sidrena-setup">';
		echo '<h1>' . esc_html__( 'Sidrena cijena: Setup Guide', 'sidrena-cijena' ) . '</h1>';
		echo '<p>' . esc_html__( 'Five steps, a few minutes. Made by administrakt0r.', 'sidrena-cijena' ) . '</p>';
		echo '<p><a class="button button-secondary" href="' . esc_url( AdminMenu::url( DocumentationPage::SLUG ) ) . '"><span class="dashicons dashicons-book" style="vertical-align:middle;margin-right:4px;"></span>' . esc_html__( 'Open documentation & guide', 'sidrena-cijena' ) . '</a></p>';

		self::render_projects();

		if ( $complete ) {
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Setup is marked complete. Reopen it any time you add locations or categories.', 'sidrena-cijena' ) . '</p></div>';
		}

		echo '<ol class="sidrena-setup__steps">';

		// Step 1: locations.
		printf(
			'<li><h2>%1$s %2$s</h2><p>%3$s</p><p>%4$s: %5$d. <a class="button" href="%6$s">%7$s</a></p></li>',
			esc_html__( '1. Add sales locations', 'sidrena-cijena' ),
			empty( $locations ) ? esc_html__( '(missing)', 'sidrena-cijena' ) : esc_html__( '(done)', 'sidrena-cijena' ),
			esc_html__( 'Every location needs a name, facility form, street address, city and a unique facility identifier. The address and identifier build the price-list filename.', 'sidrena-cijena' ),
			esc_html__( 'Locations', 'sidrena-cijena' ),
			count( $locations ),
			esc_url( AdminMenu::url( LocationsPage::SLUG ) ),
			esc_html__( 'Manage locations', 'sidrena-cijena' )
		);

		// Step 2: rules.
		printf(
			'<li><h2>%1$s %2$s</h2><p>%3$s</p><p>%4$s: %5$s / %6$s. %7$s: %8$s. <a class="button" href="%9$s">%10$s</a></p></li>',
			esc_html__( '2. Check legal rules', 'sidrena-cijena' ),
			empty( $legacy_slugs ) ? esc_html__( '(map your categories)', 'sidrena-cijena' ) : esc_html__( '(done)', 'sidrena-cijena' ),
			esc_html__( 'General reference date is 10 September 2026. Legacy categories (food, beverages, cosmetics, cleaning, toiletries, household) use 2 May 2025 — map your real category slugs, the plugin never guesses them.', 'sidrena-cijena' ),
			esc_html__( 'Reference dates', 'sidrena-cijena' ),
			esc_html( isset( $rule['general_reference_date'] ) ? (string) $rule['general_reference_date'] : '' ),
			esc_html( isset( $rule['legacy_reference_date'] ) ? (string) $rule['legacy_reference_date'] : '' ),
			esc_html__( 'Legacy slugs', 'sidrena-cijena' ),
			esc_html( $legacy_slugs ? implode( ', ', $legacy_slugs ) : __( 'none mapped yet', 'sidrena-cijena' ) ),
			esc_url( AdminMenu::url( 'sidrena-cijena-settings', array( 'tab' => 'legal' ) ) ),
			esc_html__( 'Open legal rules', 'sidrena-cijena' )
		);

		// Step 3: anchors.
		printf(
			'<li><h2>%1$s %2$s</h2><p>%3$s</p><p>%4$s: %5$d. <a class="button" href="%6$s">%7$s</a> <a class="button" href="%8$s">%9$s</a></p></li>',
			esc_html__( '3. Record anchor prices', 'sidrena-cijena' ),
			esc_html( $anchor_badge ),
			esc_html__( 'Enter evidenced prices for existing products or import them by CSV. New products published with this plugin active capture their first offer automatically.', 'sidrena-cijena' ),
			esc_html__( 'Catalog items', 'sidrena-cijena' ),
			(int) $total,
			esc_url( AdminMenu::url( 'sidrena-cijena-anchors' ) ),
			esc_html__( 'Enter anchors', 'sidrena-cijena' ),
			esc_url( AdminMenu::url( 'sidrena-cijena-import' ) ),
			esc_html__( 'Import CSV', 'sidrena-cijena' )
		);

		// Step 4: lists.
		printf(
			'<li><h2>%1$s %2$s</h2><p>%3$s</p><p><a class="button button-primary" href="%4$s">%5$s</a></p></li>',
			esc_html__( '4. Generate the price list', 'sidrena-cijena' ),
			$last ? esc_html__( '(published)', 'sidrena-cijena' ) : esc_html__( '(not published yet)', 'sidrena-cijena' ),
			esc_html__( 'Generate CSV/XML per location, keep at least 30 days of archive. Products regenerate daily before 08:00; service price changes regenerate immediately.', 'sidrena-cijena' ),
			esc_url( AdminMenu::url( 'sidrena-cijena-publications' ) ),
			esc_html__( 'Open price lists', 'sidrena-cijena' )
		);

		// Step 5: check.
		printf(
			'<li><h2>%1$s %2$s</h2><p>%3$s</p><p><a class="button button-primary" href="%4$s">%5$s</a></p></li>',
			esc_html__( '5. Run the compliance check', 'sidrena-cijena' ),
			esc_html( $check_badge ),
			esc_html__( 'Fix ERROR findings before publishing: missing anchors, missing addresses and unclassified promotions block the price list.', 'sidrena-cijena' ),
			esc_url( AdminMenu::url( 'sidrena-cijena-compliance' ) ),
			esc_html__( 'Run compliance check', 'sidrena-cijena' )
		);

		echo '</ol>';

		if ( ! $complete ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( self::COMPLETE_ACTION );
			echo '<input type="hidden" name="action" value="' . esc_attr( self::COMPLETE_ACTION ) . '" />';
			submit_button( __( 'Mark setup complete', 'sidrena-cijena' ), 'primary', 'submit', false );
			echo '</form>';
		} else {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( self::REOPEN_ACTION );
			echo '<input type="hidden" name="action" value="' . esc_attr( self::REOPEN_ACTION ) . '" />';
			submit_button( __( 'Reopen setup', 'sidrena-cijena' ), 'secondary', 'submit', false );
			echo '</form>';
		}

		echo '</div>';
	}

	/**
	 * Render the author project boxes.
	 *
	 * @return void
	 */
	protected static function render_projects() {
		echo '<div class="sidrena-setup__projects">';

		foreach ( WelcomeBanner::links() as $link ) {
			printf(
				'<div class="sidrena-setup__project"><strong><a href="%1$s" target="_blank" rel="noopener">%2$s</a></strong><br />%3$s</div>',
				esc_url( $link['url'] ),
				esc_html( $link['title'] ),
				esc_html( $link['description'] )
			);
		}

		echo '</div>';
	}

	/**
	 * Mark setup complete.
	 *
	 * @return void
	 */
	public static function handle_complete() {
		if ( ! Capabilities::current_user_can() ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'sidrena-cijena' ) );
		}

		check_admin_referer( self::COMPLETE_ACTION );

		update_option( self::COMPLETE_OPTION, 1, false );
		update_option( WelcomeBanner::DISMISSED_OPTION, 1, false );

		wp_safe_redirect( AdminMenu::url( self::SLUG ) );
		exit;
	}

	/**
	 * Reopen setup.
	 *
	 * @return void
	 */
	public static function handle_reopen() {
		if ( ! Capabilities::current_user_can() ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'sidrena-cijena' ) );
		}

		check_admin_referer( self::REOPEN_ACTION );

		delete_option( self::COMPLETE_OPTION );

		wp_safe_redirect( AdminMenu::url( self::SLUG ) );
		exit;
	}
}
