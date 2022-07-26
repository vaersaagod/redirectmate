<?php

namespace vaersaagod\redirectmate\db;

use craft\db\Query;

use vaersaagod\redirectmate\models\TrackerModel;

class TrackerQuery extends Query
{

    /** @var string */
    public const TABLE = '{{%redirectmate_tracker}}';

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        $this->from([static::TABLE]);
    }

    /**
     * @inheritdoc
     * @return TrackerModel|null
     */
    public function one($db = null): ?TrackerModel
    {
        if ($row = parent::one($db)) {
            $models = $this->populate([$row]);
            return reset($models) ?: null;
        }

        return null;
    }

    /**
     * @param $db
     * @return TrackerModel[]|array
     */
    public function all($db = null): array
    {
        return parent::all($db);
    }

    /**
     * @inheritdoc
     * @return TrackerModel[]|array The resulting tracker models
     */
    public function populate($rows): array
    {
        if (empty($rows)) {
            return [];
        }
        return array_map(static function (array $row) {
            return new TrackerModel($row);
        }, $rows);
    }

}
