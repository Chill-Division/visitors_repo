# Developing Visitors

How to run the pages on your own PC with made-up visitors, refresh the README's screenshots, and put out a release. No Home Assistant needed.

## Rules every change must follow

1. **Only `/data` survives.** The add-on runs in a container where everything else is wiped on each update and restart. The visitor log must stay at `/data/visitor_signin.db`. Never store anything under `/www/public`.
2. **Relative URLs only.** Home Assistant serves the add-on under a deep ingress path, so links, assets and `fetch()` calls never start with `/`.
3. **No internet needed.** Pico CSS, Inter, the logo and the icons all ship with the add-on. Don't add anything that loads from a CDN or Google Fonts. The page must work on a Home Assistant box that's offline.
4. **No framing rules.** Home Assistant shows the add-on in a frame. `X-Frame-Options` broke that once (0.9.4.1), and `frame-ancestors` would too.
5. **PHP 8.3 with only `pdo`, `pdo_sqlite` and `session` on top of the core.** Functions such as `mb_strtolower()` or `ctype_digit()` work on a dev PC and crash in the add-on.
6. **Brand.** The look follows the GRACe Portal's layout and the Chill Division brand guide. Colours are the `--v-*` tokens at the top of `css/visitors.css`, for both themes. Text is Inter, buttons are pills, and on-screen wording has no semicolons or dashes.
7. **Say app to the people using it.** Home Assistant renamed add-ons to apps in 2026.2, so the README, the repository's name and on-screen text say app and Settings → Apps. Keep add-on for the technical side nobody using it sees, like the add-on image and `config.yaml`. Never change the `visitors_addon` slug: Home Assistant would treat Visitors as a different app, cut off from every install's visitor log.

## Run it

You need PHP 8.1 or newer with the SQLite extension (`sudo apt install php-cli php-sqlite3` on Ubuntu, WSL works fine).

```bash
export VISITORS_DB_PATH=/tmp/visitors-dev.db
php tests/seed_demo_data.php
cd visitors_addon/files/general/www/public
php -S 127.0.0.1:8421
```

Open <http://127.0.0.1:8421/>. `VISITORS_DB_PATH` points the pages at a scratch database instead of `/data`. The add-on never sets it.

- `php tests/seed_demo_data.php --force` wipes the scratch log, adds the made-up visitors again, and puts the default checklist back. Today's visitors are timed relative to now, so the sign-out search always finds someone (try "Sam").
- With no `options.json` the admin password is `cannabis`, and the admin page shows its default password warning. To try it with your own password, put `{"admin_password": "something"}` in `visitors_addon/files/general/www/public/options.json`. Git ignores that file.
- Both themes matter. The sun and moon button switches them, and the choice is saved in the browser under `visitors-theme`.
- CSS and JS are cache-busted by version (`?v=1.1.0`). If a change doesn't show up, hard-refresh with Ctrl+Shift+R.

## Refresh the screenshots

`tests/capture_screenshots.ps1` drives headless Microsoft Edge on Windows against the dev server in WSL and saves the PNGs the README uses into `docs/screenshots`. Seed with `--force` first, then from PowerShell in the repository root:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\capture_screenshots.ps1 -AdminPassword something
```

Add `-Only tablet-sign-in,phone-sign-out` for just some of them. The notes at the top of the script cover the rest.

## Release checklist

1. Bump the version in `visitors_addon/config.yaml` and `VISITORS_VERSION` in `config.php`. They must match.
2. Add an entry to the top of `visitors_addon/CHANGELOG.md`. Write it for the people using the app: what changed for them, not how.
3. If the pages look different, refresh the screenshots.
4. Open a pull request. Home Assistant offers the update to everyone as soon as it's merged to `main`.
