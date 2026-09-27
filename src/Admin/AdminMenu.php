<?php
/**
 * Admin menu and asset registration.
 *
 * @package SidrenaCijena\Admin
 */

namespace SidrenaCijena\Admin;

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
	 * The menu is always a dedicated top-level section for discoverability.
	 *
	 * @return void
	 */
	public static function register() {
		$capability = Capabilities::capability();
		add_menu_page(
			__( 'Sidrena cijena', 'sidrena-cijena-eu' ),
			__( 'Sidrena cijena', 'sidrena-cijena-eu' ),
			$capability,
			self::PARENT,
			array( DashboardPage::class, 'render' ),
			self::menu_icon(),
			56
		);

		foreach ( self::pages() as $slug => $page ) {
			$callback = array( $page[0], 'render' );

			add_submenu_page(
				self::PARENT,
				/* translators: %s: screen title. */
				sprintf( __( '%s - Sidrena cijena', 'sidrena-cijena-eu' ), $page[1] ),
				$page[1],
				$capability,
				$slug,
				is_callable( $callback ) ? $callback : ''
			);
		}
	}

	/**
	 * Registered pages: slug => [class, label].
	 *
	 * @return array<string,array{0:class-string,1:string}>
	 */
	public static function pages() {
		return array(
			self::PARENT                  => array( DashboardPage::class, __( 'Dashboard', 'sidrena-cijena-eu' ) ),
			SetupGuidePage::SLUG          => array( SetupGuidePage::class, __( 'Setup Guide', 'sidrena-cijena-eu' ) ),
			'sidrena-cijena-publications' => array( PublicationsPage::class, __( 'Price lists', 'sidrena-cijena-eu' ) ),
			'sidrena-cijena-anchors'      => array( BulkAnchorEntryPage::class, __( 'Anchor prices', 'sidrena-cijena-eu' ) ),
			'sidrena-cijena-import'       => array( ImportPage::class, __( 'Import', 'sidrena-cijena-eu' ) ),
			'sidrena-cijena-locations'    => array( LocationsPage::class, __( 'Sales locations', 'sidrena-cijena-eu' ) ),
			'sidrena-cijena-history'      => array( PriceHistoryPage::class, __( 'Price history', 'sidrena-cijena-eu' ) ),
			'sidrena-cijena-compliance'   => array( CompliancePage::class, __( 'Compliance check', 'sidrena-cijena-eu' ) ),
			'sidrena-cijena-settings'     => array( SettingsPage::class, __( 'Settings', 'sidrena-cijena-eu' ) ),
			'sidrena-cijena-logs'         => array( LogsPage::class, __( 'Logs', 'sidrena-cijena-eu' ) ),
			'sidrena-cijena-audit'        => array( AuditPage::class, __( 'Audit', 'sidrena-cijena-eu' ) ),
			DocumentationPage::SLUG       => array( DocumentationPage::class, __( 'Documentation', 'sidrena-cijena-eu' ) ),
		);
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
		$is_product_screen = false;
		if ( in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			if ( $screen && ( 'product' === $screen->post_type || 'sidrena_item' === $screen->post_type ) ) {
				$is_product_screen = true;
			}
		}

		$is_plugin_screen = false !== strpos( (string) $hook_suffix, 'sidrena-cijena' ) || false !== strpos( (string) $hook_suffix, 'sidrena_item' );

		if ( ! $is_product_screen && ! $is_plugin_screen ) {
			return;
		}

		wp_enqueue_style(
			'sidrena-cijena-admin',
			SIDRENA_CIJENA_URL . 'assets/css/admin.css',
			array(),
			SIDRENA_CIJENA_VERSION
		);

		if ( isset( $_GET['tab'] ) && 'styling' === sanitize_key( wp_unslash( $_GET['tab'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only asset selection.
			wp_enqueue_style( 'sidrena-cijena-frontend', SIDRENA_CIJENA_URL . 'assets/css/frontend.css', array(), SIDRENA_CIJENA_VERSION );
		}

		wp_enqueue_script(
			'sidrena-cijena-admin',
			SIDRENA_CIJENA_URL . 'assets/js/admin.js',
			array(),
			SIDRENA_CIJENA_VERSION,
			true
		);
	}

	/**
	 * An anchor glyph, since Dashicons has no anchor icon.
	 *
	 * @return string
	 */
	public static function menu_icon() {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><g fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="4" r="2"/><path d="M12 6v14m-5-9h10M4 15c0 4 3.6 6 8 6s8-2 8-6m-16 0 2.5 1.5M20 15l-2.5 1.5"/></g></svg>';

		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- WordPress admin menu recognizes SVG icons only in this encoded form.
	}
}
