<?php
/**
 * Server render for swish/ac-form.
 * Available: $attributes, $content, $block.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$a = wp_parse_args( $attributes, array(
	'showName'     => true,
	'nameLabel'    => 'Name',
	'nameRequired' => false,
	'emailLabel'   => 'Email',
	'submitLabel'  => 'Subscribe',
	'buttonAlign'  => 'left',
	'showLabels'   => true,
) );

$align       = in_array( $a['buttonAlign'], array( 'left', 'center', 'right', 'full' ), true ) ? $a['buttonAlign'] : 'left';
$show_labels = ! empty( $a['showLabels'] );
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
		<button type="submit" class="swish-ac-popup-form__submit swish-ac-form__submit">
			<?php echo esc_html( $a['submitLabel'] ); ?>
		</button>
	</div>
	<p class="swish-ac-popup-form__error swish-ac-form__error" role="alert"></p>
</form>
