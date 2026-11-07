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
			__( 'Sidrena cijena', 'titanworks-bazna-cijena' ),
			__( 'Sidrena cijena', 'titanworks-bazna-cijena' ),
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
				sprintf( __( '%s - Sidrena cijena', 'titanworks-bazna-cijena' ), $page[1] ),
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
			self::PARENT                  => array( DashboardPage::class, __( 'Dashboard', 'titanworks-bazna-cijena' ) ),
			SetupGuidePage::SLUG          => array( SetupGuidePage::class, __( 'Setup Guide', 'titanworks-bazna-cijena' ) ),
			'sidrena-cijena-publications' => array( PublicationsPage::class, __( 'Price lists', 'titanworks-bazna-cijena' ) ),
			'sidrena-cijena-anchors'      => array( BulkAnchorEntryPage::class, __( 'Anchor prices', 'titanworks-bazna-cijena' ) ),
			'sidrena-cijena-import'       => array( ImportPage::class, __( 'Import', 'titanworks-bazna-cijena' ) ),
			'sidrena-cijena-locations'    => array( LocationsPage::class, __( 'Sales locations', 'titanworks-bazna-cijena' ) ),
			'sidrena-cijena-history'      => array( PriceHistoryPage::class, __( 'Price history', 'titanworks-bazna-cijena' ) ),
			'sidrena-cijena-compliance'   => array( CompliancePage::class, __( 'Compliance check', 'titanworks-bazna-cijena' ) ),
			'sidrena-cijena-settings'     => array( SettingsPage::class, __( 'Settings', 'titanworks-bazna-cijena' ) ),
			'sidrena-cijena-logs'         => array( LogsPage::class, __( 'Logs', 'titanworks-bazna-cijena' ) ),
			'sidrena-cijena-audit'        => array( AuditPage::class, __( 'Audit', 'titanworks-bazna-cijena' ) ),
			DocumentationPage::SLUG       => array( DocumentationPage::class, __( 'Documentation', 'titanworks-bazna-cijena' ) ),
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
			array( 'jquery', 'jquery-ui-datepicker' ),
			SIDRENA_CIJENA_VERSION,
			true
		);

		wp_localize_script(
			'sidrena-cijena-admin',
			'sidrenaDatepickerI18n',
			array(
				'closeText'       => __( 'Done', 'titanworks-bazna-cijena' ),
				'prevText'        => __( 'Prev', 'titanworks-bazna-cijena' ),
				'nextText'        => __( 'Next', 'titanworks-bazna-cijena' ),
				'currentText'     => __( 'Today', 'titanworks-bazna-cijena' ),
				'monthNames'      => array(
					__( 'January', 'titanworks-bazna-cijena' ),
					__( 'February', 'titanworks-bazna-cijena' ),
					__( 'March', 'titanworks-bazna-cijena' ),
					__( 'April', 'titanworks-bazna-cijena' ),
					__( 'May', 'titanworks-bazna-cijena' ),
					__( 'June', 'titanworks-bazna-cijena' ),
					__( 'July', 'titanworks-bazna-cijena' ),
					__( 'August', 'titanworks-bazna-cijena' ),
					__( 'September', 'titanworks-bazna-cijena' ),
					__( 'October', 'titanworks-bazna-cijena' ),
					__( 'November', 'titanworks-bazna-cijena' ),
					__( 'December', 'titanworks-bazna-cijena' ),
				),
				'monthNamesShort' => array(
					__( 'Jan', 'titanworks-bazna-cijena' ),
					__( 'Feb', 'titanworks-bazna-cijena' ),
					__( 'Mar', 'titanworks-bazna-cijena' ),
					__( 'Apr', 'titanworks-bazna-cijena' ),
					__( 'May', 'titanworks-bazna-cijena' ),
					__( 'Jun', 'titanworks-bazna-cijena' ),
					__( 'Jul', 'titanworks-bazna-cijena' ),
					__( 'Aug', 'titanworks-bazna-cijena' ),
					__( 'Sep', 'titanworks-bazna-cijena' ),
					__( 'Oct', 'titanworks-bazna-cijena' ),
					__( 'Nov', 'titanworks-bazna-cijena' ),
					__( 'Dec', 'titanworks-bazna-cijena' ),
				),
				'dayNames'        => array(
					__( 'Sunday', 'titanworks-bazna-cijena' ),
					__( 'Monday', 'titanworks-bazna-cijena' ),
					__( 'Tuesday', 'titanworks-bazna-cijena' ),
					__( 'Wednesday', 'titanworks-bazna-cijena' ),
					__( 'Thursday', 'titanworks-bazna-cijena' ),
					__( 'Friday', 'titanworks-bazna-cijena' ),
					__( 'Saturday', 'titanworks-bazna-cijena' ),
				),
				'dayNamesShort'   => array(
					__( 'Sun', 'titanworks-bazna-cijena' ),
					__( 'Mon', 'titanworks-bazna-cijena' ),
					__( 'Tue', 'titanworks-bazna-cijena' ),
					__( 'Wed', 'titanworks-bazna-cijena' ),
					__( 'Thu', 'titanworks-bazna-cijena' ),
					__( 'Fri', 'titanworks-bazna-cijena' ),
					__( 'Sat', 'titanworks-bazna-cijena' ),
				),
				'dayNamesMin'     => array(
					__( 'Su', 'titanworks-bazna-cijena' ),
					__( 'Mo', 'titanworks-bazna-cijena' ),
					__( 'Tu', 'titanworks-bazna-cijena' ),
					__( 'We', 'titanworks-bazna-cijena' ),
					__( 'Th', 'titanworks-bazna-cijena' ),
					__( 'Fr', 'titanworks-bazna-cijena' ),
					__( 'Sa', 'titanworks-bazna-cijena' ),
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
