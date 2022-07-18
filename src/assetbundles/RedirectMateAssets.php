<?php

namespace vaersaagod\redirectmate\assetbundles;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class RedirectMateAssets extends AssetBundle
{
    /**
     * @inheritDoc
     */
    public function init(): void
    {
        // define the path that your publishable resources live
        $this->sourcePath = '@vaersaagod/redirectmate/assetbundles/dist';

        // define the dependencies
        $this->depends = [
            CpAsset::class,
        ];

        // define the relative path to CSS/JS files that should be registered with the page
        // when this asset bundle is registered
        $this->js = [
            'redirectmate.js',
        ];

        $this->css = [
            'redirectmate_tailwind.css',
            'redirectmate.css',
        ];

        parent::init();
    }

}
