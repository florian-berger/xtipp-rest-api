# Contributing

Thank you for helping to improve the REST API for xTIPP! This file explains how to report problems, how to suggest changes, and what a pull request should look like.

## Before you start

- **Security problems are never reported as public issues.** Use a [private security advisory](https://github.com/florian-berger/xtipp-rest-api/security/advisories/new) instead, for example for a leaked or guessable key, a way to call the API without a valid key, or access to data of other users, of hidden pages or of leagues which are not available.
- **Questions about installation or usage** are answered fastest in the [plugin thread in the xTIPP forum](https://xcript.de/forum/index.php?thread/9447-restful-api-version-0-0-1/) or on [Discord](https://ultgmng.de/discord).
- **Bugs of xTIPP itself** (they also happen without this plugin) belong to the [xTIPP developers](https://xcript.de/).
- Search the [existing issues](https://github.com/florian-berger/xtipp-rest-api/issues) first. A comment with more details on an existing issue helps more than a duplicate.

## Issues

Pick the matching template (bug report, feature request, xTIPP compatibility, documentation) and fill it out completely. Good bug reports contain:

- the versions of the plugin, xTIPP and PHP,
- the method and path of the call, the status code and the answer of the API,
- the steps to reproduce the problem, what you expected and what happened instead,
- the matching lines from the log of Laravel (`storage/logs/`).

> [!WARNING]
> Remove all secrets before you post logs, `curl` commands or screenshots: API keys (`xtk_...`), the `Authorization` header, the `APP_KEY` and personal data of your users.

For larger changes, please open an issue (or comment on one) **before** you start coding, so we can agree on the approach first. That saves you work on a pull request that cannot be merged as it is.

## Ground rules of the plugin

These rules come from the plugin system of xTIPP and from the design of this plugin. A pull request that breaks one of them cannot be merged.

- **No xTIPP file is changed and no view of xTIPP is overridden.** Only the official hook points of xTIPP are used, so the plugin survives updates of xTIPP.
- **No Composer packages and no build step.** Plugins cannot bring their own dependencies; use what xTIPP and Laravel already provide. Swagger UI is the only bundled third-party code and stays unchanged.
- **The API follows the rules of the website.** Only available leagues, only visible pages (`rest-api.page`), the same deadlines and validation as the tip and bonus pages. Of other users, only `id` and display name are returned, never e-mail addresses.
- **The API stays stateless.** No session, no cookie, no CSRF; the user is identified only by the API key.
- **Respect the Content-Security-Policy.** No inline scripts and no event attributes like `onclick`. Scripts and styles are files in `resources/` that are delivered by `DocsController::ASSETS`, never loaded from external hosts.
- **The interface is in German**, like xTIPP. All texts of the key page go into `resources/lang/de/ui.php`, not directly into views or controllers. The API, its OpenAPI description, code, comments, commit messages and documentation are in English.
- **Database changes** are made with a new migration in `database/migrations/`, never by editing a migration that was already released. Tables keep the prefix `plugin_rest_api_`, and `down()` must undo `up()`.

## Development setup

1. Set up a local xTIPP installation (the version listed under `requires.xtipp` in `plugin.json`).
2. Clone your fork and link or copy it to `plugins/rest-api/` of that installation.
3. Activate the plugin under **Konfiguration → Erweiterungen**, which runs the migrations.
4. Log in, create a key on `/api-schluessel` and try the calls in Swagger UI (`/api/docs`) or with `curl`.

The setting `require_https` is on by default. Use HTTPS locally as well, or disable the setting only on your development installation. Never use keys of a live site for development, and never commit them.

## Code style

Follow the style of the existing code:

- PHP 8.4, 4 spaces for indentation, opening braces on the same line, lines up to about 120 characters.
- Namespace `FlorianBerger\RestApi\`, one class per file, PSR-4 under `src/`.
- Type declarations for parameters and return values.
- A docblock for every class and every method that explains **why** it exists, not only what it does, with `@param` and `@return`.
- Controllers stay thin; logic that can be reused or tested goes into `src/Services/`, the JSON shape of the answers into `Presenter`.
- Validate every input of a request with the validator of Laravel, and use Eloquent or the query builder with bindings, never raw SQL with concatenated values.
- Blade escapes with `{{ }}`. Use `{!! !!}` only for content that cannot contain user input, and explain in a comment why it is safe.

## Security-relevant changes

A key has the full rights of its user, so changes in these areas need extra care:

- `src/Http/Middleware/AuthenticateApiKey.php`: the lookup of the key, the checks of the user (active, deleted, access to the API), maintenance mode and the rate limits.
- `src/Services/ApiKeyService.php` and `src/Models/ApiKey.php`: generation and hashing of the keys, expiry and revocation.
- `src/Services/ApiAccess.php`: who may use the API (setting `allowed_users`).
- `src/Http/Middleware/RequireHttps.php`, `WithoutCookies.php` and `RequirePage.php`: HTTPS, cookies and the visibility of pages.
- `src/Http/Controllers/ApiKeyPageController.php`: the new key is shown only once and never written to the session.

For changes there, please describe in the pull request which attack you considered and how you tested it, for example with an unknown, revoked or expired key, the key of an inactive user or of a user without access, a call over plain HTTP, or a hidden page. Unknown and invalid keys must all get the same `401` answer, so nothing is revealed about a key. Never log keys or the `Authorization` header, and never weaken a check to make a client work.

## API changes

- New endpoints are added below `/api/v1` in `routes/api.php`, with the middleware of the page of the website that shows the same content.
- Every change of a path, parameter, field or status code is also made in `resources/openapi/openapi.json`, and the endpoint table in the README is updated.
- Existing fields are not renamed or removed, and their meaning is not changed: clients depend on them. A breaking change needs a new version of the API and an issue first.

## Pull requests

1. Fork the repository and create a branch from `main`, named after the issue, for example `feature/12-restrict-api-access` or `fix/15-ranking-filter`.
2. Keep a pull request focused on one topic. Unrelated refactorings or formatting changes belong in a separate pull request.
3. Write commit messages in the imperative mood ("Add bonus endpoints", not "Added …"), with a short subject and, if needed, a body that explains the reason for the change.
4. Update the `README.md` if you change behaviour, settings, routes or requirements.
5. Do not change the version in `plugin.json`; it is set by the release workflow.
6. Fill out the description of the pull request: what changed, why, which issue it closes (`Closes #12`) and how you tested it.

Pull requests are merged with **squash and merge**, so the history of `main` keeps one commit per change.

### Checklist

Before you open the pull request, check that:

- [ ] the changed endpoints were tested manually, with a valid key and with an unknown, revoked and expired key,
- [ ] the rules of the website still apply: hidden pages, leagues which are not available, deadlines,
- [ ] the key page and Swagger UI work and show no CSP errors in the browser console,
- [ ] there are no PHP errors or warnings in the log of Laravel,
- [ ] the OpenAPI description matches the API (open it in Swagger UI and try the changed calls),
- [ ] all new texts of the key page are in `resources/lang/de/ui.php`,
- [ ] no keys, secrets or personal data are part of the commits,
- [ ] the README is up to date.

There are no automated tests yet. A pull request that adds some (for example for `ApiKeyService` or `AuthenticateApiKey`) without new dependencies is very welcome.

## Compatibility with new xTIPP versions

The plugin supports exactly the xTIPP versions between `requires.xtipp` and `requires.xtipp_max` in `plugin.json`, because it uses internal classes of xTIPP. When a new version of xTIPP is released, compare these classes, the hook points, the CSP and the page settings with the previous version, and use the template **xTIPP compatibility** to report what has to change.

## Releases

Releases are made by the maintainer: a tag `vX.Y.Z` on `main` starts the workflow, which writes the version to `plugin.json`, builds the ZIP and creates a draft release. Versions follow [Semantic Versioning](https://semver.org/).

## AI-assisted contributions

AI tools may be used, as they were for this plugin. You are still responsible for every line you submit: understand it, test it, and say in the pull request that AI assisted. Especially for the security-relevant parts, generated code must be reviewed as carefully as code written by hand.

## License

By contributing, you agree that your contribution is licensed under the [MIT License](LICENSE) of this project.
