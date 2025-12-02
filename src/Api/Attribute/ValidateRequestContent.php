<?php

namespace Wexample\SymfonyApi\Api\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class ValidateRequestContent
{
    /** @var array<string|array{pattern: string, min?: int, max?: int}> */
    private array $dataFieldNames;

    public function __construct(
        public string $dto,
        public string $attributeName = 'content',
        string|array $dataFieldNames = 'data'
    ) {
        // Convert simple string/array to structured configuration
        if (is_string($dataFieldNames)) {
            $this->dataFieldNames = [$dataFieldNames];
        } else {
            $this->dataFieldNames = array_map(
                fn ($field) => is_string($field) ? $field : $this->validateFieldPattern($field),
                $dataFieldNames
            );
        }
    }

    /**
     * Validate and normalize field pattern configuration
     */
    private function validateFieldPattern(array $config): array
    {
        if (! isset($config['pattern'])) {
            throw new \InvalidArgumentException('Field pattern configuration must include a "pattern" key');
        }

        return array_merge([
            'min' => 0,
            'max' => PHP_INT_MAX,
        ], $config);
    }

    /**
     * Get all valid field names based on patterns
     * @param array $availableFields List of actually available fields in the request
     * @return string[]
     */
    public function getValidFieldNames(array $availableFields): array
    {
        $validFields = [];

        foreach ($this->dataFieldNames as $field) {
            if (is_string($field)) {
                if (in_array($field, $availableFields)) {
                    $validFields[] = $field;
                }
            } else {
                // Handle pattern matching
                $pattern = $field['pattern'];
                $matchingFields = array_filter($availableFields, function ($name) use ($pattern) {
                    return preg_match($this->patternToRegex($pattern), $name);
                });

                // Check if number of matching fields is within bounds
                $count = count($matchingFields);
                if ($count >= $field['min'] && $count <= $field['max']) {
                    $validFields = array_merge($validFields, $matchingFields);
                }
            }
        }

        return array_unique($validFields);
    }

    /**
     * Convert simple pattern to regex
     * Supports '*' wildcard and '{n}' numbered placeholder
     */
    private function patternToRegex(string $pattern): string
    {
        $pattern = preg_quote($pattern, '/');
        $pattern = str_replace('\*', '.*', $pattern);
        $pattern = preg_replace('/\\\{(\d+)\\\}/', '$1', $pattern);

        return '/^' . $pattern . '$/';
    }

    /**
     * Get the original field patterns configuration
     */
    public function getDataFieldNames(): array
    {
        return $this->dataFieldNames;
    }
}
