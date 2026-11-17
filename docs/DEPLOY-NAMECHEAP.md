# Deploy: Namecheap (iainreid.dev/museproject)

Live since 2026-11-16 at https://iainreid.dev/museproject/ (development mode,
all gates closed: no public registration, no live payments, AI providers off).

## Why it is not a plain git deploy

- This host's route to GitHub drops large transfers: `git clone` of this repo
  dies with `fatal: early EOF` (the repo pack is ~200 MB, mostly
  public/assets). Small fetches work; the initial clone does not.
- LiteSpeed on this host will not run PHP through a symlinked folder, and it
  ignores `FallbackResource`. Rewrite targets outside the folder containing
  the .htaccess also fail to resolve.

## How it works

- The app lives in a real folder: `/home/iainmcok/public_html/museproject`
  (the app root; its web root stays `public/`).
- A key-gated script, `deploy-song.php` in the origins-unknown repo
  (served at https://iainreid.dev/origins-unknown/deploy-song.php), drives
  everything. The key is at `~/workspace/muse-project/deploy-key.txt` on
  Muse's VM; the server stores only its SHA-256 hash.
- Initial seed: a runtime-only tarball of the repo (app, src, templates,
  public, bin, worker, database, assets + root docs; no .env, no var/,
  no design/docs/development-vault) split into 5 parts, hosted temporarily,
  downloaded server-side with curl resume, SHA-256 verified, extracted.
- The deploy script then writes `.env` (APP_URL=https://iainreid.dev/museproject,
  fresh APP_KEY, gates closed), creates `var/` dirs, moves `public/.htaccess`
  aside, and writes two files into the app root:
  - `index.php`: a shim that requires `public/index.php` (its `__DIR__`
    resolves correctly, so the app root stays this folder).
  - `.htaccess`: forbids `.env` and the private folders (app, src,
    templates, var, bin, worker, database, ...), and rewrites every path
    that is not a real file to the shim `index.php`.
- Versioned asset URLs (`/assets/r/<id>/...`) are served by the front
  controller itself (see public/index.php), because the rewrite layer here
  cannot map them to files.
- Single-file updates: the deploy script's `syncfile` action pulls an
  allowlisted path from GitHub raw (currently just `public/index.php`).
  For anything bigger, rebuild and reseed the tarball, or extend the
  allowlist.

## Worker cron (set in cPanel by Noa)

```
php /home/iainmcok/public_html/museproject/worker/run-loop.php
```

every 5 minutes (this hosting plan's cron minimum). run-loop.php keeps
processing for up to 240 seconds per run, exits early once the queue has
been idle 30 seconds, and holds an flock so runs never overlap. Until the
cron exists, development mode also processes the next generation job
inline while the UI polls job status.

## Still to do after first deploy

- Seed the owner account (`bin/` seed-owner script) with OWNER_EMAIL and
  OWNER_PASSWORD set in the server .env.
- When AI keys are added to .env, set AI_PROVIDERS_ENABLED=true and the
  monthly budget deliberately.

## Host quirks learned 2026-11-16

- The host's malware scanner quarantines PHP scripts that shell out
  (shell_exec with curl/git/nohup reads as a webshell). deploy-song.php
  was rewritten to use only PHP-native calls (streams for downloads,
  AuthService for owner seeding) and now persists. Keep it that way.
- The setup form for SMTP (hello@iainreid.dev) and the owner account
  lives in deploy-song.php at ?setup=KEY.
