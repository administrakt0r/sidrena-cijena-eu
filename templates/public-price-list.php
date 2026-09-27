<?php
/**
 * Public HTML price list.
 *
 * Overridable from a theme as `sidrena-cijena/public-price-list.php`.
 *
 * @package SidrenaCijena
 *
 * @var array<int,array<mixed>> $rows        Canonical rows with display values.
 * @var array<mixed>            $meta        Build metadata.
 * @var array<mixed>|null       $location    Sales location.
 * @var string                  $q           Search term.
 * @var string                  $type        Type filter.
 * @var int                     $paged       Current page number.
 * @var int                     $per_page    Number of items per page.
 * @var int                     $total_items Total number of filtered items.
 * @var int                     $total_pages Total number of pages.
 * @var string                  $feed_xml    XML feed URL.
 * @var string                  $feed_csv    CSV feed URL.
 * @var string                  $archive_url Archive page URL.
 */

defined( 'ABSPATH' ) || exit;

$sidrena_paged       = isset( $paged ) ? max( 1, (int) $paged ) : 1;
$sidrena_per_page    = isset( $per_page ) ? max( 1, (int) $per_page ) : 50;
$sidrena_total_items = isset( $total_items ) ? (int) $total_items : count( $rows );
$sidrena_total_pages = isset( $total_pages ) ? (int) $total_pages : 1;
?>
<div class="sidrena-cijena-cjenik">
	<?php if ( ! empty( $location ) ) : ?>
		<p class="sidrena-cijena-cjenik__meta">
			<?php
			$sidrena_generated_at = isset( $meta['generated_at'] ) ? \SidrenaCijena\Support\Dates::format_datetime_display( (string) $meta['generated_at'] ) : '';
			printf(
				/* translators: 1: sales location name, 2: legal rule version, 3: generation timestamp. */
				esc_html__( 'Sales location: %1$s. Rule version: %2$s. Generated: %3$s.', 'sidrena-cijena-eu' ),
				esc_html( (string) $location['name'] ),
				esc_html( isset( $meta['rule_version'] ) ? (string) $meta['rule_version'] : '' ),
				esc_html( $sidrena_generated_at )
			);
			?>
		</p>
	<?php endif; ?>

	<form method="get" class="sidrena-cijena-cjenik__search">
		<label for="sidrena-cijena-q"><?php esc_html_e( 'Search', 'sidrena-cijena-eu' ); ?></label>
		<input type="search" id="sidrena-cijena-q" name="q" value="<?php echo esc_attr( $q ); ?>" />
		<label for="sidrena-cijena-type"><?php esc_html_e( 'Type', 'sidrena-cijena-eu' ); ?></label>
		<select name="type" id="sidrena-cijena-type">
			<option value=""><?php esc_html_e( 'All', 'sidrena-cijena-eu' ); ?></option>
			<option value="proizvod" <?php selected( $type, 'proizvod' ); ?>><?php esc_html_e( 'Products', 'sidrena-cijena-eu' ); ?></option>
			<option value="usluga" <?php selected( $type, 'usluga' ); ?>><?php esc_html_e( 'Services', 'sidrena-cijena-eu' ); ?></option>
		</select>
		<button type="submit"><?php esc_html_e( 'Filter', 'sidrena-cijena-eu' ); ?></button>
		<?php if ( '' !== $q || '' !== $type ) : ?>
			<a href="<?php echo esc_url( remove_query_arg( array( 'q', 'type', 'paged' ) ) ); ?>" class="sidrena-cijena-cjenik__clear"><?php esc_html_e( 'Clear filters', 'sidrena-cijena-eu' ); ?></a>
		<?php endif; ?>
	</form>

	<?php if ( $sidrena_total_items > 0 ) : ?>
		<?php
		$sidrena_from = ( ( $sidrena_paged - 1 ) * $sidrena_per_page ) + 1;
		$sidrena_to   = min( $sidrena_total_items, $sidrena_from + count( $rows ) - 1 );
		?>
		<p class="sidrena-cijena-cjenik__count">
			<?php
			printf(
				/* translators: 1: start row index, 2: end row index, 3: total number of rows. */
				esc_html__( 'Showing %1$d–%2$d of %3$d items.', 'sidrena-cijena-eu' ),
				(int) $sidrena_from,
				(int) $sidrena_to,
				(int) $sidrena_total_items
			);
			?>
		</p>
	<?php endif; ?>

	<div class="sidrena-cijena-table-container">
		<table class="sidrena-cijena-table">
			<caption><?php esc_html_e( 'Price list with the anchor price', 'sidrena-cijena-eu' ); ?></caption>
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Type', 'sidrena-cijena-eu' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Name', 'sidrena-cijena-eu' ); ?></th>
					<th scope="col"><?php esc_html_e( 'SKU', 'sidrena-cijena-eu' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Brand', 'sidrena-cijena-eu' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Unit', 'sidrena-cijena-eu' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Unit price', 'sidrena-cijena-eu' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Retail price', 'sidrena-cijena-eu' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Lowest price in the last 30 days', 'sidrena-cijena-eu' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Special sale', 'sidrena-cijena-eu' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Anchor price', 'sidrena-cijena-eu' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Anchor date', 'sidrena-cijena-eu' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Barcode', 'sidrena-cijena-eu' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Availability', 'sidrena-cijena-eu' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr>
						<td colspan="13">
							<?php esc_html_e( 'No items match this filter.', 'sidrena-cijena-eu' ); ?>
							<?php if ( '' !== $q || '' !== $type ) : ?>
								<a href="<?php echo esc_url( remove_query_arg( array( 'q', 'type', 'paged' ) ) ); ?>" class="sidrena-cijena-cjenik__clear"><?php esc_html_e( 'Clear filters', 'sidrena-cijena-eu' ); ?></a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endif; ?>
				<?php foreach ( $rows as $sidrena_row ) : ?>
					<tr>
						<td><?php echo esc_html( (string) $sidrena_row['type_display'] ); ?></td>
						<td><?php echo esc_html( (string) $sidrena_row['naziv'] ); ?></td>
						<td><?php echo esc_html( (string) $sidrena_row['sifra'] ); ?></td>
						<td><?php echo esc_html( (string) $sidrena_row['marka'] ); ?></td>
						<td><?php echo esc_html( (string) $sidrena_row['jedinica_mjere'] ); ?></td>
						<td><?php echo esc_html( (string) $sidrena_row['unit_price_display'] ); ?></td>
						<td><?php echo esc_html( (string) $sidrena_row['retail_display'] ); ?></td>
						<td><?php echo esc_html( (string) $sidrena_row['lowest_30_day_display'] ); ?></td>
						<td><?php echo esc_html( (string) $sidrena_row['sale_display'] ); ?></td>
						<td><?php echo esc_html( (string) $sidrena_row['anchor_display'] ); ?></td>
						<td><?php echo esc_html( (string) $sidrena_row['reference_display'] ); ?></td>
						<td><?php echo esc_html( (string) $sidrena_row['barkod'] ); ?></td>
						<td><?php echo esc_html( (string) $sidrena_row['availability_display'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<?php if ( $sidrena_total_pages > 1 ) : ?>
		<nav class="sidrena-cijena-pagination" aria-label="<?php esc_attr_e( 'Price list pagination', 'sidrena-cijena-eu' ); ?>">
			<?php
			echo wp_kses_post(
				paginate_links(
					array(
						'base'               => add_query_arg( 'paged', '%#%' ),
						'format'             => '',
						'current'            => $sidrena_paged,
						'total'              => $sidrena_total_pages,
						'prev_text'          => '&laquo; ' . __( 'Previous', 'sidrena-cijena-eu' ),
						'next_text'          => __( 'Next', 'sidrena-cijena-eu' ) . ' &raquo;',
						'before_page_number' => '<span class="screen-reader-text">' . __( 'Page', 'sidrena-cijena-eu' ) . ' </span>',
					)
				)
			);
			?>
		</nav>
	<?php endif; ?>

	<p class="sidrena-cijena-cjenik__links">
		<a href="<?php echo esc_url( $feed_xml ); ?>"><?php esc_html_e( 'XML Feed', 'sidrena-cijena-eu' ); ?></a>
		<a href="<?php echo esc_url( $feed_csv ); ?>"><?php esc_html_e( 'CSV Feed', 'sidrena-cijena-eu' ); ?></a>
		<a href="<?php echo esc_url( $archive_url ); ?>"><?php esc_html_e( 'Archive', 'sidrena-cijena-eu' ); ?></a>
	</p>
</div>
