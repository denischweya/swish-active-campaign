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
	'padding'        => null,
	'imageHeight'    => 0,
	'focalPoint'     => array( 'x' => 0.5, 'y' => 0.5 ),
) );

$layout       = in_array( $a['layout'], array( 'stack', 'two-column', 'background' ), true ) ? $a['layout'] : 'stack';
$width        = max( 280, min( 1200, intval( $a['width'] ) ) );
$image_height = max( 0, intval( $a['imageHeight'] ) );

/**
 * Format the padding attribute (object | number | string | null) into a CSS shorthand.
 * Each side falls back to '0' if empty so a partially-filled BoxControl is still valid.
 */
$padding_css = null;
if ( is_array( $a['padding'] ) ) {
	$sides = array( 'top', 'right', 'bottom', 'left' );
	$any   = false;
	$parts = array();
	foreach ( $sides as $s ) {
		$v = isset( $a['padding'][ $s ] ) ? $a['padding'][ $s ] : '';
		if ( $v !== '' && $v !== null ) {
			$any     = true;
			$parts[] = $v;
		} else {
			$parts[] = '0';
		}
	}
	if ( $any ) {
		$padding_css = implode( ' ', $parts );
	}
} elseif ( is_numeric( $a['padding'] ) ) {
	$padding_css = intval( $a['padding'] ) . 'px';
} elseif ( is_string( $a['padding'] ) && $a['padding'] !== '' ) {
	$padding_css = $a['padding'];
}
if ( ! $padding_css ) {
	$padding_css = '28px';
}

$fp     = is_array( $a['focalPoint'] ) ? $a['focalPoint'] : array( 'x' => 0.5, 'y' => 0.5 );
$fp_x   = max( 0, min( 1, isset( $fp['x'] ) ? floatval( $fp['x'] ) : 0.5 ) );
$fp_y   = max( 0, min( 1, isset( $fp['y'] ) ? floatval( $fp['y'] ) : 0.5 ) );
$fp_str = round( $fp_x * 100 ) . '% ' . round( $fp_y * 100 ) . '%';

$style  = '--swish-accent: ' . esc_attr( $a['accentColor'] ) . ';';
$style .= '--swish-popup-width: ' . $width . 'px;';
$style .= '--swish-popup-padding: ' . esc_attr( $padding_css ) . ';';
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
