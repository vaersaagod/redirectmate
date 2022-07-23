<?php

namespace vaersaagod\redirectmate\helpers;

use Craft;
use vaersaagod\redirectmate\models\RedirectModel;
use yii\caching\TagDependency;

class CacheHelper
{
    public const TAG_REDIRECTMATE = 'redirectmate';
    public const KEY_REDIRECT_PREFIX = 'redirectmate_redirect_';

    /**
     * @throws \JsonException
     */
    public static function getCachedRedirect(string $key): ?RedirectModel
    {
        $cache = Craft::$app->getCache();
        $cachedData = $cache->get(self::KEY_REDIRECT_PREFIX.$key);
        return $cachedData ? new RedirectModel(json_decode(base64_decode($cachedData), true, 512, JSON_THROW_ON_ERROR)) : null;
    }

    /**
     * @throws \JsonException
     */
    public static function setCachedRedirect(string $key, array $data): void
    {
        $cache = Craft::$app->getCache();
        $cache->set(
            self::KEY_REDIRECT_PREFIX.$key, 
            base64_encode(json_encode($data, JSON_THROW_ON_ERROR)), 
            Craft::$app->config->general->cacheDuration,
            new TagDependency(['tags' => self::TAG_REDIRECTMATE])
        );
    }

    public static function invalidateAllCaches(): void
    {
        $cache = Craft::$app->getCache();
        TagDependency::invalidate($cache, self::TAG_REDIRECTMATE);
    }
}
