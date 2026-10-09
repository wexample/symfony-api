<?php

namespace Wexample\SymfonyApi\Helper;

/**
 * What an answer of the API must look like, whatever its route — what a smoke
 * test checks on every endpoint it calls:
 *
 * - the envelope: `type` (`success` or `error`), an integer `code`, `data`;
 * - a list: `data.items`, each item `{type, entity, metadata?, relationships?}`
 *   as `js-api-entity` reads it (`parseApiItem()`), the entity carrying its
 *   `id` — every list is a list of entities;
 * - a page: `data.pagination` with `page`, `length`, `total`, `pagesCount`,
 *   `hasMore`, as `fetchListPaginated()` reads it.
 */
final class ApiResponseNormHelper
{
    private const array PAGINATION_KEYS = ['page', 'length', 'total', 'pagesCount', 'hasMore'];

    /**
     * @return list<string> what departs from the norm; empty when it holds
     */
    public static function problems(mixed $response): array
    {
        if (! is_array($response)) {
            return ['not an object'];
        }

        $problems = [];

        if (! in_array($response[ApiHelper::KEY_RESPONSE_TYPE] ?? null, [ApiHelper::RESPONSE_TYPE_SUCCESS, ApiHelper::RESPONSE_TYPE_FAILURE], true)) {
            $problems[] = '`type` is neither success nor error';
        }

        if (! is_int($response[ApiHelper::KEY_RESPONSE_CODE] ?? null)) {
            $problems[] = 'no integer `code`';
        }

        if (! array_key_exists(ApiHelper::KEY_RESPONSE_DATA, $response)) {
            return [...$problems, 'no `data`'];
        }

        $data = $response[ApiHelper::KEY_RESPONSE_DATA];

        if (is_array($data) && array_key_exists('items', $data)) {
            if (! is_array($data['items']) || ! array_is_list($data['items'])) {
                $problems[] = '`data.items` is not a list';
            } else {
                foreach ($data['items'] as $index => $item) {
                    if ($problem = self::itemProblem($item)) {
                        $problems[] = '`data.items[' . $index . ']` ' . $problem;
                        break;
                    }
                }
            }
        }

        if (is_array($data) && array_key_exists('pagination', $data)) {
            $missing = array_diff(self::PAGINATION_KEYS, array_keys((array) $data['pagination']));

            if ($missing) {
                $problems[] = '`data.pagination` lacks ' . implode(', ', $missing);
            }
        }

        return $problems;
    }

    private static function itemProblem(mixed $item): ?string
    {
        if (! is_array($item) || array_is_list($item)) {
            return 'is not a `{type, entity}` object';
        }

        if (! is_string($item['type'] ?? null) || '' === $item['type']) {
            return 'has no `type`';
        }

        if (! is_array($item['entity'] ?? null) || array_is_list($item['entity']) && [] !== $item['entity']) {
            return 'has no `entity` object';
        }

        if (! isset($item['entity']['id']) || '' === $item['entity']['id']) {
            return 'has an `entity` without `id`';
        }

        return null;
    }
}
