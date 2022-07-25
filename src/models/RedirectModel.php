<?php

namespace vaersaagod\redirectmate\models;

use craft\base\Model;

use vaersaagod\redirectmate\db\RedirectQuery;

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
     * @var ?int
     */
    public int|null $id = null;

    /**
     * @var string
     */
    public ?string $uid = null;

    /**
     * @var null|int
     */
    public ?int $siteId = null;

    /**
     * @var bool
     */
    public bool $enabled = true;

    /**
     * @var string
     */
    public string $matchBy = self::MATCHBY_PATH;

    /**
     * @var string|null
     */
    public ?string $sourceUrl = null;

    /**
     * @var string|null
     */
    public ?string $destinationUrl = '/';
    
    /**
     * @var null|int
     */
    public ?int $destinationElementId = null;

    /**
     * @var string
     */
    public string $statusCode = self::STATUSCODE_301_PERMANENT;

    /**
     * @var boolean
     */
    public bool $isRegexp = false;

    /**
     * @var int
     */
    public int $hits = 0;

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
     * @return RedirectQuery
     */
    public static function find(): RedirectQuery
    {
        return new RedirectQuery();
    }

}
