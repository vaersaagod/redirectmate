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
        // Strip the query string before decoding, so that an encoded question mark in the path isn't mistaken for one.
        // Use rawurldecode, since a plus sign in a path is a literal plus sign, not an encoded space.
        $urlModel->url = $urlModel->parsedUrl = self::normalizeUrl(rawurldecode(self::stripQueryString($request->getAbsoluteUrl())), false);
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
        // Only ever fetch http(s) URLs, and constrain redirects to the same, to avoid this
        // being abused as a server-side request forgery primitive (e.g. file:// or gopher://).
        if (preg_match('#^https?://#i', (string)$url) !== 1) {
            return 0;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
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
        // Replace invalid UTF-8 sequences, since they can't be stored in the database
        if (!mb_check_encoding($pathOrUrl, 'UTF-8')) {
            $substituteCharacter = mb_substitute_character();
            mb_substitute_character(0xFFFD);
            $pathOrUrl = mb_scrub($pathOrUrl, 'UTF-8');
            mb_substitute_character($substituteCharacter);
        }

        // Normalize to NFC, so that composed and decomposed characters (e.g. "é") result in the same string
        $pathOrUrl = \Normalizer::normalize($pathOrUrl, \Normalizer::FORM_C) ?: $pathOrUrl;

        // Strip control characters (null bytes, line breaks etc.), invisible format characters (zero-width spaces, BOMs etc.)
        // and leading or trailing whitespace to avoid index issues
        $pathOrUrl = preg_replace('/[\p{Cc}\p{Cf}]+/u', '', $pathOrUrl) ?? $pathOrUrl;
        $pathOrUrl = preg_replace('/^[\s\p{Z}]+|[\s\p{Z}]+$/u', '', $pathOrUrl) ?? $pathOrUrl;
        
        if ($addTrailingSlashes === null) {
            $addTrailingSlashes = \Craft::$app->getConfig()->getGeneral()->addTrailingSlashesToUrls;
        }
        
        if ($pathOrUrl === '') {
            return $addTrailingSlashes ? '/' : '';
        }

        if ($pathOrUrl === '/') {
            return $pathOrUrl;
        }

        if (self::isUrl($pathOrUrl)) {
            $r = rtrim($pathOrUrl, '/');
        } else {
            $r = FileHelper::normalizePath('/'.ltrim($pathOrUrl, '/'), '/');

            // FileHelper turns ".." segments past the root into a relative path (e.g. "/../foo" => "foo", "/.." => "."),
            // so drop any leftover dot segments and make sure the path is root relative
            $segments = array_filter(explode('/', $r), static fn(string $segment) => !in_array($segment, ['', '.', '..'], true));
            $r = '/'.implode('/', $segments);

            if ($r === '/') {
                return $r;
            }
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
