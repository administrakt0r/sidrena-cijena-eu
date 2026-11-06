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
			__( 'Sidrena cijena', 'titanworks-sidrena-bazna-cijena' ),
			__( 'Sidrena cijena', 'titanworks-sidrena-bazna-cijena' ),
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
				sprintf( __( '%s - Sidrena cijena', 'titanworks-sidrena-bazna-cijena' ), $page[1] ),
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
			self::PARENT                  => array( DashboardPage::class, __( 'Dashboard', 'titanworks-sidrena-bazna-cijena' ) ),
			SetupGuidePage::SLUG          => array( SetupGuidePage::class, __( 'Setup Guide', 'titanworks-sidrena-bazna-cijena' ) ),
			'sidrena-cijena-publications' => array( PublicationsPage::class, __( 'Price lists', 'titanworks-sidrena-bazna-cijena' ) ),
			'sidrena-cijena-anchors'      => array( BulkAnchorEntryPage::class, __( 'Anchor prices', 'titanworks-sidrena-bazna-cijena' ) ),
			'sidrena-cijena-import'       => array( ImportPage::class, __( 'Import', 'titanworks-sidrena-bazna-cijena' ) ),
			'sidrena-cijena-locations'    => array( LocationsPage::class, __( 'Sales locations', 'titanworks-sidrena-bazna-cijena' ) ),
			'sidrena-cijena-history'      => array( PriceHistoryPage::class, __( 'Price history', 'titanworks-sidrena-bazna-cijena' ) ),
			'sidrena-cijena-compliance'   => array( CompliancePage::class, __( 'Compliance check', 'titanworks-sidrena-bazna-cijena' ) ),
			'sidrena-cijena-settings'     => array( SettingsPage::class, __( 'Settings', 'titanworks-sidrena-bazna-cijena' ) ),
			'sidrena-cijena-logs'         => array( LogsPage::class, __( 'Logs', 'titanworks-sidrena-bazna-cijena' ) ),
			'sidrena-cijena-audit'        => array( AuditPage::class, __( 'Audit', 'titanworks-sidrena-bazna-cijena' ) ),
			DocumentationPage::SLUG       => array( DocumentationPage::class, __( 'Documentation', 'titanworks-sidrena-bazna-cijena' ) ),
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
	 * Whether the current request is on a plugin or product screen.
	 *
	 * Scopes admin notices and styles strictly to plugin screens to comply
	 * with WordPress.org Guideline 11.
	 *
	 * @param bool $include_product Whether to include WooCommerce product edit screens.
	 * @return bool
	 */
	public static function is_plugin_screen( $include_product = false ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( '' !== $page && ( 0 === strpos( $page, 'sidrena-cijena' ) || 0 === strpos( $page, 'sidrena_item' ) ) ) {
			return true;
		}

		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();

			if ( $screen ) {
				if ( false !== strpos( (string) $screen->id, 'sidrena-cijena' ) || false !== strpos( (string) $screen->id, 'sidrena_item' ) ) {
					return true;
				}

				if ( $include_product && in_array( $screen->base, array( 'post', 'post-new' ), true ) && in_array( $screen->post_type, array( 'product', 'sidrena_item' ), true ) ) {
					return true;
				}
			}
		}

		return false;
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
