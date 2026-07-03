<?php

namespace vaersaagod\redirectmate\controllers;

use Craft;
use craft\db\Query;
use craft\web\Controller;

use League\Csv\EscapeFormula;
use League\Csv\Writer;

use SplTempFileObject;

use vaersaagod\redirectmate\db\RedirectQuery;
use vaersaagod\redirectmate\db\TrackerQuery;
use vaersaagod\redirectmate\RedirectMate;
use vaersaagod\redirectmate\utilities\RedirectMateUtility;

class ImportExportController extends Controller
{

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     * @throws \yii\web\BadRequestHttpException
     * @throws \yii\web\ForbiddenHttpException
     */
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        // Exports contain all tracked 404 data (incl. IPs, user agents, referrers) and every
        // redirect – restrict them to control panel users with access to the RedirectMate utility.
        $this->requireCpRequest();
        $this->requirePermission('utility:' . RedirectMateUtility::id());

        return true;
    }

    /**
     * @return void
     * @throws \League\Csv\CannotInsertRecord
     */
    public function actionExportLogs()
    {

        $columns = [
            'sourceUrl' => Craft::t('redirectmate', 'Source URL'),
            'hits' => Craft::t('redirectmate', 'Hits'),
            'lastHit' => Craft::t('redirectmate', 'Last hit'),
            'referrer' => Craft::t('redirectmate', 'Referrer'),
            'handled' => Craft::t('redirectmate', 'Handled'),
            'siteId' => Craft::t('redirectmate', 'Site'),
            'remoteIp' => Craft::t('redirectmate', 'Remote IP'),
            'userAgent' => Craft::t('redirectmate', 'User Agent'),
        ];

        $this->_exportCsvFile('logs', TrackerQuery::TABLE, $columns);
    }

    public function actionExportRedirects()
    {

        $columns = [
            'sourceUrl' => Craft::t('redirectmate', 'Source URL'),
            'destinationUrl' => Craft::t('redirectmate', 'Destination URL'),
            'hits' => Craft::t('redirectmate', 'Hits'),
            'matchBy' => Craft::t('redirectmate', 'Match by'),
            'isRegexp' => Craft::t('redirectmate', 'Regexp?'),
            'statusCode' => Craft::t('redirectmate', 'Status'),
            'siteId' => Craft::t('redirectmate', 'Site'),
            'lastHit' => Craft::t('redirectmate', 'Last hit'),
            'enabled' => Craft::t('redirectmate', 'Enabled'),
            'dateCreated' => Craft::t('redirectmate', 'Date created'),
            'dateUpdated' => Craft::t('redirectmate', 'Date updated'),
        ];

        $this->_exportCsvFile('redirects', RedirectQuery::TABLE, $columns);
    }

    /**
     * @param string $filename
     * @param string $table
     * @param array $columns
     * @return void
     * @throws \League\Csv\CannotInsertRecord
     */
    private function _exportCsvFile(string $filename, string $table, array $columns): void
    {

        // Help PHP detect line endings for Mac OS X
        if (!ini_get('auto_detect_line_endings')) {
            ini_set('auto_detect_line_endings', '1');
        }

        $filename = pathinfo($filename, PATHINFO_FILENAME) . '.csv';

        $data = (new Query())
            ->from([$table])
            ->select(array_keys($columns))
            ->orderBy('hits DESC')
            ->all();

        $csv = Writer::createFromFileObject(new SplTempFileObject());

        // Neutralize spreadsheet formula injection – tracked values (source URL, referrer,
        // user agent) are attacker-controlled and could otherwise be interpreted as formulas.
        $csv->addFormatter(new EscapeFormula());

        try {
            $csv->setDelimiter(RedirectMate::getInstance()->getSettings()->csvDelimiter);
        } catch (\Throwable $e) {
            Craft::error($e, __METHOD__);
        }

        $csv->insertOne(array_values($columns));
        $csv->insertAll($data);

        $csv->output($filename);

        exit(0);
    }

}
