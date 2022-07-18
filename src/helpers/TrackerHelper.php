<?php

namespace vaersaagod\redirectmate\helpers;

use Craft;
use craft\db\Query;
use craft\helpers\Db;
use craft\models\Site;
use craft\web\Request;
use vaersaagod\redirectmate\models\TrackerModel;
use vaersaagod\redirectmate\RedirectMate;
use yii\db\Exception;

class TrackerHelper 
{

    /**
     * @return Query
     */
    public static function getQuery(): Query
    {
        return (new Query())
            ->from(['{{%redirectmate_tracker}}']);
    }
    
    /**
     * @param string $sourceUrl
     * @param Site $site
     * @return TrackerModel
     */
    public static function getOrCreateModel(string $sourceUrl, Site $site): TrackerModel
    {
        try {
            $lastHit = Db::prepareDateForDb(new \DateTime());
        } catch (\Exception $e) {
            $lastHit = null;
        }
        
        $trackerModel = new TrackerModel([
            'sourceUrl' => $sourceUrl, 
            'siteId' => $site->id, 
            'hits' => 0,
            'lastHit' => $lastHit
        ]);
        
        $existingData = (new Query())
            ->from(['{{%redirectmate_tracker}}'])
            ->where(['sourceUrl' => $sourceUrl, 'siteId' => $site->id])
            ->one();
        
        if ($existingData === null) {
            return $trackerModel;
        }
        
        $trackerModel->setAttributes($existingData, false);
        
        return $trackerModel;
    }

    /**
     * @param TrackerModel $trackerModel
     * @param Request $request
     * @return TrackerModel
     */
    public static function populateModelWithRequestData(TrackerModel $trackerModel, Request $request): TrackerModel
    {
        $settings = RedirectMate::$plugin->getSettings();
        
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
     * @param array $data
     */
    public static function insertOrUpdateData(array $data): void
    {
        $db = Craft::$app->getDb();

        unset($data['uid'], $data['dateCreated'], $data['dateUpdated']);

        if ($data['id'] !== null) {
            try {
                $db->createCommand()->update('{{%redirectmate_tracker}}', $data, ['id' => $data['id']])->execute();
            } catch (Exception $e) {
                // Do not log, it's ok.
            }
        } else {
            try {
                $db->createCommand()->insert('{{%redirectmate_tracker}}', $data)->execute();
            } catch (Exception $e) {
                Craft::error($e->getMessage(), __METHOD__);
            }
        }
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
        $db->createCommand()->delete('{{%redirectmate_tracker}}', ['in', 'id', $ids])->execute();
    }
    
    /**
     * @throws Exception
     */
    public static function deleteAll(): void
    {
        $db = Craft::$app->getDb();
        $db->createCommand()->delete('{{%redirectmate_tracker}}')->execute();
    }
}
