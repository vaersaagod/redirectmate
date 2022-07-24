<?php

namespace vaersaagod\redirectmate\web\assets;

use Craft;
use craft\helpers\App;
use craft\helpers\FileHelper;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;
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

    /** @var array|null */
    private ?array $_envVars = null;

    /** @var string|null */
    private ?string $_devServer = null;

    /** @var bool|null */
    private ?bool $_isDevServerRunning = null;

    /**
     * @inheritDoc
     */
    public function init(): void
    {
        parent::init();

        if (App::devMode() && $this->_isDevServerRunning()) {
            $this->js = array_map([$this, '_prependDevServer'], $this->js);
            $this->css = array_map([$this, '_prependDevServer'], $this->css);
        }
    }

    /**
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
                $envFileContents = (file_exists($envFile) ? @file_get_contents($envFile) : '')
            ) {
                $this->_envVars = Dotenv::parse($envFileContents);
            } else {
                $this->_envVars = [];
            }
        }
        return $this->_envVars;
    }

    /**
     * @param array|string $filePath
     * @return array|string
     */
    private function _prependDevServer(array|string $filePath): array|string
    {
        if (is_array($filePath)) {
            $fileArray = $filePath;
            $filePath = $fileArray[0] ?? '';
        }
        if ($filePath && !UrlHelper::isFullUrl($filePath)) {
            $devServer = $this->_getDevServer();
            $filePath = $devServer . '/' . ltrim($filePath, '/');
        }
        if (isset($fileArray)) {
            $fileArray[0] = $filePath;
            $filePath = $fileArray;
        }
        return $filePath;
    }

    /**
     * @return bool
     */
    private function _isDevServerRunning(): bool
    {
        if ($this->_isDevServerRunning === null) {
            $devServer = $this->_getDevServer();
            try {
                Craft::createGuzzleClient([
                    'http_errors' => false,
                ])->get($devServer);
                $this->_isDevServerRunning = true;
            } catch (\Throwable) {
                $this->_isDevServerRunning = false;
            }
        }
        return $this->_isDevServerRunning;
    }

    /**
     * @return string
     */
    private function _getDevServer(): string
    {
        if ($this->_devServer === null) {
            $envVars = $this->_getEnvVars();
            $devServerHost = $envVars['VITE_DEVSERVER_HOST'] ?? 'localhost';
            if ($devServerHost === '0.0.0.0') {
                $devServerHost = 'localhost';
            }
            $devServerPort = $envVars['VITE_DEVSERVER_PORT'] ?? '3000';
            $this->_devServer = "http://$devServerHost:$devServerPort/src";
        }
        return $this->_devServer;
    }

}
