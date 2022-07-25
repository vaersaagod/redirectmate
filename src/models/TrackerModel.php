<?php

namespace vaersaagod\redirectmate\models;

use craft\base\Model;
use vaersaagod\redirectmate\db\TrackerQuery;

class TrackerModel extends Model
{
    /**
     * @var int|null
     */
    public ?int $id = null;

    /**
     * @var string|null
     */
    public ?string $uid = null;

    /**
     * @var null|int
     */
    public ?int $siteId = null;

    /**
     * @var string|null
     */
    public ?string $sourceUrl = null;

    /**
     * @var string|null
     */
    public ?string $referrer = null;

    /**
     * @var string|null
     */
    public ?string $remoteIp = null;

    /**
     * @var ?string
     */
    public string|null $userAgent = null;

    /**
     * @var int
     */
    public int $hits = 0;

    /**
     * @var bool
     */
    public bool $handled = false;

    /**
     * @var \DateTime|null
     */
    public ?\DateTime $dateCreated = null;
    
    /**
     * @var \DateTime|null
     */
    public ?\DateTime $dateUpdated = null;

    /**
     * @var \DateTime|null
     */
    public ?\DateTime $lastHit = null;


    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * @return TrackerQuery
     */
    public static function find(): TrackerQuery
    {
        return new TrackerQuery();
    }
}
