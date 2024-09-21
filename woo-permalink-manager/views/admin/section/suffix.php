<?php

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Suffix values
 *
 * @var bool $free
 * @var string $suffix
 * @var string $enable_suffix_products
 * @var string $enable_suffix_categories
 * @var string $category
 *
 */

use Premmerce\UrlManager\Admin\Settings;

$free = true;
if ( premmerce_wpm_fs()->can_use_premium_code() ) {
	$free = false;
}
?>

<table class="form-table">
	<tbody class="<?php echo $free ? 'is-free' : ''; ?>">
		<tr>
			<th>
				<label class="premium-only-label">
					<input <?php echo $free ? 'disabled' : ''; ?> type="text" id="permalink_url_suffix"
						name="<?php echo esc_attr(Settings::OPTIONS); ?>[suffix]" value="<?php echo esc_attr($suffix); ?>"
						data-inputmask="'mask': '.[*{1,10}]'">
				</label>
				<p class="description">
					<span class="premium-only-feature">
						<a class="premium-only-feature-link"
							href="<?php echo esc_url(admin_url('admin.php?page=premmerce-url-manager-admin-pricing')); ?>">
							<?php esc_html_e( 'Available only in premium version', 'premmerce-url-manager' ); ?>
						</a>
					</span>
					<?php esc_html_e( 'Specify suffix for your urls. For example ', 'premmerce-url-manager' ); ?>
					<b>.html</b>
				</p>
			</th>
		</tr>

		<tr>
			<th>
				<label class="premium-only-label">
					<input <?php echo $free ? 'disabled' : ''; ?> type="checkbox"
						name="<?php echo esc_attr(Settings::OPTIONS); ?>[enable_suffix_products]"
						<?php checked( 'on', $enable_suffix_products ); ?>>
					<?php esc_html_e( 'Enable for products', 'premmerce-url-manager' ); ?>
				</label>
				<p class="description">
					<span class="premium-only-feature">
						<a class="premium-only-feature-link"
							href="<?php echo esc_url(admin_url('admin.php?page=premmerce-url-manager-admin-pricing')); ?>">
							<?php esc_html_e( 'Available only in premium version', 'premmerce-url-manager' ); ?>
						</a>
					</span>
					<br>
					<?php if ( 'slug' === $product ) : ?>

					<code>
						<?php echo esc_url(home_url( '/sample-product' )); ?><span class="permalink_url_suffix"><?php echo esc_attr($suffix); ?></span>
					</code>

					<?php elseif ( 'category_slug' === $product ) : ?>

					<code>
						<?php echo esc_url(home_url( '/category/sample-product' )); ?><span class="permalink_url_suffix"><?php echo esc_attr($suffix); ?></span>
					</code>

					<?php elseif ( 'hierarchical' === $product ) : ?>

					<code>
						<?php echo esc_url(home_url( 'parent-category/category/sample-product' )); ?><span
								class="permalink_url_suffix"><?php echo esc_attr($suffix); ?></span>
					</code>

					<?php endif; ?>
				</p>
			</th>
		</tr>

		<tr>
			<th>
				<label class="premium-only-label">
					<input <?php echo $free ? 'disabled' : ''; ?> type="checkbox"
						name="<?php echo esc_attr(Settings::OPTIONS); ?>[enable_suffix_categories]"
						<?php checked( 'on', $enable_suffix_categories ); ?>>
					<?php esc_html_e( 'Enable for categories', 'premmerce-url-manager' ); ?>
				</label>
				<p class="description">
					<span class="premium-only-feature">
						<a class="premium-only-feature-link"
							href="<?php echo esc_url(admin_url('admin.php?page=premmerce-url-manager-admin-pricing')); ?>">
							<?php esc_html_e( 'Available only in premium version', 'premmerce-url-manager' ); ?>
						</a>
					</span>
					<br>
					<?php if ( 'slug' === $category ) : ?>

					<code>
						<?php echo esc_url(home_url( '/category' )); ?><span class="permalink_url_suffix"><?php echo esc_attr($suffix); ?></span>
					</code>


					<?php elseif ( 'hierarchical' === $category ) : ?>
					<code>
						<?php echo esc_url(home_url( 'parent-category/category' )); ?><span class="permalink_url_suffix"><?php echo esc_attr($suffix); ?></span>
					</code>

					<?php endif; ?>
				</p>
			</th>
		</tr>
	</tbody>
</table>
