# symfony_api

Version: 5.0.0

`wexample/symfony-api` is a Symfony bundle that gives backend developers a declarative, attribute-driven way to expose REST endpoints: controller methods return `ApiResponse`, request bodies are validated against a DTO via `#[ValidateRequestContent]`, and query parameters are typed and constrained through a family of `#[QueryOption]` attributes. It targets Symfony applications (PHP ≥ 8.2) inside the Wexample suite that need structured JSON APIs without hand-rolling serialization or validation boilerplate.

## Table of Contents

- [Architecture](#architecture)
- [Integration in the Suite](#integration-in-the-suite)
- [Dependencies](#dependencies)
- [Versioning & Compatibility Policy](#versioning--compatibility-policy)
- [License](#license)
- [About us](#about-us)
- [Migration Notes](#migration-notes)

## Architecture

The package is a Symfony bundle. Every HTTP call that touches a route owned by a subclass of `AbstractApiController` passes through the same pipeline:

1. Symfony resolves the controller.
2. `ApiEventSubscriber.onKernelController` validates query options and, when `#[ValidateRequestContent]` is present, deserialises and validates the request body into a DTO.
3. The controller method runs and returns an `ApiResponse` object.
4. `ApiEventSubscriber.onKernelView` converts that object into a `JsonResponse`.
5. If an exception escapes the controller and the request targets a subclass of `AbstractApiController`, `ApiEventSubscriber.onKernelException` catches it and renders a JSON error envelope instead of Symfony's default HTML error page.

### Bundle registration

src/DependencyInjection/WexampleSymfonyApiExtension.php loads the service definitions and reads the bundle configuration declared in src/DependencyInjection/Configuration.php. Two container parameters come out of that: `api_pretty_print` (boolean, default `false`) and `api_test_error_log_length` (integer, default `1000`). The routes shipped by the bundle are declared in src/Resources/config/routes.yaml, which auto-imports controllers from `src/Api/Controller/Test/` and `src/Controller/`.

### Event subscriber

src/EventSubscriber/ApiEventSubscriber.php subscribes to three kernel events.

**`kernel.controller`** — Two checks run in order.

*Query options.* For every `#[QueryOption*]` attribute found on the controller class or method, the subscriber reads the matching query parameter, calls `parseValue()` on it to coerce the type, validates the coerced value against the attribute's Symfony constraint, and replaces the raw query string value in `$request->query` with the parsed one. Unknown query parameters that are not declared by any attribute are rejected with a 400. Missing required options also return a 400. Non-required absent options fall back to the attribute's `default` property.

*Request body.* When a `#[ValidateRequestContent]` attribute is present, the subscriber delegates to `DtoValidationService::validateDtoFromRequest`. On success, the resulting DTO is stored in `$request->attributes` under the name declared by the attribute (default `content`).

**`kernel.view`** — When the controller returns an `ApiResponse`, the subscriber calls `toJsonResponse()` on it, applying the `api_pretty_print` parameter if the controller did not set a preference.

**`kernel.exception`** — If the failing request was routed to a subclass of `AbstractApiController`, the subscriber calls `AbstractApiController::apiResponseError` with the exception message and replaces the response. In debug mode it also serialises the stack trace into `data.trace`.

### Controller base class

src/Api/Controller/AbstractApiController.php owns all response factory methods so controller code never builds raw arrays manually.

| Method | HTTP status |
|---|---|
| `apiResponseSuccess(message, data)` | 200 |
| `apiResponseError(message, data)` | 400 |
| `apiResponseValidationError(ApiErrorDataInterface)` | 400 |
| `apiResponseCollection(items, extraInfo)` | 200 |
| `apiResponsePaginated(PaginationDto, items)` | 200 (protected) |

Each factory builds an array with the keys `type`, `code`, `message` (optional), and `data`, then wraps it in an `ApiResponse`.

`getQueryOptionValue(Request, name, default)` reads a validated query option back from `$request->attributes`—it is the intended accessor inside controller methods rather than reading `$request->query` directly.

### Response value objects

src/Api/Class/ApiResponse.php holds the data array and HTTP status code. `toJsonResponse()` serialises it to JSON, optionally pretty-printed.

src/Api/Class/AbstractApiResponseMember.php defines the canonical set of `DISPLAY_FORMAT_*` constants (`default`, `large`, `line`, `medium`, `pad`, `small`, `full`).

src/Api/Class/ApiSuccessCollectionData.php is a builder for collection payloads: `items` plus arbitrary extra keys. Pass its `toArray()` output as the `data` argument of `apiResponseSuccess`.

src/Api/Class/ApiValidationErrorData.php implements `ApiErrorDataInterface` and structures per-field and global validation issues for `apiResponseValidationError`. Its `toArray()` includes `errorCode`, `kind`, `issues`, and a `summary` with `global`, `fields`, and `count`.

### Query option attributes

src/Api/Attribute/QueryOption/AbstractQueryOption.php is the root of all typed query option attributes. It uses two traits:

- `QueryOptionTrait` — carries `$key` (the query string parameter name) and `$required`.
- `QueryOptionConstrainedTrait` — adds `$default`, `getConstraint(): Constraint`, and `getRequestValue(Request)`.

src/Api/Attribute/QueryOption/AbstractSimpleTypeQueryOption.php narrows the hierarchy: subclasses implement only `getSimpleTypeConstraint(): string`, which returns a Symfony validator `Type` string. The concrete leaf classes are `BooleanQueryOption`, `FloatQueryOption`, `IntegerQueryOption`, `StringQueryOption`, `LengthQueryOption`, `PageQueryOption`, `YearQueryOption`, `DateQueryOption`, `IdQueryOption`, `DisplayFormatQueryOption`, `FilterTagQueryOption`, `SearchQueryOption`, and `MultipleTypesQueryOption`.

src/Api/Attribute/QueryOption/CustomQueryOption.php is the escape hatch: the caller supplies the key and any `Constraint` instance directly. It is repeatable (`IS_REPEATABLE`).

`EveryQueryOption` is a special marker attribute: when it is present, the subscriber allows any query parameter through without rejecting unknown keys.

All concrete attributes carry `#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS | ...)]`; placing them on the class makes them apply to every action of that controller.

### Request body validation: #[ValidateRequestContent] and DtoValidationService

src/Api/Attribute/ValidateRequestContent.php is a method-level attribute. Its required constructor argument `dto` names the DTO class; `attributeName` (default `content`) names the request-attribute key where the validated DTO is stored; `dataFieldNames` lists the form-data field names (or glob patterns with `*` and `{n}`) that carry the JSON body in multipart uploads.

src/Service/DtoValidationService.php performs the actual work:

1. Extract JSON — from the raw body for `application/json`, or from a named form field for `multipart/form-data`.
2. `validateRawDataRecursive` — walk the raw array and assert that every property marked `#[RequiredDtoProperty]` is present; recurse into nested DTO and collection properties.
3. `validateExtraProperties` — reject keys that do not match any declared property of the DTO.
4. Optional `getConstraints()` collection — if the DTO returns one, validate the raw array against it with the Symfony validator.
5. Deserialise the array into the DTO class using the Symfony serialiser.
6. Validate the hydrated DTO object with `$validator->validate($dto)`.
7. Recurse into nested `AbstractDto` properties and array items.

Any failure throws a typed exception (`MissingRequiredPropertyException`, `ExtraPropertyException`, `InputValidationException`, `FieldValidationException`, `DeserializationException`, `FileValidationException`). The subscriber catches these through `onKernelException`.

### DTO hierarchy

src/Api/Dto/AbstractDto.php is the root. It exposes `getRequiredProperties()` (reads `#[RequiredDtoProperty]` attributes via reflection), `getConstraints()` (override to return a Symfony `Collection` constraint), `getFilesConstraints()`, `setFiles` / `getFiles`, and `toArray()` (serialises all initialised public properties by reflection).

src/Api/Attribute/RequiredDtoProperty.php is a property-level attribute with no arguments. Its presence is the contract that the field must exist in the incoming payload.

src/Api/Dto/AbstractEntityDto.php extends `AbstractDto` with a `$secureId: string` property and a `fromEntity(AbstractEntity): self` factory.

src/Api/Dto/AbstractCollectionDto.php adds `getCollectionKey()` (default `items`), `getCollectionItemDtoClass()`, `setCollectionItems`, and `getCollectionItems`. The serialiser is given an empty array for the collection key during deserialisation; `DtoValidationService` then hydrates each item by calling `createDto` recursively and calls `setCollectionItems`.

src/Api/Dto/AbstractEntityCollectionDto.php specialises `AbstractCollectionDto` with a required `$entities: AbstractEntityDto[]` property and sets the collection key to `entities`.

src/Api/Dto/PaginationDto.php is a value object constructed from `page`, `length`, and `total`. `AbstractApiController::getQueryOptionPagination` builds it from the `length` and `page` query options. `toArray()` produces `page`, `length`, `total`, `pagesCount`, and `hasMore`.

### Validator constraints

The `Validator\Constraint` namespace contains three synthetic constraints used only to produce structured violations:

- src/Validator/Constraint/MissingRequiredProperty.php — property name is missing.
- src/Validator/Constraint/ExtraProperty.php — property name is not declared in the DTO.
- `DeserializationError` / `JsonEncodingError` — type-level deserialisation errors.

Each constraint points to a matching `*Validator` class that unconditionally adds the violation; the constraint is the factory for the violation, not a live check.

### Exception hierarchy

src/Exception/AbstractApiException.php extends `AbstractException` from `wexample/symfony-helpers` and prepends `API` to the internal code parts it inherits. src/Exception/ConstraintViolationException.php wraps a `ConstraintViolationListInterface`, formats a human-readable multi-line message, and appends `CV` to the internal code.

### Helpers and services

src/Helper/ApiHelper.php centralises string constants for the JSON envelope keys (`type`, `code`, `message`, `data`), the two response type strings (`success`, `error`), and the bearer token header. It also provides `extractBearerTokenFromRequest` and `extractBearerTokenFromString`.

src/Service/ApiService.php builds an example URL for a named route by reading its path variables, translating each via the `api.<routeName>` translation domain, and prepending the current host.

src/Twig/ApiExtension.php exposes one Twig function, `api_build_example_url(routeName)`, backed by `ApiService::buildExampleUrl`.

### Pages controller

src/Controller/Pages/ApiController.php renders a human-readable index of the application's API routes. It iterates the router collection, keeps routes whose path starts with `/api/`, reads the `QueryOptionConstrainedTrait` attributes to list accepted query parameters, and passes the sorted map to the page template.

### Test utilities

src/Tests/Traits/TestCase/Application/ApiTestCaseTrait.php provides convenience methods for functional tests: `apiParseResponse`, `applicationParseResponse`, `goToSimpleRouteAndAssertSuccess`, `goToSimpleRouteAndAssertFailedTypeRestriction`, `goToSimpleRouteAndAssertErrorWhenMissing`, and `checkApiQueryOptionIntType`. The bundle also ships two test controllers under `src/Api/Controller/Test/` (`ResponseController` and `QueryOptionController`) that are registered by `routes.yaml` and serve as targets for the built-in test suite.

## Integration in the Suite

This package is part of the Wexample Suite — a collection of high-quality, modular tools designed to work seamlessly together across multiple languages and environments.

### Related Packages

The suite includes packages for configuration management, file handling, prompts, and more. Each package can be used independently or as part of the integrated suite.

Visit the [Wexample Suite documentation](https://docs.wexample.com) for the complete package ecosystem.

## Dependencies

- php: >=8.5
- wexample/php-date: >=2.0.0
- wexample/symfony-testing: >=2.0.0
- wexample/symfony-helpers: >=8.0.0
- wexample/symfony-loader: >=6.0.0
- wexample/symfony-forms: >=4.0.0
- wexample/symfony-content: >=2.0.0

## Versioning & Compatibility Policy

Wexample packages follow **Semantic Versioning** (SemVer):

- **MAJOR**: Breaking changes
- **MINOR**: New features, backward compatible
- **PATCH**: Bug fixes, backward compatible

We maintain backward compatibility within major versions and provide clear migration guides for breaking changes.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

Free to use in both personal and commercial projects.

## About us

[Wexample](https://wexample.com) stands as a cornerstone of the digital ecosystem — a collective of seasoned engineers, researchers, and creators driven by a relentless pursuit of technological excellence. More than a media platform, it has grown into a vibrant community where innovation meets craftsmanship, and where every line of code reflects a commitment to clarity, durability, and shared intelligence.

This packages suite embodies this spirit. Trusted by professionals and enthusiasts alike, it delivers a consistent, high-quality foundation for modern development — open, elegant, and battle-tested. Its reputation is built on years of collaboration, refinement, and rigorous attention to detail, making it a natural choice for those who demand both robustness and beauty in their tools.

Wexample cultivates a culture of mastery. Each package, each contribution carries the mark of a community that values precision, ethics, and innovation — a community proud to shape the future of digital craftsmanship.

## Migration Notes

When upgrading between major versions, refer to the migration guides in the documentation.

Breaking changes are clearly documented with upgrade paths and examples.
