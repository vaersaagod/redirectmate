<?php

namespace vaersaagod\redirectmate\controllers;

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
    
    public function actionGetLogs(): Response
    {
        $this->requireAcceptsJson();
        
        $limit = \Craft::$app->getRequest()->getParam('perPage', 20);
        $page = \Craft::$app->getRequest()->getParam('page', 1);
        $handled = \Craft::$app->getRequest()->getParam('handled', 'all');
        $site = \Craft::$app->getRequest()->getParam('site', 'all');
        $sortBy = \Craft::$app->getRequest()->getParam('sortBy', 'hits');

        $query = TrackerHelper::getQuery();
        
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

    public function actionCheckLogItem(): Response
    {
        $id = \Craft::$app->getRequest()->getRequiredParam('id');

        $query = TrackerHelper::getQuery();
        $query->where('id=:id', ['id' => $id]);
        $item = $query->one();
        
        if (!$item) {
            return $this->asFailure('Could not find log item.', ['id' => $id]);
        }
        
        $trackerModel = new TrackerModel($item);
        
        try {
            $url = UrlHelper::siteUrl($trackerModel->sourceUrl, null, null, $trackerModel->siteId);
        } catch (\Throwable $throwable) {
            return $this->asFailure('An error occured when trying to create URL: ' . $throwable->getMessage());
        }
        
        $statusCode = UrlHelper::getUrlStatusCode($url);
        
        return $this->asSuccess('URL checked', ['id' => $id, 'code' => $statusCode, 'handled' => $statusCode !== 404 ]);
    }
    
    public function actionDeleteLogItems(): Response
    {
        $ids = \Craft::$app->getRequest()->getParam('ids');
        
        if (empty($ids)) {
            return $this->asFailure(\Craft::t('redirectmate', 'No ids to delete.'));
        }
        
        try {
            TrackerHelper::deleteAllByIds($ids);
        } catch (\Throwable $throwable) {
            return $this->asFailure(\Craft::t('redirectmate', 'An error occured: ') . ' ' . $throwable->getMessage());
        }
        
        return $this->asSuccess('Big success');
    }
    
    public function actionDeleteAllLogItems(): Response
    {
        try {
            TrackerHelper::deleteAll();
        } catch (\Throwable $throwable) {
            return $this->asFailure(\Craft::t('redirectmate', 'An error occured: ') . ' ' . $throwable->getMessage());
        }
        
        return $this->asSuccess('Big success');
    }
    
    public function actionGetRedirects(): Response
    {
        $this->requireAcceptsJson();
        
        $limit = \Craft::$app->getRequest()->getParam('perPage', 20);
        $page = \Craft::$app->getRequest()->getParam('page', 1);
        $site = \Craft::$app->getRequest()->getParam('site', 'all');
        $sortBy = \Craft::$app->getRequest()->getParam('sortBy', 'newest');

        $query = RedirectHelper::getQuery();

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
    
    public function actionAddRedirect(): Response
    {
        $data = \Craft::$app->getRequest()->getParam('redirectData');
        
        if (isset($data['id'])) {
            $redirectModel = RedirectHelper::getOrCreateModel($data['id']);
        } else {
            $redirectModel = new RedirectModel();
        }
        
        $redirectModel->siteId = $data['site'] === 'all' ? null : (int)$data['site'];
        $redirectModel->matchBy = $data['matchBy'] === RedirectModel::MATCHBY_FULLURL ? RedirectModel::MATCHBY_FULLURL : RedirectModel::MATCHBY_PATH;
        $redirectModel->sourceUrl = UrlHelper::normalizeUrl($data['sourceUrl']);
        $redirectModel->destinationUrl = UrlHelper::isUrl($data['destinationUrl']) ? $data['destinationUrl'] : UrlHelper::normalizeUrl($data['destinationUrl']);
        $redirectModel->isRegexp = $data['matchAs'] === 'regexp';
        $redirectModel->statusCode = $data['statusCode'];
        
        if (!$redirectModel->validate()) {
            return $this->asFailure(\Craft::t('redirectmate', 'Validation failed'), $redirectModel->getErrors());
        } 
        
        $result = RedirectMate::getInstance()->redirect->addRedirect($redirectModel);
        
        if ($result->hasErrors()) {
            return $this->asFailure(\Craft::t('redirectmate', 'An error occured when saving.'), $result->getErrors());
        }
        
        return $this->asSuccess(\Craft::t('redirectmate', 'Redirect saved.'), $result->getAttributes());
    }
    
    public function actionDeleteRedirects(): Response
    {
        $ids = \Craft::$app->getRequest()->getParam('ids');
        
        if (empty($ids)) {
            return $this->asFailure(\Craft::t('redirectmate', 'No ids to delete.'));
        }
        
        try {
            RedirectHelper::deleteAllByIds($ids);
        } catch (\Throwable $throwable) {
            return $this->asFailure(\Craft::t('redirectmate', 'An error occured:') . ' ' . $throwable->getMessage());
        }
        
        return $this->asSuccess('Big success');
    }
    
}
