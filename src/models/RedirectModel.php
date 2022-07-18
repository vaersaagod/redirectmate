<?php


namespace vaersaagod\redirectmate\models;


use craft\base\Model;

class RedirectModel extends Model
{
    public const MATCHBY_PATH = 'pathonly';
    public const MATCHBY_FULLURL = 'fullurl';

    public const STATUSCODE_301_PERMANENT = '301';
    public const STATUSCODE_302_TEMPORARY = '302';
    public const STATUSCODE_307_TEMPORARY_REDIRECT = '307';
    public const STATUSCODE_308_PERMANENT_REDIRECT = '308';
    public const STATUSCODE_410_GONE = '410';
    
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
     * @var bool
     */
    public $enabled = true;

    /**
     * @var string
     */
    public $matchBy = self::MATCHBY_PATH;

    /**
     * @var string
     */
    public $sourceUrl;

    /**
     * @var string
     */
    public $destinationUrl;
    
    /**
     * @var null|int
     */
    public $destinationElementId;

    /**
     * @var string
     */
    public $statusCode = self::STATUSCODE_301_PERMANENT;

    /**
     * @var boolean
     */
    public $isRegexp = false;

    /**
     * @var int
     */
    public $hits = 0;

    /**
     * @var string
     */
    public $lastHit;

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
