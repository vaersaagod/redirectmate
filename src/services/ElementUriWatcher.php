<?php

namespace vaersaagod\redirectmate\services;

use Craft;
use craft\base\Component;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\events\ElementEvent;
use craft\helpers\ElementHelper;
use craft\models\Site;
use craft\services\Elements;

use vaersaagod\redirectmate\helpers\UrlHelper;
use vaersaagod\redirectmate\models\RedirectModel;
use vaersaagod\redirectmate\RedirectMate;

use yii\base\Event;
use yii\base\ModelEvent;

/**
 * ElementUriWatcher Service
 * This is an internal service, dealing with automatically creating redirects when elements' URIs change
 *
 * @author    Værsågod
 * @package   RedirectMate
 * @since     1.0.0
 */
class ElementUriWatcher extends Component
{

    /** @var string The "Match by" setting to be used for auto-created element redirects, as per the plugin setting */
    private static string $_matchElementUrisBy;

    /** @var array A memoized array of element URIs that should be watched for changes */
    private static array $_watchedElementUris;

    /**
     * Adds various event listeners to facilitate auto-creation of redirects when elements' URIs change
     *
     * @return void
     */
    public function watchElementUris(): void
    {

        if (isset(static::$_watchedElementUris)) {
            return;
        }

        static::$_watchedElementUris = [];

        if (!RedirectMate::getInstance()?->getSettings()->autoCreateElementRedirects) {
            return;
        }

        // Cache the "Match by" setting for element auto-redirects
        if (RedirectMate::getInstance()?->getSettings()->autoCreateElementRedirectsMatchBy === RedirectModel::MATCHBY_FULLURL) {
            static::$_matchElementUrisBy = RedirectModel::MATCHBY_FULLURL;
        } else {
            static::$_matchElementUrisBy = RedirectModel::MATCHBY_PATH;
        }

        // Before save element; maybe watch the element's URI
        Event::on(
            Elements::class,
            Elements::EVENT_BEFORE_SAVE_ELEMENT,
            static function (ElementEvent $event) {
                if ($event->isNew) {
                    return;
                }
                static::_maybeWatchElementUri($event->element);
            }
        );

        // After element has propagated, maybe create a redirect
        Event::on(
            Element::class,
            Element::EVENT_AFTER_PROPAGATE,
            static function (ModelEvent $event) {

                /** @var ElementInterface|null $element */
                $element = $event->sender;

                static::_maybeCreateRedirectForElement($element);
            }
        );

        // Before Craft changes an element's slug and URI, maybe watch the URI
        // This event enables us to support auto-redirects for elements being moved in structures
        // (or when structure parents has their slugs changed, and {parent.uri} is in the URL format)
        Event::on(
            Elements::class,
            Elements::EVENT_BEFORE_UPDATE_SLUG_AND_URI,
            static function (ElementEvent $event) {
                if ($event->isNew) {
                    return;
                }
                static::_maybeWatchElementUri($event->element);
            }
        );

        // After element has had its slug and URI change, maybe create a redirect
        Event::on(
            Elements::class,
            Elements::EVENT_AFTER_UPDATE_SLUG_AND_URI,
            static function (ElementEvent $event) {
                static::_maybeCreateRedirectForElement($event->element);
            }
        );

    }

    /**
     * Adds the element's URI to the watch list, if the element is watchable (i.e. has a URI, isn't a draft etc, etc)
     *
     * @param ElementInterface|null $element
     * @return void
     */
    private static function _maybeWatchElementUri(?ElementInterface $element): void
    {
        if (
            !$element ||
            !$element->id ||
            isset(static::$_watchedElementUris[(string)$element->id]) ||
            !static::_shouldWatchElementUri($element)
        ) {
            return;
        }
        static::$_watchedElementUris[(string)$element->id] = static::_getElementSiteUris($element);
    }

    /**
     * Creates a redirect from the old to the current element URI, in the event that the old URI
     * has been watched, and the new and old URIs are actually different
     *
     * @param ElementInterface|null $element
     * @return void
     */
    private static function _maybeCreateRedirectForElement(?ElementInterface $element): void
    {

        if (
            !$element || 
            !$element->id || 
            empty(static::$_watchedElementUris[(string)$element->id]) || 
            !static::_shouldWatchElementUri($element)
        ) {
            return;
        }

        $currentUris = static::_getElementSiteUris($element);
        $oldUris = static::$_watchedElementUris[(string)$element->id];

        foreach ($currentUris as $siteId => $currentUri) {

            $oldUri = $oldUris[(string)$siteId] ?? null;
            if ($oldUri === null || strcmp($oldUri, $currentUri) === 0) {
                continue;
            }

            $siteId = (int)$siteId;

            // Prepare the source and destination URLs
            if (static::$_matchElementUrisBy === RedirectModel::MATCHBY_FULLURL) {
                try {
                    $sourceUrl = UrlHelper::siteUrl($oldUri, null, null, $siteId);
                    $destinationUrl = UrlHelper::siteUrl($currentUri, null, null, $siteId);
                } catch (\Throwable $e) {
                    Craft::error($e->getMessage(), __METHOD__);
                    return;
                }
            } else {
                $sourceUrl = UrlHelper::normalizeUrl($oldUri);
                $destinationUrl = UrlHelper::normalizeUrl($currentUri);
            }

            // Create a new redirect from the old URI to the current URI
            $redirect = new RedirectModel([
                'siteId' => $siteId,
                'matchBy' => static::$_matchElementUrisBy,
                'sourceUrl' => $sourceUrl,
                'destinationUrl' => $destinationUrl,
                'destinationElementId' => $element->id,
            ]);

            RedirectMate::getInstance()->redirect->addRedirect($redirect);
        }

    }

    /**
     * Returns true if this is an element that is eligible for having a redirect created if/when its URI changes, i.e. it's "watchable"
     *
     * @param ElementInterface|null $element
     * @return bool
     */
    private static function _shouldWatchElementUri(?ElementInterface $element): bool
    {
        return
            $element !== null &&
            $element::hasUris() &&
            !$element->propagating &&
            !ElementHelper::isDraftOrRevision($element) &&
            !$element->firstSave &&
            !$element->isNewForSite &&
            !str_contains($element->uri, '__temp_');
    }

    /**
     * Returns an array of all URIs for the given element, keyed by site ID
     *
     * @param ElementInterface $element
     * @return array
     */
    private static function _getElementSiteUris(ElementInterface $element): array
    {
        return array_reduce(
            Craft::$app->getSites()->getAllSites(true),
            static function (array $carry, Site $site) use ($element) {
                if (!$uri = Craft::$app->getElements()->getElementUriForSite($element->id, $site->id)) {
                    return $carry;
                }
                $carry[(string)$site->id] = $uri;
                return $carry;
            }, []);
    }

}
