<?php

namespace vaersaagod\redirectmate\utilities;

use Craft;
use craft\base\Utility;
use vaersaagod\redirectmate\assetbundles\RedirectMateAssets;
use vaersaagod\redirectmate\RedirectMate;
use yii\base\InvalidConfigException;

/**
 * @author    Værsågod
 * @package   GeoMate
 * @since     1.0.0
 */
class RedirectMateUtility extends Utility
{
    // Static
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        $settings = RedirectMate::getInstance()?->getSettings();
        
        return Craft::t('redirectmate', $settings->pluginName ?? 'RedirectMate');
    }

    /**
     * @inheritdoc
     */
    public static function id(): string
    {
        return 'redirectmate-utility';
    }

    /**
     * @inheritdoc
     */
    public static function iconPath(): ?string
    {
        return Craft::getAlias('@vaersaagod/redirectmate/icon-mask.svg');
    }

    /**
     * @inheritdoc
     */
    public static function badgeCount(): int
    {
        return 0;
    }

    /**
     * @inheritdoc
     */
    public static function contentHtml(): string
    {
        $settings = RedirectMate::$plugin->getSettings();
        
        try {
            Craft::$app->getView()->registerAssetBundle(RedirectMateAssets::class);
        } catch (InvalidConfigException) {
            return Craft::t('redirectmate', 'Could not load asset bundle');
        }
        
        return Craft::$app->getView()->renderTemplate(
            'redirectmate/utility/_render',
            [
                'settings' => $settings,
            ]
        );
    }
}
