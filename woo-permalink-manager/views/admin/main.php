<?php

if ( ! defined('WPINC')) {
	die;
}
?>
<div class="wrap">
	<h2><?php esc_html_e('WooCommerce Permalink Settings', 'premmerce-url-manager'); ?></h2>

	<?php if (!empty($tabs)) : ?>
		<h2 class="nav-tab-wrapper">
			<?php
			foreach ($tabs as $tabKey => $tabName) :
			$class   = ( $tabKey === $current ) ? ' nav-tab-active' : '';
			$tabLink = '?page=premmerce-url-manager-admin&tab=' . $tabKey; ?>
			<a class='nav-tab<?php echo esc_attr($class); ?>' href='<?php echo esc_url($tabLink); ?>'>
				<?php echo esc_attr($tabName); ?>
			</a>
			<?php
			endforeach;
			if (!premmerce_wpm_fs()->can_use_premium_code()) : //if it is not Premium plan.
				?>
			<a class="nav-tab premmerce-upgrate-to-premium-button"
				href="<?php echo esc_url(admin_url('admin.php?page=premmerce-url-manager-admin-pricing')); ?>">
				<?php esc_html_e('Upgrate to Premium', 'premmerce-url-manager'); ?>
			</a>
			<?php
			endif;
			?>
		</h2>
	<?php endif; ?>

	<?php $file = __DIR__ . "/tabs/{$current}.php"; ?>
	<?php if (file_exists($file)) : ?>
	<?php include $file; ?>
	<?php endif; ?>
</div>
