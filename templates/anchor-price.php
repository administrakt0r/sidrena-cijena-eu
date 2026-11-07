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
$twbce_date_template   = __( 'Cijena na dan %s', 'sidrena-cijena-eu' );
$twbce_style           = isset( $style ) && array_key_exists( $style, \Titanworks\SidrenaCijena\Support\DisplayStyles::presets() ) ? $style : 'tooltip';
$twbce_date_mode       = isset( $date_mode ) ? $date_mode : (string) \Titanworks\SidrenaCijena\Support\Settings::get( 'anchor_date_mode', 'tooltip' );
$twbce_font_weight     = isset( $font_weight ) ? $font_weight : (string) \Titanworks\SidrenaCijena\Support\Settings::get( 'anchor_font_weight', 'normal' );
$twbce_default_tooltip = __( 'Sidrena (bazna) cijena je redovna maloprodajna cijena na propisani referentni datum prema mjerama izravne kontrole cijena.', 'sidrena-cijena-eu' );
$twbce_tooltip_text    = isset( $tooltip_text ) && '' !== $tooltip_text
	? $tooltip_text
	: (string) \Titanworks\SidrenaCijena\Support\Settings::get( 'anchor_tooltip_text', $twbce_default_tooltip );

if ( 'Sidrena cijena je najniža cijena u zadnjih 30 dana prije početka posebnog oblika prodaje.' === $twbce_tooltip_text ||
	'Sidrena cijena je najniza cijena u zadnjih 30 dana prije pocetka posebnog oblika prodaje.' === $twbce_tooltip_text ) {
	$twbce_tooltip_text = $twbce_default_tooltip;
}

$twbce_classes = 'sidrena-cijena sidrena-cijena--' . sanitize_html_class( $context ) . ' sidrena-cijena--style-' . $twbce_style;
if ( 'bold' === $twbce_font_weight ) {
	$twbce_classes .= ' sidrena-cijena--weight-bold';
} elseif ( 'semi-bold' === $twbce_font_weight ) {
	$twbce_classes .= ' sidrena-cijena--weight-semibold';
}

$twbce_size = isset( $size ) ? min( 160, max( 40, (int) $size ) ) : min( 160, max( 40, (int) \Titanworks\SidrenaCijena\Support\Settings::get( 'display_size', 40 ) ) );
?>
<div class="<?php echo esc_attr( $twbce_classes ); ?>"
	style="--sidrena-size:<?php echo esc_attr( (string) ( $twbce_size / 100 ) ); ?>"
	title="<?php echo esc_attr( $anchor_title ); ?>"
	data-product-id="<?php echo esc_attr( (string) $product_id ); ?>"
	data-variation-id="<?php echo esc_attr( (string) $variation_id ); ?>"
	data-sidrena-cijena-date-template="<?php echo esc_attr( $twbce_date_template ); ?>"
	<?php if ( isset( $label_template ) && false !== strpos( $label_template, '{' ) ) : ?>
	data-sidrena-cijena-label-template="<?php echo esc_attr( $label_template ); ?>"
	<?php endif; ?>
	data-sidrena-date-mode="<?php echo esc_attr( $twbce_date_mode ); ?>">
	<?php if ( $show_current && $current_display ) : ?>
		<span class="sidrena-cijena__current">
			<span class="sidrena-cijena__current-value"><?php echo esc_html( $current_display ); ?></span>
		</span>
	<?php endif; ?>

	<?php if ( $show_anchor ) : ?>
		<span class="sidrena-cijena__anchor">
			<span class="sidrena-cijena__label" data-sidrena-cijena-label><?php echo esc_html( $label ); ?></span>
			<?php if ( 'tooltip' === $twbce_date_mode || 'tooltip' === $twbce_style ) : ?>
				<span class="sidrena-cijena__tooltip-wrap">
					<span role="button" tabindex="0" class="sidrena-cijena__help-trigger" aria-label="<?php esc_attr_e( 'Objašnjenje sidrene cijene', 'sidrena-cijena-eu' ); ?>" aria-expanded="false" data-sidrena-cijena-tooltip-trigger>?</span>
					<span class="sidrena-cijena__tooltip-popup" role="tooltip" aria-hidden="true" data-sidrena-cijena-tooltip-popup>
						<span class="sidrena-cijena__tooltip-text"><?php echo esc_html( $twbce_tooltip_text ); ?></span>
						<?php if ( $reference_date_display ) : ?>
							<span class="sidrena-cijena__tooltip-date" data-sidrena-cijena-tooltip-date data-sidrena-cijena-reference-date><?php echo esc_html( sprintf( $twbce_date_template, $reference_date_display ) ); ?></span>
						<?php else : ?>
							<span class="sidrena-cijena__tooltip-date" data-sidrena-cijena-tooltip-date data-sidrena-cijena-reference-date hidden></span>
						<?php endif; ?>
					</span>
				</span>
			<?php endif; ?>
			<span class="sidrena-cijena__anchor-value" data-sidrena-cijena-anchor aria-live="polite"><?php echo esc_html( $anchor_display ); ?></span>
			<?php if ( 'inline' === $twbce_date_mode && 'tooltip' !== $twbce_style && $reference_date_display ) : ?>
				<span class="sidrena-cijena__reference-date" data-sidrena-cijena-reference-date><?php echo esc_html( sprintf( $twbce_date_template, $reference_date_display ) ); ?></span>
			<?php elseif ( 'subtext' === $twbce_date_mode && 'tooltip' !== $twbce_style && $reference_date_display ) : ?>
				<span class="sidrena-cijena__reference-date sidrena-cijena__reference-date--subtext" data-sidrena-cijena-reference-date><?php echo esc_html( sprintf( $twbce_date_template, $reference_date_display ) ); ?></span>
			<?php endif; ?>
		</span>
	<?php endif; ?>

	<?php if ( $disclaimer ) : ?>
		<span class="sidrena-cijena__disclaimer"><?php echo esc_html( $disclaimer ); ?></span>
	<?php endif; ?>
</div>
