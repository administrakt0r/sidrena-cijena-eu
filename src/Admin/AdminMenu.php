<?php
/**
 * Admin menu and asset registration.
 *
 * @package SidrenaCijena\Admin
 */

namespace SidrenaCijena\Admin;

use SidrenaCijena\WooCommerce\WooCommerceDetector;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the plugin admin screens.
 */
final class AdminMenu {

	/**
	 * Top-level menu slug.
	 */
	const PARENT = 'sidrena-cijena';

	/**
	 * Register menus and submenus.
	 *
	 * The menu lives under WooCommerce when WooCommerce is active and under
	 * Settings otherwise (contract `wordpress-integration.md`).
	 *
	 * @return void
	 */
	public static function register() {
		$capability = Capabilities::capability();
		$parent     = self::parent_slug();

		add_menu_page(
			__( 'Sidrena cijena', 'sidrena-cijena' ),
			__( 'Sidrena cijena', 'sidrena-cijena' ),
			$capability,
			self::PARENT,
			array( DashboardPage::class, 'render' ),
			'dashicons-tag',
			56
		);

		foreach ( self::pages() as $slug => $page ) {
			add_submenu_page(
				$parent,
				/* translators: %s: screen title. */
				sprintf( __( '%s - Sidrena cijena', 'sidrena-cijena' ), $page[1] ),
				$page[1],
				$capability,
				$slug,
				array( $page[0], 'render' )
			);
		}
	}

	/**
	 * Registered pages: slug => [class, label].
	 *
	 * @return array<string,array{0:string,1:string}>
	 */
	public static function pages() {
		return array(
			self::PARENT                  => array( DashboardPage::class, __( 'Dashboard', 'sidrena-cijena' ) ),
			'sidrena-cijena-publications' => array( PublicationsPage::class, __( 'Price lists', 'sidrena-cijena' ) ),
			'sidrena-cijena-anchors'      => array( BulkAnchorEntryPage::class, __( 'Anchor prices', 'sidrena-cijena' ) ),
			'sidrena-cijena-import'       => array( ImportPage::class, __( 'Import', 'sidrena-cijena' ) ),
			'sidrena-cijena-locations'    => array( LocationsPage::class, __( 'Sales locations', 'sidrena-cijena' ) ),
			'sidrena-cijena-history'      => array( PriceHistoryPage::class, __( 'Price history', 'sidrena-cijena' ) ),
			'sidrena-cijena-compliance'   => array( CompliancePage::class, __( 'Compliance check', 'sidrena-cijena' ) ),
			'sidrena-cijena-settings'     => array( SettingsPage::class, __( 'Settings', 'sidrena-cijena' ) ),
			'sidrena-cijena-logs'         => array( LogsPage::class, __( 'Logs', 'sidrena-cijena' ) ),
			'sidrena-cijena-audit'        => array( AuditPage::class, __( 'Audit', 'sidrena-cijena' ) ),
		);
	}

	/**
	 * Parent menu slug.
	 *
	 * @return string
	 */
	public static function parent_slug() {
		return WooCommerceDetector::is_active() ? 'woocommerce' : 'options-general.php';
	}

	/**
	 * URL of a plugin screen.
	 *
	 * @param string $slug Screen slug.
	 * @param array  $args Extra query arguments.
	 * @return string
	 */
	public static function url( $slug, array $args = array() ) {
		$url = menu_page_url( $slug, false );

		if ( ! $url ) {
			$url = admin_url( 'admin.php?page=' . rawurlencode( $slug ) );
		}

		return empty( $args ) ? $url : add_query_arg( $args, $url );
	}

	/**
	 * Enqueue admin assets on plugin screens.
	 *
	 * @param string $hook_suffix Current screen hook.
	 * @return void
	 */
	public static function enqueue_assets( $hook_suffix ) {
		if ( false === strpos( (string) $hook_suffix, 'sidrena-cijena' ) && false === strpos( (string) $hook_suffix, 'sidrena_item' ) ) {
			return;
		}

		wp_enqueue_style(
			'sidrena-cijena-admin',
			SIDRENA_CIJENA_URL . 'assets/css/admin.css',
			array(),
			SIDRENA_CIJENA_VERSION
		);

		wp_enqueue_script(
			'sidrena-cijena-admin',
			SIDRENA_CIJENA_URL . 'assets/js/admin.js',
			array( 'wp-i18n' ),
			SIDRENA_CIJENA_VERSION,
			true
		);
	}
}
