<?php

namespace vaersaagod\redirectmate\services;

use Craft;
use craft\base\Component;

use vaersaagod\redirectmate\helpers\CacheHelper;
use vaersaagod\redirectmate\helpers\RedirectHelper;
use vaersaagod\redirectmate\helpers\UrlHelper;
use vaersaagod\redirectmate\models\RedirectModel;
use vaersaagod\redirectmate\RedirectMate;

/**
 * RedirectService Service
 *
 * @author    Værsågod
 * @package   RedirectMate
 * @since     1.0.0
 */
class RedirectService extends Component
{

    /**
     * @param RedirectModel $redirect
     * @return void
     * @throws \craft\errors\SiteNotFoundException
     * @throws \yii\base\Exception
     */
    public function doRedirect(RedirectModel $redirect): void
    {

        $response = Craft::$app->getResponse();
        $statusCode = $redirect->statusCode;

        RedirectHelper::updateRedirectStats($redirect);

        // If we have a status code above 400, trigger an exception and let Craft handle it.
        if ($statusCode >= 400) {
            RedirectMate::$currentException->statusCode = $statusCode;
            $errorHandler = Craft::$app->getErrorHandler();
            $errorHandler->exception = RedirectMate::$currentException;

            try {
                $response = Craft::$app->runAction('templates/render-error');
            } catch (\Throwable $e) {
                Craft::error($e->getMessage(), __METHOD__);
            }
        }

        // Do final parsing of destination URL
        $siteId = $redirect->siteId ?? Craft::$app->getSites()->getCurrentSite()->siteId ?? null;
        $destinationUrl = $redirect->destinationUrl ?? '';

        if (RedirectMate::getInstance()?->getSettings()->queryStringPassthrough && !empty(Craft::$app->getRequest()->getQueryString())) {
            $destinationUrl = UrlHelper::siteUrl($destinationUrl, Craft::$app->getRequest()->getQueryString(), null, $siteId);
        } else {
            $destinationUrl = UrlHelper::siteUrl($destinationUrl, null, null, $siteId);
        }

        // Redirect
        $response->redirect(UrlHelper::sanitizeUrl($destinationUrl), $redirect->statusCode)->send();

        try {
            Craft::$app->end();
        } catch (\Throwable $e) {
            Craft::error($e->getMessage(), __METHOD__);
        }
    }

    /**
     * @param RedirectModel $redirectModel
     * @return RedirectModel
     * @throws \Exception
     */
    public function addRedirect(RedirectModel $redirectModel): RedirectModel
    {
        // Normalize slashes. Always without trailing in the db.
        if (!$redirectModel->isRegexp) {
            $redirectModel->sourceUrl = UrlHelper::normalizeUrl($redirectModel->sourceUrl, false);
        }

        if (!UrlHelper::isUrl($redirectModel->destinationUrl)) {
            $redirectModel->destinationUrl = UrlHelper::normalizeUrl($redirectModel->destinationUrl, false);
        }

        // Check if we already have redirects with this source URL and site ID
        $existingRedirectQuery = RedirectModel::find()
            ->where(['sourceUrl' => $redirectModel->sourceUrl]);

        if (!empty($redirectModel->id)) {
            $existingRedirectQuery->andWhere(['!=', 'id', $redirectModel->id]);
        }

        if (!empty($redirectModel->siteId)) {
            $existingRedirectQuery->andWhere([
                'or', [
                    'siteId' => $redirectModel->siteId,
                ], [
                    'siteId' => null,
                ]
            ]);
        }

        /** @var RedirectModel $existingRedirect */
        foreach ($existingRedirectQuery->each() as $existingRedirect) {
            if ($redirectModel->siteId === $existingRedirect->siteId || $redirectModel->siteId === null) {
                // The existing redirect should be safe to delete, because the redirect being saved is going to supersede it
                // Let's retain the existing redirect's stats, though
                $redirectModel->hits += $existingRedirect->hits;
                try {
                    RedirectHelper::deleteAllByIds([$existingRedirect->id]);
                } catch (\Throwable $e) {
                    Craft::error('An error occurred when trying to delete redundant existing redirect: ' . $e->getMessage(), __METHOD__);
                }
            }
        }

        // Check if we have any redirects with source URL equal to our destination. This opens up for redirect loops, which we should avoid.
        $conflictingRedirectQuery = RedirectModel::find()
            ->where(['sourceUrl' => $redirectModel->destinationUrl]);

        if (!empty($redirectModel->id)) {
            $conflictingRedirectQuery->andWhere(['!=', 'id', $redirectModel->id]);
        }

        if (!empty($redirectModel->siteId)) {
            $conflictingRedirectQuery->andWhere([
                'or', [
                    'siteId' => $redirectModel->siteId,
                ], [
                    'siteId' => null,
                ]
            ]);
        }

        /** @var RedirectModel $conflictingRedirect */
        foreach ($conflictingRedirectQuery->each() as $conflictingRedirect) {
            try {
                RedirectHelper::deleteAllByIds([$conflictingRedirect->id]);
            } catch (\Throwable $e) {
                Craft::error('An error occurred when trying to delete potential redirect loop redirect: ' . $e->getMessage(), __METHOD__);
            }
        }

        // Update any existing redirects pointing to the old URI, to avoid additional redirects in cases where an element URI changes multiple times
        $oldRedirectsQuery = RedirectModel::find()
            ->where(['destinationUrl' => $redirectModel->sourceUrl]);

        if (!empty($redirectModel->id)) {
            $oldRedirectsQuery->andWhere(['!=', 'id', $redirectModel->id]);
        };

        if (!empty($redirectModel->siteId)) {
            $oldRedirectsQuery
                ->andWhere([
                    'or', [
                        'siteId' => $redirectModel->siteId,
                    ], [
                        'siteId' => null,
                    ]
                ]);
        }

        /** @var RedirectModel $oldRedirect */
        foreach ($oldRedirectsQuery->each() as $oldRedirect) {
            $oldRedirect->destinationUrl = $redirectModel->destinationUrl;
            RedirectHelper::insertOrUpdateRedirect($oldRedirect);
        }

        // Invalidate caches
        CacheHelper::invalidateAllCaches();

        // Insert or update redirect and return it
        return RedirectHelper::insertOrUpdateRedirect($redirectModel);

    }

}

