<?php
/**
 * Server render for swish/ac-form.
 * Available: $attributes, $content, $block.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$a = wp_parse_args( $attributes, array(
	'showName'          => true,
	'nameLabel'         => 'Name',
	'nameRequired'      => false,
	'emailLabel'        => 'Email',
	'submitLabel'       => 'Subscribe',
	'buttonAlign'       => 'left',
	'showLabels'        => true,
	'buttonBg'          => '',
	'buttonText'        => '',
	'buttonBorderWidth' => 0,
	'buttonBorderColor' => '',
	'buttonRadius'      => 4,
) );

$align       = in_array( $a['buttonAlign'], array( 'left', 'center', 'right', 'full' ), true ) ? $a['buttonAlign'] : 'left';
$show_labels = ! empty( $a['showLabels'] );

$btn_style = '';
if ( ! empty( $a['buttonBg'] ) ) {
	$btn_style .= 'background:' . esc_attr( $a['buttonBg'] ) . ';';
}
if ( ! empty( $a['buttonText'] ) ) {
	$btn_style .= 'color:' . esc_attr( $a['buttonText'] ) . ';';
}
$bw = max( 0, intval( $a['buttonBorderWidth'] ) );
if ( $bw > 0 ) {
	$bc = ! empty( $a['buttonBorderColor'] ) ? $a['buttonBorderColor'] : '#000';
	$btn_style .= 'border:' . $bw . 'px solid ' . esc_attr( $bc ) . ';';
}
if ( isset( $a['buttonRadius'] ) && $a['buttonRadius'] !== '' ) {
	$btn_style .= 'border-radius:' . max( 0, intval( $a['buttonRadius'] ) ) . 'px;';
}
?>
<form class="swish-ac-popup-form__form swish-ac-form" novalidate>
	<?php if ( ! empty( $a['showName'] ) ) : ?>
		<label class="swish-ac-form__field">
			<?php if ( $show_labels ) : ?>
				<span><?php echo esc_html( $a['nameLabel'] ); ?><?php echo ! empty( $a['nameRequired'] ) ? ' *' : ''; ?></span>
			<?php endif; ?>
			<input type="text" name="name"
				placeholder="<?php echo esc_attr( $a['nameLabel'] ); ?>"
				<?php if ( ! $show_labels ) : ?>aria-label="<?php echo esc_attr( $a['nameLabel'] ); ?>"<?php endif; ?>
				autocomplete="given-name"
				<?php echo ! empty( $a['nameRequired'] ) ? 'required' : ''; ?>>
		</label>
	<?php endif; ?>

	<label class="swish-ac-form__field">
		<?php if ( $show_labels ) : ?>
			<span><?php echo esc_html( $a['emailLabel'] ); ?> *</span>
		<?php endif; ?>
		<input type="email" name="email"
			placeholder="<?php echo esc_attr( $a['emailLabel'] ); ?>"
			<?php if ( ! $show_labels ) : ?>aria-label="<?php echo esc_attr( $a['emailLabel'] ); ?>"<?php endif; ?>
			autocomplete="email" required>
	</label>

	<div class="swish-ac-form__submit-wrap swish-ac-form__submit-wrap--align-<?php echo esc_attr( $align ); ?>">
		<button type="submit" class="swish-ac-popup-form__submit swish-ac-form__submit"
			<?php if ( $btn_style ) : ?>style="<?php echo esc_attr( $btn_style ); ?>"<?php endif; ?>>
			<?php echo esc_html( $a['submitLabel'] ); ?>
		</button>
	</div>
	<p class="swish-ac-popup-form__error swish-ac-form__error" role="alert"></p>
</form>
