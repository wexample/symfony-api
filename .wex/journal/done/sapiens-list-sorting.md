# Sapiens — sorting of list endpoints

Opened: 2026-10-01
Updated: 2026-10-01
Author: agent:main

## Context

Passed in chat by the owner, after the review of Sapiens' list endpoints todo (`HOME_HABILIS/local/sapiens/.wex/journal/todo/3991ca14c89f.md`): the Vue tables need search, sort and pagination from the API. Pagination and search are covered by the package; sorting is not. The front table is handled by another agent.

## Task

- A generic `sort` query option: a whitelist of sortable names declared on the route, a default order, several terms, `-` for descending.
- A value outside the whitelist is refused (`400`): a free sort would end in the DQL.
- A helper applying the validated order to a Doctrine `QueryBuilder`, with a stable tie-breaker so pages do not overlap.
- Documented in the OpenAPI bridge.

## Reply

**Verdict: real gap, implemented, no demo.** Commits `77bbf71` (found on the way; its `AbstractApiController` part rides with the feature) and `44a3ed5`. 7 new tests (51 in the suite) on the fixture app. Not checked in Sapiens.

**The package now.** `#[SortQueryOption(allowed: ['lastName', 'establishment' => 'establishment.name'], default: 'lastName', tieBreaker: 'id')]` and `AbstractApiController::applyQueryOptionSort($request, $queryBuilder)`. `?sort=a,-b`: several terms, `-` descending; a value outside the whitelist — or an array — is a `400` listing the allowed names; only declared expressions reach the DQL. The order is replaced, and ends on the tie-breaker (`id` by default) so pages never overlap on equal values — checked on the DQL, since SQLite hides the problem. Documented by the OpenAPI bridge. Doc: `usage/list-endpoints` (pagination, search, sort together).

**Found and fixed.** `Request::get()`, removed in HttpFoundation 8, was called by `QueryOptionConstrainedTrait::getRequestValue()` — so `getQueryOptionValue()` and **`getQueryOptionPagination()` crashed with a 500** on Symfony 8 components — and by the date filter and the test controller. Replaced by `ApiHelper::getRequestParameter()` (attributes, then query, then body, as before).

**Notice for the application agent.**

> On each list route: `#[PageQueryOption]`, `#[LengthQueryOption]`, `#[SearchQueryOption]`, `#[SortQueryOption(allowed: [...columns of the specs...], default: '...')]`; in the method, count on the same scoped query builder, then `self::applyQueryOptionSort($request, $queryBuilder)` before `setFirstResult` / `setMaxResults`, and `self::apiResponsePaginated()`. Full example: `symfony-api` → `usage/list-endpoints`.
>
> The front sends `?sort=column` / `?sort=-column` through `fetchListPaginated({query: {sort, search}})`; an undeclared name is a `400`, so the table must only offer the columns the route allows.
