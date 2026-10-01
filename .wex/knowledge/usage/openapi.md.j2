## OpenAPI documents

The package does not generate documentation itself: `nelmio/api-doc-bundle` does, from the routes and the DTOs, and this package teaches it what its own attributes mean. Install it and the bridge — `ApiRouteDescriber` — registers itself; without it, nothing changes.

```bash
composer require nelmio/api-doc-bundle
```

For every route of an `AbstractApiController` subclass, the bridge adds to what nelmio reads on its own:

| From | To the operation |
|---|---|
| `#[ValidateRequestContent(dto: X::class)]` | the JSON request body, `$ref` to the schema of `X` — types and Symfony constraints read by nelmio |
| `#[QueryOption…]` | one query parameter each: name, type, required, default |
| `#[ApiBatch(itemDto: X::class, maxItems: 15)]` | the batch body `{items: [{key, data: X}]}`, the `ApiBatchResponse` report, the `422` |
| every operation | `200` in the success envelope (`ApiSuccessResponse`), `400` in the error envelope (`ApiErrorResponse`) when it takes a body or query options |
| `openapi.bearer_paths` | the `machineToken` bearer scheme, `401` with `WWW-Authenticate`, `429` with `Retry-After` |
| `versions.<v>.deprecation` | `deprecated: true`, the dates in the description, `Deprecation` and `Sunset` on every response |

`#[ApiBatch]` only documents: `BatchReceiverService` is called inside the method, where no generator can see it — keep its `itemDto` and `maxItems` the same as the call's.

Declare `methods` on every API route: nelmio documents a route without them under every HTTP verb.

## One document per version

```yaml
# config/packages/nelmio_api_doc.yaml
nelmio_api_doc:
  documentation:
    openapi: 3.1.0
    info: { title: Device API, version: 1.0.0 }
  areas:
    default:
      path_patterns: ['^/api/device/(?!v\d+/)']
    v1:
      path_patterns: ['^/api/device/v1/']
      documentation: { info: { title: Device API, version: v1 } }
    v2:
      path_patterns: ['^/api/device/v2/']
      documentation: { info: { title: Device API, version: v2 } }

# config/packages/wexample_symfony_api.yaml
wexample_symfony_api:
  openapi:
    bearer_paths: ['^/api/device/']
```

Writing the files:

```bash
php bin/console nelmio:apidoc:dump --area=v1 --format=json > docs/api/v1.json
php bin/console nelmio:apidoc:dump --area=v2 --format=yaml > docs/api/v2.yaml
```

## Serving them, or not

nelmio serves nothing unless its routes are imported. An API description is reconnaissance material: import them in development only.

```yaml
# config/routes/nelmio_api_doc.yaml
when@dev:
  app.swagger_ui:
    path: /api/doc/{area}
    methods: GET
    defaults: { _controller: nelmio_api_doc.controller.swagger_ui, area: default }
  app.swagger:
    path: /api/doc/{area}.json
    methods: GET
    defaults: { _controller: nelmio_api_doc.controller.swagger, area: default }
```

The files written by the command are what goes to a third party.

## What the tests hold it to

In the package's fixture application: each document validates against the official OpenAPI 3.1 schema; every API route appears once, and nothing else; v1 and v2 produce two documents, the deprecated one marked; and requests built from nothing but the documents — the smallest body each schema accepts — are accepted by the routes they describe.

## Not covered

Response bodies past the envelope: `data` is an open object, since a controller returns what it builds. Describe it with nelmio's own `#[OA\Response]` where a client needs its shape. The `/api/` page of the package lists the routes from the router as before.
