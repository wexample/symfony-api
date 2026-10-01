## Versions in the path

An API version is a segment of the endpoint's path: `/api/device/v1/echo`, `/api/device/v2/echo`. It shows in proxy logs, in the journal and in the route itself, and two versions of one endpoint are two routes — each with its own controller and its own DTOs:

```php
namespace App\Api\Controller\V1;

#[Route(path: '/api/device/v1/', name: 'api_device_v1_')]
class EchoApiController extends AbstractApiController
{
    #[Route(path: 'echo', name: 'echo', methods: ['POST'])]
    #[ValidateRequestContent(dto: \App\Api\Dto\V1\EchoDto::class)]
    public function echo(EchoDto $content): ApiResponse { … }
}
```

`App\Api\Controller\V2\EchoApiController` does the same on `/api/device/v2/` with `App\Api\Dto\V2\EchoDto`. `#[ApiEndpoint(version: 'v2')]` on an entity has filestate scaffold its controller in the matching versioned directory, which must exist beforehand.

`ApiVersionHelper::fromPath()` reads the version back — the first `/v<number>/` segment — for whoever needs it.

## Deprecating a version

```yaml
wexample_symfony_api:
  versions:
    v1:
      deprecation: '2026-09-01'                      # any date DateTimeImmutable reads, UTC
      sunset: '2027-03-01'
      link: 'https://example.com/api/v1-sunset'      # optional
```

Every response under a `/v1/` path — successes, validation errors and authentication refusals alike, so an old client with a stale token learns it too — then carries:

```
Deprecation: @1788220800                                   (RFC 9745)
Sunset: Mon, 01 Mar 2027 00:00:00 GMT                      (RFC 8594)
Link: <https://example.com/api/v1-sunset>; rel="deprecation"; type="text/html"
```

A version left out of `versions`, or with no dates, sends none. Turning the version off after its sunset is removing its routes.

## In the journal

The version reaches `MachineSecurityEvent` (`extra.api_version`) and `ApiBatchEvent` (`api_version`), so the spread of client versions in the field can be read from the logs.

## Not covered

Schemas and generated TypeScript: `api:export:entities` exports entities, which have no version — the versioned contract is the DTOs of each version's controllers, and generating clients from them belongs to `@wexample/js-api`.
