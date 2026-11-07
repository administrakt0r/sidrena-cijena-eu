<?php
/**
 * Admin menu and asset registration.
 *
 * @package Titanworks\SidrenaCijena\Admin
 */

namespace Titanworks\SidrenaCijena\Admin;

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

		if ( '' !== $page && ( 0 === strpos( $page, 'sidrena-cijena' ) || 0 === strpos( $page, 'twbce_item' ) ) ) {
			return true;
		}

		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();

			if ( $screen ) {
				if ( false !== strpos( (string) $screen->id, 'sidrena-cijena' ) || false !== strpos( (string) $screen->id, 'twbce_item' ) ) {
					return true;
				}

				if ( $include_product && in_array( $screen->base, array( 'post', 'post-new' ), true ) && in_array( $screen->post_type, array( 'product', 'twbce_item' ), true ) ) {
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
			if ( $screen && ( 'product' === $screen->post_type || 'twbce_item' === $screen->post_type ) ) {
				$is_product_screen = true;
			}
		}

		$is_plugin_screen = false !== strpos( (string) $hook_suffix, 'sidrena-cijena' ) || false !== strpos( (string) $hook_suffix, 'twbce_item' );

		if ( ! $is_product_screen && ! $is_plugin_screen ) {
			return;
		}

		wp_enqueue_style(
			'sidrena-cijena-admin',
			TWBCE_CIJENA_URL . 'assets/css/admin.css',
			array(),
			TWBCE_CIJENA_VERSION
		);

		if ( isset( $_GET['tab'] ) && 'styling' === sanitize_key( wp_unslash( $_GET['tab'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only asset selection.
			wp_enqueue_style( 'sidrena-cijena-frontend', TWBCE_CIJENA_URL . 'assets/css/frontend.css', array(), TWBCE_CIJENA_VERSION );
		}

		wp_enqueue_script(
			'sidrena-cijena-admin',
			TWBCE_CIJENA_URL . 'assets/js/admin.js',
			array( 'jquery', 'jquery-ui-datepicker' ),
			TWBCE_CIJENA_VERSION,
			true
		);

		wp_localize_script(
			'sidrena-cijena-admin',
			'sidrenaDatepickerI18n',
			array(
				'closeText'       => __( 'Done', 'sidrena-cijena-eu' ),
				'prevText'        => __( 'Prev', 'sidrena-cijena-eu' ),
				'nextText'        => __( 'Next', 'sidrena-cijena-eu' ),
				'currentText'     => __( 'Today', 'sidrena-cijena-eu' ),
				'monthNames'      => array(
					__( 'January', 'sidrena-cijena-eu' ),
					__( 'February', 'sidrena-cijena-eu' ),
					__( 'March', 'sidrena-cijena-eu' ),
					__( 'April', 'sidrena-cijena-eu' ),
					__( 'May', 'sidrena-cijena-eu' ),
					__( 'June', 'sidrena-cijena-eu' ),
					__( 'July', 'sidrena-cijena-eu' ),
					__( 'August', 'sidrena-cijena-eu' ),
					__( 'September', 'sidrena-cijena-eu' ),
					__( 'October', 'sidrena-cijena-eu' ),
					__( 'November', 'sidrena-cijena-eu' ),
					__( 'December', 'sidrena-cijena-eu' ),
				),
				'monthNamesShort' => array(
					__( 'Jan', 'sidrena-cijena-eu' ),
					__( 'Feb', 'sidrena-cijena-eu' ),
					__( 'Mar', 'sidrena-cijena-eu' ),
					__( 'Apr', 'sidrena-cijena-eu' ),
					__( 'May', 'sidrena-cijena-eu' ),
					__( 'Jun', 'sidrena-cijena-eu' ),
					__( 'Jul', 'sidrena-cijena-eu' ),
					__( 'Aug', 'sidrena-cijena-eu' ),
					__( 'Sep', 'sidrena-cijena-eu' ),
					__( 'Oct', 'sidrena-cijena-eu' ),
					__( 'Nov', 'sidrena-cijena-eu' ),
					__( 'Dec', 'sidrena-cijena-eu' ),
				),
				'dayNames'        => array(
					__( 'Sunday', 'sidrena-cijena-eu' ),
					__( 'Monday', 'sidrena-cijena-eu' ),
					__( 'Tuesday', 'sidrena-cijena-eu' ),
					__( 'Wednesday', 'sidrena-cijena-eu' ),
					__( 'Thursday', 'sidrena-cijena-eu' ),
					__( 'Friday', 'sidrena-cijena-eu' ),
					__( 'Saturday', 'sidrena-cijena-eu' ),
				),
				'dayNamesShort'   => array(
					__( 'Sun', 'sidrena-cijena-eu' ),
					__( 'Mon', 'sidrena-cijena-eu' ),
					__( 'Tue', 'sidrena-cijena-eu' ),
					__( 'Wed', 'sidrena-cijena-eu' ),
					__( 'Thu', 'sidrena-cijena-eu' ),
					__( 'Fri', 'sidrena-cijena-eu' ),
					__( 'Sat', 'sidrena-cijena-eu' ),
				),
				'dayNamesMin'     => array(
					__( 'Su', 'sidrena-cijena-eu' ),
					__( 'Mo', 'sidrena-cijena-eu' ),
					__( 'Tu', 'sidrena-cijena-eu' ),
					__( 'We', 'sidrena-cijena-eu' ),
					__( 'Th', 'sidrena-cijena-eu' ),
					__( 'Fr', 'sidrena-cijena-eu' ),
					__( 'Sa', 'sidrena-cijena-eu' ),
				),
				'dateFormat'      => 'dd.mm.yy.',
				'firstDay'        => 1,
			)
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
