<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$per_box = SPBF_Fees::get_per_box();
$max     = SPBF_Quantity::get_max();
$end     = SPBF_Promotion::get_end_label();
$price   = SPBF_Pricing::get_promo_price();
?>
<div class="spbf-notice spbf-notice--product" role="note">
	<p class="spbf-notice__eyebrow"><?php esc_html_e( 'BLACK FRIDAY – 1P A BOX', 'systempak-bf-1p' ); ?></p>
	<p class="spbf-notice__body">
		<?php echo wp_kses_post( wc_price( $price ) ); ?>
		<?php
		echo esc_html(
			sprintf(
				/* translators: %s: delivery amount */
				__( ' per box + £%s delivery per box.', 'systempak-bf-1p' ),
				wc_format_decimal( $per_box, 2 )
			)
		);
		?>
	</p>
	<ul class="spbf-notice__list">
		<li><?php echo esc_html( sprintf( __( 'Maximum %d boxes per product.', 'systempak-bf-1p' ), $max ) ); ?></li>
		<li><?php esc_html_e( 'UK only. Regional delivery surcharges may apply.', 'systempak-bf-1p' ); ?></li>
		<?php if ( $end ) : ?>
			<li><?php echo esc_html( sprintf( __( 'Offer ends %s.', 'systempak-bf-1p' ), $end ) ); ?></li>
		<?php endif; ?>
		<li><?php esc_html_e( 'While stocks last.', 'systempak-bf-1p' ); ?></li>
	</ul>
</div>
