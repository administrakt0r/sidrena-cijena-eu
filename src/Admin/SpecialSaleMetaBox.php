<?php
/**
 * Special sale meta box.
 *
 * @package SidrenaCijena\Admin
 */

namespace SidrenaCijena\Admin;

use SidrenaCijena\Catalog\CatalogItem;
use SidrenaCijena\Catalog\StandalonePostType;
use SidrenaCijena\Plugin;
use SidrenaCijena\Support\Currency;
use SidrenaCijena\Support\Dates;
use SidrenaCijena\Support\Labels;
use SidrenaCijena\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Classifies a special form of sale on product and standalone item screens.
 */
class SpecialSaleMetaBox {

	/**
	 * Meta box ID.
	 *
	 * @var string
	 */
	const ID = 'sidrena-cijena-special-sale';

	/**
	 * Admin-post action.
	 *
	 * @var string
	 */
	const ACTION = 'sidrena_cijena_save_sale';

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
				__( 'Sidrena cijena - special sale', 'sidrena-cijena-eu' ),
				array( __CLASS__, 'render' ),
				$screen,
				'side',
				'high'
			);
		}
	}

	/**
	 * Render the meta box.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public static function render( $post ) {
		$item = Plugin::instance()->catalog()->get_item( (int) $post->ID );

		if ( ! $item ) {
			echo '<p>' . esc_html__( 'This item is not part of the active catalog.', 'sidrena-cijena-eu' ) . '</p>';

			return;
		}

		$sales        = Plugin::instance()->special_sales()->for_item( (int) $item->id, (int) $item->variation_id );
		$today        = Dates::today();
		$variation_id = (int) $item->variation_id;
		$field_suffix = $variation_id > 0 ? '[' . $variation_id . ']' : '';
		$id_suffix    = $variation_id > 0 ? '-' . $variation_id : '';
		$action_url   = admin_url( 'admin-post.php' );

		if ( $variation_id > 0 ) {
			$action_url = add_query_arg( 'variation_id', $variation_id, $action_url );
		}

		$explainer = Plugin::instance()->lowest_price_explainer();

		?>
		<div class="sidrena-cijena-sale">
			<p class="sidrena-cijena-item-identity">
				<strong><?php echo esc_html__( 'Item:', 'sidrena-cijena-eu' ); ?></strong>
				<code><?php echo esc_html( $explainer->format_identity( $item ) ); ?></code>
			</p>
			<?php if ( ! empty( $sales ) ) : ?>
				<table class="widefat striped">
					<caption class="screen-reader-text"><?php echo esc_html__( 'Recorded special sales', 'sidrena-cijena-eu' ); ?></caption>
					<thead>
						<tr>
							<th scope="col"><?php echo esc_html__( 'Type', 'sidrena-cijena-eu' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Period', 'sidrena-cijena-eu' ); ?></th>
							<th scope="col"><?php echo esc_html__( '30 days', 'sidrena-cijena-eu' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Status', 'sidrena-cijena-eu' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( array_slice( $sales, 0, 5 ) as $sale ) : ?>
							<tr>
								<td>
									<?php
									$name = ! empty( $sale['sale_name'] ) ? (string) $sale['sale_name'] : Labels::sale_type( (string) $sale['sale_type'] );
									echo esc_html( $name );
									?>
								</td>
								<td>
									<?php
									$start  = Dates::format_date_display( (string) $sale['start_date'] );
									$period = '' !== $start ? $start : (string) $sale['start_date'];

									if ( ! empty( $sale['end_date'] ) ) {
										$end     = Dates::format_date_display( (string) $sale['end_date'] );
										$period .= ' - ' . ( '' !== $end ? $end : (string) $sale['end_date'] );
									}

									echo esc_html( $period );
									?>
								</td>
								<td>
									<?php
									echo null === $sale['lowest_30_day_price']
										? esc_html__( 'Not calculated', 'sidrena-cijena-eu' )
										: esc_html( Currency::format( (float) $sale['lowest_30_day_price'], $item->currency, array( 'display' => 'symbol' ) ) );

									if ( ! empty( $sale['window_start'] ) && ! empty( $sale['window_end'] ) ) {
										$w_start = Dates::format_date_display( (string) $sale['window_start'] );
										$w_end   = Dates::format_date_display( (string) $sale['window_end'] );
										/* translators: 1: window start date, 2: window end date */
										echo '<br /><span class="description">' . esc_html( sprintf( __( 'Window: %1$s to %2$s', 'sidrena-cijena-eu' ), '' !== $w_start ? $w_start : (string) $sale['window_start'], '' !== $w_end ? $w_end : (string) $sale['window_end'] ) ) . '</span>';
									}
									?>
								</td>
								<td><?php echo esc_html( Labels::sale_status( (string) $sale['status'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<div class="sidrena-cijena-sale-explanations">
					<?php foreach ( array_slice( $sales, 0, 5 ) as $sale ) : ?>
						<?php
						$explanation = $explainer->explain( $item, $sale );
						$sale_name   = ! empty( $sale['sale_name'] ) ? (string) $sale['sale_name'] : Labels::sale_type( (string) $sale['sale_type'] );
						$start_disp  = Dates::format_date_display( (string) $sale['start_date'] );
						?>
						<details class="sidrena-cijena-explanation-details">
							<summary class="sidrena-cijena-explanation-summary">
								<?php
								/* translators: 1: sale name, 2: sale start date */
								echo esc_html( sprintf( __( 'Calculation explanation: %1$s (starts %2$s)', 'sidrena-cijena-eu' ), $sale_name, '' !== $start_disp ? $start_disp : (string) $sale['start_date'] ) );
								?>
							</summary>
							<?php echo $explainer->render_html( $explanation ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</details>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<p><?php echo esc_html__( 'No special sale is recorded for this item.', 'sidrena-cijena-eu' ); ?></p>
				<?php
				$today_explanation = $explainer->explain( $item, $today );
				?>
				<details class="sidrena-cijena-explanation-details">
					<summary class="sidrena-cijena-explanation-summary">
						<?php echo esc_html__( 'Current 30-day reference baseline (preview for today)', 'sidrena-cijena-eu' ); ?>
					</summary>
					<?php echo $explainer->render_html( $today_explanation ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</details>
			<?php endif; ?>

			<div class="sidrena-cijena-sale-form">
				<?php wp_nonce_field( self::ACTION, 'sidrena_cijena_sale_nonce' ); ?>
				<input type="hidden" name="product_id" value="<?php echo esc_attr( (string) $item->id ); ?>" />
				<?php if ( 0 === $variation_id ) : ?>
					<input type="hidden" name="variation_id" value="0" />
				<?php endif; ?>

				<p>
					<label for="sidrena-cijena-sale-type<?php echo esc_attr( $id_suffix ); ?>"><?php echo esc_html__( 'Sale type', 'sidrena-cijena-eu' ); ?></label>
					<select id="sidrena-cijena-sale-type<?php echo esc_attr( $id_suffix ); ?>" name="sale_type<?php echo esc_attr( $field_suffix ); ?>" class="widefat">
						<option value=""><?php echo esc_html__( '- select -', 'sidrena-cijena-eu' ); ?></option>
						<?php foreach ( self::sale_types() as $type ) : ?>
							<option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( Labels::sale_type( $type ) ); ?></option>
						<?php endforeach; ?>
					</select>
					<span class="description"><?php echo esc_html__( 'Choose the configured category that describes this promotion; add available categories in Sidrena cijena → Settings → Special sales.', 'sidrena-cijena-eu' ); ?></span>
				</p>

				<p>
					<label for="sidrena-cijena-sale-name<?php echo esc_attr( $id_suffix ); ?>"><?php echo esc_html__( 'Sale name', 'sidrena-cijena-eu' ); ?></label>
					<input type="text" id="sidrena-cijena-sale-name<?php echo esc_attr( $id_suffix ); ?>" name="sale_name<?php echo esc_attr( $field_suffix ); ?>" class="widefat" />
					<span class="description"><?php echo esc_html__( 'Optional customer-facing promotion name, for example “Spring sale”. Leave blank if there is no named campaign.', 'sidrena-cijena-eu' ); ?></span>
				</p>

				<p>
					<label for="sidrena-cijena-sale-start<?php echo esc_attr( $id_suffix ); ?>"><?php echo esc_html__( 'Start date', 'sidrena-cijena-eu' ); ?></label>
					<input type="date" id="sidrena-cijena-sale-start<?php echo esc_attr( $id_suffix ); ?>" name="start_date<?php echo esc_attr( $field_suffix ); ?>" value="<?php echo esc_attr( $today ); ?>" />
					<span class="description"><?php echo esc_html__( 'Use the first day the promotional price applies.', 'sidrena-cijena-eu' ); ?></span>
				</p>

				<p>
					<label for="sidrena-cijena-sale-end<?php echo esc_attr( $id_suffix ); ?>"><?php echo esc_html__( 'End date (optional)', 'sidrena-cijena-eu' ); ?></label>
					<input type="date" id="sidrena-cijena-sale-end<?php echo esc_attr( $id_suffix ); ?>" name="end_date<?php echo esc_attr( $field_suffix ); ?>" />
					<span class="description"><?php echo esc_html__( 'If the promotion has a scheduled last day, enter it; otherwise leave this empty.', 'sidrena-cijena-eu' ); ?></span>
				</p>

				<p>
					<label for="sidrena-cijena-sale-promo<?php echo esc_attr( $id_suffix ); ?>"><?php echo esc_html__( 'Promotional price', 'sidrena-cijena-eu' ); ?></label>
					<input type="text" id="sidrena-cijena-sale-promo<?php echo esc_attr( $id_suffix ); ?>" name="promo_price<?php echo esc_attr( $field_suffix ); ?>" class="widefat" inputmode="decimal" />
					<span class="description"><?php echo esc_html__( 'Enter the actual selling price during this promotion, using a decimal comma or point.', 'sidrena-cijena-eu' ); ?></span>
				</p>

				<p class="description">
					<?php echo esc_html__( 'The lowest price of the 30 days before the start date is calculated and stored automatically.', 'sidrena-cijena-eu' ); ?>
				</p>

				<button type="submit" class="button button-secondary" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" formaction="<?php echo esc_url( $action_url ); ?>" formmethod="post">
					<?php echo esc_html__( 'Record special sale', 'sidrena-cijena-eu' ); ?>
				</button>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle the meta box submission.
	 *
	 * @return void
	 */
	public static function handle_save() {
		if ( ! Capabilities::current_user_can() ) {
			wp_die( esc_html__( 'You do not have permission to manage special sales.', 'sidrena-cijena-eu' ) );
		}

		check_admin_referer( self::ACTION, 'sidrena_cijena_sale_nonce' );

		$product_id   = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
		$variation_id = isset( $_REQUEST['variation_id'] ) ? absint( wp_unslash( $_REQUEST['variation_id'] ) ) : 0;

		$item = $variation_id > 0
			? Plugin::instance()->catalog()->get_variation( $product_id, $variation_id )
			: Plugin::instance()->catalog()->get_item( $product_id );

		if ( ! $item ) {
			self::redirect_back( $product_id, 'error', __( 'The item could not be loaded.', 'sidrena-cijena-eu' ) );

			return;
		}

		$args = array(
			'sale_type'   => sanitize_key( self::posted_value( 'sale_type', $variation_id ) ),
			'sale_name'   => self::posted_value( 'sale_name', $variation_id ),
			'start_date'  => self::posted_value( 'start_date', $variation_id ),
			'end_date'    => self::posted_value( 'end_date', $variation_id ),
			'promo_price' => self::posted_value( 'promo_price', $variation_id ),
		);

		$result = Plugin::instance()->special_sale_service()->create( $item, $args );

		if ( is_wp_error( $result ) ) {
			self::redirect_back( $product_id, 'error', $result->get_error_message() );

			return;
		}

		self::redirect_back( $product_id, 'success', __( 'The special sale was recorded and the 30-day price was calculated.', 'sidrena-cijena-eu' ) );
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
	 * Configured sale types.
	 *
	 * @return string[]
	 */
	protected static function sale_types() {
		return array_values( array_filter( array_map( 'sanitize_key', (array) Settings::get( 'sale_types', array() ) ) ) );
	}

	/**
	 * Redirect back to the edited screen with a notice.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $type    `success` or `error`.
	 * @param string $message Message.
	 * @return void
	 */
	protected static function redirect_back( $post_id, $type, $message ) {
		$url = add_query_arg(
			array(
				'post'                       => (int) $post_id,
				'action'                     => 'edit',
				'sidrena_cijena_notice'      => $type,
				'sidrena_cijena_notice_text' => rawurlencode( $message ),
			),
			admin_url( 'post.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}
}
