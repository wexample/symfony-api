<?php

namespace Wexample\SymfonyApi\Api\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class ValidateRequestContent
{
    /** @var string[] */
    private array $dataFieldNames;

    public function __construct(
        public string $dto,
        public string $attributeName = 'content',
        string|array $dataFieldNames = 'data'
    ) {
        $this->dataFieldNames = is_array($dataFieldNames) ? $dataFieldNames : [$dataFieldNames];
    }

    /**
     * Get the field names to look for data in multipart requests
     * @return string[]
     */
    public function getDataFieldNames(): array
    {
        return $this->dataFieldNames;
    }
}
