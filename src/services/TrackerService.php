<?php

namespace vaersaagod\redirectmate\services;

use Craft;
use craft\base\Component;
use craft\db\Query;

use vaersaagod\redirectmate\helpers\RedirectHelper;
use vaersaagod\redirectmate\helpers\TrackerHelper;
use vaersaagod\redirectmate\helpers\UrlHelper;
use vaersaagod\redirectmate\models\ParsedUrlModel;
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
     * @param $request
     * @return void
     * @throws \craft\errors\SiteNotFoundException
     * @throws \yii\base\Exception
     * @throws \yii\base\InvalidConfigException
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

        // Check if this path is excluded
        if ($this->shouldExclude($parsedUrl, $currentSite->handle)) {
            return;
        }

        // Get the tracking data
        $trackerModel = TrackerHelper::getOrCreateModel($parsedUrl->parsedPath, $currentSite);

        // If this is not just an internal test, track it!
        if ($request->getUserAgent() !== UrlHelper::REDIRECTMATE_BOT_USER_AGENT) {
            if (!str_contains($request->getReferrer() ?? '', 'redirectmate-utility')) {
                $trackerModel = TrackerHelper::populateModelWithRequestData($trackerModel, $request);
                ++$trackerModel->hits;
                $trackerModel->lastHit = new \DateTime();
            }
        }

        // Get redirect
        try {
            $redirect = RedirectHelper::getRedirectForUrlAndSite($parsedUrl, $currentSite);
        } catch (\Throwable $throwable) {
            Craft::error('An error occurred when trying to get redirect: ' . $throwable->getMessage(), __METHOD__);
            $redirect = null;
        }

        if ($redirect === null) {
            $trackerModel->handled = false;
            TrackerHelper::insertOrUpdateTracker($trackerModel);
            return;
        }

        $trackerModel->handled = true;
        TrackerHelper::insertOrUpdateTracker($trackerModel);
        RedirectMate::getInstance()->redirect->doRedirect($redirect);
    }

    private function shouldExclude(ParsedUrlModel $parsedUrlModel, string $siteHandle): bool
    {
        $excludePatterns = RedirectMate::getInstance()?->getSettings()->getParsedExcludeUrlPatterns($siteHandle);

        if (!empty($excludePatterns)) {
            foreach ($excludePatterns as $excludePattern) {
                $pattern = '`'.$excludePattern.'`i';

                try {
                    if (preg_match($pattern, $parsedUrlModel->parsedPath) === 1) {
                        return true;
                    }
                } catch (\Throwable $throwable) {
                    Craft::error('An error occured when trying to match patern "'.$pattern.'": '.$throwable->getMessage(), __METHOD__);
                }
            }
        }

        return false;
    }
}
