<?php
/**
 * First-run setup guide.
 *
 * @package SidrenaCijena\Admin
 */

namespace SidrenaCijena\Admin;

use SidrenaCijena\Compliance\RuleConfig;
use SidrenaCijena\Plugin;
use SidrenaCijena\Support\Dates;

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
				'title'       => __( '1. Add sales locations', 'sidrena-cijena-eu' ),
				'description' => __( 'Every location needs a name, facility form, street address, city and a unique facility identifier. The address and identifier build the price-list filename.', 'sidrena-cijena-eu' ),
			),
			array(
				'key'         => 'rules',
				'title'       => __( '2. Check legal rules', 'sidrena-cijena-eu' ),
				'description' => __( 'General reference date is 10 September 2026. Legacy categories (food, beverages, cosmetics, cleaning, toiletries, household) use 2 May 2025 — map your real category slugs, the plugin never guesses them.', 'sidrena-cijena-eu' ),
			),
			array(
				'key'         => 'anchors',
				'title'       => __( '3. Record anchor prices', 'sidrena-cijena-eu' ),
				'description' => __( 'Enter evidenced prices for existing products or import them by CSV. New products published with this plugin active capture their first offer automatically.', 'sidrena-cijena-eu' ),
			),
			array(
				'key'         => 'lists',
				'title'       => __( '4. Generate the price list', 'sidrena-cijena-eu' ),
				'description' => __( 'Generate CSV/XML per location, keep at least 30 days of archive. Products regenerate daily before 08:00; service price changes regenerate immediately.', 'sidrena-cijena-eu' ),
			),
			array(
				'key'         => 'check',
				'title'       => __( '5. Run the compliance check', 'sidrena-cijena-eu' ),
				'description' => __( 'Fix ERROR findings before publishing: missing anchors, missing addresses and unclassified promotions block the price list.', 'sidrena-cijena-eu' ),
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
	 * Current completion status of every setup step.
	 *
	 * @return array<string,array<string,mixed>> Keyed by step key.
	 */
	public static function statuses() {
		$plugin       = Plugin::instance();
		$locations    = $plugin->locations()->all();
		$rule         = RuleConfig::all();
		$total        = (int) $plugin->catalog()->count_items();
		$last         = $plugin->publications()->last_successful();
		$anchor_count = (int) $plugin->history()->count( array( 'price_type' => 'anchor' ) );
		$report       = CompliancePage::stored_report();

		$legacy_slugs = isset( $rule['legacy_category_slugs'] ) ? array_filter( array_map( 'strval', (array) $rule['legacy_category_slugs'] ) ) : array();

		if ( $anchor_count <= 0 ) {
			$anchor_badge = __( '(none recorded)', 'sidrena-cijena-eu' );
		} elseif ( $total > $anchor_count ) {
			$anchor_badge = sprintf(
				/* translators: 1: recorded anchors, 2: catalog items. */
				__( '(done: %1$d of %2$d recorded)', 'sidrena-cijena-eu' ),
				$anchor_count,
				$total
			);
		} else {
			$anchor_badge = sprintf(
				/* translators: %d: count of recorded anchors. */
				__( '(done: %d recorded)', 'sidrena-cijena-eu' ),
				$anchor_count
			);
		}

		$error_count = is_array( $report ) && isset( $report['counts']['ERROR'] ) ? (int) $report['counts']['ERROR'] : null;

		if ( null === $error_count ) {
			$check_badge = __( '(not run yet)', 'sidrena-cijena-eu' );
		} else {
			$check_badge = $error_count > 0 ? __( '(errors found)', 'sidrena-cijena-eu' ) : __( '(clean)', 'sidrena-cijena-eu' );
		}

		return array(
			'locations' => array(
				'complete' => ! empty( $locations ),
				'badge'    => empty( $locations ) ? __( '(missing)', 'sidrena-cijena-eu' ) : __( '(done)', 'sidrena-cijena-eu' ),
				'count'    => count( $locations ),
			),
			'rules'     => array(
				'complete'               => ! empty( $legacy_slugs ),
				'badge'                  => empty( $legacy_slugs ) ? __( '(map your categories)', 'sidrena-cijena-eu' ) : __( '(done)', 'sidrena-cijena-eu' ),
				'slugs'                  => array_values( $legacy_slugs ),
				'general_reference_date' => isset( $rule['general_reference_date'] ) ? (string) $rule['general_reference_date'] : '',
				'legacy_reference_date'  => isset( $rule['legacy_reference_date'] ) ? (string) $rule['legacy_reference_date'] : '',
			),
			'anchors'   => array(
				'complete' => $anchor_count > 0,
				'badge'    => $anchor_badge,
				'count'    => $anchor_count,
				'total'    => $total,
			),
			'lists'     => array(
				'complete' => (bool) $last,
				'badge'    => $last ? __( '(published)', 'sidrena-cijena-eu' ) : __( '(not published yet)', 'sidrena-cijena-eu' ),
			),
			'check'     => array(
				'complete' => null !== $error_count && 0 === $error_count,
				'badge'    => $check_badge,
				'errors'   => $error_count,
			),
		);
	}

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! Capabilities::current_user_can() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'sidrena-cijena-eu' ) );
		}

		$statuses = self::statuses();
		$complete = self::is_complete();

		echo '<div class="wrap sidrena-setup">';
		echo '<h1>' . esc_html__( 'Sidrena cijena: Setup Guide', 'sidrena-cijena-eu' ) . '</h1>';
		echo '<p>' . esc_html__( 'Five steps, a few minutes. Made by administrakt0r.', 'sidrena-cijena-eu' ) . '</p>';
		echo '<p><a class="button button-secondary" href="' . esc_url( AdminMenu::url( DocumentationPage::SLUG ) ) . '"><span class="dashicons dashicons-book sidrena-icon-inline"></span>' . esc_html__( 'Open documentation & guide', 'sidrena-cijena-eu' ) . '</a></p>';

		Notice::from_request();

		self::render_projects();

		if ( $complete ) {
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Setup is marked complete. Reopen it any time you add locations or categories.', 'sidrena-cijena-eu' ) . '</p></div>';
		}

		echo '<ol class="sidrena-setup__steps">';

		// Step 1: locations.
		printf(
			'<li><h2>%1$s %2$s</h2><p>%3$s</p><p>%4$s: %5$d. <a class="button" href="%6$s">%7$s</a></p></li>',
			esc_html__( '1. Add sales locations', 'sidrena-cijena-eu' ),
			esc_html( (string) $statuses['locations']['badge'] ),
			esc_html__( 'Every location needs a name, facility form, street address, city and a unique facility identifier. The address and identifier build the price-list filename.', 'sidrena-cijena-eu' ),
			esc_html__( 'Locations', 'sidrena-cijena-eu' ),
			(int) $statuses['locations']['count'],
			esc_url( AdminMenu::url( LocationsPage::SLUG ) ),
			esc_html__( 'Manage locations', 'sidrena-cijena-eu' )
		);

		// Step 2: rules.
		$legacy_slugs = array_map( 'strval', (array) $statuses['rules']['slugs'] );

		printf(
			'<li><h2>%1$s %2$s</h2><p>%3$s</p><p>%4$s: %5$s / %6$s. %7$s: %8$s. <a class="button" href="%9$s">%10$s</a></p></li>',
			esc_html__( '2. Check legal rules', 'sidrena-cijena-eu' ),
			esc_html( (string) $statuses['rules']['badge'] ),
			esc_html__( 'General reference date is 10 September 2026. Legacy categories (food, beverages, cosmetics, cleaning, toiletries, household) use 2 May 2025 — map your real category slugs, the plugin never guesses them.', 'sidrena-cijena-eu' ),
			esc_html__( 'Reference dates', 'sidrena-cijena-eu' ),
			esc_html( Dates::format_date_display( (string) $statuses['rules']['general_reference_date'] ) ),
			esc_html( Dates::format_date_display( (string) $statuses['rules']['legacy_reference_date'] ) ),
			esc_html__( 'Legacy slugs', 'sidrena-cijena-eu' ),
			esc_html( $legacy_slugs ? implode( ', ', $legacy_slugs ) : __( 'none mapped yet', 'sidrena-cijena-eu' ) ),
			esc_url( AdminMenu::url( 'sidrena-cijena-settings', array( 'tab' => 'legal' ) ) ),
			esc_html__( 'Open legal rules', 'sidrena-cijena-eu' )
		);

		// Step 3: anchors.
		printf(
			'<li><h2>%1$s %2$s</h2><p>%3$s</p><p>%4$s: %5$d. <a class="button" href="%6$s">%7$s</a> <a class="button" href="%8$s">%9$s</a></p></li>',
			esc_html__( '3. Record anchor prices', 'sidrena-cijena-eu' ),
			esc_html( (string) $statuses['anchors']['badge'] ),
			esc_html__( 'Enter evidenced prices for existing products or import them by CSV. New products published with this plugin active capture their first offer automatically.', 'sidrena-cijena-eu' ),
			esc_html__( 'Catalog items', 'sidrena-cijena-eu' ),
			(int) $statuses['anchors']['total'],
			esc_url( AdminMenu::url( 'sidrena-cijena-anchors' ) ),
			esc_html__( 'Enter anchors', 'sidrena-cijena-eu' ),
			esc_url( AdminMenu::url( 'sidrena-cijena-import' ) ),
			esc_html__( 'Import CSV', 'sidrena-cijena-eu' )
		);

		// Step 4: lists.
		printf(
			'<li><h2>%1$s %2$s</h2><p>%3$s</p><p><a class="button button-primary" href="%4$s">%5$s</a></p></li>',
			esc_html__( '4. Generate the price list', 'sidrena-cijena-eu' ),
			esc_html( (string) $statuses['lists']['badge'] ),
			esc_html__( 'Generate CSV/XML per location, keep at least 30 days of archive. Products regenerate daily before 08:00; service price changes regenerate immediately.', 'sidrena-cijena-eu' ),
			esc_url( AdminMenu::url( 'sidrena-cijena-publications' ) ),
			esc_html__( 'Open price lists', 'sidrena-cijena-eu' )
		);

		// Step 5: check.
		printf(
			'<li><h2>%1$s %2$s</h2><p>%3$s</p><p><a class="button button-primary" href="%4$s">%5$s</a></p></li>',
			esc_html__( '5. Run the compliance check', 'sidrena-cijena-eu' ),
			esc_html( (string) $statuses['check']['badge'] ),
			esc_html__( 'Fix ERROR findings before publishing: missing anchors, missing addresses and unclassified promotions block the price list.', 'sidrena-cijena-eu' ),
			esc_url( AdminMenu::url( 'sidrena-cijena-compliance' ) ),
			esc_html__( 'Run compliance check', 'sidrena-cijena-eu' )
		);

		echo '</ol>';

		if ( ! $complete ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( self::COMPLETE_ACTION );
			echo '<input type="hidden" name="action" value="' . esc_attr( self::COMPLETE_ACTION ) . '" />';
			submit_button( __( 'Mark setup complete', 'sidrena-cijena-eu' ), 'primary', 'submit', false );
			echo '</form>';
		} else {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( self::REOPEN_ACTION );
			echo '<input type="hidden" name="action" value="' . esc_attr( self::REOPEN_ACTION ) . '" />';
			submit_button( __( 'Reopen setup', 'sidrena-cijena-eu' ), 'secondary', 'submit', false );
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
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'sidrena-cijena-eu' ) );
		}

		check_admin_referer( self::COMPLETE_ACTION );

		update_option( self::COMPLETE_OPTION, 1, false );
		update_option( WelcomeBanner::DISMISSED_OPTION, 1, false );

		wp_safe_redirect(
			add_query_arg(
				array(
					'sidrena_cijena_notice'      => 'success',
					'sidrena_cijena_notice_text' => rawurlencode( __( 'Setup marked as complete.', 'sidrena-cijena-eu' ) ),
				),
				AdminMenu::url( self::SLUG )
			)
		);
		exit;
	}

	/**
	 * Reopen setup.
	 *
	 * @return void
	 */
	public static function handle_reopen() {
		if ( ! Capabilities::current_user_can() ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'sidrena-cijena-eu' ) );
		}

		check_admin_referer( self::REOPEN_ACTION );

		delete_option( self::COMPLETE_OPTION );

		wp_safe_redirect(
			add_query_arg(
				array(
					'sidrena_cijena_notice'      => 'success',
					'sidrena_cijena_notice_text' => rawurlencode( __( 'Setup reopened.', 'sidrena-cijena-eu' ) ),
				),
				AdminMenu::url( self::SLUG )
			)
		);
		exit;
	}
}
