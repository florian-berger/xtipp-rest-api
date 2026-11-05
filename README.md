# REST API for xTIPP

![GitHub License](https://img.shields.io/github/license/florian-berger/xtipp-rest-api?style=for-the-badge)
[![Issues](https://img.shields.io/github/issues/florian-berger/xtipp-rest-api?style=for-the-badge)](https://github.com/florian-berger/xtipp-rest-api/issues)
[![Discord](https://img.shields.io/discord/302523634075828226?label=Discord&logo=discord&style=for-the-badge)](https://ultgmng.de/discord)

A plugin for [xTIPP](https://xcript.de/), the tippgame software, that adds a stateless REST API with a Swagger UI. It is
built on the plugin system introduced in xTIPP 1.0.35, so **no file of xTIPP is changed**, and the plugin survives
updates of xTIPP.

The interface of the plugin (key page) is in German, like xTIPP itself. The API and its documentation are in English.

> **Status:** version 0.0.1. Made for and pinned to xTIPP 1.0.35 (`xtipp_max` in `plugin.json`), because it uses
> internal classes of xTIPP (`Ranking`, `Season`, `Game`, `TipPointCalculation`, `SendTipConfirmationEmail`).

## Features

- Read leagues, seasons, matchdays/rounds with their games, your own tips, the tip overview and the rankings (single season and all-time).
- Create, change and delete your own tips, with the same rules as the tip page (deadline, no draw tips for knockout games when the extra time counts, e-mail confirmation).
- Authentication with a personal API key as Bearer token. No username and password, no session, no cookie.
- Every user has one key and manages it on an own page: create, regenerate, revoke, delete. An optional expiry date can be set.
- Swagger UI with the complete description of the API, for logged-in users only. All calls can be tried out there.

## Installation

1. Upload the folder to `plugins/rest-api/` of your xTIPP installation. Check that **all files** arrived.
2. Activate it in the admin area under **Konfiguration → Erweiterungen**. This runs the migration, which creates the table `plugin_rest_api_keys`.
3. Link to `/api-schluessel` from somewhere (for example a news entry): xTIPP has no place where a plugin can add a menu entry (yet).

There are no settings. To switch the API off, disable the plugin.

## Pages

| Address | Who | Purpose |
|---|---|---|
| `/api-schluessel` | logged-in users | Create, regenerate, revoke and delete the own API key |
| `/api/docs` | logged-in users | Swagger UI |
| `/api/docs/openapi.json` | logged-in users | The OpenAPI description |
| `/api/docs/assets/{file}` | logged-in users | CSS/JavaScript of Swagger UI (the web root is `public/`, so the files of a plugin are delivered by a route) |
| `/api/v1/...` | holders of a key | The API |

## Authentication

1. Open `/api-schluessel` and create your key. It looks like `xtk_...` (256 bit random) and is **shown only once**.
2. Send it with every call:

```
curl -H "Authorization: Bearer xtk_..." https://your-site/api/v1/leagues
```

- A user has exactly one key. *Revoke* stops it (the entry stays visible), *regenerate* replaces the secret and starts the key again, *delete* removes it so that a new one can be created.
- A key has no expiry unless you set a date. A leaked key works until you revoke, regenerate or delete it.
- Inactive and deleted users cannot use the API, even with a valid key.
- Unknown, revoked and expired keys all get the same `401` answer.

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

`{matchday}` is a key of the matchday list, for example `matchday_5` or `round_12-13`, exactly like on the website. Start with `GET /leagues`, then the seasons, then the matchdays.

Status codes: `200`/`201`/`204` success, `401` key missing or invalid, `403` function disabled by the administrator (see below), `404` unknown id (also for leagues, seasons and games of leagues which are not available), `409` deadline of the game reached,
`422` invalid input, `423` the data are synchronized at the moment, `429` too many requests (60 per minute and key; 10 failed attempts per minute and address).

### Rules worth knowing

- **Leagues:** only the leagues in the setting "available leagues" can be used. Other leagues, their seasons and their
  games answer `404`, as if they did not exist.
- **Pages:** an endpoint answers `403`, when the administrator has hidden the matching page of the website for the user
  (or its parent menu entry):

  | Endpoint | Page |
  |---|---|
  | matchdays, one matchday | tipping, tip overview or single ranking (at least one) |
  | own tips, `PUT`/`DELETE` tip | tipping (`/tippen`) |
  | overview | tip overview (`/tippen/uebersicht`) |
  | ranking of a season | single ranking (`/rangliste`) |
  | all-time ranking | all-time ranking (`/rangliste/ewige-rangliste`) |
- **Overview:** like on the website, the tips of other users are only listed when the deadline of the game is reached (`tips_visible`), whatever the status of the game is. Before, only your own tip is returned. The distribution (`allocation`) follows the settings of the administrator for the tip overview and is `null` when the website would not show it.
- **Users** in answers contain only `id` and the display name the website shows. No e-mail addresses.
- **Ranking:** users with equal values share a place and the next place is skipped (1, 1, 3). `current_matchday_points` are the points at the current matchday/round.
- **Tips:** `PUT` can be repeated safely. A game which started or reached its deadline answers `409`.

## Security notes

- Only the SHA-256 hash of a key is stored. A key is random with 256 bit, so a fast hash is sufficient.
- The API does not use the middleware group `web` (no session, no cookie, no CSRF), so the API cannot be called with the browser login of a user. Active user, deleted user and maintenance mode are checked by the plugin itself.
- Answers of the API are marked `Cache-Control: no-store, private`.
- A new key is shown directly in the answer of the form (`Cache-Control: no-store`). It is never written to the session, which xTIPP stores unencrypted.
- There are no CORS headers. Browser applications on another address cannot call the API.
- Swagger UI is delivered by the plugin (no CDN, no third-party requests). It is the unchanged `swagger-ui-dist` 5.34.1 (Apache License 2.0, see `resources/swagger-ui/LICENSE`).

## Limitations

- Not covered: bonus questions, tip groups, messages, news, profiles, administration.
- The API depends on internal classes of xTIPP and is pinned to 1.0.35. Check it after an update of xTIPP.
- A key has the full rights of its user, there are no scopes.

## Removing the plugin

Deactivating the plugin does **not** run `down()` of the migration, and tables stay in the database.
To remove everything:

1. Deactivate the plugin.
2. Optional: `php artisan migrate:rollback --path=plugins/rest-api/database/migrations --realpath --force` (drops both tables and all connections; run it before deleting the folder).
3. Delete `plugins/rest-api/`.
4. If you kept the tables and want a clean state, remove the migration row from the `migrations` table.

## AI disclaimer

While writing this, I am not forced to write this AI disclaimer based on the rules of the European Union. But to be fully transparent, I write the following paragraph.

Developing this plugin was assisted by AI, specific **Claude Code** by **Anthropic**. The used model was **Claude Sonnect 5.5**. AI assisted means that a human designed the approach and AI helped building it.
