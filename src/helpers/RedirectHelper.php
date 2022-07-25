<?php

namespace vaersaagod\redirectmate\helpers;

use Craft;
use craft\helpers\Db;
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

        $redirect = RedirectModel::find()
            ->orderBy(new Expression('FIELD (sourceUrl, \'' . implode('\',\'', $urlPatterns) . '\')'))
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
                    $redirect->destinationUrl = preg_replace(
                        $pattern,
                        $redirect->destinationUrl,
                        $target,
                    );
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
     *
     * @return RedirectModel
     */
    public static function insertOrUpdateRedirect(RedirectModel $redirectModel): RedirectModel
    {

        $attributes = $redirectModel->getAttributes(null, ['uid', 'dateCreated', 'dateUpdated']);
        if (isset($redirectModel->lastHit)) {
            $attributes['lastHit'] = Db::prepareDateForDb($redirectModel->lastHit);
        }

        $db = Craft::$app->getDb();

        if (isset($redirectModel->id)) {
            try {
                $db->createCommand()->update(RedirectQuery::TABLE, $attributes, ['id' => $redirectModel->id])->execute();
            } catch (Exception $e) {
                // Do not log, it's ok.
                $redirectModel->addError('*', $e->getMessage());
            }
        } else {
            try {
                $db->createCommand()->insert(RedirectQuery::TABLE, $attributes)->execute();
            } catch (Exception $e) {
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

}
