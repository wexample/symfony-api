## A paginated, searchable, sortable list

```php
#[Route(path: 'patients', name: 'patients', methods: ['GET'])]
#[PageQueryOption]                 // ?page=0 — zero-based; negative counts from the end
#[LengthQueryOption]               // ?length=10 — 0 for everything
#[SearchQueryOption]               // ?search=…
#[SortQueryOption(
    allowed: ['lastName', 'birthDate', 'establishment' => 'establishment.name'],
    default: 'lastName',
    emptyLast: true,
)]                                 // ?sort=lastName,-birthDate
public function patients(Request $request, PatientRepository $patients): ApiResponse
{
    return self::apiResponseQueryPage(
        $request,
        $patients->createListQueryBuilderForCaller(self::getQueryOptionValue($request, 'search', null)),
        fn (array $page) => array_map(PatientListDto::fromEntity(...), $page)
    );
}
```

`apiResponseQueryPage()` counts on the query it is given (`COUNT(DISTINCT root)`, its scope and search included), applies `?sort=` when the route declares it, cuts the page, and hands the page's entities to the callable together — what each row needs from elsewhere is read once for the page. A query fetch-joining a collection cannot be cut this way. `applyQueryOptionSort()` and `getQueryOptionPagination()` remain, for a list built otherwise.

The response is the one `js-api-entity`'s `fetchListPaginated()` reads: `data.items`, and `data.pagination` = `{page, length, total, pagesCount, hasMore}`. A query parameter the route does not declare is refused with a `400`.

## Sorting

`?sort=` takes sortable names separated by commas, the first one first, `-` before a name for descending: `?sort=establishment,-birthDate`.

- **Only the declared names are accepted.** Anything else — an undeclared name, a property path, SQL, a doubled `-`, an array — is refused with a `400` whose message lists the allowed names. What the caller sends never reaches the query: each name is replaced by its declared expression.
- A name alone stands for the property of the same name on the query's root alias; `'name' => 'alias.property'` reaches a joined entity, whose join the query builder must already have.
- `default` applies when `sort` is absent; it is checked against `allowed` when the attribute is read.
- `applyQueryOptionSort()` **replaces** any order the query builder had, then appends the tie-breaker — `id` of the root alias by default, `tieBreaker: null` to drop it — unless the order already ends on it. Without it, rows of equal value may come back in a different order from one page to the next on PostgreSQL, and a row can appear on two pages or none.
- A name asked twice counts once, at its first place.
- `emptyLast: true` sends the rows with nothing to sort on to the end, whichever way the list runs: PostgreSQL puts NULL first in a descending order, and DQL has no `NULLS LAST`.

The OpenAPI bridge documents the parameter with its default and its allowed names (`usage/openapi`).

## Searching

`#[SearchQueryOption]` validates and passes the string; matching it — which columns, accents and case — is the repository's business. The count goes through the same query as the page, filters and scoping included: a total counted otherwise tells the caller how many rows exist outside what it may see.
