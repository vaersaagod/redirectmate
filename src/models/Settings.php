<?php


namespace vaersaagod\redirectmate\models;

use craft\base\Model;

/**
 * @author    Værsågod
 * @package   RedirectMate
 * @since     1.0.0
 */
class Settings extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public string $pluginName = 'RedirectMate';

    /**
     * Whether or not to cache resolved redirects 
     * 
     * @var bool 
     */
    public bool $cacheEnabled = false;
    
    /**
     * Whether to track and keep the query string when a 404 is triggered.
     *
     * Can be either a boolean, or an array of whitelisted params that will be kept. 
     * They will be sorted to account for different order.
     *
     * @var bool|array
     */
    public bool|array $trackQueryString = false;

    /**
     * Whether or not to pass the query string from the source url, to the target url.
     *
     * @var bool
     */
    public bool $queryStringPassthrough = false;

    /**
     * An array of request properties to track for 404's
     *
     * @var array
     */
    public array $track = ['ip', 'referrer', 'useragent'];

    /**
     * Urls patterns to exclude from tracking.
     *
     * The array can either be a flat array of patterns, or a nested array where the
     * keys are site handles, or '*' for all sites, and the value is an array of
     * patterns.
     * 
     * '^/excluded' matches all parsed paths starting with "/exlcuded" 
     * '^/excluded/' matches all parsed paths starting with "/exlcuded/", but not "/excluded" itself 
     * '/excluded/' matches all parsed paths that contains a segment "/exlcuded/"
     * 'excluded' matches all parsed paths that contains a the string "exlcuded"
     *
     * All matches are case insensitive (/i is automatically added)
     *
     * @var array
     */
    public array $excludeUrlPatterns = [];


    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [];
    }

    public function getParsedExcludeUrlPatterns($siteHandle): array
    {
        return $this->getLocalizedConfigSetting('excludeUrlPatterns', $siteHandle);
    }
    
    public function getLocalizedConfigSetting(string $name, string $siteHandle = null): mixed
    {
        if ($siteHandle === null) {
            try {
                $siteHandle = \Craft::$app->getSites()->getCurrentSite()->handle;
            } catch (\Throwable) {
                return $this->$name;
            }
        }
        
        if (is_array($this->$name) && array_key_exists($siteHandle, $this->$name)) {
            return $this->$name[$siteHandle];
        }
        
        if (is_array($this->$name) && array_key_exists('*', $this->$name)) {
            return $this->$name['*'];
        }
        
        return $this->$name;
    }
}
