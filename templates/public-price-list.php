<?php
/**
 * Public HTML price list.
 *
 * Overridable from a theme as `sidrena-cijena/public-price-list.php`.
 *
 * @package SidrenaCijena
 *
 * @var array<int,array<mixed>> $rows     Canonical rows with display values.
 * @var array<mixed>            $meta     Build metadata.
 * @var array<mixed>|null       $location Sales location.
 * @var string                  $q        Search term.
 * @var string                  $type     Type filter.
 * @var string                  $feed_xml XML feed URL.
 * @var string                  $feed_csv CSV feed URL.
 * @var string                  $archive_url Archive page URL.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="sidrena-cijena-cjenik">
	<?php if ( ! empty( $location ) ) : ?>
		<p class="sidrena-cijena-cjenik__meta">
			<?php
			printf(
				/* translators: 1: sales location name, 2: legal rule version, 3: generation timestamp. */
				esc_html__( 'Sales location: %1$s. Rule version: %2$s. Generated: %3$s.', 'sidrena-cijena' ),
				esc_html( (string) $location['name'] ),
				esc_html( isset( $meta['rule_version'] ) ? (string) $meta['rule_version'] : '' ),
				esc_html( isset( $meta['generated_at'] ) ? (string) $meta['generated_at'] : '' )
			);
			?>
		</p>
	<?php endif; ?>

	<form method="get" class="sidrena-cijena-cjenik__search">
		<label for="sidrena-cijena-q"><?php esc_html_e( 'Search', 'sidrena-cijena' ); ?></label>
		<input type="search" id="sidrena-cijena-q" name="q" value="<?php echo esc_attr( $q ); ?>" />
		<label for="sidrena-cijena-type"><?php esc_html_e( 'Type', 'sidrena-cijena' ); ?></label>
		<select name="type" id="sidrena-cijena-type">
			<option value=""><?php esc_html_e( 'All', 'sidrena-cijena' ); ?></option>
			<option value="proizvod" <?php selected( $type, 'proizvod' ); ?>><?php esc_html_e( 'Products', 'sidrena-cijena' ); ?></option>
			<option value="usluga" <?php selected( $type, 'usluga' ); ?>><?php esc_html_e( 'Services', 'sidrena-cijena' ); ?></option>
		</select>
		<button type="submit"><?php esc_html_e( 'Filter', 'sidrena-cijena' ); ?></button>
	</form>

	<table class="sidrena-cijena-table">
		<caption><?php esc_html_e( 'Price list with the anchor price', 'sidrena-cijena' ); ?></caption>
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Type', 'sidrena-cijena' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Name', 'sidrena-cijena' ); ?></th>
				<th scope="col"><?php esc_html_e( 'SKU', 'sidrena-cijena' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Brand', 'sidrena-cijena' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Unit', 'sidrena-cijena' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Unit price', 'sidrena-cijena' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Retail price', 'sidrena-cijena' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Special sale', 'sidrena-cijena' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Anchor price', 'sidrena-cijena' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Anchor date', 'sidrena-cijena' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Barcode', 'sidrena-cijena' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Availability', 'sidrena-cijena' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $rows ) ) : ?>
				<tr>
					<td colspan="12"><?php esc_html_e( 'No items match this filter.', 'sidrena-cijena' ); ?></td>
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
					<td><?php echo esc_html( (string) $sidrena_row['sale_display'] ); ?></td>
					<td><?php echo esc_html( (string) $sidrena_row['anchor_display'] ); ?></td>
					<td><?php echo esc_html( (string) $sidrena_row['reference_display'] ); ?></td>
					<td><?php echo esc_html( (string) $sidrena_row['barkod'] ); ?></td>
					<td><?php echo esc_html( (string) $sidrena_row['availability_display'] ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<p class="sidrena-cijena-cjenik__links">
		<a href="<?php echo esc_url( $feed_xml ); ?>">XML</a>
		<a href="<?php echo esc_url( $feed_csv ); ?>">CSV</a>
		<a href="<?php echo esc_url( $archive_url ); ?>"><?php esc_html_e( 'Archive', 'sidrena-cijena' ); ?></a>
	</p>
</div>
