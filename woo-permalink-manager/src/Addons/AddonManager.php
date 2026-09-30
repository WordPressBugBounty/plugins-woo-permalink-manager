<?php namespace Premmerce\UrlManager\Addons;

use Premmerce\UrlManager\PermalinkListener;

class AddonManager
{
    /**
       * Get Addons
       *
     * @return string[]
     */
    public function getAddons()
    {
        return array(
          BreadcrumbsAddon::class,
          YoastBreadcrumbsAddon::class
        );
    }

    /**
     * Init Addons
     *
     * @param PermalinkListener|null $permalinkListener Shared with the addons, so breadcrumbs
     *                                                  use the same category as the URL.
     */
    public function initAddons($permalinkListener = null)
    {
        foreach ($this->getAddons() as $addon) {
            $addon = new $addon($permalinkListener);
            if ($addon->isActive()) {
                $addon->init();
            }
        }
    }
}
