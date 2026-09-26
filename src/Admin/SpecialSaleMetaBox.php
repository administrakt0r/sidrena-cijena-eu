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
use SidrenaCijena\WooCommerce\WooCommerceDetector;

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

		if ( WooCommerceDetector::is_active() ) {
			$screens[] = 'product';
		}

		if ( post_type_exists( StandalonePostType::POST_TYPE ) ) {
			$screens[] = StandalonePostType::POST_TYPE;
		}

		foreach ( $screens as $screen ) {
			add_meta_box(
				self::ID,
				__( 'Sidrena cijena - special sale', 'sidrena-cijena' ),
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
			echo '<p>' . esc_html__( 'This item is not part of the active catalog.', 'sidrena-cijena' ) . '</p>';

			return;
		}

		$sales = Plugin::instance()->special_sales()->for_item( (int) $item->id, (int) $item->variation_id );
		$today = Dates::today();

		?>
		<div class="sidrena-cijena-sale">
			<?php if ( ! empty( $sales ) ) : ?>
				<table class="widefat striped">
					<caption class="screen-reader-text"><?php echo esc_html__( 'Recorded special sales', 'sidrena-cijena' ); ?></caption>
					<thead>
						<tr>
							<th scope="col"><?php echo esc_html__( 'Type', 'sidrena-cijena' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Period', 'sidrena-cijena' ); ?></th>
							<th scope="col"><?php echo esc_html__( '30 days', 'sidrena-cijena' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Status', 'sidrena-cijena' ); ?></th>
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
									$period = (string) $sale['start_date'];

									if ( ! empty( $sale['end_date'] ) ) {
										$period .= ' - ' . (string) $sale['end_date'];
									}

									echo esc_html( $period );
									?>
								</td>
								<td>
									<?php
									echo null === $sale['lowest_30_day_price']
										? esc_html__( 'Not calculated', 'sidrena-cijena' )
										: esc_html( Currency::format( (float) $sale['lowest_30_day_price'], $item->currency, array( 'display' => 'symbol' ) ) );
									?>
								</td>
								<td><?php echo esc_html( Labels::sale_status( (string) $sale['status'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p><?php echo esc_html__( 'No special sale is recorded for this item.', 'sidrena-cijena' ); ?></p>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( self::ACTION, 'sidrena_cijena_sale_nonce' ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
				<input type="hidden" name="product_id" value="<?php echo esc_attr( (string) $item->id ); ?>" />
				<input type="hidden" name="variation_id" value="<?php echo esc_attr( (string) $item->variation_id ); ?>" />

				<p>
					<label for="sidrena-cijena-sale-type"><?php echo esc_html__( 'Sale type', 'sidrena-cijena' ); ?></label>
					<select id="sidrena-cijena-sale-type" name="sale_type" class="widefat" required>
						<option value=""><?php echo esc_html__( '- select -', 'sidrena-cijena' ); ?></option>
						<?php foreach ( self::sale_types() as $type ) : ?>
							<option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( Labels::sale_type( $type ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>

				<p>
					<label for="sidrena-cijena-sale-name"><?php echo esc_html__( 'Sale name', 'sidrena-cijena' ); ?></label>
					<input type="text" id="sidrena-cijena-sale-name" name="sale_name" class="widefat" />
				</p>

				<p>
					<label for="sidrena-cijena-sale-start"><?php echo esc_html__( 'Start date', 'sidrena-cijena' ); ?></label>
					<input type="date" id="sidrena-cijena-sale-start" name="start_date" value="<?php echo esc_attr( $today ); ?>" required />
				</p>

				<p>
					<label for="sidrena-cijena-sale-end"><?php echo esc_html__( 'End date (optional)', 'sidrena-cijena' ); ?></label>
					<input type="date" id="sidrena-cijena-sale-end" name="end_date" />
				</p>

				<p>
					<label for="sidrena-cijena-sale-promo"><?php echo esc_html__( 'Promotional price', 'sidrena-cijena' ); ?></label>
					<input type="text" id="sidrena-cijena-sale-promo" name="promo_price" class="widefat" inputmode="decimal" />
				</p>

				<p class="description">
					<?php echo esc_html__( 'The lowest price of the 30 days before the start date is calculated and stored automatically.', 'sidrena-cijena' ); ?>
				</p>

				<?php submit_button( __( 'Record special sale', 'sidrena-cijena' ), 'secondary', 'submit', false ); ?>
			</form>
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
			wp_die( esc_html__( 'You do not have permission to manage special sales.', 'sidrena-cijena' ) );
		}

		check_admin_referer( self::ACTION, 'sidrena_cijena_sale_nonce' );

		$product_id   = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
		$variation_id = isset( $_POST['variation_id'] ) ? absint( wp_unslash( $_POST['variation_id'] ) ) : 0;

		$item = $variation_id > 0
			? Plugin::instance()->catalog()->get_variation( $product_id, $variation_id )
			: Plugin::instance()->catalog()->get_item( $product_id );

		if ( ! $item ) {
			self::redirect_back( $product_id, 'error', __( 'The item could not be loaded.', 'sidrena-cijena' ) );

			return;
		}

		$args = array(
			'sale_type'   => isset( $_POST['sale_type'] ) ? sanitize_key( wp_unslash( $_POST['sale_type'] ) ) : '',
			'sale_name'   => isset( $_POST['sale_name'] ) ? sanitize_text_field( wp_unslash( $_POST['sale_name'] ) ) : '',
			'start_date'  => isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '',
			'end_date'    => isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : '',
			'promo_price' => isset( $_POST['promo_price'] ) ? sanitize_text_field( wp_unslash( $_POST['promo_price'] ) ) : '',
		);

		$result = Plugin::instance()->special_sale_service()->create( $item, $args );

		if ( is_wp_error( $result ) ) {
			self::redirect_back( $product_id, 'error', $result->get_error_message() );

			return;
		}

		self::redirect_back( $product_id, 'success', __( 'The special sale was recorded and the 30-day price was calculated.', 'sidrena-cijena' ) );
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
