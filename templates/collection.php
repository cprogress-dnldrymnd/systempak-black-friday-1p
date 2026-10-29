<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$per_box = SPBF_Fees::get_per_box();
$max     = SPBF_Quantity::get_max();
$end     = SPBF_Promotion::get_end_label();
$count   = is_array( $product_ids ) ? count( $product_ids ) : 0;

// Avoid a stretched single-card layout when few products are assigned.
$columns = max( 1, absint( $columns ) );
if ( $count > 0 ) {
	$columns = min( $columns, $count );
}
if ( 1 === $count ) {
	$columns = 1;
} elseif ( 2 === $count ) {
	$columns = min( 2, $columns );
}

$wrapper_class = 'spbf-collection spbf-collection--count-' . absint( $count );
?>
<div class="<?php echo esc_attr( $wrapper_class ); ?>">
	<header class="spbf-collection__header">
		<p class="spbf-notice__eyebrow"><?php esc_html_e( 'BLACK FRIDAY', 'systempak-bf-1p' ); ?></p>
		<h2 class="spbf-collection__title"><?php esc_html_e( '1p a Box Offer', 'systempak-bf-1p' ); ?></h2>
		<p class="spbf-collection__intro">
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: delivery per box 2: max qty */
					__( 'Selected boxes for 1p each, plus £%1$s delivery per promotional box. Maximum %2$d boxes per product. UK delivery only — existing regional or pallet charges may still apply on top.', 'systempak-bf-1p' ),
					wc_format_decimal( $per_box, 2 ),
					$max
				)
			);
			?>
		</p>
		<?php if ( $end ) : ?>
			<p class="spbf-collection__ends"><strong><?php echo esc_html( sprintf( __( 'Offer ends %s.', 'systempak-bf-1p' ), $end ) ); ?></strong></p>
		<?php endif; ?>
	</header>

	<?php if ( empty( $product_ids ) ) : ?>
		<p class="spbf-collection__empty"><?php esc_html_e( 'Promotional products will appear here once they have been assigned to the Black Friday 1p Offer.', 'systempak-bf-1p' ); ?></p>
	<?php else : ?>
		<div class="spbf-collection__grid">
			<?php
			$shortcode = sprintf(
				'[products ids="%s" columns="%d" orderby="title" order="ASC"]',
				esc_attr( implode( ',', array_map( 'absint', $product_ids ) ) ),
				absint( $columns )
			);
			echo do_shortcode( $shortcode ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		</div>
	<?php endif; ?>
</div>
