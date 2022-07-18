<?php


namespace vaersaagod\redirectmate\models;


use craft\base\Model;

class TrackerModel extends Model
{
    /**
     * @var int
     */
    public $id;
    
    /**
     * @var string
     */
    public $uid;
    
    /**
     * @var null|int
     */
    public $siteId;

    /**
     * @var string
     */
    public $sourceUrl;

    /**
     * @var string
     */
    public $referrer;

    /**
     * @var string
     */
    public $remoteIp;

    /**
     * @var string
     */
    public $userAgent;

    /**
     * @var int
     */
    public $hits;

    /**
     * @var \DateTime
     */
    public $lastHit;

    /**
     * @var bool
     */
    public $handled = false;

    /**
     * @var \DateTime
     */
    public $dateCreated;
    
    /**
     * @var \DateTime
     */
    public $dateUpdated;


    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [];
    }
}
