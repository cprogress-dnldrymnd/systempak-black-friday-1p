<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$per_box = SPBF_Fees::get_per_box();
$end     = SPBF_Promotion::get_end_label();
?>
<aside class="spbf-banner" role="complementary">
	<div class="spbf-banner__inner">
		<p class="spbf-banner__eyebrow"><?php esc_html_e( 'BLACK FRIDAY', 'systempak-bf-1p' ); ?></p>
		<p class="spbf-banner__headline"><?php esc_html_e( '1p a Box clearance', 'systempak-bf-1p' ); ?></p>
		<p class="spbf-banner__copy">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: delivery fee */
					__( 'Selected stock at 1p per box + £%s delivery per box. UK only. While stocks last.', 'systempak-bf-1p' ),
					wc_format_decimal( $per_box, 2 )
				)
			);
			?>
			<?php if ( $end ) : ?>
				<?php echo esc_html( ' ' . sprintf( __( 'Ends %s.', 'systempak-bf-1p' ), $end ) ); ?>
			<?php endif; ?>
		</p>
		<?php if ( $url ) : ?>
			<p class="spbf-banner__cta">
				<a class="spbf-banner__button" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Shop the offer', 'systempak-bf-1p' ); ?></a>
			</p>
		<?php endif; ?>
	</div>
</aside>
