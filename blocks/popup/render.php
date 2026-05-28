<?php
/**
 * Server render for swish/popup.
 *
 * Available: $attributes, $content (inner blocks rendered), $block.
 * Outputs the popup body. The modal scaffolding is added by the frontend loader.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$a = wp_parse_args( $attributes, array(
	'accentColor'    => '#ffba00',
	'successMessage' => 'Thanks!',
	'imageId'        => 0,
	'imageUrl'       => '',
	'imageAlt'       => '',
	'layout'         => 'stack',
	'imageOpacity'   => 0.4,
	'width'          => 460,
	'padding'        => 28,
	'imageHeight'    => 0,
	'focalPoint'     => array( 'x' => 0.5, 'y' => 0.5 ),
) );

$layout       = in_array( $a['layout'], array( 'stack', 'two-column', 'background' ), true ) ? $a['layout'] : 'stack';
$width        = max( 280, min( 1200, intval( $a['width'] ) ) );
$padding      = max( 0, min( 120, intval( $a['padding'] ) ) );
$image_height = max( 0, intval( $a['imageHeight'] ) );

$fp     = is_array( $a['focalPoint'] ) ? $a['focalPoint'] : array( 'x' => 0.5, 'y' => 0.5 );
$fp_x   = max( 0, min( 1, isset( $fp['x'] ) ? floatval( $fp['x'] ) : 0.5 ) );
$fp_y   = max( 0, min( 1, isset( $fp['y'] ) ? floatval( $fp['y'] ) : 0.5 ) );
$fp_str = round( $fp_x * 100 ) . '% ' . round( $fp_y * 100 ) . '%';

$style  = '--swish-accent: ' . esc_attr( $a['accentColor'] ) . ';';
$style .= '--swish-popup-width: ' . $width . 'px;';
$style .= '--swish-popup-padding: ' . $padding . 'px;';
$style .= '--swish-img-pos: ' . $fp_str . ';';
$style .= '--swish-img-height: ' . ( $image_height > 0 ? $image_height . 'px' : 'auto' ) . ';';
if ( $layout === 'background' && ! empty( $a['imageUrl'] ) ) {
	$style .= '--swish-bg-image: url(\'' . esc_url( $a['imageUrl'] ) . '\');';
	$style .= '--swish-bg-opacity: ' . esc_attr( floatval( $a['imageOpacity'] ) ) . ';';
}

$wrapper_attrs = get_block_wrapper_attributes( array(
	'class'        => 'swish-ac-popup swish-ac-popup--layout-' . $layout,
	'style'        => $style,
	'data-success' => esc_attr( $a['successMessage'] ),
) );

$image_html = '';
if ( ! empty( $a['imageUrl'] ) && $layout !== 'background' ) {
	$image_html = sprintf(
		'<img class="swish-ac-popup__image" src="%s" alt="%s">',
		esc_url( $a['imageUrl'] ),
		esc_attr( $a['imageAlt'] )
	);
}
?>
<div <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php if ( $layout === 'two-column' ) : ?>
		<div class="swish-ac-popup__media"><?php echo $image_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		<div class="swish-ac-popup__content"><?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
	<?php elseif ( $layout === 'background' ) : ?>
		<div class="swish-ac-popup__content"><?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
	<?php else : ?>
		<?php echo $image_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<div class="swish-ac-popup__content"><?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
	<?php endif; ?>
</div>
