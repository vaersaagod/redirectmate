<?php

namespace vaersaagod\redirectmate\migrations;

use Craft;
use craft\db\Migration;

/**
 * m240224_160337_change_url_column_lengths migration.
 */
class m240224_160337_change_url_column_lengths extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->alterColumn('{{%redirectmate_tracker}}', 'sourceUrl', $this->string(255)->notNull()->defaultValue(''));
        $this->alterColumn('{{%redirectmate_tracker}}', 'referrer', $this->string(255)->defaultValue(''));
        $this->alterColumn('{{%redirectmate_redirects}}', 'sourceUrl', $this->string(255)->notNull()->defaultValue(''));
        $this->alterColumn('{{%redirectmate_redirects}}', 'destinationUrl', $this->string(255)->defaultValue(''));

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240224_160337_change_url_column_lengths cannot be reverted.\n";
        return false;
    }
}
