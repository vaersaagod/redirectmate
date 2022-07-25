<?php

namespace vaersaagod\redirectmate\helpers;

use Craft;
use craft\helpers\Db;
use craft\models\Site;
use craft\web\Request;

use vaersaagod\redirectmate\db\TrackerQuery;
use vaersaagod\redirectmate\models\TrackerModel;
use vaersaagod\redirectmate\RedirectMate;

class TrackerHelper 
{

    /**
     * @param string $sourceUrl
     * @param Site $site
     * @return TrackerModel
     */
    public static function getOrCreateModel(string $sourceUrl, Site $site): TrackerModel
    {

        $existingTrackerModel = TrackerModel::find()
            ->where(['sourceUrl' => $sourceUrl, 'siteId' => $site->id])
            ->one();

        try {
            $lastHit = Db::prepareDateForDb(new \DateTime());
        } catch (\Exception) {
            $lastHit = null;
        }

        $trackerModel = new TrackerModel([
            'sourceUrl' => $sourceUrl, 
            'siteId' => $site->id, 
            'lastHit' => $lastHit,
            'hits' => 0,
        ]);

        if (!$existingTrackerModel) {
            return $trackerModel;
        }
        
        $trackerModel->setAttributes($existingTrackerModel->getAttributes(), false);
        
        return $trackerModel;
    }

    /**
     * @param TrackerModel $trackerModel
     * @param Request $request
     * @return TrackerModel
     */
    public static function populateModelWithRequestData(TrackerModel $trackerModel, Request $request): TrackerModel
    {
        $settings = RedirectMate::getInstance()->getSettings();
        
        if (in_array('ip', $settings->track, true)) {
            $trackerModel->remoteIp = $request->getUserIP();
        }
        
        if (in_array('referrer', $settings->track, true)) {
            $trackerModel->referrer = $request->getReferrer();
        }
        
        if (in_array('useragent', $settings->track, true)) {
            $trackerModel->userAgent = $request->getUserAgent();
        }
        
        return $trackerModel;
    }

    /**
     * @param TrackerModel $trackerModel
     * @return void
     */
    public static function insertOrUpdateTracker(TrackerModel $trackerModel): void
    {

        $db = Craft::$app->getDb();

        $attributes = $trackerModel->getAttributes(null, ['uid', 'dateCreated', 'dateUpdated']);
        if (isset($trackerModel->lastHit)) {
            $attributes['lastHit'] = Db::prepareDateForDb($trackerModel->lastHit);
        }

        if (isset($trackerModel->id)) {
            try {
                $db->createCommand()->update(TrackerQuery::TABLE, $attributes, ['id' => $trackerModel->id])->execute();
            } catch (\Exception) {
                // Do not log, it's ok.
            }
        } else {
            try {
                $db->createCommand()->insert(TrackerQuery::TABLE, $attributes)->execute();
            } catch (\Exception $e) {
                Craft::error($e->getMessage(), __METHOD__);
            }
        }
    }

    /**
     * @param array $ids
     * @return void
     * @throws \yii\db\Exception
     */
    public static function deleteAllByIds(array $ids): void
    {
        if (empty($ids)) {
            return;
        }
        
        $db = Craft::$app->getDb();
        $db->createCommand()->delete(TrackerQuery::TABLE, ['in', 'id', $ids])->execute();
    }

    /**
     * @return void
     * @throws \yii\db\Exception
     */
    public static function deleteAll(): void
    {
        $db = Craft::$app->getDb();
        $db->createCommand()->delete(TrackerQuery::TABLE)->execute();
    }
}
