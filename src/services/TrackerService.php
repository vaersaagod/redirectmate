<?php

namespace vaersaagod\redirectmate\services;

use Craft;
use craft\base\Component;
use craft\db\Query;
use vaersaagod\redirectmate\helpers\RedirectHelper;
use vaersaagod\redirectmate\helpers\TrackerHelper;
use vaersaagod\redirectmate\helpers\UrlHelper;
use vaersaagod\redirectmate\RedirectMate;

/**
 * TrackerService Service
 *
 * @author    Værsågod
 * @package   RedirectMate
 * @since     1.0.0
 *
 * @property-read Query $query
 */
class TrackerService extends Component
{

    /**
     * @param null $request
     */
    public function handleRequest($request = null): void 
    {
        $request = $request ?? Craft::$app->getRequest();
        
        try {
            $currentSite = Craft::$app->getSites()->getCurrentSite();
        } catch (\Throwable $e) {
            Craft::error($e->getMessage(), __METHOD__);
            return;
        }
        
        // Get the parsed url, this will be the sourceUrl that we track and search for
        $parsedUrl = UrlHelper::parseRequestUrl($request);
        
        // Get the tracking data
        $trackerModel = TrackerHelper::getOrCreateModel($parsedUrl->parsedPath, $currentSite);

        // If this is not just an internal test, track it!
        if ($request->getUserAgent() !== UrlHelper::REDIRECTMATE_BOT_USER_AGENT) {
            $trackerModel = TrackerHelper::populateModelWithRequestData($trackerModel, $request);
            ++$trackerModel->hits;
        }
        
        // Get redirect
        $redirect = RedirectHelper::getRedirectForUrlAndSite($parsedUrl, $currentSite);
        
        if ($redirect === null) {
            $trackerModel->handled = false;
            TrackerHelper::insertOrUpdateData($trackerModel->getAttributes());
            return; 
        }
        
        $trackerModel->handled = true;
        TrackerHelper::insertOrUpdateData($trackerModel->getAttributes());
        
        RedirectMate::getInstance()->redirect->doRedirect($redirect);
    }

}
