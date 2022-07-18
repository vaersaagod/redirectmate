<?php

namespace vaersaagod\redirectmate\models;

use craft\base\Model;

class ParsedUrlModel extends Model
{
    /**
     * @var string
     */
    public string $parsedPath;

    /**
     * @var string
     */
    public string $parsedUrl;

    /**
     * @var string
     */
    public string $path;

    /**
     * @var string
     */
    public string $url;

    /**
     * @var string
     */
    public string $queryString;

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
