<?php

namespace vaersaagod\redirectmate\services;

use Craft;
use craft\base\Component;
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

    public function doRedirect(RedirectModel $redirect): void
    {
        $response = Craft::$app->getResponse();
        $statusCode = $redirect->statusCode;
        
        RedirectHelper::updateRedirectStats($redirect);

        // If we have an status code above 400, trigger an exception and let Craft handle it.
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

        if (RedirectMate::getInstance()?->getSettings()->queryStringPassthrough && !empty(Craft::$app->getRequest()->getQueryString())) {
            $destinationUrl = UrlHelper::siteUrl($redirect->destinationUrl, Craft::$app->getRequest()->getQueryString(), null, $siteId);
        } else {
            $destinationUrl = UrlHelper::siteUrl($redirect->destinationUrl, null, null, $siteId);
        }

        // Redirect
        $response->redirect($destinationUrl, $redirect->statusCode)->send();

        try {
            Craft::$app->end();
        } catch (\Throwable $e) {
            Craft::error($e->getMessage(), __METHOD__);
        }
    }

    public function addRedirect(RedirectModel $model): RedirectModel
    {
        // Check if we already have a redirect with this source url and site id, if so, update it.
        $query = RedirectHelper::getQuery();
        $query->where(['sourceUrl' => $model->sourceUrl]);

        if ($model->siteId !== null) {
            $query->andWhere([
                'or', [
                    'siteId' => $model->siteId,
                ], [
                    'siteId' => null,
                ]
            ]);
        }

        $redirectData = $query->one();

        if ($redirectData) {
            $model->id = $redirectData['id'];
            $model->siteId = $redirectData['siteId'];
        }

        // Check if we have any redirects with source url equal to our destination. This opens up for redirect loops, which we should avoid (?)
        $query = RedirectHelper::getQuery();
        $query->where(['sourceUrl' => $model->destinationUrl]);

        if ($model->siteId !== null) {
            $query->andWhere([
                'or', [
                    'siteId' => $model->siteId,
                ], [
                    'siteId' => null,
                ]
            ]);
        }

        $redirectData = $query->one();

        if ($redirectData) {
            try {
                RedirectHelper::deleteAllByIds([$redirectData['id']]);
            } catch (\Throwable $e) {
                Craft::error('An error occured when trying to delete potential redirect loop redirects: ', $e->getMessage(), __METHOD__);
            }
        }

        // Insert or update redirect and return.
        return RedirectHelper::insertOrUpdateData($model);
    }

}

