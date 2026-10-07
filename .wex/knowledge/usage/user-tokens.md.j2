## A person's tokens

A user token lets a script — a historical import, an automation — call the API in the name of the person who runs it. It authenticates as that user and carries their roles, or only those of them it was given as scopes: it opens what they open, no more, and `access_control` and voters apply to it as to their session. It is presented in `Authorization: Bearer`, to a stateless firewall of its own, beside the machine one if the application has devices.

Everything that makes machine tokens safe holds for user tokens, through the same pieces (`usage/machine-authentication`): only a hash and a hint are stored, every refusal is the same `401`, two rate limits apply, the last use is written, any token in any log is replaced by its hint. What differs:

| | Machine token | User token |
|---|---|---|
| Holder | an entity implementing `MachineClientInterface` | the application's user entity |
| Roles | `machine_token.roles` only, anything else refused | the user's own, or its scopes among them |
| Abstract entity | `AbstractMachineToken` | `AbstractUserToken` |
| Service | `MachineTokenService` | `UserTokenService` |
| Firewall handler | `MachineTokenHandler`, `MachineTokenAuthenticationFailureHandler` | `UserTokenHandler`, `UserTokenAuthenticationFailureHandler` |
| Prefix | `mt_` | `ut_` |
| Journal | `machine_token.*`, channel `machine_security` | `user_token.*`, channel `user_token_security` |
| Managed from | `api:machine-token:*`, code | `api:user-token:*`, the person's session (below), code |

A machine client never holds a user token: `UserTokenService` refuses it, since it would escape the machine roles.

## Entity

```php
#[ORM\Entity]
class UserToken extends AbstractUserToken
{
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    public function getClient(): User
    {
        return $this->user;
    }

    public function setClient(UserInterface $client): static
    {
        $this->user = $client;

        return $this;
    }
}
```

Generate a migration after adding it. The package finds the user class through the one single-valued association of the token class to a `UserInterface`.

## Configuration

```yaml
# config/packages/wexample_symfony_api.yaml
wexample_symfony_api:
  user_token:
    token_class: App\Entity\UserToken   # required, null by default
    prefix: ut_
    max_lifetime: P90D                  # optional, null by default: no limit
    last_used_interval: 60
    rate_limit:
      enabled: true
      client: { limit: 3600, interval: '1 hour' }       # requests per user
      ip_failures: { limit: 30, interval: '15 minutes' }

# config/packages/security.yaml
security:
  firewalls:
    # Before the page firewall.
    scripts:
      pattern: ^/api/script/
      stateless: true
      provider: app_user_provider
      access_token:
        token_handler: Wexample\SymfonyApi\Security\UserTokenHandler
        failure_handler: Wexample\SymfonyApi\Security\UserTokenAuthenticationFailureHandler
      entry_point: Wexample\SymfonyApi\Security\UserTokenAuthenticationFailureHandler
      user_checker: App\Security\UserChecker   # the one the login form uses
  access_control:
    - { path: ^/api/script/import, roles: ROLE_IMPORTER }
    - { path: ^/api/script/, roles: ROLE_USER }
```

`max_lifetime` caps every token of the kind: one issued without an expiration gets the latest one allowed, a later one is refused. `machine_token.max_lifetime` does the same for machine tokens.

Use the login form's `user_checker`: an account switched off is refused on its tokens as on its password, journalled `client_disabled`. The limits are counted apart from the machine firewall's (`api_user_client`, `api_user_ip_failures`). With several providers, Symfony requires `provider` on each firewall even though the token handler loads the user itself.

## Scopes

A token can be limited to some of its holder's roles:

```php
$secret = $userTokenService->issue($user, label: 'nightly import', scopes: ['ROLE_USER', 'ROLE_IMPORTER']);
```

- A scope must be one of the holder's roles, the hierarchy included: a user whose `ROLE_ADMIN` implies `ROLE_IMPORTER` may give a token `ROLE_IMPORTER` alone. Anything else is refused when the token is issued.
- The request authenticated by the token carries the scopes as its roles — still expanded by the hierarchy —, so `access_control`, `#[IsGranted]` and voters read them without knowing about tokens. `$user->getRoles()` still answers all the user's roles: read `Security::isGranted()`, not the entity.
- A scope the holder has lost since is dropped at the next request.
- Include `ROLE_USER` when the firewall's paths require it, or the token opens nothing there.
- No scopes (`null`) means all the holder's roles.

## Managing them from a session

`routes_user_tokens.yaml` gives a signed-in person three endpoints over their own tokens, for a screen of the application to call:

```yaml
# config/routes/wexample_symfony_api.yaml
wexample_symfony_api_user_tokens:
  resource: '@WexampleSymfonyApiBundle/Resources/config/routes_user_tokens.yaml'

# config/packages/security.yaml — on the page firewall
security:
  access_control:
    - { path: ^/api/user-tokens, roles: ROLE_USER }
```

| Route | Does |
|---|---|
| `GET /api/user-tokens` | the caller's tokens: `id`, `hint`, `label`, `scopes`, `dateCreated`, `dateExpiration`, `dateLastUsed`, `dateRevoked`, `usable` |
| `POST /api/user-tokens` `{"label": "import script", "expiresAt": "2027-01-01", "scopes": ["ROLE_USER"]}` | `201`, `{"secret": "ut_…", "token": {…}}` — the secret once; every field optional; a past date, a date beyond `max_lifetime` or a role the person lacks is a `400` |
| `DELETE /api/user-tokens/{id}` | revokes it; someone else's token answers `404`, as a missing one |

They answer `403` to a request authenticated by a token, should the routes fall under a token firewall — a stolen token cannot mint another one or survive its revocation —, and to an account the token class cannot relate to (a machine client, a user of another provider).

From code, `UserTokenService` has the same methods as `MachineTokenService` — `issue()` and `rotate()` taking `scopes` too, `revoke()`, `revokeAll()`. From the console, `<user>` being the user's id:

```bash
php bin/console api:user-token:issue <user> [--expires="+30 days"] [--label=import] [--scope=ROLE_USER --scope=ROLE_IMPORTER]
php bin/console api:user-token:list <user>
php bin/console api:user-token:revoke <token id|hint>
php bin/console api:user-token:revoke <user> --all      # an administrator cutting a person off
```

## Not covered

- **Scopes other than roles.** A scope is a role of the holder; rights finer than roles — one entity, one action — are a voter's business, reading the request's roles.
- The OpenAPI bridge names the bearer scheme of every token firewall `machineToken`.
