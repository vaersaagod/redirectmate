<?php

namespace vaersaagod\redirectmate\migrations;

use Craft;
use craft\config\DbConfig;
use craft\db\Connection;
use craft\db\Migration;

class Install extends Migration
{
    /**
     * @var string The database driver to use
     */
    public string $driver;

    // Public Methods
    // =========================================================================

    /**
     * This method contains the logic to be executed when applying this migration.
     * This method differs from [[up()]] in that the DB logic implemented here will
     * be enclosed within a DB transaction.
     * Child classes may implement this method instead of [[up()]] if the DB logic
     * needs to be within a transaction.
     *
     * @return boolean return a false value to indicate the migration fails
     * and should not proceed further. All other return values mean the migration succeeds.
     */
    public function safeUp(): bool
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        if ($this->createTables()) {
            $this->createIndexes();
            $this->addForeignKeys();
            // Refresh the db schema caches
            Craft::$app->db->schema->refresh();
            $this->insertDefaultData();
        }

        return true;
    }

    /**
     * This method contains the logic to be executed when removing this migration.
     * This method differs from [[down()]] in that the DB logic implemented here will
     * be enclosed within a DB transaction.
     * Child classes may implement this method instead of [[down()]] if the DB logic
     * needs to be within a transaction.
     *
     * @return boolean return a false value to indicate the migration fails
     * and should not proceed further. All other return values mean the migration succeeds.
     */
    public function safeDown(): bool
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        $this->removeTables();

        return true;
    }

    // Protected Methods
    // =========================================================================

    /**
     * Creates the tables needed for the Records used by the plugin
     *
     * @return bool
     */
    protected function createTables(): bool
    {
        $tablesCreated = false;

        // tracker table
        $trackerTableSchema = Craft::$app->db->schema->getTableSchema('{{%redirectmate_tracker}}');

        if ($trackerTableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                '{{%redirectmate_tracker}}',
                [
                    'id' => $this->primaryKey(),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'uid' => $this->uid(),

                    // Custom columns in the table
                    'siteId' => $this->integer()->null()->defaultValue(null),
                    'sourceUrl' => $this->string(1000)->notNull()->defaultValue(''),
                    'referrer' => $this->string(1000)->defaultValue(''),
                    'remoteIp' => $this->string(45)->defaultValue(''),
                    'userAgent' => $this->string(255)->defaultValue(''),
                    'hits' => $this->integer()->defaultValue(1),
                    'lastHit' => $this->dateTime(),
                    'handled' => $this->boolean()->defaultValue(false),
                ]
            );
        }

        // redirect table
        $redirectsTableSchema = Craft::$app->db->schema->getTableSchema('{{%redirectmate_redirects}}');

        if ($redirectsTableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                '{{%redirectmate_redirects}}',
                [
                    'id' => $this->primaryKey(),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'uid' => $this->uid(),

                    // Custom columns in the table
                    'siteId' => $this->integer()->null()->defaultValue(null),
                    'enabled' => $this->boolean()->defaultValue(true),
                    'matchBy' => $this->string(8)->defaultValue('path'),
                    'sourceUrl' => $this->string(1000)->notNull()->defaultValue(''),
                    'destinationUrl' => $this->string(1000)->defaultValue(''),
                    'destinationElementId' => $this->integer()->null()->defaultValue(null),
                    'statusCode' => $this->string(8)->defaultValue('301'),
                    'isRegexp' => $this->boolean()->defaultValue(false),
                    'hits' => $this->integer()->defaultValue(0),
                    'lastHit' => $this->dateTime(),
                ]
            );
        }

        return $tablesCreated;
    }

    /**
     * Creates the indexes needed for the Records used by the plugin
     *
     * @return void
     */
    protected function createIndexes(): void
    {
        $this->createIndex(
            $this->db->getIndexName('{{%redirectmate_tracker}}', 'siteId', true),
            '{{%redirectmate_tracker}}',
            'siteId',
            false
        );

        $this->createIndex(
            $this->db->getIndexName('{{%redirectmate_tracker}}', 'sourceUrl', true),
            '{{%redirectmate_tracker}}',
            'sourceUrl',
            false
        );

        $this->createIndex(
            $this->db->getIndexName('{{%redirectmate_tracker}}', 'sourceUrl', true),
            '{{%redirectmate_tracker}}',
            ['sourceUrl', 'siteId'],
            true
        );

        $this->createIndex(
            $this->db->getIndexName('{{%redirectmate_redirects}}', 'siteId', true),
            '{{%redirectmate_redirects}}',
            'siteId',
            false
        );

        // Additional commands depending on the db driver
        /*
        switch ($this->driver) {
            case Connection::DRIVER_MYSQL:
                break;
            case Connection::DRIVER_PGSQL:
                break;
        }
        */
    }

    /**
     * Creates the foreign keys needed for the Records used by the plugin
     *
     * @return void
     */
    protected function addForeignKeys(): void
    {
        $this->addForeignKey(
            $this->db->getForeignKeyName('{{%redirectmate_tracker}}', 'siteId'),
            '{{%redirectmate_tracker}}',
            'siteId',
            '{{%sites}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            $this->db->getForeignKeyName('{{%redirectmate_redirects}}', 'siteId'),
            '{{%redirectmate_redirects}}',
            'siteId',
            '{{%sites}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            $this->db->getForeignKeyName('{{%redirectmate_redirects}}', 'destinationElementId'),
            '{{%redirectmate_redirects}}',
            'destinationElementId',
            '{{%elements}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    /**
     * Populates the DB with the default data.
     *
     * @return void
     */
    protected function insertDefaultData(): void
    {
    }

    /**
     * Removes the tables needed for the Records used by the plugin
     *
     * @return void
     */
    protected function removeTables(): void
    {
        $this->dropTableIfExists('{{%redirectmate_tracker}}');
        $this->dropTableIfExists('{{%redirectmate_redirects}}');
    }
}
