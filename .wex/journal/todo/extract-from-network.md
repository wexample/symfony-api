# Finish API v2 extraction from network: paginated entity lists, filter tags, yearly responses, display formats

Opened: 2026-09-24
Updated: 2026-09-24
Author: agent:archeology

## Read this first — status of this todo

> **This is a proposal for discussion, not an order to code.** It was written by the 2026-09 network archaeology pass. Read it, then discuss it with the owner: every design choice and recommendation below is to be challenged and validated **before** any code is written. Do not start implementing on your own.
>
> - Context: `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/local/network/.wex/knowledge/readme/archeology/index.md.j2` (entry point, order between packages), then `sources.md.j2` (where the legacy code lives: archive repo, branch checkouts, GitLab issues) and the domain page linked below.
> - Pending owner decisions affecting this work are listed in `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/local/network/.wex/knowledge/readme/archeology/recap.md.j2`, section "Décisions qui t'attendent". Where this todo assumes an answer, treat it as an open question.
> - Safety: `NETWORK/local/network` runs on **production data** (real bookkeeping, real invoices in `var/`, a prod dump in `.wex/mysql/dumps/`) — read its code only, never run anything against it. Anonymize any fixture taken from network (bank exports, FEC, mails contain real names/accounts). Never copy secrets found in its history (Stripe keys, tokens, passwords, private keys).

## Goal

network's own API framework ("API v2", issue #311, replaced API Platform on develop-131-fos-user) is largely extracted here (query-option attributes, validation subscriber, ApiResponse, DTOs). Remaining generic pieces: the generic paginated/filterable entity list, the filter-tag mechanism (option exists, nothing consumes it), year-filtered responses, and a decision about display formats (small/medium/large/line/pad).

Knowledge: `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/local/network/.wex/knowledge/readme/archeology/already-extracted-check.md.j2` (section API). Issues: `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/gitlab/issues/311.md`, `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/gitlab/issues/051.md`, `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/gitlab/issues/164.md`, `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/gitlab/issues/184.md`, `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/gitlab/issues/158.md` (lazy loading lists), `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/gitlab/issues/261.md` (error handling), `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/gitlab/issues/219.md` (inject repositories). Wiki: `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/gitlab/wiki/` (API page).

## Best sources

- `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/trees/develop/src/Wex/BaseBundle/Api/Controller/AbstractApiEntityController.php` and `AbstractApiController.php` (develop: same code as fos-user but renamed and already on package namespaces — start here)
- `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/trees/develop-131-fos-user/src/Wex/BaseBundle/Api/Controller/ApiEntityController.php` (`defaultEntityActionListApi`, `renderBuilderPaginatedView`, `countBuilderResults` = cloned COUNT query, `builderApiSearchTextFilter`, `apiResponseViewFilterable`, `entityEditField`)
- `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/trees/develop-131-fos-user/src/Wex/BaseBundle/Repository/Traits/HasFilterTagRepositoryTrait.php` + `FILTER_TAG_*` constants in `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/trees/develop-131-fos-user/src/Repository/*Repository.php`
- `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/trees/develop-131-fos-user/src/Wex/BaseBundle/Controller/Traits/HasYearlyResponseControllerTrait.php`
- `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/trees/develop-131-fos-user/src/Serializer/Normalizer/ApiNormalizer.php` + `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/trees/develop-131-fos-user/src/Api/Dto/{Small,Medium,Large,Line,Pad}/Entity/` (display formats, for the decision only)
- `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/trees/develop-131-fos-user/src/Wex/BaseBundle/Api/Controller/VirtualEntityController.php` (generic list for entities without controller)

## Steps

1. `AbstractApiEntityController::paginatedCollectionResponse(QueryBuilder, Request)`: reads `length`/`page` options, counts on a cloned builder (`COUNT(DISTINCT alias.id)`, reset orderBy), returns `apiResponseCollection` + `PaginationDto`. Kernel test with a fixture entity (41 rows, page 3 of 20).
2. Text filter: `search` option → `SearchableRepositoryTrait::querySearch*` (symfony-helpers). Test.
3. Filter tags: move `HasFilterTagRepositoryTrait` to symfony-helpers (`queryWithFilterTag(string)`, declared tags list, unknown tag → validation error), and make `FilterTagQueryOption` validate against the repository's declared tags. Test.
4. Year responses: `HasYearlyResponseControllerTrait` equivalent using `YearQueryOption` + `DateRepositoryTrait::queryDateInYear`. Test.
5. Display formats: write a short decision note in `.wex/knowledge`: either (a) resolve `<Format><Entity>Normalizer` falling back to `Default<Entity>Normalizer`, or (b) drop formats. Implement (a) only if the owner agrees (open question in the knowledge page).
6. Optional: collection PATCH helper for editable lists (network `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/trees/develop-131-fos-user/src/Service/ListItemService.php`: items with `_newLine`/`_deleted` flags).

## Do not

- Do not port `QueryStringParamConverter`/`QueryParamConverterService` (SensioFrameworkExtra ParamConverter, obsolete), `ApiControllerTrait` envelope, DataTransformers/flat DTOs (API Platform era), `Controller/System/VueEntityController`.
- Do not port the per-entity DTO lists (app-specific).

## Acceptance

- Kernel tests for pagination, search, filter tag, year; README section "list an entity collection".
