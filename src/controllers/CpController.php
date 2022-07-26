<?php

namespace vaersaagod\redirectmate\controllers;

use Craft;
use craft\web\Controller;

use vaersaagod\redirectmate\helpers\RedirectHelper;
use vaersaagod\redirectmate\helpers\TrackerHelper;
use vaersaagod\redirectmate\helpers\UrlHelper;
use vaersaagod\redirectmate\models\RedirectModel;
use vaersaagod\redirectmate\models\TrackerModel;
use vaersaagod\redirectmate\RedirectMate;

use yii\web\Response;


class CpController extends Controller
{
    // Protected Properties
    // =========================================================================
    protected int|bool|array $allowAnonymous = false;


    // Public Methods
    // =========================================================================

    /**
     * @return Response
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionGetLogs(): Response
    {
        $this->requireAcceptsJson();
        
        $limit = Craft::$app->getRequest()->getParam('perPage', 20);
        $page = Craft::$app->getRequest()->getParam('page', 1);
        $handled = Craft::$app->getRequest()->getParam('handled', 'all');
        $site = Craft::$app->getRequest()->getParam('site', 'all');
        $sortBy = Craft::$app->getRequest()->getParam('sortBy', 'hits');

        $query = TrackerModel::find();
        
        if ($handled !== 'all') {
            $query->andWhere('handled = ' . ($handled === 'handled' ? '1' : '0'));
        }
        
        if ($site !== 'all') {
            $query->andWhere('siteId = :site', ['site' => $site]);
        }
        
        $totalCount = $query->count();
        
        switch ($sortBy) {
            case 'lasthit':
                $query->orderBy('lastHit DESC');
                break;
            default:
                $query->orderBy('hits DESC');
                break;
        }

        $query->limit($limit);
        $query->offset($limit * ($page-1));

        return $this->asSuccess('Big success', [
            'count' => $totalCount,
            'data' => $query->all()
        ]);
    }

    /**
     * @return Response
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionCheckLogItem(): Response
    {
        $id = Craft::$app->getRequest()->getRequiredParam('id');

        $query = TrackerModel::find()
            ->where('id=:id', ['id' => $id]);

        if (!$trackerModel = $query->one()) {
            return $this->asFailure(Craft::t('redirectmate', 'Could not find log item.'), ['id' => $id]);
        }

        try {
            $url = UrlHelper::siteUrl($trackerModel->sourceUrl, null, null, $trackerModel->siteId);
        } catch (\Throwable $throwable) {
            Craft::error($throwable->getMessage(), __METHOD__);
            return $this->asFailure(Craft::t('redirectmate', 'An error occurred when trying to create URL.'));
        }

        $statusCode = UrlHelper::getUrlStatusCode($url);

        return $this->asSuccess(Craft::t('redirectmate', 'URL checked'), ['id' => $id, 'code' => $statusCode, 'handled' => $statusCode !== 404 ]);
    }

    /**
     * @return Response
     */
    public function actionDeleteLogItems(): Response
    {
        $ids = Craft::$app->getRequest()->getParam('ids');
        
        if (empty($ids)) {
            return $this->asFailure(Craft::t('redirectmate', 'No log items to delete.'));
        }
        
        try {
            TrackerHelper::deleteAllByIds($ids);
        } catch (\Throwable $throwable) {
            Craft::error($throwable->getMessage(), __METHOD__);
            return $this->asFailure(Craft::t('redirectmate', 'An error occurred.'));
        }
        
        return $this->asSuccess('Big success');
    }

    /**
     * @return Response
     */
    public function actionDeleteAllLogItems(): Response
    {
        try {
            TrackerHelper::deleteAll();
        } catch (\Throwable $throwable) {
            Craft::error($throwable->getMessage(), __METHOD__);
            return $this->asFailure(Craft::t('redirectmate', 'An error occurred.'));
        }
        
        return $this->asSuccess('Big success');
    }

