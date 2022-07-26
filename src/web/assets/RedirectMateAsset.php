<?php

namespace vaersaagod\redirectmate\web\assets;

use Craft;
use craft\helpers\App;
use craft\helpers\FileHelper;
use craft\helpers\UrlHelper;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;
use craft\web\View;

use GuzzleHttp\Client;

use Dotenv\Dotenv;

class RedirectMateAsset extends AssetBundle
{

    /** @var string */
    public $sourcePath = '@vaersaagod/redirectmate/web/assets/dist';

    /** @var array */
    public $depends = [
        CpAsset::class,
    ];

    /** @var array */
    public $js = [[
        'redirectmate.js',
        'type' => 'module',
    ]];

    /** @var array */
    public $css = [
        'redirectmate.css',
    ];

    /** @var string|null */
    private ?string $_devServerUrl = null;

    /** @var bool|null */
    private ?bool $_isDevServerRunning = null;

    /** @var array|null */
    private ?array $_envVars = null;

    /**
     * @inheritDoc
     */
    public function init(): void
    {
        parent::init();

        if (App::devMode() && $this->_isDevServerRunning()) {

            // Prepend the Vite dev server's URL to relative JS resources
            $this->js = array_map([$this, '_prependDevServerUrl'], $this->js);

            // Remove relative CSS resources as they are served from Vite's dev server
            $this->css = array_filter($this->css, function (array|string $filePath) {
                if (is_array($filePath)) {
                    $filePath = $filePath[0];
                }
                return UrlHelper::isFullUrl($filePath);
            });
        }
    }

    /**
     * @param $view
     * @return void
     */
    public function registerAssetFiles($view): void
    {
        parent::registerAssetFiles($view);

        if ($view instanceof View) {
            $this->_registerTranslations($view);
        }
    }

    /**
     * Registers all of RedirectMate's static translations, for use in JS
     *
     * @param View $view
     * @return void
     */
    private function _registerTranslations(View $view): void
    {
        $translations = @include(App::parseEnv('@vaersaagod/redirectmate/translations/en/redirectmate.php'));
        if (!is_array($translations)) {
            Craft::error('Unable to register translations', __METHOD__);
            return;
        }
        $view->registerTranslations('redirectmate', array_keys($translations));
    }

    /**
     * Prepends the dev server's URL to a resource file path
     *
     * @param array|string $filePath
     * @return array|string
     */
    private function _prependDevServerUrl(array|string $filePath): array|string
    {
        if (is_array($filePath)) {
            $fileArray = $filePath;
            $filePath = $fileArray[0] ?? '';
        }
        if ($filePath && !UrlHelper::isFullUrl($filePath)) {
            $devServer = $this->_getDevServerUrl();
            $filePath = $devServer . '/' . ltrim($filePath, '/');
        }
        if (isset($fileArray)) {
            $fileArray[0] = $filePath;
            $filePath = $fileArray;
        }
        return $filePath;
    }

    /**
     * Checks if the dev server is running
     *
     * @return bool
     */
    private function _isDevServerRunning(): bool
    {
        if ($this->_isDevServerRunning === null) {
            $devServerUrl = $this->_getDevServerUrl();
            try {
                (new Client([
                    'http_errors' => false,
                ]))->get($devServerUrl);
                $this->_isDevServerRunning = true;
            } catch (\Throwable) {
                $this->_isDevServerRunning = false;
            }
        }
        return $this->_isDevServerRunning;
    }

    /**
     * Returns the URL to the dev server
     *
     * @return string
     */
    private function _getDevServerUrl(): string
    {
        if ($this->_devServerUrl === null) {
            $envVars = $this->_getEnvVars();
            $devServerHost = $envVars['VITE_DEVSERVER_HOST'] ?? 'localhost';
            if ($devServerHost === '0.0.0.0') {
                $devServerHost = 'localhost';
            }
            $devServerPort = $envVars['VITE_DEVSERVER_PORT'] ?? '3000';
            $this->_devServerUrl = "http://$devServerHost:$devServerPort/src";
        }
        return $this->_devServerUrl;
    }

    /**
     * Returns an array of env vars from a .env file in the plugin's root folder
     *
     * @return array
     */
    private function _getEnvVars(): array
    {
        if ($this->_envVars === null) {
            $envFile = FileHelper::normalizePath(App::parseEnv('@vaersaagod/redirectmate') . '/../.env');
            if (
                class_exists('Dotenv\Dotenv') &&
                file_exists($envFile) &&
                !is_dir($envFile) &&
                $envFileContents = (file_exists($envFile) ? @file_get_contents($envFile) : null)
            ) {
                $this->_envVars = Dotenv::parse($envFileContents);
            } else {
                $this->_envVars = [];
            }
        }
        return $this->_envVars;
    }

}
