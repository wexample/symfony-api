<?php

namespace Wexample\SymfonyApi\Api\Class;

use Wexample\SymfonyApi\Enum\BatchItemOutcome;

/**
 * The per-item answer to a batch: index, key, outcome, and the result or the
 * errors of each item.
 */
final class BatchReport
{
    /**
     * @var list<array{index: int, key: ?string, outcome: string, result?: mixed, errors?: array}>
     */
    private array $items = [];

    public function add(
        int $index,
        ?string $key,
        BatchItemOutcome $outcome,
        mixed $result = null,
        ?array $errors = null
    ): void {
        $item = [
            'index' => $index,
            'key' => $key,
            'outcome' => $outcome->value,
        ];

        if (null !== $result) {
            $item['result'] = $result;
        }

        if (null !== $errors) {
            $item['errors'] = $errors;
        }

        $this->items[] = $item;
    }

    /**
     * @return list<array{index: int, key: ?string, outcome: string, result?: mixed, errors?: array}>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @return array<string, int> Every outcome, zero included.
     */
    public function getSummary(): array
    {
        $summary = array_fill_keys(array_map(fn (BatchItemOutcome $outcome) => $outcome->value, BatchItemOutcome::cases()), 0);

        foreach ($this->items as $item) {
            $summary[$item['outcome']]++;
        }

        return $summary;
    }
}