    /**
     * @return Response
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionGetRedirects(): Response
    {
        $this->requireAcceptsJson();
        
        $limit = Craft::$app->getRequest()->getParam('perPage', 20);
        $page = Craft::$app->getRequest()->getParam('page', 1);
        $site = Craft::$app->getRequest()->getParam('site', 'all');
        $sortBy = Craft::$app->getRequest()->getParam('sortBy', 'newest');

        $query = RedirectModel::find();

        if ($site !== 'all') {
            $query->andWhere('siteId = :site', ['site' => $site]);
        }
        
        $totalCount = $query->count();
        
        switch ($sortBy) {
            case 'lasthit':
                $query->orderBy('lastHit DESC');
                break;
            case 'hits':
                $query->orderBy('hits DESC');
                break;
            default:
                $query->orderBy('dateCreated DESC');
                break;
        }
        
        $query->limit($limit);
        $query->offset($limit * ($page-1));

        return $this->asSuccess('Big success', [
            'count' => $totalCount,
            'data' => $query->all()
        ]);
    }

    /**
     * @return Response
     */
    public function actionAddRedirect(): Response
    {
        $data = Craft::$app->getRequest()->getParam('redirectData');
        
        if (isset($data['id'])) {
            $redirectModel = RedirectHelper::getOrCreateModel($data['id']);
        } else {
            $redirectModel = new RedirectModel();
        }
        
        $sourceUrl = $data['sourceUrl'];
        $destinationUrl = $data['destinationUrl'];
        
        $redirectModel->siteId = $data['site'] === 'all' ? null : (int)$data['site'];
        $redirectModel->matchBy = $data['matchBy'] === RedirectModel::MATCHBY_FULLURL ? RedirectModel::MATCHBY_FULLURL : RedirectModel::MATCHBY_PATH;
        $redirectModel->sourceUrl = $data['matchAs'] !== 'regexp' ? UrlHelper::normalizeUrl($sourceUrl) : $sourceUrl;
        $redirectModel->destinationUrl = UrlHelper::isUrl($destinationUrl) ? $destinationUrl : UrlHelper::normalizeUrl($destinationUrl);
        $redirectModel->isRegexp = $data['matchAs'] === 'regexp';
        $redirectModel->statusCode = $data['statusCode'];
        
        if (!$redirectModel->validate()) {
            return $this->asFailure(Craft::t('redirectmate', 'Redirect validation failed.'), $redirectModel->getErrors());
        } 
        
        $result = RedirectMate::getInstance()->redirect->addRedirect($redirectModel);
        
        if ($result->hasErrors()) {
            return $this->asFailure(Craft::t('redirectmate', 'An error occurred when saving.'), $result->getErrors());
        }

        // If we've a tracker, check it
        $trackerId = $data['logId'] ?? null;
        if ($trackerId) {
            $tracker = TrackerModel::find()
                ->where('id=:id', ['id' => $trackerId])
                ->one();
            if ($tracker) {
                try {
                    if ($url = UrlHelper::siteUrl($tracker->sourceUrl, null, null, $tracker->siteId)) {
                        $handled = UrlHelper::getUrlStatusCode($url) !== 404;
                        if ($handled !== $tracker->handled) {
                            $tracker->handled = $handled;
                            TrackerHelper::insertOrUpdateTracker($tracker);
                        }
                    }
                } catch (\Throwable) {
                    // It's ok
                }
            }
        }
        
        return $this->asSuccess(Craft::t('redirectmate', 'Redirect saved.'), $result->getAttributes());
    }

    /**
     * @return Response
     */
    public function actionDeleteRedirects(): Response
    {
        $ids = Craft::$app->getRequest()->getParam('ids');
        
        if (empty($ids)) {
            return $this->asFailure(Craft::t('redirectmate', 'No redirects to delete.'));
        }
        
        try {
            RedirectHelper::deleteAllByIds($ids);
        } catch (\Throwable $throwable) {
            Craft::error($throwable->getMessage(), __METHOD__);
            return $this->asFailure(Craft::t('redirectmate', 'An error occurred.'));
        }
        
        return $this->asSuccess('Big success');
    }
    
}
