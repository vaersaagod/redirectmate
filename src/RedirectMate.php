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
use craft\events\RegisterComponentTypesEvent;
use craft\log\MonologTarget;
use craft\services\Utilities;
use craft\web\ErrorHandler;

use Monolog\Formatter\LineFormatter;
use Psr\Log\LogLevel;
use yii\base\Event;
use yii\web\HttpException;

use vaersaagod\redirectmate\models\Settings;
use vaersaagod\redirectmate\services\RedirectService;
use vaersaagod\redirectmate\services\TrackerService;
use vaersaagod\redirectmate\utilities\RedirectMateUtility;

/**
 * Class RedirectMate
 *
 * @package   vaersaagod\redirectmate
 * @since     1.0.0
 *
 * @property  TrackerService    $tracker
 * @property  RedirectService   $redirect
 * @property  Settings          $settings
 * @method    Settings          getSettings()
 */
class RedirectMate extends Plugin
{

    /**
     * @var string
     */
    public string $schemaVersion = '1.0.0';

    /**
     * @var null|HttpException
     */
    public static ?HttpException $currentException = null;

    // Public Methods
    // =========================================================================

    public function init()
    {
        parent::init();

        $this->name = self::getInstance()->getSettings()->pluginName;

        // Register services
        $this->setComponents([
            'tracker' => TrackerService::class,
            'redirect' => RedirectService::class,
        ]);

        // Register a custom log target, keeping the format as simple as possible.
        Craft::getLogger()->dispatcher->targets[] = new MonologTarget([
            'name' => 'redirectmate',
            'categories' => ['redirectmate'],
            'level' => LogLevel::INFO,
            'logContext' => false,
            'allowLineBreaks' => false,
            'formatter' => new LineFormatter(
                format: "%datetime% %message%\n",
                dateFormat: 'Y-m-d H:i:s',
            ),
        ]);

        if ($this->tableSchemaExists()) {
            $request = Craft::$app->getRequest();

            // Handle errors
            if ($request->getIsSiteRequest() && $request->method === 'GET' && !$request->getIsActionRequest() && !$request->getIsPreview() && !$request->getIsLivePreview()) {
                Event::on(ErrorHandler::class, ErrorHandler::EVENT_BEFORE_HANDLE_EXCEPTION,
                    static function(ExceptionEvent $e) {
                        $exception = $e->exception;

                        if ($exception instanceof HttpException && $exception->statusCode === 404) {
                            self::$currentException = $exception;
                            self::getInstance()->tracker->handleRequest();
                        }
                    });
            }

            // Add utility
            Event::on(Utilities::class, Utilities::EVENT_REGISTER_UTILITY_TYPES,
                static function(RegisterComponentTypesEvent $event) {
                    $event->types[] = RedirectMateUtility::class;
                }
            );
        }
    }

    // Protected Methods
    // =========================================================================

    /**
     * Check if tables have been created
     *
     * @return bool
     */
    protected function tableSchemaExists(): bool
    {
        return (Craft::$app->db->schema->getTableSchema('{{%redirectmate_tracker}}') !== null);
    }

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
