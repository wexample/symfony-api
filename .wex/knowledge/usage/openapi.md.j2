## OpenAPI documents

The package does not generate documentation itself: `nelmio/api-doc-bundle` does, from the routes and the DTOs, and this package teaches it what its own attributes mean. Install it and the bridge — `ApiRouteDescriber` — registers itself; without it, nothing changes.

```bash
composer require nelmio/api-doc-bundle
```

## What is described without writing anything

For every route of an `AbstractApiController` subclass:

| From | To the operation |
|---|---|
| `#[ValidateRequestContent(dto: X::class)]` | the JSON request body, `$ref` to the schema of `X` — types and Symfony constraints read by nelmio |
| `#[QueryOption…]` | one query parameter each, typed from its constraint: `integer`, `number`, `boolean`, an `enum` for a `Choice` (`display-format`), a `pattern` for dates (`Y` to `Y-m-d H:i:s`), the allowed names for `sort` |
| `#[ApiBatch(itemDto: X::class, maxItems: 15)]` | the batch body `{items: [{key, data: X}]}`, the `ApiBatchResponse` report, the `422` |
| every operation | `200` in the success envelope, `400` in the error envelope when it takes a body or query options |
| errors | `ApiErrorResponse.data` and each rejected batch item carry the `ApiValidationErrorData` schema: `errorCode`, `kind`, `issues` (`code`, `path`, `message`), `summary` |
| the firewall covering the route | read from the security configuration: an `access_token` firewall gives the `machineToken` bearer scheme, `401` with `WWW-Authenticate` and — when the rate limit is on — `429` with `Retry-After`; a session firewall gives the `sessionCookie` scheme (the session cookie's name), `401` and `403`; an open route, none |
| `versions.<v>.deprecation` | `deprecated: true`, the dates in the description, `Deprecation` and `Sunset` on every response |

`openapi.bearer_paths` still forces the bearer description on paths whose firewall cannot tell it, a custom authenticator for instance.

Declare `methods` on every API route: nelmio documents a route without them under every HTTP verb.

## What takes one line per route

The `data` of a success response: the controller returns an `ApiResponse`, whose content no generator can see. Declare it, and its class is described from its properties as a body DTO is:

```php
#[ApiResponseData(PatientRowDto::class, paginated: true)]   // data: {items: [PatientRowDto], pagination}
#[ApiResponseData(AccountDto::class)]                       // data: AccountDto
#[ApiResponseData(MeasurementDto::class, collection: true)] // data: {items: [MeasurementDto]}
```

`paginated` matches `apiResponsePaginated()`, `collection` matches `apiResponseCollection()`. A route that builds its rows with a normalizer needs a class describing those rows — the declaration documents, it does not check what the controller returns.

## What stays manual

- **Realistic examples.** Swagger UI, Redoc and Scalar build examples from the schemas — valid, typed, within the constraints — but meaningless. A meaningful one is written with nelmio's own `#[OA\…]` attributes.
- **Callback constraints.** A rule written as code (`Callback`, a custom validator) has no OpenAPI form; say it in the property's description.

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
    app:
      path_patterns: ['^/api/app/']
    v1:
      path_patterns: ['^/api/device/v1/']
      documentation: { info: { title: Device API, version: v1 } }
```

```bash
php bin/console nelmio:apidoc:dump --area=v1 --format=json > docs/api/v1.json
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

In the package's fixture application: each document validates against the official OpenAPI 3.1 schema; every API route appears once, and nothing else; the security of each route matches its firewall; declared response data, pagination, validation errors and query formats are described; v1 and v2 produce two documents, the deprecated one marked; and requests built from nothing but the documents — the smallest body each schema accepts — are accepted by the routes they describe, on the machine firewall and on the session one.
