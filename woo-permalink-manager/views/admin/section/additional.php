<?php

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Additional values
 *
 * @var bool $free
 * @var string $tag
 * @var string $canonical
 * @var string $redirect
 * @var string $use_primary_category
 * @var string $breadcrumbs
 * @var string $br_remove_shop
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
					<input <?php echo $free ? 'disabled' : ''; ?> type="checkbox"
						name="<?php echo esc_attr(Settings::OPTIONS); ?>[tag]" value="on" <?php checked( 'on', $tag ); ?>>
					<?php esc_html_e( 'Remove product tag base', 'premmerce-url-manager' ); ?>
				</label>
				<p class="description">
					<span class="premium-only-feature">
						<a class="premium-only-feature-link"
							href="<?php echo esc_url(admin_url('admin.php?page=premmerce-url-manager-admin-pricing')); ?>">
							<?php esc_html_e( 'Available only in premium version', 'premmerce-url-manager' ); ?>
						</a>
					</span>
				</p>
			</th>
		</tr>
		<tr>
			<th>
				<label>
					<input type="checkbox" name="<?php echo esc_attr(Settings::OPTIONS); ?>[use_primary_category]"
						<?php checked( 'on', $use_primary_category ); ?>>
					<?php esc_html_e( 'Use primary category', 'premmerce-url-manager' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( "Use 'Yoast SEO' primary category to build product path", 'premmerce-url-manager' ); ?>
				</p>
			</th>
		</tr>
		<tr>
			<th>
				<label>
					<input type="checkbox" name="<?php echo esc_attr(Settings::OPTIONS); ?>[canonical]"
						<?php checked( 'on', $canonical ); ?>>
					<?php esc_html_e( 'Add canonicals', 'premmerce-url-manager' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Add canonical meta tag to duplicated pages', 'premmerce-url-manager' ); ?></p>
			</th>
		</tr>
		<tr>
			<th>
				<label class="premium-only-label">
					<input <?php echo $free ? 'disabled' : ''; ?> type="checkbox"
						name="<?php echo esc_attr(Settings::OPTIONS); ?>[redirect]" <?php checked( 'on', $redirect ); ?>>
					<?php esc_html_e( 'Create redirects', 'premmerce-url-manager' ); ?>
				</label>
				<p class="description">
					<span class="premium-only-feature">
						<a class="premium-only-feature-link"
							href="<?php echo esc_url(admin_url('admin.php?page=premmerce-url-manager-admin-pricing')); ?>">
							<?php esc_html_e( 'Available only in premium version', 'premmerce-url-manager' ); ?>
						</a>
					</span>
					<?php esc_html_e( 'Create 301 redirect from duplicated pages', 'premmerce-url-manager' ); ?>
				</p>
			</th>
		</tr>
		<tr>
			<th>
				<label class="premium-only-label">
					<input <?php echo $free ? 'disabled' : ''; ?> type="checkbox"
						name="<?php echo esc_attr(Settings::OPTIONS); ?>[breadcrumbs]" <?php checked( 'on', $breadcrumbs ); ?>>
					<?php esc_html_e( 'Support breadcrumbs', 'premmerce-url-manager' ); ?>
				</label>
				<p class="description">
					<span class="premium-only-feature">
						<a class="premium-only-feature-link"
							href="<?php echo esc_url(admin_url('admin.php?page=premmerce-url-manager-admin-pricing')); ?>">
							<?php esc_html_e( 'Available only in premium version', 'premmerce-url-manager' ); ?>
						</a>
					</span>
					<?php esc_html_e( 'Enable breadcrumbs support', 'premmerce-url-manager' ); ?>
				</p>
			</th>
		</tr>
		<tr>
			<th>
				<label class="premium-only-label">
					<input <?php echo $free ? 'disabled' : ''; ?> type="checkbox"
						name="<?php echo esc_attr(Settings::OPTIONS); ?>[br_remove_shop]"
						<?php checked( 'on', $br_remove_shop ); ?>>
					<?php esc_html_e( 'Remove Shop', 'premmerce-url-manager' ); ?>
				</label>
				<p class="description">
					<span class="premium-only-feature">
						<a class="premium-only-feature-link"
							href="<?php echo esc_url(admin_url('admin.php?page=premmerce-url-manager-admin-pricing')); ?>">
							<?php esc_html_e( 'Available only in premium version', 'premmerce-url-manager' ); ?>
						</a>
					</span>
					<?php esc_html_e( 'Remove "Shop" from breadcrumbs', 'premmerce-url-manager' ); ?>
				</p>
			</th>
		</tr>

	</tbody>
</table>
