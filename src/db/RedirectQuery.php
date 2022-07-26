<?php

namespace vaersaagod\redirectmate\db;

use craft\db\Query;

use vaersaagod\redirectmate\models\RedirectModel;

class RedirectQuery extends Query
{

    /** @var string */
    public const TABLE = '{{%redirectmate_redirects}}';

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
     * @return RedirectModel|null
     */
    public function one($db = null): ?RedirectModel
    {
        if ($row = parent::one($db)) {
            $models = $this->populate([$row]);
            return reset($models) ?: null;
        }

        return null;
    }

    /**
     * @param $db
     * @return RedirectModel[]|array
     */
    public function all($db = null): array
    {
        return parent::all($db);
    }

    /**
     * @inheritdoc
     * @return TrackerModel[]|array The resulting redirect models
     */
    public function populate($rows): array
    {
        if (empty($rows)) {
            return [];
        }
        return array_map(static function (array $row) {
            return new RedirectModel($row);
        }, $rows);
    }

}
