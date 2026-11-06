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
 * @var int                     $paged       Current page.
 * @var int                     $per_page    Items per page.
 * @var int                     $total_items Total items.
 * @var int                     $total_pages Total pages.
 */

defined( 'ABSPATH' ) || exit;

$sidrena_paged       = isset( $paged ) ? max( 1, (int) $paged ) : 1;
$sidrena_per_page    = isset( $per_page ) ? max( 1, (int) $per_page ) : 50;
$sidrena_total_items = isset( $total_items ) ? (int) $total_items : count( $rows );
$sidrena_total_pages = isset( $total_pages ) ? (int) $total_pages : 1;
?>
<div class="sidrena-cijena-arhiva">
	<?php if ( ! empty( $location ) ) : ?>
		<p class="sidrena-cijena-cjenik__meta">
			<?php
			printf(
				/* translators: %s: sales location name. */
				esc_html__( 'Published price lists for %s.', 'titanworks-sidrena-bazna-cijena' ),
				esc_html( (string) $location['name'] )
			);
			?>
		</p>
	<?php endif; ?>

	<?php if ( $sidrena_total_items > 0 ) : ?>
		<?php
		$sidrena_from = ( ( $sidrena_paged - 1 ) * $sidrena_per_page ) + 1;
		$sidrena_to   = min( $sidrena_total_items, $sidrena_from + count( $rows ) - 1 );
		?>
		<p class="sidrena-cijena-cjenik__count">
			<?php
			printf(
				/* translators: 1: start row index, 2: end row index, 3: total number of rows. */
				esc_html__( 'Showing %1$d–%2$d of %3$d published price lists.', 'titanworks-sidrena-bazna-cijena' ),
				(int) $sidrena_from,
				(int) $sidrena_to,
				(int) $sidrena_total_items
			);
			?>
		</p>
	<?php endif; ?>

	<div class="sidrena-cijena-table-container">
		<table class="sidrena-cijena-table">
			<caption><?php esc_html_e( 'Archive of published price lists', 'titanworks-sidrena-bazna-cijena' ); ?></caption>
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Published', 'titanworks-sidrena-bazna-cijena' ); ?></th>
					<th scope="col"><?php esc_html_e( 'File', 'titanworks-sidrena-bazna-cijena' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Format', 'titanworks-sidrena-bazna-cijena' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Items', 'titanworks-sidrena-bazna-cijena' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Size', 'titanworks-sidrena-bazna-cijena' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Checksum', 'titanworks-sidrena-bazna-cijena' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Download', 'titanworks-sidrena-bazna-cijena' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr>
						<td colspan="7"><?php esc_html_e( 'No price lists have been published yet.', 'titanworks-sidrena-bazna-cijena' ); ?></td>
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
						<td>
							<a href="<?php echo esc_url( (string) $sidrena_row['download_url'] ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: publication file name. */ __( 'Download %s', 'titanworks-sidrena-bazna-cijena' ), (string) $sidrena_row['filename'] ) ); ?>">
								<?php esc_html_e( 'Download', 'titanworks-sidrena-bazna-cijena' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<?php if ( $sidrena_total_pages > 1 ) : ?>
		<nav class="sidrena-cijena-pagination" aria-label="<?php esc_attr_e( 'Archive pagination', 'titanworks-sidrena-bazna-cijena' ); ?>">
			<?php
			echo wp_kses_post(
				paginate_links(
					array(
						'base'               => add_query_arg( 'paged', '%#%' ),
						'format'             => '',
						'current'            => $sidrena_paged,
						'total'              => $sidrena_total_pages,
						'prev_text'          => '&laquo; ' . __( 'Previous', 'titanworks-sidrena-bazna-cijena' ),
						'next_text'          => __( 'Next', 'titanworks-sidrena-bazna-cijena' ) . ' &raquo;',
						'before_page_number' => '<span class="screen-reader-text">' . __( 'Page', 'titanworks-sidrena-bazna-cijena' ) . ' </span>',
					)
				)
			);
			?>
		</nav>
	<?php endif; ?>

	<p class="sidrena-cijena-cjenik__links">
		<a href="<?php echo esc_url( $list_url ); ?>"><?php esc_html_e( 'Current price list', 'titanworks-sidrena-bazna-cijena' ); ?></a>
		<a href="<?php echo esc_url( $feed_xml ); ?>"><?php esc_html_e( 'XML Feed', 'titanworks-sidrena-bazna-cijena' ); ?></a>
		<a href="<?php echo esc_url( $feed_csv ); ?>"><?php esc_html_e( 'CSV Feed', 'titanworks-sidrena-bazna-cijena' ); ?></a>
	</p>
</div>
