<?php

namespace vaersaagod\redirectmate\models;

use Craft;
use craft\base\Model;
use craft\validators\DateTimeValidator;
use craft\validators\SiteIdValidator;

use vaersaagod\redirectmate\db\RedirectQuery;

use yii\validators\BooleanValidator;

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
     * @var string|null
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
     * @var string
     */
    public string $sourceUrl = '';

    /**
     * @var string
     */
    public string $destinationUrl = '';
    
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
     * @return array
     */
    public function attributeLabels(): array
    {
        return [
            'id' => Craft::t('app', 'ID'),
            'slug' => Craft::t('app', 'Slug'),
            'uid' => Craft::t('app', 'UID'),
            'siteId' => Craft::t('app', 'Site ID'),
            'dateCreated' => Craft::t('app', 'Date Created'),
            'dateUpdated' => Craft::t('app', 'Date Updated'),
            'enabled' => Craft::t('app', 'Enabled'),
            'sourceUrl' => Craft::t('redirectmate', 'Source URL'),
            'destinationUrl' => Craft::t('redirectmate', 'Destination URL'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['id', 'destinationElementId', 'hits'], 'number', 'integerOnly' => true];
        $rules[] = [
            ['siteId'],
            SiteIdValidator::class,
            'allowDisabled' => true,
        ];
        $rules[] = [['isRegexp'], BooleanValidator::class];
        $rules[] = [['dateCreated', 'dateUpdated', 'lastHit'], DateTimeValidator::class];
        $rules[] = [['sourceUrl', 'statusCode', 'matchBy'], 'required'];
        return $rules;
    }

    /**
     * @return RedirectQuery
     */
    public static function find(): RedirectQuery
    {
        return new RedirectQuery();
    }

}
