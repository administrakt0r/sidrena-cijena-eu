<?php
/**
 * Anchor price template.
 *
 * Overridable from a theme at `sidrena-cijena/anchor-price.php`.
 *
 * @package SidrenaCijena
 *
 * @var array $args Template arguments.
 */

defined( 'ABSPATH' ) || exit;

/* translators: %s: date of the reference price. */
$sidrena_date_template   = __( 'Cijena na dan %s', 'titanworks-sidrena-bazna-cijena' );
$sidrena_style           = isset( $style ) && array_key_exists( $style, \SidrenaCijena\Support\DisplayStyles::presets() ) ? $style : 'tooltip';
$sidrena_date_mode       = isset( $date_mode ) ? $date_mode : (string) \SidrenaCijena\Support\Settings::get( 'anchor_date_mode', 'tooltip' );
$sidrena_font_weight     = isset( $font_weight ) ? $font_weight : (string) \SidrenaCijena\Support\Settings::get( 'anchor_font_weight', 'normal' );
$sidrena_default_tooltip = __( 'Sidrena (bazna) cijena je redovna maloprodajna cijena na propisani referentni datum prema mjerama izravne kontrole cijena.', 'titanworks-sidrena-bazna-cijena' );
$sidrena_tooltip_text    = isset( $tooltip_text ) && '' !== $tooltip_text
	? $tooltip_text
	: (string) \SidrenaCijena\Support\Settings::get( 'anchor_tooltip_text', $sidrena_default_tooltip );

if ( 'Sidrena cijena je najniža cijena u zadnjih 30 dana prije početka posebnog oblika prodaje.' === $sidrena_tooltip_text ||
	'Sidrena cijena je najniza cijena u zadnjih 30 dana prije pocetka posebnog oblika prodaje.' === $sidrena_tooltip_text ) {
	$sidrena_tooltip_text = $sidrena_default_tooltip;
}

$sidrena_classes = 'sidrena-cijena sidrena-cijena--' . sanitize_html_class( $context ) . ' sidrena-cijena--style-' . $sidrena_style;
if ( 'bold' === $sidrena_font_weight ) {
	$sidrena_classes .= ' sidrena-cijena--weight-bold';
} elseif ( 'semi-bold' === $sidrena_font_weight ) {
	$sidrena_classes .= ' sidrena-cijena--weight-semibold';
}

$sidrena_size = min( 160, max( 40, (int) \SidrenaCijena\Support\Settings::get( 'display_size', 40 ) ) );
?>
<div class="<?php echo esc_attr( $sidrena_classes ); ?>"
	style="--sidrena-size:<?php echo esc_attr( (string) ( $sidrena_size / 100 ) ); ?>"
	title="<?php echo esc_attr( $anchor_title ); ?>"
	data-product-id="<?php echo esc_attr( (string) $product_id ); ?>"
	data-variation-id="<?php echo esc_attr( (string) $variation_id ); ?>"
	data-sidrena-cijena-date-template="<?php echo esc_attr( $sidrena_date_template ); ?>"
	<?php if ( isset( $label_template ) && false !== strpos( $label_template, '{' ) ) : ?>
	data-sidrena-cijena-label-template="<?php echo esc_attr( $label_template ); ?>"
	<?php endif; ?>
	data-sidrena-date-mode="<?php echo esc_attr( $sidrena_date_mode ); ?>">
	<?php if ( $show_current && $current_display ) : ?>
		<span class="sidrena-cijena__current">
			<span class="sidrena-cijena__current-value"><?php echo esc_html( $current_display ); ?></span>
		</span>
	<?php endif; ?>

	<?php if ( $show_anchor ) : ?>
		<span class="sidrena-cijena__anchor">
			<span class="sidrena-cijena__label" data-sidrena-cijena-label><?php echo esc_html( $label ); ?></span>
			<?php if ( 'tooltip' === $sidrena_date_mode || 'tooltip' === $sidrena_style ) : ?>
				<span class="sidrena-cijena__tooltip-wrap">
					<span role="button" tabindex="0" class="sidrena-cijena__help-trigger" aria-label="<?php esc_attr_e( 'Objašnjenje sidrene cijene', 'titanworks-sidrena-bazna-cijena' ); ?>" aria-expanded="false" data-sidrena-cijena-tooltip-trigger>?</span>
					<span class="sidrena-cijena__tooltip-popup" role="tooltip" aria-hidden="true" data-sidrena-cijena-tooltip-popup>
						<span class="sidrena-cijena__tooltip-text"><?php echo esc_html( $sidrena_tooltip_text ); ?></span>
						<?php if ( $reference_date_display ) : ?>
							<span class="sidrena-cijena__tooltip-date" data-sidrena-cijena-tooltip-date data-sidrena-cijena-reference-date><?php echo esc_html( sprintf( $sidrena_date_template, $reference_date_display ) ); ?></span>
						<?php else : ?>
							<span class="sidrena-cijena__tooltip-date" data-sidrena-cijena-tooltip-date data-sidrena-cijena-reference-date style="display:none;"></span>
						<?php endif; ?>
					</span>
				</span>
			<?php endif; ?>
			<span class="sidrena-cijena__anchor-value" data-sidrena-cijena-anchor aria-live="polite"><?php echo esc_html( $anchor_display ); ?></span>
			<?php if ( 'inline' === $sidrena_date_mode && 'tooltip' !== $sidrena_style && $reference_date_display ) : ?>
				<span class="sidrena-cijena__reference-date" data-sidrena-cijena-reference-date><?php echo esc_html( sprintf( $sidrena_date_template, $reference_date_display ) ); ?></span>
			<?php elseif ( 'subtext' === $sidrena_date_mode && 'tooltip' !== $sidrena_style && $reference_date_display ) : ?>
				<span class="sidrena-cijena__reference-date sidrena-cijena__reference-date--subtext" data-sidrena-cijena-reference-date><?php echo esc_html( sprintf( $sidrena_date_template, $reference_date_display ) ); ?></span>
			<?php endif; ?>
		</span>
	<?php endif; ?>

	<?php if ( $disclaimer ) : ?>
		<span class="sidrena-cijena__disclaimer"><?php echo esc_html( $disclaimer ); ?></span>
	<?php endif; ?>
</div>
