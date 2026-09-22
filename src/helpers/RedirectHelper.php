<?php

namespace vaersaagod\redirectmate\helpers;

use Craft;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use craft\models\Site;

use vaersaagod\redirectmate\db\RedirectQuery;
use vaersaagod\redirectmate\models\ParsedUrlModel;
use vaersaagod\redirectmate\models\RedirectModel;
use vaersaagod\redirectmate\RedirectMate;

use yii\db\Exception;
use yii\db\Expression;

class RedirectHelper
{

    /**
     * @param string|int $id
     * @return RedirectModel
     */
    public static function getOrCreateModel(string|int $id): RedirectModel
    {
        return RedirectModel::find()
            ->where(['id' => $id])
            ->one() ?? new RedirectModel();
    }

    /**
     * @param ParsedUrlModel $parsedUrlModel
     * @param Site $site
     * @return RedirectModel|null
     * @throws \JsonException
     */
    public static function getRedirectForUrlAndSite(ParsedUrlModel $parsedUrlModel, Site $site): ?RedirectModel
    {
        $cacheAttributes = $parsedUrlModel->getAttributes();

        $cacheKey = md5(json_encode($cacheAttributes, JSON_THROW_ON_ERROR));

        if (RedirectMate::getInstance()?->getSettings()->cacheEnabled) {
            try {
                $cachedRedirect = CacheHelper::getCachedRedirect($cacheKey);

                if ($cachedRedirect) {
                    return $cachedRedirect;
                }
            } catch (\Throwable $throwable) {
                Craft::error('An error occurred when trying to get cached redirect' . $throwable->getMessage(), __METHOD__);
            }
        }

        // Match exact match redirects
        $urlPatterns = [
            $parsedUrlModel->parsedUrl,
            $parsedUrlModel->url . '?' . $parsedUrlModel->queryString,
            $parsedUrlModel->url,
            $parsedUrlModel->parsedPath,
            $parsedUrlModel->path . '?' . $parsedUrlModel->queryString,
            $parsedUrlModel->path
        ];

        // Bind the URL patterns as query params rather than concatenating them into the
        // ORDER BY expression – $urlPatterns is derived from the (attacker-controlled) request
        // URL, so raw interpolation here is a SQL injection vector.
        $fieldPlaceholders = [];
        $fieldParams = [];

        foreach (array_values($urlPatterns) as $i => $urlPattern) {
            $placeholder = ":rmField$i";
            $fieldPlaceholders[] = $placeholder;
            $fieldParams[$placeholder] = $urlPattern;
        }

        $redirect = RedirectModel::find()
            ->orderBy(new Expression('FIELD(sourceUrl, ' . implode(', ', $fieldPlaceholders) . ')', $fieldParams))
            ->where([
                'or', [
                    'siteId' => $site->id,
                ], [
                    'siteId' => null,
                ]
            ])
            ->andWhere(['sourceUrl' => $urlPatterns])
            ->andWhere(['isRegexp' => false])
            ->one();

        // Skip redirects that would redirect the request to itself
        if ($redirect && self::_isExactRedirectLoop($redirect, $parsedUrlModel)) {
            Craft::warning('Skipped redirect ' . $redirect->id . ' since it would redirect "' . $parsedUrlModel->url . '" to itself', __METHOD__);
            $redirect = null;
        }

        if ($redirect) {
            CacheHelper::setCachedRedirect($cacheKey, $redirect->getAttributes());
            return $redirect;
        }

        // Match regexp redirects
        $redirects = RedirectModel::find()
            ->orderBy('dateCreated DESC')
            ->where([
                'or', [
                    'siteId' => $site->id,
                ], [
                    'siteId' => null,
                ]
            ])
            ->andWhere(['isRegexp' => true])
            ->all();

        foreach ($redirects as $redirect) {
            if ($redirect->matchBy === RedirectModel::MATCHBY_PATH) {
                $target = $parsedUrlModel->parsedPath;
            } else {
                $target = $parsedUrlModel->parsedUrl;
            }

            $pattern = '`' . $redirect->sourceUrl . '`i';

            try {
                if (preg_match($pattern, $target) === 1) {
                    $destinationUrl = preg_replace(
                        $pattern,
                        $redirect->destinationUrl,
                        $target,
                    );

                    // Skip redirects that would send the request into a redirect loop
                    if (self::_isRegexpRedirectLoop($pattern, $redirect->destinationUrl, $target, $destinationUrl)) {
                        Craft::warning('Skipped redirect ' . $redirect->id . ' since it would redirect "' . $target . '" into a redirect loop', __METHOD__);
                        continue;
                    }

                    $redirect->destinationUrl = $destinationUrl;
                    CacheHelper::setCachedRedirect($cacheKey, $redirect->getAttributes());
                    return $redirect;
                }
            } catch (\Throwable $throwable) {
                Craft::error('Error in regexp "' . $pattern . '": ' . $throwable->getMessage(), __METHOD__);
            }
        }

        return null;
    }

