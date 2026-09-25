<?php
/**
 * First-run welcome banner with author projects.
 *
 * @package SidrenaCijena\Admin
 */

namespace SidrenaCijena\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Shows a dismissible welcome banner after activation.
 */
final class WelcomeBanner {

	/**
	 * Flag set on activation to trigger a one-time setup redirect.
	 */
	const REDIRECT_OPTION = 'sidrena_cijena_activation_redirect';

	/**
	 * Dismissal flag.
	 */
	const DISMISSED_OPTION = 'sidrena_cijena_welcome_dismissed';

	/**
	 * Dismiss action.
	 */
	const DISMISS_ACTION = 'sidrena_cijena_dismiss_welcome';

	/**
	 * Free projects promoted in the banner and setup guide.
	 *
	 * @return array<int,array{url:string,title:string,description:string}>
	 */
	public static function links() {
		return array(
			array(
				'url'         => 'https://wpineu.com',
				'title'       => 'WPinEU.com',
				'description' => __( 'WordPress in Europe — premium WordPress hosting, start free.', 'sidrena-cijena' ),
			),
			array(
				'url'         => 'https://llm.kiwi',
				'title'       => 'LLM.kiwi',
				'description' => __( 'FREE AI API endpoint, OpenAI compatible — put AI inference in your apps and projects for free!', 'sidrena-cijena' ),
			),
		);
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'admin_notices', array( __CLASS__, 'render' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_redirect' ) );
		add_action( 'admin_post_' . self::DISMISS_ACTION, array( __CLASS__, 'handle_dismiss' ) );
	}

	/**
	 * Whether the banner should be shown.
	 *
	 * @return bool
	 */
	public static function should_show() {
		if ( ! Capabilities::current_user_can() ) {
			return false;
		}

		if ( get_option( self::DISMISSED_OPTION, false ) ) {
			return false;
		}

		return true;
	}

	/**
	 * One-time redirect to the setup guide after activation.
	 *
	 * @return void
	 */
	public static function maybe_redirect() {
		if ( ! get_option( self::REDIRECT_OPTION, false ) ) {
			return;
		}

		if ( ! Capabilities::current_user_can() ) {
			return;
		}

		if ( ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}

		delete_option( self::REDIRECT_OPTION );

		// Never redirect on bulk/plugin activation screens or when already there.
		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();

			if ( $screen && false !== strpos( (string) $screen->id, SetupGuidePage::SLUG ) ) {
				return;
			}
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing checks.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( SetupGuidePage::SLUG === $page || 'plugins' === $page ) {
			return;
		}

		if ( ! function_exists( 'wp_safe_redirect' ) ) {
			return;
		}

		wp_safe_redirect( AdminMenu::url( SetupGuidePage::SLUG ) );
		exit;
	}

	/**
	 * Render the banner.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! self::should_show() ) {
			return;
		}

		$setup_url   = AdminMenu::url( SetupGuidePage::SLUG );
		$dismiss_url = wp_nonce_url(
			add_query_arg(
				array( 'action' => self::DISMISS_ACTION ),
				admin_url( 'admin-post.php' )
			),
			self::DISMISS_ACTION
		);

		echo '<div class="notice notice-info sidrena-welcome-banner">';
		echo '<h2>' . esc_html__( 'Welcome to Sidrena cijena!', 'sidrena-cijena' ) . '</h2>';
		echo '<p>' . esc_html__( 'Thank you for installing. Open the setup guide to configure locations, legal rules, anchor prices and the daily price list in a few minutes.', 'sidrena-cijena' ) . '</p>';
		echo '<p>' . esc_html__( 'Made by administrakt0r.', 'sidrena-cijena' ) . '</p>';
		echo '<ul class="sidrena-welcome-banner__projects">';

		foreach ( self::links() as $link ) {
			printf(
				'<li><a href="%1$s" target="_blank" rel="noopener"><strong>%2$s</strong></a> — %3$s</li>',
				esc_url( $link['url'] ),
				esc_html( $link['title'] ),
				esc_html( $link['description'] )
			);
		}

		echo '</ul>';
		printf(
			'<p><a class="button button-primary" href="%1$s">%2$s</a> <a class="button" href="%3$s">%4$s</a></p>',
			esc_url( $setup_url ),
			esc_html__( 'Open Setup Guide', 'sidrena-cijena' ),
			esc_url( $dismiss_url ),
			esc_html__( 'Dismiss', 'sidrena-cijena' )
		);
		echo '</div>';
	}

	/**
	 * Dismiss the banner.
	 *
	 * @return void
	 */
	public static function handle_dismiss() {
		if ( ! Capabilities::current_user_can() ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'sidrena-cijena' ) );
		}

		check_admin_referer( self::DISMISS_ACTION );

		update_option( self::DISMISSED_OPTION, 1, false );

		$back = wp_get_referer();

		if ( ! $back ) {
			$back = admin_url();
		}

		wp_safe_redirect( $back );
		exit;
	}
}
