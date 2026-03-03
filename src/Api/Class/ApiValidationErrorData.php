<?php

namespace Wexample\SymfonyApi\Api\Class;

final class ApiValidationErrorData implements ApiErrorDataInterface
{
    public const string KIND_VALIDATION_COLLECTION = 'validation_collection';

    /**
     * @var array<int, array{
     *     code: string,
     *     path: string,
     *     message?: string,
     *     meta?: array
     * }>
     */
    private array $issues = [];

    /** @var array<string, array<int, string>> */
    private array $fieldSummary = [];

    /** @var array<int, string> */
    private array $globalSummary = [];

    public static function create(): self
    {
        return new self();
    }

    public function addIssue(
        string $code,
        string $path = '',
        ?string $message = null,
        array $meta = []
    ): self {
        $issue = [
            'code' => $code,
            'path' => $path,
        ];

        if (null !== $message && '' !== $message) {
            $issue['message'] = $message;
        }

        if (! empty($meta)) {
            $issue['meta'] = $meta;
        }

        $this->issues[] = $issue;

        if ('' === $path) {
            $this->globalSummary[] = $code;
        } else {
            if (!isset($this->fieldSummary[$path])) {
                $this->fieldSummary[$path] = [];
            }

            $this->fieldSummary[$path][] = $code;
        }

        return $this;
    }

    public function addGlobalIssue(
        string $code,
        ?string $message = null,
        array $meta = []
    ): self {
        return $this->addIssue(
            code: $code,
            path: '',
            message: $message,
            meta: $meta
        );
    }

    public function addFieldIssue(
        string $path,
        string $code,
        ?string $message = null,
        array $meta = []
    ): self {
        return $this->addIssue(
            code: $code,
            path: $path,
            message: $message,
            meta: $meta
        );
    }

    public function toArray(): array
    {
        return [
            'kind' => self::KIND_VALIDATION_COLLECTION,
            'issues' => $this->issues,
            'summary' => [
                'global' => array_values(array_unique($this->globalSummary)),
                'fields' => $this->fieldSummary,
                'count' => count($this->issues),
            ],
        ];
    }
}

