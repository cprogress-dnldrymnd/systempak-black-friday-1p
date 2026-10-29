<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cat_link        = admin_url( 'edit-tags.php?taxonomy=product_cat&post_type=product' );
$settings_link   = admin_url( 'admin.php?page=spbf-settings' );
$collection_id   = absint( SPBF_Settings::get( 'collection_page_id', 0 ) );
$collection_url  = $collection_id ? get_permalink( $collection_id ) : home_url( '/black-friday-1p-a-box/' );
$products_link   = admin_url( 'edit.php?post_type=product' );
?>
<div class="spbf-docs">
	<p class="spbf-docs__lead">
		<?php esc_html_e( 'This plugin runs the Black Friday clearance: selected SKUs at £0.01 per box, max 4 per product, plus a compulsory £10.95 promotional delivery charge per promotional box. It sits alongside Tier Pricing Table, Flexible Shipping, and Conditional Extra Fees — it does not replace them.', 'systempak-bf-1p' ); ?>
	</p>

	<nav class="spbf-docs__toc" aria-label="<?php esc_attr_e( 'Documentation sections', 'systempak-bf-1p' ); ?>">
		<strong><?php esc_html_e( 'Jump to:', 'systempak-bf-1p' ); ?></strong>
		<a href="#spbf-docs-visibility"><?php esc_html_e( 'Visibility', 'systempak-bf-1p' ); ?></a>
		<a href="#spbf-docs-products"><?php esc_html_e( 'Assign products', 'systempak-bf-1p' ); ?></a>
		<a href="#spbf-docs-pages"><?php esc_html_e( 'Pages & shortcodes', 'systempak-bf-1p' ); ?></a>
		<a href="#spbf-docs-test"><?php esc_html_e( 'How to test', 'systempak-bf-1p' ); ?></a>
		<a href="#spbf-docs-rules"><?php esc_html_e( 'Promo rules', 'systempak-bf-1p' ); ?></a>
		<a href="#spbf-docs-live"><?php esc_html_e( 'Go live', 'systempak-bf-1p' ); ?></a>
		<a href="#spbf-docs-vat"><?php esc_html_e( 'VAT', 'systempak-bf-1p' ); ?></a>
		<a href="#spbf-docs-troubleshoot"><?php esc_html_e( 'Troubleshooting', 'systempak-bf-1p' ); ?></a>
	</nav>

	<section id="spbf-docs-visibility" class="spbf-docs__section">
		<h3><?php esc_html_e( '1. Visibility modes (keep customers safe)', 'systempak-bf-1p' ); ?></h3>
		<p><?php esc_html_e( 'Change this on the Settings tab. Default is Admin testing only so the public never sees the offer until you are ready.', 'systempak-bf-1p' ); ?></p>
		<table class="widefat striped spbf-docs__table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Mode', 'systempak-bf-1p' ); ?></th>
					<th><?php esc_html_e( 'Who sees the offer', 'systempak-bf-1p' ); ?></th>
					<th><?php esc_html_e( 'Schedule', 'systempak-bf-1p' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><strong><?php esc_html_e( 'Off', 'systempak-bf-1p' ); ?></strong></td>
					<td><?php esc_html_e( 'Nobody', 'systempak-bf-1p' ); ?></td>
					<td><?php esc_html_e( 'Ignored', 'systempak-bf-1p' ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Admin testing only', 'systempak-bf-1p' ); ?></strong></td>
					<td><?php esc_html_e( 'Users with manage_woocommerce (admins / shop managers)', 'systempak-bf-1p' ); ?></td>
					<td><?php esc_html_e( 'Ignored — so you can test before 27 November', 'systempak-bf-1p' ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Live', 'systempak-bf-1p' ); ?></strong></td>
					<td><?php esc_html_e( 'Everyone', 'systempak-bf-1p' ); ?></td>
					<td><?php esc_html_e( 'Only between Start and End (Europe/London by default)', 'systempak-bf-1p' ); ?></td>
				</tr>
			</tbody>
		</table>
		<p><em><?php esc_html_e( 'Tip: open the site in an Incognito window to confirm customers still see normal prices while you test as admin.', 'systempak-bf-1p' ); ?></em></p>
	</section>

	<section id="spbf-docs-products" class="spbf-docs__section">
		<h3><?php esc_html_e( '2. Assigning products / SKUs', 'systempak-bf-1p' ); ?></h3>
		<p>
			<?php
			echo wp_kses_post(
				sprintf(
					/* translators: %s: category admin URL */
					__( 'A product qualifies only when it is in the <a href="%s"><strong>Black Friday 1p Offer</strong></a> product category. For variable products, assign the category on the parent — all variations are included.', 'systempak-bf-1p' ),
					esc_url( $cat_link )
				)
			);
			?>
		</p>
		<p>
			<a class="button" href="<?php echo esc_url( $products_link ); ?>"><?php esc_html_e( 'Edit products', 'systempak-bf-1p' ); ?></a>
			<a class="button" href="<?php echo esc_url( $cat_link ); ?>"><?php esc_html_e( 'Manage BF category', 'systempak-bf-1p' ); ?></a>
		</p>
		<p><?php esc_html_e( 'Normal WooCommerce stock still applies. Add or remove SKUs by ticking/unticking that category only.', 'systempak-bf-1p' ); ?></p>
	</section>

	<section id="spbf-docs-pages" class="spbf-docs__section">
		<h3><?php esc_html_e( '3. Pages & shortcodes', 'systempak-bf-1p' ); ?></h3>
		<table class="widefat striped spbf-docs__table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Shortcode', 'systempak-bf-1p' ); ?></th>
					<th><?php esc_html_e( 'Purpose', 'systempak-bf-1p' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><code>[spbf_collection]</code></td>
					<td><?php esc_html_e( 'Lists all qualifying promotional products with offer rules copy. Use on the dedicated collection page.', 'systempak-bf-1p' ); ?></td>
				</tr>
			</tbody>
		</table>
		<p>
			<?php esc_html_e( 'Collection page (stable marketing URL):', 'systempak-bf-1p' ); ?>
			<?php if ( $collection_url ) : ?>
				<a href="<?php echo esc_url( $collection_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $collection_url ); ?></a>
			<?php else : ?>
				<?php esc_html_e( 'Not set — create a page with [spbf_collection] and select it under Settings.', 'systempak-bf-1p' ); ?>
			<?php endif; ?>
		</p>
		<p><em><?php esc_html_e( 'While Visibility is Admin testing only, [spbf_collection] also shows private products in the Black Friday category (for shop managers / admins). Live mode shows published products only.', 'systempak-bf-1p' ); ?></em></p>
	</section>

	<section id="spbf-docs-test" class="spbf-docs__section">
		<h3><?php esc_html_e( '4. How to check the plugin is working', 'systempak-bf-1p' ); ?></h3>
		<ol>
			<li><?php esc_html_e( 'Stay on “Admin testing only”. Confirm “Active for you right now?” is Yes on the Settings tab.', 'systempak-bf-1p' ); ?></li>
			<li><?php esc_html_e( 'Assign one in-stock test product to the Black Friday 1p Offer category.', 'systempak-bf-1p' ); ?></li>
			<li><?php esc_html_e( 'While logged in as admin, open the product page → expect £0.01 and the Black Friday notice.', 'systempak-bf-1p' ); ?></li>
			<li><?php esc_html_e( 'Open the same page Incognito (logged out) → expect normal price / no notice.', 'systempak-bf-1p' ); ?></li>
			<li><?php esc_html_e( 'Add 1 box to the basket → expect £0.01 + £10.95 promotional delivery fee.', 'systempak-bf-1p' ); ?></li>
			<li><?php esc_html_e( 'Try quantity 5 → should be blocked (max 4 per product).', 'systempak-bf-1p' ); ?></li>
			<li><?php esc_html_e( 'Mix with a normal product → promotional fee remains; normal shipping rules still apply.', 'systempak-bf-1p' ); ?></li>
			<li><?php esc_html_e( 'Apply a coupon → promo product and £10.95 fee must not be discounted.', 'systempak-bf-1p' ); ?></li>
			<li><?php esc_html_e( 'At checkout, set a non-UK shipping country → clear UK-only error.', 'systempak-bf-1p' ); ?></li>
		</ol>
	</section>

	<section id="spbf-docs-rules" class="spbf-docs__section">
		<h3><?php esc_html_e( '5. Promotion rules (summary)', 'systempak-bf-1p' ); ?></h3>
		<ul>
			<li><?php esc_html_e( 'Price: £0.01 per qualifying box (overrides Tier Pricing Table for those SKUs while active).', 'systempak-bf-1p' ); ?></li>
			<li><?php esc_html_e( 'Limit: maximum 4 boxes per promotional product (not 4 across the whole basket).', 'systempak-bf-1p' ); ?></li>
			<li><?php esc_html_e( 'Delivery fee: £10.95 × total promotional boxes in the basket (cart fee, separate from shipping).', 'systempak-bf-1p' ); ?></li>
			<li><?php esc_html_e( 'Mixed baskets: normal pallet / regional / Highlands surcharges still apply on top.', 'systempak-bf-1p' ); ?></li>
			<li><?php esc_html_e( 'Free shipping on the rest of the order does not remove the £10.95 fee.', 'systempak-bf-1p' ); ?></li>
			<li><?php esc_html_e( 'UK shipping destination only.', 'systempak-bf-1p' ); ?></li>
			<li><?php esc_html_e( 'Coupons and BOGO do not discount promotional lines or the promo fee.', 'systempak-bf-1p' ); ?></li>
			<li><?php esc_html_e( 'Default schedule: Friday 27 Nov 2026 00:00 → Monday 30 Nov 2026 17:00 (Europe/London).', 'systempak-bf-1p' ); ?></li>
		</ul>
	</section>

	<section id="spbf-docs-live" class="spbf-docs__section">
		<h3><?php esc_html_e( '6. Going live checklist', 'systempak-bf-1p' ); ?></h3>
		<ol>
			<li><?php esc_html_e( 'Final SKU list assigned and stock checked.', 'systempak-bf-1p' ); ?></li>
			<li><?php esc_html_e( 'Staging tests above completed and signed off.', 'systempak-bf-1p' ); ?></li>
			<li><?php esc_html_e( 'VAT treatment confirmed for the 1p price and £10.95 fee.', 'systempak-bf-1p' ); ?></li>
			<li><?php esc_html_e( 'Collection page URL ready for Meta / email / social.', 'systempak-bf-1p' ); ?></li>
			<li>
				<?php
				echo wp_kses_post(
					sprintf(
						/* translators: %s: settings URL */
						__( 'On the <a href="%s">Settings</a> tab, switch Visibility to <strong>Live</strong> when the campaign should be public. It will still only run inside the start/end window.', 'systempak-bf-1p' ),
						esc_url( $settings_link )
					)
				);
				?>
			</li>
			<li><?php esc_html_e( 'After 5pm on 30 November (or your End time), pricing and fees stop automatically. You can also set Visibility to Off.', 'systempak-bf-1p' ); ?></li>
		</ol>
	</section>

	<section id="spbf-docs-vat" class="spbf-docs__section">
		<h3><?php esc_html_e( '7. VAT (confirm before go-live)', 'systempak-bf-1p' ); ?></h3>
		<p><?php esc_html_e( 'The original brief left two points unconfirmed:', 'systempak-bf-1p' ); ?></p>
		<ol>
			<li><?php esc_html_e( 'Whether the £0.01 product price is inclusive or exclusive of VAT.', 'systempak-bf-1p' ); ?></li>
			<li><?php esc_html_e( 'Whether the £10.95 promotional delivery fee is inclusive or exclusive of VAT.', 'systempak-bf-1p' ); ?></li>
		</ol>
		<p><?php esc_html_e( 'This plugin follows the store’s existing WooCommerce tax / fee tax settings (fee taxable is configurable on Settings). Do not switch to Live until SystemPAK has confirmed both.', 'systempak-bf-1p' ); ?></p>
	</section>

	<section id="spbf-docs-troubleshoot" class="spbf-docs__section">
		<h3><?php esc_html_e( '8. Troubleshooting', 'systempak-bf-1p' ); ?></h3>
		<table class="widefat striped spbf-docs__table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Symptom', 'systempak-bf-1p' ); ?></th>
					<th><?php esc_html_e( 'Check', 'systempak-bf-1p' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><?php esc_html_e( 'Still seeing normal / tier prices as admin', 'systempak-bf-1p' ); ?></td>
					<td><?php esc_html_e( 'Visibility not Off? Product assigned? Hard-refresh / clear WP Fastest Cache. Confirm “Active for you right now?” = Yes.', 'systempak-bf-1p' ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Customers can see the 1p offer too early', 'systempak-bf-1p' ); ?></td>
					<td><?php esc_html_e( 'Visibility must be Admin testing only or Off — not Live.', 'systempak-bf-1p' ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'No £10.95 fee in basket', 'systempak-bf-1p' ); ?></td>
					<td><?php esc_html_e( 'Line must be a qualifying product while the promo is active for your user. Recalculate totals / reload cart.', 'systempak-bf-1p' ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Collection page empty', 'systempak-bf-1p' ); ?></td>
					<td><?php esc_html_e( 'No products assigned yet, or you are logged out while Visibility is Admin testing only.', 'systempak-bf-1p' ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Live mode but nothing happens', 'systempak-bf-1p' ); ?></td>
					<td><?php esc_html_e( 'Check server time vs Start/End on the Settings tab. Live only runs inside that window.', 'systempak-bf-1p' ); ?></td>
				</tr>
			</tbody>
		</table>
	</section>

	<p class="spbf-docs__footer">
		<a class="spbf-btn spbf-btn--primary" href="<?php echo esc_url( $settings_link ); ?>"><?php esc_html_e( 'Back to Settings', 'systempak-bf-1p' ); ?></a>
	</p>
</div>
