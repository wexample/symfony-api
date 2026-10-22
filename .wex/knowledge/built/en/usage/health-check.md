## The endpoint

`GET /api/health` tells a load balancer, an orchestrator or an uptime monitor whether the application can work: `200` when every probe passes, `503` when one fails. It is routed only where the application imports it:

```yaml
# config/routes/wexample_symfony_api.yaml
wexample_symfony_api_health:
  resource: '@WexampleSymfonyApiBundle/Resources/config/routes_health.yaml'

# config/packages/security.yaml — before any rule covering /api/
security:
  access_control:
    - { path: ^/api/health$, roles: PUBLIC_ACCESS }
```

```json
{"type": "success", "code": 200, "data": {
  "status": "ok",
  "checks": {"database": {"status": "ok", "durationMs": 2}}
}}
```

A failing probe gives `503`, `"type": "error"`, `"message": "Unhealthy."`, the same `data` with `"status": "fail"` on the whole and on that probe. Every probe runs even when an earlier one failed. The response carries `Cache-Control: no-store`.

**What failed is never sent.** The exception of a failing probe may quote a host, a port, a DSN: it is logged at `error` (`Health check failed: {check}.`, with the exception), and the response only says `fail`.

## Probes

| Probe | Shipped by | Checks |
|---|---|---|
| `database` | this package | the default Doctrine connection answers its platform's dummy select |

The application answering the request is itself the check of the application: there is no `app` probe.

A probe is any autoconfigured service implementing `HealthCheckInterface` — an application's, or another package's for what it ships (`symfony-messenger` for its transport):

```php
class SearchIndexHealthCheck implements HealthCheckInterface
{
    public function getName(): string
    {
        return 'search';
    }

    public function check(): void
    {
        $this->client->ping();   // throws when the index is down
    }
}
```

`check()` returns when the dependency answers and throws otherwise; its name is its key under `checks`.

## Not covered

- **Timeouts.** A probe that hangs makes the endpoint hang; the caller's own timeout is what reports it. Keep each probe to one cheap round trip.
- **Liveness and readiness apart.** One endpoint answers both; an orchestrator that restarts on a failed liveness probe should point it at a route that checks nothing, or a database outage restarts every container.
- **Details for an operator.** No authenticated variant lists the failure reasons: read the logs.
- The OpenAPI bridge documents the `200`, not the `503`.
