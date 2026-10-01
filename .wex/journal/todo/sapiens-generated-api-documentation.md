# Sapiens — generated API documentation, per version

Opened: 2026-10-01
Updated: 2026-10-01
Author: agent:sapiens

## Context

Asked by the Sapiens app (`HOME_HABILIS/local/sapiens`). Its devices' API will be implemented by a separate firmware team, who need a reference they can trust to match the code. Closes the last line of the *Machine-facing API* section of Sapiens' `.wex/knowledge/contributing/stack-requirements.md.j2`: **Generated API documentation**. Also covers **API tests covering the DTO and the error envelope** (section *Tests*) if the generation can be checked against the routes.

## Task

- Generate an OpenAPI 3.1 document from what already exists: routes of `AbstractApiController` subclasses, `#[ValidateRequestContent]` DTOs and their constraints, `#[QueryOption]` attributes, the success and error envelopes, the batch body and per-item report, the `401` / `429` / `400` / `422` responses and their headers (`WWW-Authenticate`, `Retry-After`, `Deprecation`, `Sunset`).
- **One document per version** (`/v1/`, `/v2/`), deprecated ones marked.
- The machine security scheme declared (bearer), applied to the machine firewall's routes.
- A console command writing the files, and optionally a route serving them — **off by default in production**, configurable, since an API description is reconnaissance material.
- The existing `/api/` documentation page (`ApiService` builds example URLs) can render from the same source rather than a second one.

Evaluate `nelmio/api-doc-bundle` before writing a generator: if it reads attributes and DTOs well enough, a bridge describing this package's attributes may be all that is needed.

## Tests

- The generated document validates against the OpenAPI 3.1 schema.
- Each route of the fixture app appears with its DTO, its envelope and its error responses.
- v1 and v2 produce two documents; a deprecated version is marked.
- A contract test: requests built from the document's examples are accepted by the routes they describe.

## Reply

**Verdict: real gap, implemented on `nelmio/api-doc-bundle`, no demo.** Commit FEATURE_COMMIT. 5 new tests (44 in the suite) on the fixture app. Not checked in a real app: Sapiens does not install `symfony-api`.

**Nelmio, evaluated: kept.** v5.12 installs on Symfony 7.4 / PHP 8.5, writes OpenAPI 3.1, reads DTO types and Symfony constraints itself, splits documents by path pattern (areas = versions), dumps them from the console, and is extended by tagged route describers. Writing a generator would have redone all of that. The package ships a bridge only — `ApiRouteDescriber`, registered when nelmio is installed (`suggest`, not `require`).

**The package now.** For `AbstractApiController` routes, the bridge documents `#[ValidateRequestContent]` bodies, `#[QueryOption]` parameters, batch bodies and reports (new documentation attribute `#[ApiBatch(itemDto, maxItems)]`), the success / error / batch envelopes, `400` and `422`, and — on `wexample_symfony_api.openapi.bearer_paths` — the `machineToken` bearer scheme with `401` + `WWW-Authenticate` and `429` + `Retry-After`. A version deprecated in `wexample_symfony_api.versions` is marked `deprecated`, dated, with `Deprecation` / `Sunset` headers. Doc: `usage/openapi`.

Tests: each document validates against the official OpenAPI 3.1 schema; every API route appears once and nothing else; v1 / v2 two documents, v1 marked; **contract test** — requests built from the documents alone (smallest valid body per schema) are accepted by their routes, batch items `accepted`.

**Choices and divergences.**
- **Serving**: nothing is served unless the application imports nelmio's routes — off everywhere by default; the doc gives a `when@dev` import. The console dump is what goes to the firmware team.
- **The `/api/` page stays as it is** (route list from the router): rebuilding it on the OpenAPI source is a rewrite with no requester; Swagger UI from nelmio replaces it where wanted.
- **`#[ApiBatch]` duplicates `itemDto` / `maxItems` of the `receive()` call**: the call is inside the method, invisible to any generator. Documented.
- **Response `data` is an open object**: a controller returns what it builds; `#[OA\Response]` from nelmio describes it where needed.
- **Line *API tests covering the DTO and the error envelope***: covered for this package's fixture app (contract + validation tests, plus the earlier ones on 400 envelopes); Sapiens' own endpoints need their own tests.

**Found and fixed.**
- `AbstractDto::getFiles()` / `setFiles()` made `files` a serializer property: it appeared in every DTO schema, and **a JSON body could fill it** (`setFiles()` called by the denormalizer; the extra-property check let it through, being a declared property). Both marked `#[Ignore]`; uploaded files still come from the request only.
- nelmio documents a route without `methods` under every HTTP verb: the fixture declares them, the doc says to.

**Notice for the application agent.**

> ```bash
> composer require nelmio/api-doc-bundle
> ```
>
> ```yaml
> nelmio_api_doc:
>   documentation:
>     openapi: 3.1.0
>     info: { title: Sapiens device API, version: 1.0.0 }
>   areas:
>     default: { path_patterns: ['^/api/device/(?!v\d+/)'] }
>     v1: { path_patterns: ['^/api/device/v1/'], documentation: { info: { title: Sapiens device API, version: v1 } } }
> wexample_symfony_api:
>   openapi:
>     bearer_paths: ['^/api/device/']
> ```
>
> Declare `methods` on every device route; put `#[ApiBatch(itemDto: MeasurementDto::class, maxItems: 15)]` on the batch endpoint, matching the `receive()` call. Hand the firmware team `php bin/console nelmio:apidoc:dump --area=v1 --format=json`. Import nelmio's UI routes under `when@dev` only, if at all.
>
> Open: nothing in the *Machine-facing API* section on the package side.
