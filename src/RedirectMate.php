<?php
/**
 * RedirectMate plugin for Craft CMS 4.x
 *
 * @link      https://www.vaersaagod.no
 * @copyright Copyright (c) 2022 Værsågod
 */

namespace vaersaagod\redirectmate;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\events\ExceptionEvent;
use craft\events\RegisterCacheOptionsEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\log\MonologTarget;
use craft\services\Utilities;
use craft\utilities\ClearCaches;
use craft\web\ErrorHandler;

use vaersaagod\redirectmate\helpers\CacheHelper;
use yii\base\Event;
use yii\web\HttpException;

use vaersaagod\redirectmate\models\Settings;
use vaersaagod\redirectmate\services\ElementUriWatcher;
use vaersaagod\redirectmate\services\RedirectService;
use vaersaagod\redirectmate\services\TrackerService;
use vaersaagod\redirectmate\utilities\RedirectMateUtility;

/**
 * Class RedirectMate
 *
 * @package   vaersaagod\redirectmate
 * @since     1.0.0
 *
 * @property  TrackerService $tracker
 * @property  RedirectService $redirect
 * @property  ElementUriWatcher $elementUriWatcher The internal service for auto-creating element redirects
 * @property  Settings $settings
 * @method    Settings getSettings()
 */
class RedirectMate extends Plugin
{

    /**
     * @var string
     */
    public string $schemaVersion = '1.1.0';

    /**
     * @var null|HttpException
     */
    public static ?HttpException $currentException = null;

    // Public Methods
    // =========================================================================

    public function init()
    {
        parent::init();

        $this->name = static::getInstance()->getSettings()->pluginName;

        // Register services
        $this->setComponents([
            'tracker' => TrackerService::class,
            'redirect' => RedirectService::class,
            'elementUriWatcher' => ElementUriWatcher::class,
        ]);

        // Register a custom log target, keeping the format as simple as possible.
        Craft::getLogger()->dispatcher->targets[] = new MonologTarget([
            'name' => 'redirectmate',
            'categories' => ['redirectmate', 'vaersaagod\\redirectmate\\*'],
            'allowLineBreaks' => true,
        ]);

        // Add utility
        Event::on(Utilities::class, Utilities::EVENT_REGISTER_UTILITY_TYPES,
            static function (RegisterComponentTypesEvent $event) {
                $event->types[] = RedirectMateUtility::class;
            }
        );

        // Handle front-end 404 exceptions
        $request = Craft::$app->getRequest();
        if ($request->getIsSiteRequest() && $request->method === 'GET' && !$request->getIsActionRequest() && !$request->getIsPreview() && !$request->getIsLivePreview()) {
            Event::on(ErrorHandler::class, ErrorHandler::EVENT_BEFORE_HANDLE_EXCEPTION,
                static function (ExceptionEvent $e) {
                    $exception = $e->exception;
                    if ($exception instanceof \Twig\Error\RuntimeError && ($previousException = $exception->getPrevious()) !== null) {
                        // Use the previous exception in the case of a Twig runtime exception
                        $exception = $previousException;
                    }
                    if (!$exception instanceof HttpException || $exception->statusCode !== 404) {
                        return;
                    }
                    static::$currentException = $exception;
                    try {
                        static::getInstance()->tracker->handleRequest();
                    } catch (\Throwable $e) {
                        Craft::error($e->getMessage(), __METHOD__);
                    }
                });
        }

        // (Maybe) automatically create element redirects
        static::getInstance()->elementUriWatcher->watchElementUris();

        // Add a clear cache option for resolved redirects
        Event::on(
            ClearCaches::class,
            ClearCaches::EVENT_REGISTER_TAG_OPTIONS,
            static function (RegisterCacheOptionsEvent $event) {
                $event->options[] = [
                    'tag' => CacheHelper::TAG_REDIRECTMATE,
                    'label' => Craft::t('redirectmate', 'Cached redirects'),
                ];
            }
        );

    }

    // Protected Methods
    // =========================================================================

    /**
     * Creates and returns the model used to store the plugin’s settings.
     *
     * @return Model|null
     */
    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    // Private Methods
    // =========================================================================

}
