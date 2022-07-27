<?php

namespace vaersaagod\redirectmate\controllers;

use Craft;
use craft\web\Controller;

use League\Csv\Writer;

use SplTempFileObject;

use vaersaagod\redirectmate\db\TrackerQuery;
use vaersaagod\redirectmate\RedirectMate;

class ImportExportController extends Controller
{

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

        $data = (new TrackerQuery())
            ->select(array_keys($columns))
            ->orderBy('hits DESC')
            ->all();

        $this->exportCsvFile('logs', $data, $columns);
    }

    public function actionExportRedirects()
    {

    }

    /**
     * @param string $filename
     * @param array $rows
     * @param array $columns
     */
    protected function exportCsvFile(string $filename, array $rows, array $columns): void
    {

        // Help PHP detect line endings in Mac OS X
        if (!ini_get('auto_detect_line_endings')) {
            ini_set('auto_detect_line_endings', '1');
        }

        $csv = Writer::createFromFileObject(new SplTempFileObject());
        $csv->setDelimiter(RedirectMate::getInstance()->getSettings()->csvDelimiter);
        $csv->insertOne(array_values($columns));
        $csv->insertAll($rows);

        $filename = pathinfo($filename, PATHINFO_FILENAME) . '.csv';

        $csv->output($filename);

        exit(0);
    }

}
