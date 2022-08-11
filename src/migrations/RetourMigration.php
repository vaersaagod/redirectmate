<?php

namespace vaersaagod\redirectmate\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;
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

        $retourStatsQuery = (new Query())
            ->select('*')
            ->from($tableName)
            ->groupBy(['redirectSrcUrl', 'siteId']);

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
                'siteId' => ((int)$retourStatRow['siteId']) ?: null,
                'sourceUrl' => $retourStatRow['redirectSrcUrl'],
                'hits' => (int)$retourStatRow['hitCount'],
                'lastHit' => Db::prepareDateForDb($retourStatRow['hitLastTime']),
                'handled' => (bool)$retourStatRow['handledByRetour'],
            ];

            if (in_array('ip', $settings->track, true)) {
                $columns['remoteIp'] = $retourStatRow['remoteIp'];
            }

            if (in_array('referrer', $settings->track, true)) {
                $columns['referrer'] = $retourStatRow['referrerUrl'];
            }

            if (in_array('useragent', $settings->track, true)) {
                $columns['userAgent'] = $retourStatRow['userAgent'];
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

        $retourRedirectsQuery = (new Query())
            ->select('*')
            ->from($tableName);

        foreach ($retourRedirectsQuery->each() as $retourRedirectRow) {

            // Map attributes
            [
                'siteId' => $siteId,
                'enabled' => $enabled,
                'redirectSrcMatch' => $matchBy,
                'redirectSrcUrlParsed' => $sourceUrl,
                'redirectDestUrl' => $destinationUrl,
                'associatedElementId' => $destinationElementId,
                'redirectHttpCode' => $statusCode,
                'hitCount' => $hits,
                'hitLastTime' => $lastHit,
            ] = $retourRedirectRow;

            // Drop redirects with a trash __temp value in the source URL
            if (str_contains($sourceUrl, '__temp')) {
                continue;
            }

            // Drop redirects for non-existent sites
            if (!empty($siteId) && !in_array($siteId, $allSiteIds, false)) {
                continue;
            }

            // Make sure the matchBy value is valid
            if (!in_array($matchBy, [RedirectModel::MATCHBY_FULLURL, RedirectModel::MATCHBY_PATH], true)) {
                $matchBy = RedirectModel::MATCHBY_PATH;
            }

            // Is this a regex redirect?
            $isRegExp = ($retourRedirectRow['redirectMatchType'] ?? null) === 'regexmatch';

            // If the destination element ID is set, make sure it points to an existing element (otherwise we run into FK constraint issues)
            // We want to retain the redirect even if the element doesn't exist though, just make sure it's set to null
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
