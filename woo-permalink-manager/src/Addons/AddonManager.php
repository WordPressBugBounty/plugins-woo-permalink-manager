<?php namespace Premmerce\UrlManager\Addons;

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
   */
    public function initAddons()
    {
        foreach ($this->getAddons() as $addon) {
            $addon = new $addon();
            if ($addon->isActive()) {
                $addon->init();
            }
        }
    }
}
