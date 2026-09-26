<?php
/**
 * Public archive of published price lists.
 *
 * Overridable from a theme as `sidrena-cijena/public-archive.php`.
 *
 * @package SidrenaCijena
 *
 * @var array<int,array<mixed>> $rows     Publication rows with display values.
 * @var array<mixed>|null       $location Sales location.
 * @var string                  $list_url Price-list page URL.
 * @var string                  $feed_xml XML feed URL.
 * @var string                  $feed_csv CSV feed URL.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="sidrena-cijena-arhiva">
	<?php if ( ! empty( $location ) ) : ?>
		<p class="sidrena-cijena-cjenik__meta">
			<?php
			printf(
				/* translators: %s: sales location name. */
				esc_html__( 'Published price lists for %s.', 'sidrena-cijena-eu' ),
				esc_html( (string) $location['name'] )
			);
			?>
		</p>
	<?php endif; ?>

	<table class="sidrena-cijena-table">
		<caption><?php esc_html_e( 'Archive of published price lists', 'sidrena-cijena-eu' ); ?></caption>
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Published', 'sidrena-cijena-eu' ); ?></th>
				<th scope="col"><?php esc_html_e( 'File', 'sidrena-cijena-eu' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Format', 'sidrena-cijena-eu' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Items', 'sidrena-cijena-eu' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Size', 'sidrena-cijena-eu' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Checksum', 'sidrena-cijena-eu' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Download', 'sidrena-cijena-eu' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $rows ) ) : ?>
				<tr>
					<td colspan="7"><?php esc_html_e( 'No price lists have been published yet.', 'sidrena-cijena-eu' ); ?></td>
				</tr>
			<?php endif; ?>
			<?php foreach ( $rows as $sidrena_row ) : ?>
				<tr>
					<td><?php echo esc_html( (string) $sidrena_row['published_display'] ); ?></td>
					<td><?php echo esc_html( (string) $sidrena_row['filename'] ); ?></td>
					<td><?php echo esc_html( (string) $sidrena_row['format_display'] ); ?></td>
					<td><?php echo (int) $sidrena_row['item_count']; ?></td>
					<td><?php echo esc_html( (string) $sidrena_row['size_display'] ); ?></td>
					<td><code><?php echo esc_html( (string) $sidrena_row['checksum_short'] ); ?></code></td>
					<td><a href="<?php echo esc_url( (string) $sidrena_row['download_url'] ); ?>"><?php esc_html_e( 'Download', 'sidrena-cijena-eu' ); ?></a></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<p class="sidrena-cijena-cjenik__links">
		<a href="<?php echo esc_url( $list_url ); ?>"><?php esc_html_e( 'Current price list', 'sidrena-cijena-eu' ); ?></a>
		<a href="<?php echo esc_url( $feed_xml ); ?>">XML</a>
		<a href="<?php echo esc_url( $feed_csv ); ?>">CSV</a>
	</p>
</div>
