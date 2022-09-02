<?php

namespace vaersaagod\redirectmate\migrations;

use Craft;
use craft\db\Migration;

use vaersaagod\redirectmate\helpers\CacheHelper;

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

        CacheHelper::invalidateAllCaches();

        $this->driver = Craft::$app->getConfig()->getDb()->driver;

        if ($this->createTables()) {

            $this->createIndexes();
            $this->addForeignKeys();

            // Refresh the db schema caches
            Craft::$app->db->schema->refresh();

            // Migrate Retour tables
            try {
                (new RetourMigration())->safeUp();
            } catch (\Throwable $e) {
                Craft::error($e->getMessage(), __METHOD__);
                $this->safeDown();
                return false;
            }

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

        // Tracker table (log)
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
                    'enabled' => $this->boolean()->defaultValue(true),
                    'sourceUrl' => $this->string(1000)->notNull()->defaultValue(''),
                    'referrer' => $this->string(1000)->defaultValue(''),
                    'remoteIp' => $this->string(45)->defaultValue(''),
                    'userAgent' => $this->string(255)->defaultValue(''),
                    'hits' => $this->integer()->notNull()->defaultValue(1),
                    'lastHit' => $this->dateTime(),
                    'handled' => $this->boolean()->notNull()->defaultValue(false),
                ]
            );
        }

        // Redirects table
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
                    'hits' => $this->integer()->notNull()->defaultValue(0),
                    'lastHit' => $this->dateTime(),
                ]
            );
        }

        return $tablesCreated;
    }

    /**
     * @return void
     */
    protected function createIndexes(): void
    {
        $this->createIndex(
            $this->db->getIndexName(),
            '{{%redirectmate_tracker}}',
            'siteId',
            false
        );

        $this->createIndex(
            $this->db->getIndexName(),
            '{{%redirectmate_tracker}}',
            'enabled',
            false
        );

        $this->createIndex(
            $this->db->getIndexName(),
            '{{%redirectmate_tracker}}',
            'sourceUrl',
            false
        );

        $this->createIndex(
            $this->db->getIndexName(),
            '{{%redirectmate_tracker}}',
            ['sourceUrl', 'siteId'],
            true
        );

        $this->createIndex(
            $this->db->getIndexName(),
            '{{%redirectmate_redirects}}',
            'siteId',
            false
        );
    }

    /**
     * @return void
     */
    protected function addForeignKeys(): void
    {
        $this->addForeignKey(
            $this->db->getForeignKeyName(),
            '{{%redirectmate_tracker}}',
            'siteId',
            '{{%sites}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            $this->db->getForeignKeyName(),
            '{{%redirectmate_redirects}}',
            'siteId',
            '{{%sites}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            $this->db->getForeignKeyName(),
            '{{%redirectmate_redirects}}',
            'destinationElementId',
            '{{%elements}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    /**
     * @return void
     */
    protected function removeTables(): void
    {
        $this->dropTableIfExists('{{%redirectmate_tracker}}');
        $this->dropTableIfExists('{{%redirectmate_redirects}}');
    }
}
