<?php
/**
 * Admin meta box for anchor prices.
 *
 * @package SidrenaCijena\Admin
 */

namespace SidrenaCijena\Admin;

use SidrenaCijena\Catalog\StandalonePostType;
use SidrenaCijena\Plugin;
use SidrenaCijena\Support\Currency;
use SidrenaCijena\Support\Labels;
use SidrenaCijena\WooCommerce\WooCommerceDetector;
use SidrenaCijena\WooCommerce\Display\CardVisibility;

defined( 'ABSPATH' ) || exit;

/**
 * Shows and records the anchor price on item edit screens.
 */
final class AnchorMetaBox {

	/**
	 * Meta box ID.
	 */
	const ID = 'sidrena-cijena-anchor';

	/**
	 * Form action name.
	 */
	const ACTION = 'sidrena_cijena_record_anchor';

	/**
	 * Register the meta box on every supported screen.
	 *
	 * @return void
	 */
	public static function register() {
		if ( ! Capabilities::current_user_can() ) {
			return;
		}

		$screens = array();

		if ( post_type_exists( StandalonePostType::POST_TYPE ) ) {
			$screens[] = StandalonePostType::POST_TYPE;
		}

		foreach ( $screens as $screen ) {
			add_meta_box(
				self::ID,
				__( 'Sidrena cijena - anchor price', 'sidrena-cijena-eu' ),
				array( __CLASS__, 'render' ),
				$screen,
				'side',
				'high'
			);
		}
	}

	/**
	 * Add a dedicated tab to WooCommerce's native Product data panel.
	 *
	 * @param array<string,array<mixed>> $tabs Product data tabs.
	 * @return array<string,array<mixed>>
	 */
	public static function product_data_tab( $tabs ) {
		if ( ! Capabilities::current_user_can() || ! WooCommerceDetector::is_active() ) {
			return $tabs;
		}

		$tabs['sidrena_cijena'] = array(
			'label'    => __( 'Sidrena cijena', 'sidrena-cijena-eu' ),
			'target'   => 'sidrena_cijena_product_data',
			'class'    => array( 'show_if_simple', 'show_if_variable', 'show_if_grouped', 'show_if_external' ),
			'priority' => 80,
		);

		return $tabs;
	}

