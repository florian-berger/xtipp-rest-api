# REST API for xTIPP

![GitHub License](https://img.shields.io/github/license/florian-berger/xtipp-rest-api?style=for-the-badge)
[![Issues](https://img.shields.io/github/issues/florian-berger/xtipp-rest-api?style=for-the-badge)](https://github.com/florian-berger/xtipp-rest-api/issues)
[![Discord](https://img.shields.io/discord/302523634075828226?label=Discord&logo=discord&style=for-the-badge)](https://ultgmng.de/discord)

A plugin for [xTIPP](https://xcript.de/), the tippgame software, that adds a stateless REST API with a Swagger UI. It is
built on the plugin system introduced in xTIPP 1.0.35, so **no file of xTIPP is changed**, and the plugin survives
updates of xTIPP.

The interface of the plugin (key page) is in German, like xTIPP itself. The API and its documentation are in English.

> **Status:** Made for and pinned to xTIPP 1.0.36 (`xtipp_max` in `plugin.json`), because it uses
> internal classes of xTIPP (`Ranking`, `Season`, `Game`, `Bonus`, `BonusTip`, `TipPointCalculation`, `SendTipConfirmationEmail`).
> If you're using the plugin, wait with the xTIPP update until this plugin is updated.

## Features

- Read leagues, seasons, matchdays/rounds with their games, your own tips, the tip overview and the rankings (single season and all-time).
- Create, change and delete your own tips, with the same rules as the tip page (deadline, no draw tips for knockout games when the extra time counts, e-mail confirmation).
- Read the bonus questions and give, change and delete your own answers, with the same rules as the bonus page (deadline, clubs/players which can be chosen, number of answers).
- Authentication with a personal API key as Bearer token. No username and password, no session, no cookie.
- Every user has one key and manages it on an own page: create, regenerate, revoke, delete. An optional expiry date can be set.
- The administrator decides who may use the API: all users, only administrators or only members of the role `API-Benutzer`.
- Swagger UI with the complete description of the API, only for logged-in users with access to the API. All calls can be tried out there.

## Installation

1. Upload the folder to `plugins/rest-api/` of your xTIPP installation. Check that **all files** arrived.
   Alternatively upload the ZIP of a release (`rest-api-<version>.zip`) in the admin area under **Konfiguration → Erweiterungen**.
   xTIPP 1.0.36 offers this only when `XTIPP_PLUGIN_UPLOAD=1` is set in its `.env` file. Uploading a newer ZIP also updates
   the plugin, and the old version is kept in `storage/app/plugin-backups/`.
2. Activate it in the admin area under **Konfiguration → Erweiterungen**. This runs the migrations, which create the table `plugin_rest_api_keys` and the role `API-Benutzer`.
3. Logged-in users with access to the API find the key page `/api-schluessel` in their profile menu ("API-Schlüssel"), no link has to be added.

Settings (admin area, **Konfiguration → Erweiterungen**, settings of the plugin):

| Setting | Default | Purpose |
|---|---|---|
| Anfragen pro Minute (`rate_limit`) | 60 | Maximum number of requests per minute and API key (1 to 10000). More requests answer `429`. |
| Nur HTTPS erlauben (`require_https`) | on | Requests over plain HTTP answer `403` before the API key is processed. Disable it only if your setup cannot pass `HTTPS=on` to PHP (see security notes). |
| API-Zugriff erlauben für (`allowed_users`) | Alle Benutzer (`all`) | Who may create keys, call the API and open Swagger UI: all users (`all`), only administrators (`admins`) or only members of the role `API-Benutzer` (`role`). See [Access to the API](#access-to-the-api). |

To switch the API off, disable the plugin.

## Pages

| Address | Who | Purpose |
|---|---|---|
| `/api-schluessel` | logged-in users (users without access to the API can only revoke and delete an existing key) | Create, regenerate, revoke and delete the own API key (entry "API-Schlüssel" in the profile menu) |
| `/api/docs` | logged-in users with access to the API | Swagger UI |
| `/api/docs/openapi.json` | logged-in users with access to the API | The OpenAPI description |
| `/api/docs/assets/{file}` | logged-in users with access to the API | CSS/JavaScript of Swagger UI and the script of the key page (the web root is `public/`, so the files of a plugin are delivered by a route; xTIPP blocks inline scripts with its Content-Security-Policy) |
| `/api/v1/...` | holders of a key | The API |

## Access to the API

The setting `allowed_users` decides who may use the API:

- **Alle Benutzer** (`all`, default): every active user.
- **Nur Administratoren** (`admins`): only users with the role `Admin`.
- **Nur ausgewählte Mitglieder** (`role`): only the users with the role `API-Benutzer`. Administrators need the role, too. The role is created when the plugin is activated; assign it in the admin area in the user management (edit a user, roles).

Users without access do not see the entry "API-Schlüssel" in the profile menu, cannot create or regenerate a key and cannot open Swagger UI (`404`). An existing key of such a user is **not deleted**: it answers `401` as long as the user has no access and works again as soon as access is allowed (setting changed or role assigned). The user can still open `/api-schluessel` by its address to revoke or delete the key.

## Authentication

1. Open `/api-schluessel` (profile menu, "API-Schlüssel") and create your key. It looks like `xtk_...` (256 bit random) and is **shown only once**.
2. Send it with every call:

```
curl -H "Authorization: Bearer xtk_..." https://your-site/api/v1/leagues
```

- A user has exactly one key. *Revoke* stops it (the entry stays visible), *regenerate* replaces the secret and starts the key again, *delete* removes it so that a new one can be created.
- A key has no expiry unless you set a date. A leaked key works until you revoke, regenerate or delete it.
- Inactive and deleted users cannot use the API, even with a valid key. The same applies to users without access to the API (setting `allowed_users`).
- Unknown, revoked and expired keys, and keys of users without access, all get the same `401` answer.

## Endpoints

All paths start with `/api/v1`. All answers are JSON, the content is in `data`. Errors have a `message` (and `errors` for invalid input).

| Method | Path | Purpose |
|---|---|---|
| GET | `/leagues` | All leagues made available by the administrator |
| GET | `/leagues/{league}/seasons` | Seasons of a league |
| GET | `/seasons/{season}/matchdays` | Matchdays and rounds of a season (`key` is the identifier below) |
| GET | `/seasons/{season}/matchdays/{matchday}` | One matchday/round with its games |
| GET | `/seasons/{season}/matchdays/{matchday}/tips` | Your own tips with points |
| GET | `/seasons/{season}/matchdays/{matchday}/overview` | Tip overview: tips of all users and distribution |
| GET | `/seasons/{season}/ranking?from=&to=` | Single ranking of a season, optionally between two matchdays |
| GET | `/leagues/{league}/ranking/all-time` | All-time ranking of a league |
| PUT | `/games/{game}/tip` | Create or change your tip (`{"home_goals": 2, "guest_goals": 1}`), both `null` delete it |
| DELETE | `/games/{game}/tip` | Delete your tip |
| GET | `/seasons/{season}/bonus-questions` | Bonus questions of a season with your answers |
| GET | `/bonus-questions/{bonus}` | One bonus question with your answers |
| GET | `/bonus-questions/{bonus}/clubs?search=` | Clubs/countries which can be chosen for a question |
| GET | `/bonus-questions/{bonus}/players?club=&search=` | Players of a club which can be chosen (questions of type `player`) |
| PUT | `/bonus-questions/{bonus}/answers` | Set your answers (`{"answers": [7, 12]}`), an empty list deletes them |
| DELETE | `/bonus-questions/{bonus}/answers` | Delete your answers |

`{matchday}` is a key of the matchday list, for example `matchday_5` or `round_12-13`, exactly like on the website. Start with `GET /leagues`, then the seasons, then the matchdays.

Status codes: `200`/`201`/`204` success, `401` key missing or invalid, `403` function disabled by the administrator (see below) or request not made over HTTPS, `404` unknown id (also for leagues, seasons, games and bonus questions of leagues which are not available), `409` deadline of the game or bonus question reached,
`422` invalid input, `423` the data are synchronized at the moment, `429` too many requests (60 per minute and key by default, see settings; 10 failed attempts per minute and address; a valid key is not blocked by failed attempts of others),
`503` maintenance mode (only admins can continue) or the database of xTIPP is not available (then xTIPP answers before the API with a plain HTML page and `Retry-After: 60`, not with JSON).

### Rules worth knowing

- **Leagues:** only the leagues in the setting "available leagues" can be used. Other leagues, their seasons and their
  games answer `404`, as if they did not exist.
- **Pages:** an endpoint answers `403`, when the administrator has hidden the matching page of the website for the user.
  Like on the website, only the page itself counts, not its parent menu entry:

  | Endpoint | Page |
  |---|---|
  | matchdays, one matchday | tipping, tip overview or single ranking (at least one) |
  | own tips, `PUT`/`DELETE` tip | tipping (`/tippen`) |
  | bonus questions, clubs, players, `PUT`/`DELETE` answers | bonus (`/tippen/bonus`) |
  | overview | tip overview (`/tippen/uebersicht`) |
  | ranking of a season | single ranking (`/rangliste`) |
  | all-time ranking | all-time ranking (`/rangliste/ewige-rangliste`) |
- **Overview:** like on the website, the tips of other users are only listed when the deadline of the game is reached (`tips_visible`), whatever the status of the game is. Before, only your own tip is returned. The distribution (`allocation`) follows the settings of the administrator for the tip overview and is `null` when the website would not show it.
- **Users** in answers contain only `id` and the display name the website shows. No e-mail addresses.
- **Ranking:** users with equal values share a place and the next place is skipped (1, 1, 3). Like the website, `current_matchday_points` are the points at the last played matchday/round of the selection (`null` if nothing of the selection has been played), and `place_change` is the change of the place by this matchday (positive = places gained, `null` if nothing was played before).
- **Tips:** `PUT` can be repeated safely. A game which started or reached its deadline answers `409`.
- **Bonus questions:** an answer is the id of a club (type `club`) or of a player (type `player`). `PUT` replaces all your answers of the question (add one: send the given answers plus the new one; remove one: send the list without it) and can be repeated safely. The points depend on the time of the answer, so an answer which is sent again keeps its time, like on the website. Every answer counts only once: an answer stored twice (e.g. by an old import) is listed once and the extra row is removed with the next `PUT`. Answers are possible until the last deadline of the question (`points[].until`), afterwards `409`. The answers of other users (bonus overview) are not part of the API.

## Security notes

- Only the SHA-256 hash of a key is stored. A key is random with 256 bit, so a fast hash is sufficient.
- The API does not use the middleware group `web` (no session, no cookie, no CSRF), so the API cannot be called with the browser login of a user. Active user, deleted user and maintenance mode are checked by the plugin itself.
- Only HTTPS is allowed by default (setting `require_https`), so API keys are not sent in clear text. A request over HTTP answers `403` and its key is not processed. A client that already sent its key over HTTP should regenerate it.
- HTTPS behind a reverse proxy or load balancer that terminates TLS: since xTIPP 1.0.36, add the address of the proxy to `TRUSTED_PROXIES` in the `.env` file of xTIPP (e.g. `TRUSTED_PROXIES=172.17.0.1` or a list like `172.17.0.1,10.0.0.0/8`). Then `X-Forwarded-Proto` of this proxy counts, for the API as well as for xTIPP's own redirect to the `https` address. Without the setting, `X-Forwarded-Proto` is ignored, and the web server must pass the server variable `HTTPS` to PHP, for example `fastcgi_param HTTPS on;` (nginx) or `SetEnvIf X-Forwarded-Proto https HTTPS=on` (Apache).
- Only list the real proxies in `TRUSTED_PROXIES`. With `*` and a site that can also be reached without the proxy, a client can send `X-Forwarded-Proto: https` over plain HTTP and pass the HTTPS check.
- Answers of the API set no cookie. Cookies that xTIPP sets itself on every request (e.g. `xtipp-current-league-id`) are removed from API answers.
- Answers of the API are marked `Cache-Control: no-store, private`.
- Since xTIPP 1.0.36, xTIPP adds its security headers to every answer, also of the API: `Content-Security-Policy`, `X-Frame-Options`, `X-Content-Type-Options: nosniff`, `Referrer-Policy` and, with an `https://` domain, `Strict-Transport-Security`. The key page and Swagger UI therefore use no inline scripts.
- A new key is shown directly in the answer of the form (`Cache-Control: no-store`). It is never written to the session, which xTIPP stores unencrypted.
- There are no CORS headers. Browser applications on another address cannot call the API.
- Swagger UI is delivered by the plugin (no CDN, no third-party requests). It is the unchanged `swagger-ui-dist` 5.34.1 (Apache License 2.0, see `resources/swagger-ui/LICENSE`).

## Limitations

- Not covered: the bonus overview (answers of other users), tip groups, messages, news, profiles, administration.
- The API depends on internal classes of xTIPP and is pinned to 1.0.36. Check it after an update of xTIPP.
- A key has the full rights of its user, there are no scopes.

## Removing the plugin

Deactivating the plugin does **not** run `down()` of the migration, and tables stay in the database.
To remove everything:

1. Deactivate the plugin.
2. Optional: `php artisan migrate:rollback --path=plugins/rest-api/database/migrations --realpath --force` (drops the table of the keys and removes the role `API-Benutzer` with its assignments; run it before deleting the folder).
3. Delete `plugins/rest-api/`.
4. If you kept the tables and want a clean state, remove the migration row from the `migrations` table.
