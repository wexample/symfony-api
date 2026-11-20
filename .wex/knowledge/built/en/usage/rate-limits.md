## Who is limited, by what

Every call to the api is counted against a limit, and its answer says where the caller stands:

- **a call signed by a token**: its kind's `rate_limit.client` (`usage/machine-authentication`, `usage/user-tokens`), or the plan its holder names (below);
- **a call carrying no token**, under `anonymous_rate_limit.paths`: a limit per address, made of one window or several — a call passes when none is spent.

Past a limit the answer is a `429` in the api's envelope (`Too many requests.`) with `Retry-After`, the same for a token as for an address.

```yaml
# config/packages/wexample_symfony_api.yaml
wexample_symfony_api:
  anonymous_rate_limit:
    enabled: true                     # by default
    paths: ['^/api/']                 # by default
    excluded_routes: ['api_health']   # by default: the probes are not callers
    windows:                          # by default
      - { limit: 60, interval: '1 minute' }
      - { limit: 2000, interval: '1 day' }

  rate_limit_plans:                   # none by default
    free:
      - { limit: 60, interval: '1 minute' }
      - { limit: 10000, interval: '1 day' }
    paid:
      - { limit: 600, interval: '1 minute' }
```

A call the firewall refuses — an api the application keeps private, a token refused — never reaches the anonymous limit: it is answered before.

## Plans

A token's holder implementing `ApiRateLimitPlanHolderInterface` names its plan, one of `rate_limit_plans`, from `getApiRateLimitPlan()`; null keeps its kind's limit. Each plan is a limiter of the framework's, `api_plan_<name>`, counted per holder. `ApiRateLimitPlanService` creates them by name.

## Headers

Every answer counted against a limit carries:

- `X-RateLimit-Limit`: the window's allowance — for several windows, the one nearest to being spent;
- `X-RateLimit-Remaining`: the calls left in it;
- `X-RateLimit-Reset`: the seconds until a call is allowed again, 0 while some are left.

The limit counted is on the request, `AbstractApiTokenHandler::REQUEST_ATTRIBUTE_RATE_LIMIT`, for whatever reads it afterwards.
