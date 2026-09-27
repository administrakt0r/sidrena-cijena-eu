<?php
/**
 * Price history screen.
 *
 * @package SidrenaCijena\Admin
 */

namespace SidrenaCijena\Admin;

use SidrenaCijena\Database\PriceHistoryRepository;
use SidrenaCijena\Plugin;
use SidrenaCijena\Support\Currency;
use SidrenaCijena\Support\Dates;
use SidrenaCijena\Support\Labels;

defined( 'ABSPATH' ) || exit;

/**
 * Lists the append-only price history and records corrections.
 */
class PriceHistoryPage {

	/**
	 * Page slug.
	 *
	 * @var string
	 */
	const SLUG = 'sidrena-cijena-history';

	/**
	 * Correction action.
	 *
	 * @var string
	 */
	const ACTION = 'sidrena_cijena_correct_history';

	/**
	 * Rows per page.
	 *
	 * @var int
	 */
	const PER_PAGE = 50;

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! Capabilities::current_user_can() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'sidrena-cijena-eu' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filters.
		$product_id = isset( $_GET['product_id'] ) ? absint( wp_unslash( $_GET['product_id'] ) ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filters.
		$variation_id = isset( $_GET['variation_id'] ) ? absint( wp_unslash( $_GET['variation_id'] ) ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filters.
		$type = isset( $_GET['price_type'] ) ? sanitize_key( wp_unslash( $_GET['price_type'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filters.
		$source = isset( $_GET['source'] ) ? sanitize_key( wp_unslash( $_GET['source'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination.
		$page = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;

		$history = Plugin::instance()->history();
		$args    = array(
			'limit'  => self::PER_PAGE,
			'offset' => ( $page - 1 ) * self::PER_PAGE,
			'order'  => 'DESC',
		);

		if ( $product_id > 0 ) {
			$args['product_id'] = $product_id;
		}

		if ( $variation_id > 0 ) {
			$args['variation_id'] = $variation_id;
		}

		if ( in_array( $type, PriceHistoryRepository::TYPES, true ) ) {
			$args['price_type'] = $type;
		}

		if ( '' !== $source ) {
			$args['source'] = $source;
		}

		$rows  = $history->query( $args );
		$total = $history->count(
			array_diff_key(
				$args,
				array(
					'limit'  => 1,
					'offset' => 1,
					'order'  => 1,
				)
			)
		);

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Sidrena cijena: Price history', 'sidrena-cijena-eu' ) . '</h1>';

		self::notice();

		echo '<p>' . esc_html__( 'Price history is append-only. Corrections add a new record that references the original; nothing is overwritten or deleted.', 'sidrena-cijena-eu' ) . '</p>';

		self::render_filters( $product_id, $variation_id, $type, $source );

		if ( $product_id > 0 ) {
			$item = $variation_id > 0
				? Plugin::instance()->catalog()->get_variation( $product_id, $variation_id )
				: Plugin::instance()->catalog()->get_item( $product_id );

			if ( $item ) {
				$explainer   = Plugin::instance()->lowest_price_explainer();
				$explanation = $explainer->explain( $item, Dates::today() );
				echo '<div class="sidrena-cijena-history-explanation" style="margin: 15px 0; padding: 12px; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px;">';
				echo '<h2 style="margin-top: 0; font-size: 14px;">' . esc_html__( '30-day reference price explanation', 'sidrena-cijena-eu' ) . '</h2>';
				echo $explainer->render_html( $explanation ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '</div>';
			}
		}

		self::render_table( $rows, $total, $page );
		self::render_correction_form( $product_id );

		echo '</div>';
	}

	/**
	 * Render the filter bar.
	 *
	 * @param int    $product_id   Product filter.
	 * @param int    $variation_id Variation filter.
	 * @param string $type         Price type filter.
	 * @param string $source       Source filter.
	 * @return void
	 */
	protected static function render_filters( $product_id, $variation_id, $type, $source ) {
		echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '">';
		echo '<input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '" />';
		echo '<p>';
		echo '<label>' . esc_html__( 'Product ID', 'sidrena-cijena-eu' ) . ' <input type="number" name="product_id" value="' . esc_attr( (string) $product_id ) . '" min="0" /></label> ';
		echo '<label>' . esc_html__( 'Variation ID', 'sidrena-cijena-eu' ) . ' <input type="number" name="variation_id" value="' . esc_attr( (string) $variation_id ) . '" min="0" /></label> ';

		echo '<label>' . esc_html__( 'Type', 'sidrena-cijena-eu' ) . ' <select name="price_type">';
		echo '<option value="">' . esc_html__( 'All types', 'sidrena-cijena-eu' ) . '</option>';

		foreach ( PriceHistoryRepository::TYPES as $option ) {
			echo '<option value="' . esc_attr( $option ) . '"' . selected( $type, $option, false ) . '>' . esc_html( Labels::price_type( $option ) ) . '</option>';
		}

		echo '</select></label> ';

		echo '<label>' . esc_html__( 'Source', 'sidrena-cijena-eu' ) . ' <select name="source">';
		echo '<option value="">' . esc_html__( 'All sources', 'sidrena-cijena-eu' ) . '</option>';

		foreach ( PriceHistoryRepository::SOURCES as $option ) {
			echo '<option value="' . esc_attr( $option ) . '"' . selected( $source, $option, false ) . '>' . esc_html( Labels::source( $option ) ) . '</option>';
		}

		echo '</select></label> ';

		submit_button( __( 'Filter', 'sidrena-cijena-eu' ), 'secondary', 'submit', false );
		echo '</p></form>';
	}

	/**
	 * Render the history table.
	 *
	 * @param array<mixed> $rows  Rows.
	 * @param int          $total Total rows.
	 * @param int          $page  Current page.
	 * @return void
	 */
	protected static function render_table( array $rows, $total, $page ) {
		echo '<table class="widefat striped">';
		echo '<caption class="screen-reader-text">' . esc_html__( 'Price history records', 'sidrena-cijena-eu' ) . '</caption>';
		echo '<thead><tr>';
		foreach ( array(
			__( 'Date', 'sidrena-cijena-eu' ),
			__( 'Item', 'sidrena-cijena-eu' ),
			__( 'Type', 'sidrena-cijena-eu' ),
			__( 'Price', 'sidrena-cijena-eu' ),
			__( 'Source', 'sidrena-cijena-eu' ),
			__( 'User', 'sidrena-cijena-eu' ),
			__( 'Reference date', 'sidrena-cijena-eu' ),
			__( 'Note', 'sidrena-cijena-eu' ),
		) as $heading ) {
			echo '<th scope="col">' . esc_html( $heading ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		if ( empty( $rows ) ) {
			echo '<tr><td colspan="8">' . esc_html__( 'No price history records match the filter.', 'sidrena-cijena-eu' ) . '</td></tr>';
		}

		foreach ( $rows as $row ) {
			$item = self::item_label( (int) $row['product_id'], (int) $row['variation_id'] );
			$user = self::user_label( isset( $row['created_by'] ) ? (int) $row['created_by'] : 0 );
			$note = isset( $row['correction_reason'] ) ? (string) $row['correction_reason'] : '';

			echo '<tr>';
			echo '<td>' . esc_html( Dates::from_utc( (string) $row['created_at'] )->format( 'Y-m-d H:i' ) ) . '</td>';
			echo '<td>' . esc_html( $item ) . '</td>';
			echo '<td>' . esc_html( Labels::price_type( (string) $row['price_type'] ) ) . '</td>';
			echo '<td>' . esc_html( Currency::format_decimal( (float) $row['price'] ) . ' ' . (string) $row['currency'] ) . '</td>';
			echo '<td>' . esc_html( Labels::source( (string) $row['source'] ) ) . '</td>';
			echo '<td>' . esc_html( $user ) . '</td>';
			$reference = Dates::format_date_display( isset( $row['reference_date'] ) ? $row['reference_date'] : '' );
			echo '<td>' . esc_html( '' !== $reference ? $reference : '—' ) . '</td>';
			echo '<td>';
			echo esc_html( $note );

			if ( ! empty( $row['correction_of'] ) ) {
				echo '<br /><em>' . esc_html( sprintf( /* translators: %d: corrected record ID. */ __( 'correction of record #%d', 'sidrena-cijena-eu' ), (int) $row['correction_of'] ) ) . '</em>';
			}

			echo '</td></tr>';
		}

		echo '</tbody></table>';

		Pagination::render( $page, $total, self::PER_PAGE );
	}

	/**
	 * Render the correction form.
	 *
	 * @param int $product_id Prefilled product ID.
	 * @return void
	 */
	protected static function render_correction_form( $product_id ) {
		$state     = FormState::pull( 'history-correction' );
		$values    = $state['values'];
		$errors    = $state['errors'];
		$product   = isset( $values['product_id'] ) ? (string) $values['product_id'] : (string) $product_id;
		$variation = isset( $values['variation_id'] ) ? (string) $values['variation_id'] : '0';
		$type      = isset( $values['price_type'] ) ? (string) $values['price_type'] : 'anchor';
		$price     = isset( $values['price'] ) ? (string) $values['price'] : '';
		$reference = isset( $values['reference_date'] ) ? (string) $values['reference_date'] : '';
		$reason    = isset( $values['correction_reason'] ) ? (string) $values['correction_reason'] : '';

		echo '<h2>' . esc_html__( 'Record a correction', 'sidrena-cijena-eu' ) . '</h2>';
		echo '<p>' . esc_html__( 'The original record stays in the history. A correction stores who recorded it, when and why.', 'sidrena-cijena-eu' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( self::ACTION );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '" />';

		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row"><label for="sidrena-history-product">' . esc_html__( 'Product ID', 'sidrena-cijena-eu' ) . '</label></th>';
		echo '<td><input type="number" id="sidrena-history-product" name="product_id" min="1" required value="' . esc_attr( $product ) . '"' . FormState::error_attributes( $errors, 'product_id', 'sidrena-history-product' ) . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attribute markup escaped in FormState::error_attributes().
		FormState::render_error( $errors, 'product_id', 'sidrena-history-product' );
		echo '</td></tr>';
		echo '<tr><th scope="row"><label for="sidrena-history-variation">' . esc_html__( 'Variation ID', 'sidrena-cijena-eu' ) . '</label></th>';
		echo '<td><input type="number" id="sidrena-history-variation" name="variation_id" min="0" value="' . esc_attr( $variation ) . '" /></td></tr>';
		echo '<tr><th scope="row"><label for="sidrena-history-type">' . esc_html__( 'Price type', 'sidrena-cijena-eu' ) . '</label></th><td><select id="sidrena-history-type" name="price_type"' . FormState::error_attributes( $errors, 'price_type', 'sidrena-history-type' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attribute markup escaped in FormState::error_attributes().

		foreach ( array( 'anchor', 'regular', 'sale' ) as $option ) {
			echo '<option value="' . esc_attr( $option ) . '"' . selected( $type, $option, false ) . '>' . esc_html( Labels::price_type( $option ) ) . '</option>';
		}

		echo '</select>';
		FormState::render_error( $errors, 'price_type', 'sidrena-history-type' );
		echo '</td></tr>';
		echo '<tr><th scope="row"><label for="sidrena-history-price">' . esc_html__( 'New price', 'sidrena-cijena-eu' ) . '</label></th>';
		echo '<td><input type="text" id="sidrena-history-price" name="price" required value="' . esc_attr( $price ) . '"' . FormState::error_attributes( $errors, 'price', 'sidrena-history-price' ) . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attribute markup escaped in FormState::error_attributes().
		FormState::render_error( $errors, 'price', 'sidrena-history-price' );
		echo '</td></tr>';
		echo '<tr><th scope="row"><label for="sidrena-history-reference">' . esc_html__( 'Reference date', 'sidrena-cijena-eu' ) . '</label></th>';
		echo '<td><input type="date" id="sidrena-history-reference" name="reference_date" value="' . esc_attr( $reference ) . '"' . FormState::error_attributes( $errors, 'reference_date', 'sidrena-history-reference' ) . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attribute markup escaped in FormState::error_attributes().
		echo '<p class="description">' . esc_html__( 'Required for anchor prices.', 'sidrena-cijena-eu' ) . '</p>';
		FormState::render_error( $errors, 'reference_date', 'sidrena-history-reference' );
		echo '</td></tr>';
		echo '<tr><th scope="row"><label for="sidrena-history-reason">' . esc_html__( 'Reason', 'sidrena-cijena-eu' ) . '</label></th>';
		echo '<td><input type="text" class="regular-text" id="sidrena-history-reason" name="correction_reason" required value="' . esc_attr( $reason ) . '"' . FormState::error_attributes( $errors, 'correction_reason', 'sidrena-history-reason' ) . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attribute markup escaped in FormState::error_attributes().
		echo '<p class="description">' . esc_html__( 'Required. Explain why the original value is being corrected.', 'sidrena-cijena-eu' ) . '</p>';
		FormState::render_error( $errors, 'correction_reason', 'sidrena-history-reason' );
		echo '</td></tr>';
		echo '</tbody></table>';

		submit_button( __( 'Record correction', 'sidrena-cijena-eu' ) );
		echo '</form>';
	}

	/**
	 * Handle the correction form.
	 *
	 * @return void
	 */
	public static function handle_correct() {
		if ( ! Capabilities::current_user_can() ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'sidrena-cijena-eu' ) );
		}

		check_admin_referer( self::ACTION );

		$product_id   = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
		$variation_id = isset( $_POST['variation_id'] ) ? absint( wp_unslash( $_POST['variation_id'] ) ) : 0;
		$type         = isset( $_POST['price_type'] ) ? sanitize_key( wp_unslash( $_POST['price_type'] ) ) : '';
		$price        = isset( $_POST['price'] ) ? sanitize_text_field( wp_unslash( $_POST['price'] ) ) : '';
		$reference    = isset( $_POST['reference_date'] ) ? sanitize_text_field( wp_unslash( $_POST['reference_date'] ) ) : '';
		$reason       = isset( $_POST['correction_reason'] ) ? sanitize_text_field( wp_unslash( $_POST['correction_reason'] ) ) : '';

		$values = array(
			'product_id'        => $product_id,
			'variation_id'      => $variation_id,
			'price_type'        => $type,
			'price'             => $price,
			'reference_date'    => $reference,
			'correction_reason' => $reason,
		);

		if ( $product_id <= 0 || ! in_array( $type, array( 'anchor', 'regular', 'sale' ), true ) || '' === $price || '' === $reason ) {
			$errors = array();

			if ( $product_id <= 0 ) {
				$errors['product_id'] = __( 'Enter a product ID.', 'sidrena-cijena-eu' );
			}

			if ( ! in_array( $type, array( 'anchor', 'regular', 'sale' ), true ) ) {
				$errors['price_type'] = __( 'Choose a price type.', 'sidrena-cijena-eu' );
			}

			if ( '' === $price ) {
				$errors['price'] = __( 'Enter the new price.', 'sidrena-cijena-eu' );
			}

			if ( '' === $reason ) {
				$errors['correction_reason'] = __( 'Enter a correction reason.', 'sidrena-cijena-eu' );
			}

			self::redirect_error( __( 'The product, price type, price and reason are required.', 'sidrena-cijena-eu' ), $values, $errors );
		}

		$catalog = Plugin::instance()->catalog();
		$item    = $variation_id > 0 ? $catalog->get_variation( $product_id, $variation_id ) : $catalog->get_item( $product_id );

		if ( ! $item ) {
			self::redirect_error( __( 'The product could not be found.', 'sidrena-cijena-eu' ), $values, array( 'product_id' => __( 'The product could not be found.', 'sidrena-cijena-eu' ) ) );
		}

		$history = Plugin::instance()->history();
		$latest  = $history->latest( $product_id, $variation_id, $type );

		if ( ! $latest ) {
			self::redirect_error( __( 'There is no record of this type to correct.', 'sidrena-cijena-eu' ), $values, array( 'price_type' => __( 'There is no record of this type to correct.', 'sidrena-cijena-eu' ) ) );
		}

		if ( 'anchor' === $type && ! Dates::is_valid_date( $reference ) ) {
			self::redirect_error( __( 'A valid reference date is required for anchor prices.', 'sidrena-cijena-eu' ), $values, array( 'reference_date' => __( 'A valid reference date is required for anchor prices.', 'sidrena-cijena-eu' ) ) );
		}

		$normalized = Currency::to_storage( $price );

		if ( null === $normalized || $normalized < 0 ) {
			self::redirect_error( __( 'The price is not a valid amount.', 'sidrena-cijena-eu' ), $values, array( 'price' => __( 'The price is not a valid amount.', 'sidrena-cijena-eu' ) ) );
		}

		$record = array(
			'product_id'        => $product_id,
			'variation_id'      => $variation_id,
			'item_type'         => $item->type,
			'sku'               => $item->sku,
			'price_type'        => $type,
			'price'             => $normalized,
			'currency'          => $item->currency,
			'reference_date'    => 'anchor' === $type ? $reference : null,
			'source'            => 'manual',
			'created_by'        => get_current_user_id(),
			'correction_of'     => (int) $latest['id'],
			'correction_reason' => $reason,
		);

		$result = $history->insert_correction( $record );

		if ( ! $result ) {
			self::redirect_error( __( 'The correction could not be stored.', 'sidrena-cijena-eu' ), $values );
		}

		Plugin::instance()->flush_caches();

		self::redirect( 'success', __( 'Correction recorded. The original value is preserved.', 'sidrena-cijena-eu' ) );
	}

	/**
	 * Redirect back to the form keeping the submitted values and field errors.
	 *
	 * @param string                $message Notice text.
	 * @param array<string, mixed>  $values  Submitted values.
	 * @param array<string, string> $errors  Field errors.
	 * @return void
	 */
	private static function redirect_error( $message, array $values, array $errors = array() ) {
		FormState::store( 'history-correction', $values, $errors );

		self::redirect( 'error', $message );
	}

	/**
	 * Human label for an item.
	 *
	 * @param int $product_id   Product ID.
	 * @param int $variation_id Variation ID.
	 * @return string
	 */
	protected static function item_label( $product_id, $variation_id ) {
		$catalog = Plugin::instance()->catalog();
		$item    = $variation_id > 0 ? $catalog->get_variation( $product_id, $variation_id ) : $catalog->get_item( $product_id );

		if ( ! $item ) {
			return '#' . $product_id . ( $variation_id > 0 ? ' / #' . $variation_id : '' );
		}

		return $item->name . ' (' . $item->sku . ')';
	}

	/**
	 * Display name for a user ID.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	protected static function user_label( $user_id ) {
		if ( $user_id <= 0 ) {
			return __( 'system', 'sidrena-cijena-eu' );
		}

		$user = get_userdata( $user_id );

		return $user ? $user->display_name : '#' . $user_id;
	}

	/**
	 * Redirect back with a notice.
	 *
	 * @param string $type    `success` or `error`.
	 * @param string $message Notice text.
	 * @return void
	 */
	protected static function redirect( $type, $message ) {
		$url = add_query_arg(
			array(
				'page'                       => self::SLUG,
				'sidrena_cijena_notice'      => $type,
				'sidrena_cijena_notice_text' => rawurlencode( $message ),
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Render the notice.
	 *
	 * @return void
	 */
	protected static function notice() {
		Notice::from_request();
	}
}
