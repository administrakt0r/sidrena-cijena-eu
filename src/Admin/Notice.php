<?php
/**
 * Shared dismissible admin notices.
 *
 * @package SidrenaCijena\Admin
 */

namespace SidrenaCijena\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * One dismissible notice renderer used by every plugin screen.
 */
class Notice {

	/**
	 * Render a dismissible notice.
	 *
	 * @param string $type    Notice type: success, info, warning or error.
	 * @param string $message Notice text.
	 * @param bool   $focus   Move focus to the notice on page load.
	 * @return void
	 */
	public static function render( $type, $message, $focus = true ) {
		$text = (string) $message;

		if ( '' === $text ) {
			return;
		}

		$style = in_array( $type, array( 'success', 'info', 'warning', 'error' ), true ) ? $type : 'error';

		printf(
			'<div class="notice notice-%1$s sidrena-cijena-notice is-dismissible" tabindex="-1"%2$s><p>%3$s</p></div>' . "\n",
			esc_attr( $style ),
			$focus ? ' data-sidrena-notice-focus="1"' : '',
			esc_html( $text )
		);
	}

	/**
	 * Render the notice carried in the query string after a redirect.
	 *
	 * Known types map to themselves (`error`, `info`, `warning`); any other
	 * type is a success notice.
	 *
	 * @return void
	 */
	public static function from_request() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only notice flags.
		$type = isset( $_GET['sidrena_cijena_notice'] ) ? sanitize_key( wp_unslash( $_GET['sidrena_cijena_notice'] ) ) : '';

		if ( '' === $type ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only notice text.
		$text = isset( $_GET['sidrena_cijena_notice_text'] ) ? sanitize_text_field( wp_unslash( $_GET['sidrena_cijena_notice_text'] ) ) : '';

		$style = in_array( $type, array( 'error', 'info', 'warning' ), true ) ? $type : 'success';

		self::render( $style, $text );
	}
}
