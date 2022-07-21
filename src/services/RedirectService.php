<?php

namespace vaersaagod\redirectmate\services;

use Craft;
use craft\base\Component;
use craft\db\Query;
use vaersaagod\redirectmate\helpers\RedirectHelper;
use vaersaagod\redirectmate\models\RedirectModel;
use vaersaagod\redirectmate\RedirectMate;

/**
 * RedirectService Service
 *
 * @author    Værsågod
 * @package   RedirectMate
 * @since     1.0.0
 *
 * @property-read Query $query
 */
class RedirectService extends Component
{
    
    /**
     * @param RedirectModel $redirect
     */
    public function doRedirect(RedirectModel $redirect): void
    {
        $response = Craft::$app->getResponse();
        $statusCode = $redirect->statusCode;

        RedirectHelper::updateRedirectStats($redirect);

        // If we have an status code above 400, we want to trigger an exception
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
        $destinationUrl = $redirect->destinationUrl;

        if (RedirectMate::getInstance()?->getSettings()->queryStringPassthrough && !empty(Craft::$app->getRequest()->getQueryString())) {
            $destinationUrl .= '?' . Craft::$app->getRequest()->getQueryString();
        }
        
        // Redirect
        $response->redirect($destinationUrl, $redirect->statusCode)->send();

        try {
            Craft::$app->end();
        } catch (\Throwable $e) {
            Craft::error($e->getMessage(), __METHOD__);
        }
    }

}
