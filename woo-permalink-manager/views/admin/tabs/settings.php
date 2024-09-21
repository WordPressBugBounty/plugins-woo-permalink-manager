<?php
if ( ! defined('WPINC')) {
	die;
}
?>
<p><?php echo esc_attr($settings->getMainSettingsText()); ?></p>
<?php $settings->show(); ?>
