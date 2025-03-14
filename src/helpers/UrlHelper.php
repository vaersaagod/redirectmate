<?php

namespace vaersaagod\redirectmate\helpers;

use craft\helpers\FileHelper;
use craft\helpers\UrlHelper as CraftUrlHelper;
use craft\web\Request;

use vaersaagod\redirectmate\models\ParsedUrlModel;
use vaersaagod\redirectmate\RedirectMate;

class UrlHelper extends CraftUrlHelper
{
    public const REDIRECTMATE_BOT_USER_AGENT = 'RedirectMate';

    /**
     * Parses the requested URL based on settings.
     *
     * @param Request $request
     *
     * @return ParsedUrlModel
     * @throws \yii\base\InvalidConfigException
     */
    public static function parseRequestUrl(Request $request): ParsedUrlModel
    {
        $settings = RedirectMate::getInstance()->getSettings();

        $urlModel = new ParsedUrlModel();
        $urlModel->url = $urlModel->parsedUrl = self::normalizeUrl(self::stripQueryString(urldecode($request->getAbsoluteUrl())), false);
        $urlModel->path = $urlModel->parsedPath = self::normalizeUrl($request->getPathInfo(), false);
        $urlModel->queryString = urldecode($request->getQueryStringWithoutPath());

        $queryStringParams = $request->getQueryParams();
        unset($queryStringParams['p']);

        if ($settings->trackQueryString === true && count($queryStringParams) > 0) {
            ksort($queryStringParams);
            $queryString = urldecode(http_build_query($queryStringParams));
        }

        if (is_array($settings->trackQueryString) && count($queryStringParams) > 0 && count($settings->trackQueryString) > 0) {
            $filteredParams = array_filter($queryStringParams, static function($k) use (&$settings) {
                return in_array($k, $settings->trackQueryString, true);
            }, ARRAY_FILTER_USE_KEY);

            ksort($filteredParams);
            $queryString = urldecode(http_build_query($filteredParams));
        }

        if (!empty($queryString)) {
            $urlModel->parsedPath = $urlModel->path.'?'.$queryString;
            $urlModel->parsedUrl = $urlModel->url.'?'.$queryString;
        }

        return $urlModel;
    }

    public static function getUrlStatusCode($url): int
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, self::REDIRECTMATE_BOT_USER_AGENT);
        $output = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpcode;
    }

    /**
     * Overrides the internal normalizePath
     *
     * @param string    $pathOrUrl
     * @param bool|null $addTrailingSlashes
     *
     * @return string
     */
    public static function normalizeUrl(string $pathOrUrl, ?bool $addTrailingSlashes = null): string
    {
        if ($addTrailingSlashes === null) {
            $addTrailingSlashes = \Craft::$app->getConfig()->getGeneral()->addTrailingSlashesToUrls;
        }
        
        if ($pathOrUrl === '/') {
            return $pathOrUrl;
        }

        if (self::isUrl($pathOrUrl)) {
            $r = rtrim($pathOrUrl, '/');
        } else {
            $r = FileHelper::normalizePath('/'.ltrim($pathOrUrl, '/'), '/');
        }

        return $r.($addTrailingSlashes ? '/' : '');
    }

    /**
     * @param string $url
     *
     * @return bool
     */
    public static function isUrl(string $url): bool
    {
        return self::isAbsoluteUrl($url) || self::isProtocolRelativeUrl($url);
    }

    /**
     * @param string $url
     *
     * @return string
     */
    public static function sanitizeUrl(string $url): string
    {
        // HTML decode and strip out any tags
        $url = html_entity_decode($url, ENT_NOQUOTES, 'UTF-8');
        $url = urldecode($url);
        $url = strip_tags($url);
        
        $url = preg_replace('/{.*}/', '', $url); // Remove twig
        $url = (string)str_replace([PHP_EOL,"\r","\n",], '', $url); // Remove any linebreaks

        return $url;
    }
}
