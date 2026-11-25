# Mobile phone authentication

The mobile frontend is not changed in this release. All paths below use the
`/api/mobile/v1` prefix and JSON requests/responses. Email login is replaced by
phone login; clients must update before using this API contract.

## Login

`POST /auth/login`, rate limited to six requests per minute:

```json
{
  "phone": "077637949",
  "password": "existing-password",
  "device_name": "My phone",
  "fcm_token": "optional-device-token"
}
```

`+37477637949`, `37477637949` and `077637949` resolve to the same identity.
The stored canonical value is `37477637949`. The shared phone validation also
accepts common spaces, parentheses, dots and hyphens, and rejects letters.
`device_name` and `fcm_token` are optional.

Success returns HTTP 200 with `data.access_token`, `data.token_type` (`Bearer`)
and `data.person`. Invalid credentials return HTTP 422 with a `phone` error.
Existing passwords, including older passwords shorter than eight characters,
continue to work. An empty stored password cannot authenticate.

A gym link, visitor type or membership is not required. Gym blocks do not block
mobile login. Soft-deleted people cannot authenticate or be recreated through
registration. `mobile_deleted` no longer prevents login; successful login clears
it without changing the shared version, CRM status, gym links or block state.
`DELETE /me` still clears local tokens and sets this local flag. CRM active status
does not depend on `mobile_deleted`.

## Registration

`POST /auth/register`, rate limited to six requests per minute:

```json
{
  "name": "Example Person",
  "phone": "077637949",
  "birth_date": "1990-01-01",
  "password": "new-password",
  "password_confirmation": "new-password"
}
```

The public registration endpoint is served by GymCrmProd (SEV). GymCRM (SPITAK)
has the same account-reuse behavior but rejects creation of a new identity with
HTTP 503. `config/mobile.php` fixes the primary creation authority; do not enable
independent new-account creation in both applications.

For a new phone, name, phone, birth date and confirmed password are required.
The birth date cannot be in the future. New passwords require at least eight
characters. The response is HTTP 201 with the same token/person envelope as login.
Only a Person is created; no gym link, card, membership or sale is created.

If the phone exists, the supplied password must match the existing hash. A match
returns HTTP 200 using the existing Person and UUID. No personal fields, password
or gym state are overwritten. Name and birth date need not be resent in this
case. A wrong or empty stored password returns HTTP 422. This endpoint does not
set a first password for an existing passwordless account.

## Change password

`PUT /me/password` requires a Person bearer token and is rate limited to six
requests per minute:

```json
{
  "current_password": "existing-password",
  "password": "changed-password",
  "password_confirmation": "changed-password"
}
```

The current password must match. The new password must be different, confirmed
and at least eight characters long. Success is HTTP 200:

```json
{"data":{"password_changed":true}}
```

Only the authenticated person's password changes. The current token remains
valid and other local tokens are deleted in the same transaction. When the new
hash is applied in the other database, that person's tokens in that database are
also deleted. Remote revocation is eventual and depends on successful sync;
tokens are not copied between databases. Other people's tokens are unaffected.
Wrong credentials or invalid new passwords return HTTP 422 without any changes.

## Synchronization and deployment

Apply the multi-gym deployment prerequisites in `MULTI_GYM_PEOPLE.md` to both
applications before exposing this contract. No additional authentication schema
migration is needed. Shared fields use the existing Person UUID/version mapper,
after-commit Redis jobs, retries and conflict handling. New passwords are hashed
once; sync copies that hash unchanged. FCM tokens, mobile flags, notifications,
biometrics and bearer tokens remain local under the existing sync contract.

For the paired deployment, enable the existing `SYNC_ENABLED=true` master switch
on SEV and run its Redis workers and scheduler. This replaces the older
standalone-mobile recommendation to disable sync. This release does not change
the deployed environment automatically. When the master switch is off, no
cross-database persistence is promised; enabling it and recovery are deployment
prerequisites for the paired system.

Before SEV authenticates, registers or changes a password, it resolves matching
identities in both databases using the existing `Auto` Person handler. This lets
an identity present only in SPITAK be reused with its original UUID. Existing
version and collision rules apply: conflicts are recorded and return HTTP 409;
accounts are never merged automatically. An unavailable peer returns HTTP 503,
preventing creation of an independent duplicate while sync is enabled.

SEV's scheduler runs `sync:reconcile-people` every five minutes. This bounded,
UUID-paginated scan queues the existing `Auto` Person jobs for people without gym
links or with local access tokens in either database, including their tombstones.
It repairs missed events and imports shared mobile changes from SPITAK. Linked
CRM identities with no access tokens retain their existing reconciliation path.
The dashboard's
gym-scoped import and all gym-person synchronization rules are unchanged.
To queue identity recovery manually:

```sh
php artisan sync:reconcile-people
```

After deployment clear configuration/routes and restart long-running workers:

```sh
php artisan config:clear
php artisan route:clear
php artisan queue:restart
php artisan route:list --path=api/mobile
```

CRM creation now starts with an explicit phone search. Search never creates a
Person or gym link. Existing identity fields are read-only; saving the creation
form adds only the selected gym link and its gym-specific data. A person already
linked to the current gym is shown with a profile link instead of another form.