    /**
     * @param RedirectModel $redirect
     */
    public static function updateRedirectStats(RedirectModel $redirect): void
    {
        $db = Craft::$app->getDb();

        try {
            $lastHit = Db::prepareDateForDb(new \DateTime());
        } catch (\Exception $e) {
            $lastHit = null;
        }

        try {
            $db->createCommand()->update(RedirectQuery::TABLE, ['hits' => new Expression('hits + 1'), 'lastHit' => $lastHit], ['id' => $redirect->id])->execute();
        } catch (Exception $e) {
            // Do not log, it's ok.
        }
    }

    /**
     * @param RedirectModel $redirectModel
     * @return RedirectModel
     * @throws \Exception
     */
    public static function insertOrUpdateRedirect(RedirectModel $redirectModel): RedirectModel
    {

        $attributes = $redirectModel->getAttributes(null, ['uid', 'dateCreated', 'dateUpdated']);

        if (isset($redirectModel->lastHit)) {
            $attributes['lastHit'] = Db::prepareDateForDb($redirectModel->lastHit);
        }

        if (isset($redirectModel->dateCreated)) {
            $attributes['dateCreated'] = Db::prepareDateForDb($redirectModel->dateCreated);
        }

        $db = Craft::$app->getDb();
        $isNew = !isset($redirectModel->id);

        if (!$isNew) {
            try {
                $db->createCommand()->update(RedirectQuery::TABLE, $attributes, ['id' => $redirectModel->id])->execute();
            } catch (\Throwable $e) {
                // Do not log, it's ok.
                $redirectModel->addError('*', $e->getMessage());
            }
        } else {
            // Give it a UID right away
            if (!$redirectModel->uid) {
                $redirectModel->uid = $attributes['uid'] = StringHelper::UUID();
            }
            try {
                $db->createCommand()->insert(RedirectQuery::TABLE, $attributes)->execute();
                $redirectModel->id = $db->getLastInsertID();
            } catch (\Throwable $e) {
                Craft::error($e->getMessage(), __METHOD__);
                $redirectModel->addError('*', $e->getMessage());
            }
        }

        return $redirectModel;
    }

    /**
     * @param array $ids
     *
     * @throws Exception
     */
    public static function deleteAllByIds(array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        $db = Craft::$app->getDb();
        $db->createCommand()->delete(RedirectQuery::TABLE, ['in', 'id', $ids])->execute();
    }

    /**
     * Checks if an exact match redirect would redirect the request to itself.
     *
     * @param RedirectModel $redirect
     * @param ParsedUrlModel $parsedUrlModel
     * @return bool
     */
    private static function _isExactRedirectLoop(RedirectModel $redirect, ParsedUrlModel $parsedUrlModel): bool
    {
        $destinationUrl = UrlHelper::normalizeUrl(urldecode($redirect->destinationUrl ?? ''), false);

        if (UrlHelper::isUrl($destinationUrl)) {
            return in_array($destinationUrl, [$parsedUrlModel->url, $parsedUrlModel->url . '?' . $parsedUrlModel->queryString], true);
        }

        return in_array($destinationUrl, [$parsedUrlModel->path, $parsedUrlModel->path . '?' . $parsedUrlModel->queryString], true);
    }

    /**
     * Checks if a regexp redirect would send the request into a redirect loop.
     *
     * Regexp redirects replace the matched part of the target, so a destination can be matched by
     * the same redirect again, e.g. `/foo` => `/bar/foo` turns `/bar/foo` into `/bar/bar/foo`. Since
     * redirects only kick in on 404s, that's only a problem if the destination doesn't exist, which
     * we can't know. But if the target already looks like the output of the redirect, the request
     * has most likely been through it already, and the destination will 404 just like the target.
     *
     * @param string $pattern
     * @param string $replacement
     * @param string $target
     * @param string $destinationUrl
     * @return bool
     */
    private static function _isRegexpRedirectLoop(string $pattern, string $replacement, string $target, string $destinationUrl): bool
    {
        if (preg_match($pattern, $destinationUrl) !== 1) {
            return false;
        }

        // Turn the replacement into a pattern where the backreferences ($1, ${1} or \1) match anything
        $literals = preg_split('/\$\{?\d+\}?|\\\\\d+/', $replacement);
        $outputPattern = '`' . implode('.*', array_map(static fn(string $literal) => preg_quote($literal, '`'), $literals)) . '`i';

        return preg_match($outputPattern, $target) === 1;
    }

}
