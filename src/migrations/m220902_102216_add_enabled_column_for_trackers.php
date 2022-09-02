<?php

namespace vaersaagod\redirectmate\migrations;

use craft\db\Migration;

use vaersaagod\redirectmate\db\TrackerQuery;

/**
 * m220902_102216_add_enabled_column_for_trackers migration.
 */
class m220902_102216_add_enabled_column_for_trackers extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {

        $this->addColumn(
            TrackerQuery::TABLE,
            'enabled',
            $this->boolean()->defaultValue(true)->after('siteId')
        );

        $this->createIndex(
            $this->db->getIndexName(),
            TrackerQuery::TABLE,
            'enabled',
            false
        );

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m220902_102216_add_enabled_column_for_trackers cannot be reverted.\n";
        return false;
    }
}
