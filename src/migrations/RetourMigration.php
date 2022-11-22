<?php

namespace vaersaagod\redirectmate\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;

use vaersaagod\redirectmate\db\TrackerQuery;
use vaersaagod\redirectmate\models\RedirectModel;
use vaersaagod\redirectmate\RedirectMate;

class RetourMigration extends Migration
{

    /**
     * @return bool
     * @throws \Exception
     */
    public function safeUp(): bool
    {

        // Migrate Retour redirects
        foreach (['{{%retour_redirects}}', '{{%retour_static_redirects}}'] as $tableName) {
            $this->migrateRedirects($tableName);
        }

        // Migrate Retour stats
        $this->migrateStats('{{%retour_stats}}');

        // Disable Retour
        if (Craft::$app->getConfig()->getGeneral()->allowAdminChanges && Craft::$app->getPlugins()->getPlugin('retour')) {
            try {
                Craft::$app->getPlugins()->disablePlugin('retour');
            } catch (\Throwable $e) {
                Craft::error($e, __METHOD__);
            }
        }

        return true;
    }

    /**
     * @return bool
     */
    public function safeDown(): bool
    {
        return false;
    }

    /**
     * @param string $tableName
     * @return void
     */
    protected function migrateStats(string $tableName): void
    {
        if (!$this->db->tableExists($tableName)) {
            return;
        }

        $settings = RedirectMate::getInstance()->getSettings();
        $allSiteIds = Craft::$app->getSites()->getAllSiteIds(true);

        // These columns exist for both the Craft 2 and Craft 3 versions of Retour
        $select = [
            'redirectSrcUrl',
            'MAX(referrerUrl) as referrerUrl',
            'MAX(hitCount) as hitCount',
            'MAX(hitLastTime) as hitLastTime',
            'MAX(handledByRetour) as handledByRetour',
            'MAX(dateCreated) as dateCreated',
            'MAX(dateUpdated) as dateUpdated',
        ];

        if ($this->db->columnExists($tableName, 'siteId')) {
            $select = [
                ...$columns,
                // These columns only exist for the Craft 3 version
                'siteId',
                'MAX(remoteIp) as remoteIp',
                'MAX(userAgent) as userAgent'
            ];
        }

        $retourStatsQuery = (new Query())
            ->select($select)
            ->from($tableName);

        if ($this->db->columnExists($tableName, 'siteId')) {
            $retourStatsQuery->groupBy(['redirectSrcUrl', 'siteId']);
        } else {
            $retourStatsQuery->groupBy('redirectSrcUrl');
        }

        foreach ($retourStatsQuery->each() as $retourStatRow) {

            if (!isset($retourStatRow['redirectSrcUrl'])) {
                continue;
            }

            // Drop any Retour stats for non-existent sites
            if (!empty($retourStatRow['siteId']) && !in_array($retourStatRow['siteId'], $allSiteIds, false)) {
                continue;
            }

            $columns = [
                'dateCreated' => Db::prepareDateForDb($retourStatRow['dateCreated']),
                'dateUpdated' => Db::prepareDateForDb($retourStatRow['dateUpdated']),
                'siteId' => $retourStatRow['siteId'] ?? null,
                'sourceUrl' => $retourStatRow['redirectSrcUrl'],
                'hits' => (int)$retourStatRow['hitCount'],
                'lastHit' => Db::prepareDateForDb($retourStatRow['hitLastTime']),
                'handled' => (bool)$retourStatRow['handledByRetour'],
            ];

            if (in_array('ip', $settings->track, true)) {
                $columns['remoteIp'] = $retourStatRow['remoteIp'] ?? null;
            }

            if (in_array('useragent', $settings->track, true)) {
                $columns['userAgent'] = $retourStatRow['userAgent'] ?? null;
            }

            if (in_array('referrer', $settings->track, true)) {
                $columns['referrer'] = $retourStatRow['referrerUrl'];
            }

            $this->insert(TrackerQuery::TABLE, $columns);
        }

    }

    /**
     * @param string $tableName
     * @return void
     * @throws \Exception
     */
    protected function migrateRedirects(string $tableName): void
    {

        if (!$this->db->tableExists($tableName)) {
            return;
        }

        $allSiteIds = Craft::$app->getSites()->getAllSiteIds(true);

        // If the Retour table contains a "locale" column, we're dealing with a Craft 2 install
        // Get site handles and attempt to map those to the Yii 1 locales
        $siteIdsByHandle = [];
        if ($this->db->columnExists($tableName, 'locale')) {
            $siteIdsByHandle = ArrayHelper::map((new Query())
                ->select(['id', 'handle'])
                ->from([Table::SITES])
                ->all($this->db), 'handle', 'id');
        }

        $retourRedirectsQuery = (new Query())
            ->select('*')
            ->from($tableName);

        foreach ($retourRedirectsQuery->each() as $retourRedirectRow) {

            // Map attributes
            [
                'siteId' => $siteId,
                'enabled' => $enabled,
                'redirectSrcUrlParsed' => $sourceUrl,
                'redirectDestUrl' => $destinationUrl,
                'associatedElementId' => $destinationElementId,
                'redirectHttpCode' => $statusCode,
                'hitCount' => $hits,
                'hitLastTime' => $lastHit,
            ] = $retourRedirectRow + [
                'siteId' => null, // Note: Craft 2 installs won't have a siteId, but a "locale" key. Not going to do the work to map that!
                'enabled' => true,
            ];

            // Drop redirects with a trash __temp value in the source URL
            if (str_contains($sourceUrl, '__temp')) {
                continue;
            }

            // Drop redirects for non-existent sites
            if (!empty($siteId) && !in_array($siteId, $allSiteIds, false)) {
                continue;
            }

            // If a "locale" is set, try to map that to a site ID
            if (!$siteId && isset($retourRedirectRow['locale'])) {
                $siteId = $siteIdsByHandle[$retourRedirectRow['locale']] ?? null;
            }

            // Make sure the matchBy value is valid
            $matchBy = $retourRedirectRow['redirectSrcMatch'] ?? $retourRedirectRow['redirectMatchType'] ?? null;
            if (!in_array($matchBy, [RedirectModel::MATCHBY_FULLURL, RedirectModel::MATCHBY_PATH], true)) {
                $matchBy = RedirectModel::MATCHBY_PATH;
            }

            // Is this a regex redirect?
            $isRegExp = ($retourRedirectRow['redirectMatchType'] ?? null) === 'regexmatch';

            // If the destination element ID is set, make sure it points to an existing element (otherwise we run into FK constraint issues)
            // We want to retain the redirect even if the element doesn't exist though – just make sure it's set to null if that's the case.
            if (!empty($destinationElementId)) {
                $destinationElementId = (new Query())
                    ->select('id')
                    ->from(Table::ELEMENTS)
                    ->scalar();
            } else {
                $destinationElementId = null;
            }

            $redirectModel = new RedirectModel([
                'siteId' => $siteId,
                'enabled' => $enabled,
                'matchBy' => $matchBy,
                'sourceUrl' => $sourceUrl,
                'destinationUrl' => $destinationUrl,
                'destinationElementId' => $destinationElementId,
                'statusCode' => $statusCode,
                'isRegexp' => $isRegExp,
                'hits' => $hits,
                'lastHit' => $lastHit,
            ]);

            $redirectModel = RedirectMate::getInstance()->redirect->addRedirect($redirectModel);

            if ($redirectModel->getErrors()) {
                throw new \Exception($redirectModel->getFirstError('*'));
            }
        }

    }

}
