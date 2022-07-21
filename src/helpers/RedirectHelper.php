<?php

namespace vaersaagod\redirectmate\helpers;

use Craft;
use craft\db\Query;
use craft\helpers\Db;
use craft\models\Site;
use vaersaagod\redirectmate\models\ParsedUrlModel;
use vaersaagod\redirectmate\models\RedirectModel;
use yii\db\Exception;
use yii\db\Expression;

class RedirectHelper
{

    /**
     * @return Query
     */
    public static function getQuery(): Query
    {
        return (new Query())
            ->from(['{{%redirectmate_redirects}}']);
    }

    public static function getOrCreateModel($id): RedirectModel
    {
        $existingData = (new Query())
            ->from(['{{%redirectmate_redirects}}'])
            ->where(['id' => $id])
            ->one();

        if ($existingData) {
            $model = new RedirectModel($existingData);
        } else {
            $model = new RedirectModel();
        }

        return $model;
    }

    /**
     * @param ParsedUrlModel $parsedUrlModel
     * @param Site           $site
     *
     * @return null|RedirectModel
     */
    public static function getRedirectForUrlAndSite(ParsedUrlModel $parsedUrlModel, Site $site): ?RedirectModel
    {
        $urlPatterns = [
            $parsedUrlModel->parsedUrl,
            $parsedUrlModel->url.'?'.$parsedUrlModel->queryString,
            $parsedUrlModel->url,
            $parsedUrlModel->parsedPath,
            $parsedUrlModel->path.'?'.$parsedUrlModel->queryString,
            $parsedUrlModel->path
        ];

        $query = (new Query())
            ->from(['{{%redirectmate_redirects}}'])
            ->orderBy(new Expression('FIELD (sourceUrl, \''.implode('\',\'', $urlPatterns).'\')'))
            ->where(['siteId' => $site->id])
            ->orWhere(['siteId' => null])
            ->andWhere(['sourceUrl' => $urlPatterns])
            ->andWhere(['isRegexp' => false]);

        $redirectData = $query->one();

        if ($redirectData !== null) {
            return new RedirectModel($redirectData);
        }

        $query = (new Query())
            ->from(['{{%redirectmate_redirects}}'])
            ->orderBy('dateCreated DESC')
            ->where(['siteId' => $site->id])
            ->orWhere(['siteId' => null])
            ->andWhere(['isRegexp' => true]);

        $reqexpRecords = $query->all();

        foreach ($reqexpRecords as $reqexpRecord) {
            $redirectModel = new RedirectModel($reqexpRecord);

            if ($redirectModel->matchBy === RedirectModel::MATCHBY_PATH) {
                $target = $parsedUrlModel->parsedPath;
            } else {
                $target = $parsedUrlModel->parsedUrl;
            }
            
            $pattern = '`'.$redirectModel->sourceUrl.'`i';

            try {
                if (preg_match($pattern, $target) === 1) {
                    $redirectModel->destinationUrl = preg_replace(
                        $pattern,
                        $redirectModel->destinationUrl,
                        $target,
                    );

                    return $redirectModel;
                }
            } catch (\Throwable $throwable) {
                Craft::error('Error in regexp "'.$matchRegEx.'": '.$throwable->getMessage(), __METHOD__);
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
            $db->createCommand()->update('{{%redirectmate_redirects}}', ['hits' => $redirect->hits + 1, 'lastHit' => $lastHit], ['id' => $redirect->id])->execute();
        } catch (Exception $e) {
            // Do not log, it's ok.
        }
    }

    /**
     * @param RedirectModel $model
     *
     * @return RedirectModel
     */
    public static function insertOrUpdateData(RedirectModel $model): RedirectModel
    {
        $data = $model->getAttributes();

        $db = Craft::$app->getDb();

        unset($data['uid'], $data['dateCreated'], $data['dateUpdated']);

        if ($data['id'] !== null) {
            try {
                $db->createCommand()->update('{{%redirectmate_redirects}}', $data, ['id' => $data['id']])->execute();
            } catch (Exception $e) {
                // Do not log, it's ok.
                $model->addError('*', $e->getMessage());
            }
        } else {
            try {
                $db->createCommand()->insert('{{%redirectmate_redirects}}', $data)->execute();
            } catch (Exception $e) {
                Craft::error($e->getMessage(), __METHOD__);
                $model->addError('*', $e->getMessage());
            }
        }

        return $model;
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
        $db->createCommand()->delete('{{%redirectmate_redirects}}', ['in', 'id', $ids])->execute();
    }

}