	/**
	 * Render the full-width Sidrena panel inside WooCommerce Product data.
	 *
	 * @return void
	 */
	public static function render_product_data_panel() {
		global $post;

		if ( ! $post instanceof \WP_Post || 'product' !== $post->post_type || ! Capabilities::current_user_can() ) {
			return;
		}

		echo '<div id="sidrena_cijena_product_data" class="panel woocommerce_options_panel">';
		echo '<div class="sidrena-cijena-product-panel">';
		echo '<h2>' . esc_html__( 'Anchor price and special-sale evidence', 'sidrena-cijena-eu' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Use the product Update button to save WooCommerce product details. Use the separate evidence buttons below to record an anchor price or a special sale in the append-only compliance history.', 'sidrena-cijena-eu' ) . '</p>';
		CardVisibility::render_control( $post->ID );

		$product = wc_get_product( $post->ID );

		if ( $product && $product->is_type( 'variable' ) ) {
			$items = Plugin::instance()->catalog()->get_items(
				array(
					'include'            => array( $post->ID ),
					'per_page'           => 1,
					'include_variations' => true,
				)
			);

			foreach ( $items as $item ) {
				if ( ! $item->is_variation() ) {
					continue;
				}

				$variation_post = get_post( (int) $item->variation_id );

				if ( ! $variation_post instanceof \WP_Post ) {
					continue;
				}

				echo '<section class="sidrena-cijena-variation-evidence">';
				printf( '<h3>%1$s (#%2$d)</h3>', esc_html( $item->name ), (int) $item->variation_id );
				echo '<h4>' . esc_html__( 'Anchor price', 'sidrena-cijena-eu' ) . '</h4>';
				self::render( $variation_post );
				echo '<hr /><h4>' . esc_html__( 'Special sale', 'sidrena-cijena-eu' ) . '</h4>';
				SpecialSaleMetaBox::render( $variation_post );
				echo '</section>';
			}
		} else {
			echo '<h3>' . esc_html__( 'Anchor price', 'sidrena-cijena-eu' ) . '</h3>';
			self::render( $post );
			echo '<hr /><h3>' . esc_html__( 'Special sale', 'sidrena-cijena-eu' ) . '</h3>';
			SpecialSaleMetaBox::render( $post );
		}

		echo '</div></div>';
	}

	/**
	 * Render the meta box.
	 *
	 * @param \WP_Post $post Post.
	 * @return void
	 */
	public static function render( $post ) {
		$item = Plugin::instance()->catalog()->get_item( $post->ID );

		if ( ! $item ) {
			echo '<p>' . esc_html__( 'The catalog item could not be loaded.', 'sidrena-cijena-eu' ) . '</p>';

			return;
		}

		$status            = Plugin::instance()->anchors()->status( $item );
		$anchor_date_value = $status['reference_date'];
		$variation_id      = (int) $item->variation_id;
		$field_suffix      = $variation_id > 0 ? '[' . $variation_id . ']' : '';
		$id_suffix         = $variation_id > 0 ? '-' . $variation_id : '';
		$product_id        = (int) $item->id;
		$action_url        = admin_url( 'admin-post.php' );

		if ( $variation_id > 0 ) {
			$action_url = add_query_arg( 'variation_id', $variation_id, $action_url );
		}
		if ( $status['introduced_after'] && ! $status['anchor'] && $item->first_seen ) {
			$anchor_date_value = substr( (string) $item->first_seen, 0, 10 );
		}

		echo '<table class="widefat striped"><tbody>';

		printf(
			'<tr><td>%1$s</td><td>%2$s</td></tr>',
			esc_html__( 'Anchor price', 'sidrena-cijena-eu' ),
			$status['anchor'] && null !== $status['anchor']['price']
				? esc_html( Currency::format( (float) $status['anchor']['price'], $item->currency ) )
				: '<em>' . esc_html__( 'Not recorded', 'sidrena-cijena-eu' ) . '</em>'
		);

		printf(
			'<tr><td>%1$s</td><td>%2$s</td></tr>',
			esc_html__( 'Applicable reference date', 'sidrena-cijena-eu' ),
			esc_html( $status['reference_date'] . ( 'legacy' === $status['rule'] ? ' (' . __( 'legacy', 'sidrena-cijena-eu' ) . ')' : '' ) )
		);

		if ( $status['anchor'] ) {
			printf(
				'<tr><td>%1$s</td><td>%2$s</td></tr>',
				esc_html__( 'Recorded for', 'sidrena-cijena-eu' ),
				esc_html( (string) $status['anchor']['reference_date'] . ' / ' . Labels::source( (string) $status['anchor']['source'] ) )
			);
		}

		printf(
			'<tr><td>%1$s</td><td>%2$s</td></tr>',
			esc_html__( 'Status', 'sidrena-cijena-eu' ),
			esc_html( self::state_label( $status ) )
		);

		echo '</tbody></table>';

		if ( 'missing' === $status['state'] && $status['introduced_after'] ) {
			echo '<p class="description">' . esc_html__( 'For an item first offered after the reference date, record the price applied when it first entered sale and that date. Verify the date and price against your sales records.', 'sidrena-cijena-eu' ) . '</p>';
		}

		?>
		<div class="sidrena-cijena-anchor-form" style="margin-top:1em">
			<?php wp_nonce_field( self::ACTION, 'sidrena_cijena_anchor_nonce' ); ?>
			<input type="hidden" name="product_id" value="<?php echo esc_attr( (string) $product_id ); ?>" />

			<p>
				<label for="sidrena-anchor-price<?php echo esc_attr( $id_suffix ); ?>"><?php echo esc_html__( 'Anchor price', 'sidrena-cijena-eu' ); ?></label>
				<input type="text" class="regular-text" id="sidrena-anchor-price<?php echo esc_attr( $id_suffix ); ?>" name="anchor_price<?php echo esc_attr( $field_suffix ); ?>"
					value="<?php echo $status['anchor'] && null !== $status['anchor']['price'] ? esc_attr( (string) $status['anchor']['price'] ) : ''; ?>" />
				<span class="description"><?php echo esc_html__( 'Enter the verified price applicable on the reference date. Use your sales records; this becomes part of the permanent price history.', 'sidrena-cijena-eu' ); ?></span>
			</p>

			<p>
				<label for="sidrena-anchor-date<?php echo esc_attr( $id_suffix ); ?>"><?php echo esc_html__( 'Reference date', 'sidrena-cijena-eu' ); ?></label>
				<input type="date" id="sidrena-anchor-date<?php echo esc_attr( $id_suffix ); ?>" name="reference_date<?php echo esc_attr( $field_suffix ); ?>" value="<?php echo esc_attr( $anchor_date_value ); ?>" />
				<span class="description"><?php echo esc_html__( 'Choose the date required for this product under the applicable rule. Do not use today’s date unless it is the correct reference date.', 'sidrena-cijena-eu' ); ?></span>
			</p>

			<?php if ( $status['anchor'] ) : ?>
				<p>
						<label for="sidrena-anchor-reason<?php echo esc_attr( $id_suffix ); ?>"><?php echo esc_html__( 'Correction reason (required)', 'sidrena-cijena-eu' ); ?></label>
						<input type="text" class="regular-text" id="sidrena-anchor-reason<?php echo esc_attr( $id_suffix ); ?>" name="correction_reason<?php echo esc_attr( $field_suffix ); ?>" value="" />
				</p>
			<?php endif; ?>

			<button type="submit" class="button button-secondary" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" formaction="<?php echo esc_url( $action_url ); ?>" formmethod="post">
				<?php echo esc_html__( 'Record anchor price', 'sidrena-cijena-eu' ); ?>
			</button>
		</div>
		<?php

		printf(
			'<p><a href="%1$s">%2$s</a></p>',
			esc_url(
				AdminMenu::url(
					'sidrena-cijena-history',
					array(
						'product_id'   => $product_id,
						'variation_id' => $variation_id,
					)
				)
			),
			esc_html__( 'View the full price history', 'sidrena-cijena-eu' )
		);
	}

	/**
	 * Handle the record form.
	 *
	 * @return void
	 */
	public static function handle_record() {
		if ( ! Capabilities::current_user_can() ) {
			wp_die( esc_html__( 'You are not allowed to record anchor prices.', 'sidrena-cijena-eu' ) );
		}

		check_admin_referer( self::ACTION, 'sidrena_cijena_anchor_nonce' );

		$product_id   = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
		$variation_id = isset( $_REQUEST['variation_id'] ) ? absint( wp_unslash( $_REQUEST['variation_id'] ) ) : 0;
		$item         = $variation_id > 0
			? Plugin::instance()->catalog()->get_variation( $product_id, $variation_id )
			: ( $product_id ? Plugin::instance()->catalog()->get_item( $product_id ) : null );

		if ( ! $item ) {
			self::redirect_back( 0, __( 'The catalog item could not be loaded.', 'sidrena-cijena-eu' ) );

			return;
		}

		$price = self::posted_value( 'anchor_price', $variation_id );

		$args = array(
			'source'            => 'manual',
			'reference_date'    => self::posted_value( 'reference_date', $variation_id ),
			'correction_reason' => self::posted_value( 'correction_reason', $variation_id ),
		);

		$result = Plugin::instance()->anchors()->record( $item, $price, $args );

		if ( is_wp_error( $result ) ) {
			self::redirect_back( $product_id, $result->get_error_message() );

			return;
		}

		self::redirect_back( $product_id, __( 'The anchor price has been recorded.', 'sidrena-cijena-eu' ), 'success' );
	}

	/**
	 * Read a scalar field or the value posted for the selected variation.
	 *
	 * @param string $key          POST field name.
	 * @param int    $variation_id Variation ID, or zero for a non-variation.
	 * @return string
	 */
	private static function posted_value( $key, $variation_id ) {
		// The public handler verifies the nonce before calling this helper.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST[ $key ] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The handler verifies the nonce and this value is sanitized below.
		$value = wp_unslash( $_POST[ $key ] );

		if ( $variation_id > 0 ) {
			$value = is_array( $value ) && isset( $value[ $variation_id ] ) ? $value[ $variation_id ] : '';
		} elseif ( is_array( $value ) ) {
			$value = '';
		}

		return sanitize_text_field( (string) $value );
	}

	/**
	 * Redirect back to the edit screen with a notice.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $message    Message.
	 * @param string $type       `error` or `success`.
	 * @return void
	 */
	private static function redirect_back( $product_id, $message, $type = 'error' ) {
		$url = $product_id
			? get_edit_post_link( $product_id, 'raw' )
			: admin_url();

		$url = add_query_arg(
			array(
				'sidrena_cijena_notice'      => $type,
				'sidrena_cijena_notice_text' => rawurlencode( $message ),
			),
			$url
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Show a notice after a redirect.
	 *
	 * @return void
	 */
	public static function maybe_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['sidrena_cijena_notice'] ) ) {
			return;
		}

		// Our own screens render the notice themselves, with the type that
		// belongs to their own action instead of a plain success/error.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( 0 === strpos( $page, 'sidrena-cijena' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$type = 'success' === sanitize_key( wp_unslash( $_GET['sidrena_cijena_notice'] ) ) ? 'success' : 'error';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$text = isset( $_GET['sidrena_cijena_notice_text'] ) ? rawurldecode( sanitize_text_field( wp_unslash( $_GET['sidrena_cijena_notice_text'] ) ) ) : '';

		printf(
			'<div class="notice notice-%1$s sidrena-cijena-notice is-dismissible"><p>%2$s</p></div>',
			esc_attr( $type ),
			esc_html( $text )
		);
	}

	/**
	 * Human-readable state label.
	 *
	 * @param array<mixed> $status Anchor status.
	 * @return string
	 */
	private static function state_label( array $status ) {
		if ( 'valid' === $status['state'] ) {
			return __( 'Valid', 'sidrena-cijena-eu' );
		}

		if ( 'needs_verification' === $status['state'] ) {
			return __( 'Needs verification', 'sidrena-cijena-eu' );
		}

		return __( 'Missing', 'sidrena-cijena-eu' );
	}
}
