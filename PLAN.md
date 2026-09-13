# Routeler — refactor and deployment plan

**Status:** draft for verification
**Date:** 12 September 2026
**Repo:** `github.com/Leoiskind/Routeler` (currently **public** — see Phase 0)

---

## Decisions taken

| Question | Your answer | Consequence for this plan |
|---|---|---|
| Database engine | **MySQL** | Use `docs/schema.mysql.sql`; Dockerfile keeps `pdo_mysql`; no query porting |
| Database host | **Aiven** free MySQL | 1 GB, no card, permanent — but powers off when idle (see Phase 6b) |
| App host | **Render** free tier | Docker runtime, auto-deploys from GitHub; sleeps after ~15 min idle |
| Authentication | **Both** GitHub and Google, behind one `AuthProvider` interface | CAS code is deleted, not ported; login rewritten from scratch |
| Repo ownership | Solo continuation | History rewrite and force-push are allowed; teammates' placeholder pages can go |
| Existing schema | Redo the design | Phase 2 designs a fresh schema; no `mysqldump` needed |
| The REST API files | **Delete and rewrite** | `route_api.php` and `user_api.php` are scrapped, not repaired — see Phase 4 |

## Still open

1. **Whether the Phase 2a schema needs changes** before it's committed.
2. **Project name.** The repo is `Routeler`, the code says `Routler`, the original GitLab project was `routler`. Pick one spelling and apply it everywhere. This plan writes it `Routeler`.

## A note on how we'll work

**You write all the code.** Claude drafts and maintains this plan, explains the concepts, reviews what you write and answers questions as you go — but does not write the implementation. Ask when you want guidance on a specific piece.

Separately, your machine's Claude workspace is currently unavailable — a Windows update from 8 September is blocking it, so I can't run commands on your computer this session. I can still read and write files in the repo folder. In practice that means **you run every command in this plan yourself**, which suits the "teach me" framing anyway. Paste back anything that errors.

---

## Phase 0 — Containment (do today, before any refactoring)

The repo is still public as of this morning, and it contains a live database password.

- [ ] **Make the repo private.** GitHub → Settings → Danger Zone → Change visibility → Private.
- [ ] **Email your unit coordinator / CS lab support** asking for the `2025_comp10120_cm14` database password to be rotated. This is the only step that actually closes the exposure. Rotating is safe — Phase 2 replaces that database entirely, so you don't need the old credential for anything.
- [ ] **Tell George about the Mapbox token** (details in Phase 1). His account, his quota.

Nothing below depends on Phase 0 finishing, so start Phase 1 while you wait for replies.

> **Why "just delete the file" isn't enough.** The password is in a commit that has been publicly reachable. Anyone who cloned or forked keeps their copy, and GitHub caches fork contents independently of the original repo. Rotation is what makes the credential worthless; everything else is tidying.

---

## Phase 1 — Scrub secrets, and replace them properly

### 1a. What's actually in there

Two separate problems, with genuinely different severities. Don't treat them the same.

**The database password — a real leak.**

`config/db.php` line 4:

```php
$database_pass = "h8xgeE33zWmnkaIEEv+2lXkCQjbbllk3jFAgMRUQfsE";
```

Also exposes the host (`dbhost.cs.man.ac.uk`), the user (`b44939bn`) and the schema name. Rotate, then move to env vars.

**The Mapbox token — not a leak, but not yours.**

Hardcoded in three files (`front_page.php:78`, `create_route.php:1,48`, `view_route.php:1,26`):

```
pk.eyJ1IjoiZ2VvcmdlZmVpbnNvbiIsImEiOiJjbWxrd2NqcXowMTRrM2ZxeHgwNnJyYzRvIn0.MjrY8KmelJ7DsQpqkoM2RA
```

Decode the middle segment (it's a JWT, base64) and the payload is `{"u":"georgefeinson", ...}`.

Here's the part worth understanding rather than just fixing: **Mapbox `pk.` tokens are *public* tokens.** They are designed to ship to the browser — every Mapbox site on the internet has one visible in its JavaScript. So hiding this one in an environment variable achieves almost nothing; it would still be in the page source at runtime.

The real problems are different ones:

1. It's **George's** token, so every map load on your project bills his free-tier quota.
2. It has **no URL restriction**, so anyone can lift it and spend his quota on their own site.

The fix is therefore *not* "hide it" — it's "get your own, and restrict it":

- [ ] Create a free Mapbox account
- [ ] **Create a new public token — do not use the "Default public token."** Mapbox does not allow URL restrictions on the default token, so restricting it is impossible. This catches people out.
- [ ] On that new token, add **URL restrictions**: `localhost:8080` for development, and your deployed domain once Phase 6 gives you one
- [ ] Replace all five occurrences
- [ ] Delete the `<!-- mapbox access token '...' -->` HTML comments at the top of `create_route.php` and `view_route.php` — those serve no purpose but do make the token trivially greppable

URL restriction details worth knowing: no wildcards and no IP addresses are accepted, so you list each host explicitly (up to 100 per token). Subdomains and subpaths of a listed URL are allowed automatically, and if you omit the port, 80 and 443 are permitted by default — which is why `localhost:8080` needs its port written out.

It's also worth knowing *how* the restriction works, because it explains the gaps: Mapbox checks the browser's `Referer` header. That means the restriction quietly does nothing for requests with no referrer — a privacy extension like Brave Shields or Ghostery stripping it, a page with a `noreferrer` or `same-origin` referrer policy, or a mobile SDK. Mapbox itself describes URL restrictions as "a best-effort mitigation technique." Treat it as a speed bump, not a lock: also set a usage alert on the account so a spike tells you something is wrong.

> **The general rule, worth internalising:** a secret is something the *server* knows and the *browser* must never see. A database password qualifies. A Mapbox public token doesn't — it's public by design, and the control you want is scoping, not concealment. Confusing the two leads people to build pointless server-side proxies for things that were never secret.

### 1b. The env var refactor

Rewrite `config/db.php`:

```php
<?php
declare(strict_types=1);

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$name = getenv('DB_NAME') ?: 'routeler';
$user = getenv('DB_USER') ?: 'routeler';
$pass = getenv('DB_PASS');

if ($pass === false) {
    http_response_code(500);
    exit('DB_PASS is not set in the environment.');
}

$dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";

$conn = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
]);
```

Three changes beyond the secret, each worth knowing:

- `charset=utf8mb4` — emoji and accented characters in route descriptions survive the round trip
- `FETCH_ASSOC` — PDO's default returns every row *twice*, once keyed by column name and once by index. Your code stores fetch results straight into `$_SESSION`, so right now you're storing double
- `EMULATE_PREPARES => false` — sends real prepared statements to the server instead of interpolating client-side; the stronger position against injection

Then:

**`.env`** (real values, never committed)

```
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=routeler
DB_USER=routeler
DB_PASS=pick-something-local
MAPBOX_TOKEN=pk.your-own-token
```

**`.env.example`** (committed, no values) — same keys, all blank after the `=`. This is documentation: it tells you in March which variables the app needs.

**`.gitignore`** — append `.env` and `.env.local`.

### 1c. The three places a secret lives

This is the bit that confuses people, so it's worth stating plainly. Three machines run your code and none of them can see the others' config:

| Machine | Where the secret goes | What it's for |
|---|---|---|
| Your laptop | `.env`, gitignored | Local development |
| GitHub Actions | Settings → Secrets and variables → Actions | **Only** secrets the *pipeline* needs (a deploy hook, an SSH key) |
| Your host | The provider's env-var dashboard | What the live site actually reads |

Your database password belongs in columns 1 and 3, **never** column 2. You will see tutorials put app secrets in Actions and have the workflow write them into a file — that's an extra hop with no benefit and one more place to leak from.

### 1d. Scrubbing history

You said this is yours now, so a force-push is fine.

```bash
pip install git-filter-repo

git filter-repo --path config/db.php --invert-paths --force

# filter-repo removes the remote as a safety measure; add it back
git remote add origin https://github.com/Leoiskind/Routeler.git
git push origin --force --all
```

This rewrites every commit hash. Do it **after** the password is rotated, not instead of.

> Alternative worth considering: start a **fresh repo** with clean history from commit one, and archive the old one. It's less clever but has zero chance of leaving something behind, and it lets you write a proper first commit rather than inheriting four coursework commits.

**Phase 1 done when:** no credential appears anywhere in `git grep`, `docker compose` can run the app from `.env` alone, and your own Mapbox token is in place and URL-restricted.

---

## Phase 2 — Redesign the database

You said redo it, so this is a fresh design rather than a port. I derived it from what your UI already promises — the profile page shows Routes / Followers / Following / Rating tiles, a bio and a location, and `view-profile.php` has a Follow button. None of that exists in the current three tables.

### 2a. The design

Five tables:

- **`users`** — one row per person. Stores OAuth provider + provider's user id instead of a password, plus the profile fields your mockups show (bio, location, avatar, joined date).
- **`routes`** — belongs to a user. Name, description, distance, public flag, timestamps.
- **`route_points`** — the ordered coordinates. Renamed from `place`, which was misleading: these aren't places, they're vertices of a line.
- **`follows`** — follower/followee pairs. Makes the Followers and Following tiles real.
- **`ratings`** — one score per user per route. Makes the Rating tile real.

Notable changes from the current schema:

| Was | Now | Why |
|---|---|---|
| `user` | `users` | `user` is a **reserved word in Postgres** — `CREATE TABLE user` is a hard syntax error there. Also reserved-adjacent in MySQL. Plural table names sidestep a whole category of pain. |
| `place` | `route_points` | Says what it is |
| `place_index` | `position` | Ditto |
| `internal_username` / `external_username` | `provider` + `provider_uid` + `username` | The old pair only made sense with CAS |
| no constraints | CHECK constraints on lat/long/score, unique `(route_id, position)` | The database refuses impossible data even if the PHP has a bug |
| no foreign keys | FKs with `ON DELETE CASCADE` | Deleting a route takes its points with it, automatically |

### 2b. MySQL vs Postgres — the actual evidence

You asked to see both. Here's the honest comparison, specific to your code rather than generic.

**What porting would cost you.** I went through every query in `route_api.php` and `user_api.php`. The complete list of incompatibilities:

| # | Issue | Affected | Fix |
|---|---|---|---|
| 1 | `FROM user` is a syntax error in Postgres | 2 queries in `user_api.php` | Already fixed — the redesign renames it to `users` |
| 2 | `$conn->lastInsertId()` needs a sequence name in Postgres | `route_api.php`, `user_api.php` | Use `INSERT ... RETURNING id` and fetch it |
| 3 | `AUTO_INCREMENT` → `GENERATED ALWAYS AS IDENTITY` | schema only | Already handled — you're rewriting the schema |
| 4 | DSN prefix `mysql:` → `pgsql:` | `config/db.php`, one line | Trivial |
| 5 | Dockerfile extension `pdo_mysql` → `pdo_pgsql` | `Dockerfile`, one line | Trivial |
| 6 | `LIMIT x, y` syntax differs | **none** — you don't use `LIMIT` | — |
| 7 | Backtick quoting | **none** — you don't use backticks | — |

So the real cost of choosing Postgres is **item 2, in two files**. Everything else is absorbed by the schema rewrite you're doing anyway. I expected this list to be longer; it isn't, because your SQL is simple.

**What each engine's free tier gives you:**

| | Aiven (MySQL) | Neon (Postgres) | Supabase (Postgres) |
|---|---|---|---|
| Permanent free? | Yes | Yes | Yes |
| Card required | No | No | No |
| Storage | 1 GB | ~0.5 GB free branch | 0.5 GB |
| RAM / CPU | 1 GB / 1 vCPU | serverless | shared |
| Idle behaviour | **Powers off after inactivity** (email warning first) | Scales to zero, wakes on connect | Pauses after ~1 week idle |
| Extras | daily backups | branching, instant restore | auth, storage, REST API included |

**My read, stated as a recommendation you're free to reject:** go Postgres. The porting cost is two files, the free tiers are healthier, and Postgres is what you'll meet in most graduate backend roles — "I migrated a MySQL app to Postgres" is a better interview sentence than "I kept it on MySQL." The one honest argument for staying on MySQL is that it's zero work and you have five other phases to get through.

Both schema files are written and attached. **The Postgres one I've actually run** — created it on PostgreSQL 16, inserted sample data, and confirmed the profile-stats query returns the right numbers and all the CHECK constraints fire. The MySQL one is careful but untested; I had no MySQL server available.

### 2c. What you do

- [x] ~~Pick an engine~~ — **MySQL**, hosted on Aiven
- [ ] Review the schema in 2a and say what's missing or wrong before it's committed
- [ ] Commit `docs/schema.mysql.sql` to the repo root as `schema.sql`
- [ ] Write the new repositories against it (Phase 4) rather than porting the old queries — the old API files are being deleted

> **RESOLVED (13 Sep).** The schema was executed for real: `docker compose up` ran it against **MySQL 8.4.11** with no errors, so the whole DDL is valid — including `REGEXP` inside a CHECK constraint, the one line flagged below as uncertain. Note `mysql:8` now resolves to the 8.4 LTS line, not 8.0; consider pinning `mysql:8.4` so local and Aiven can't drift.
>
> **Earlier verification status, kept for the record.** The container's egress policy blocks `archive.ubuntu.com` and `pypi.org`, so no MySQL server or SQL parser could be installed to execute the file directly. What *was* checked: every identifier against the MySQL 8.0 keyword list (all clear — `position` is not reserved, `follows` and `description` are non-reserved); CHECK-expression legality against the MySQL manual (deterministic built-ins like `TRIM`/`CHAR_LENGTH` allowed, multi-column table CHECKs allowed); a structural lint for paren balance, duplicate constraint names, FK ordering and column references; and the full design behaviourally, by running the equivalent Postgres schema and confirming all seven named constraints reject bad data and `ON DELETE CASCADE` cleans up correctly.
>
> What remains unverified is MySQL dialect syntax specifically — and one line in particular: `REGEXP` inside a CHECK constraint. The manual permits deterministic operators but doesn't name `REGEXP` explicitly. If `docker compose up` rejects it, drop that constraint and validate usernames in the application instead.

**Attachments delivered in chat, not committed:** `schema.postgres.sql` is kept as a reference copy only — delete `docs/schema.postgres.sql` from the repo.

---

## Phase 3 — Containerise  ✅ DONE (13 Sep)

Working: `Dockerfile`, `.dockerignore`, `compose.yaml`, `.env`/`.env.example`, `.gitattributes` + `.vscode/settings.json` for LF. `docker compose up --build` brings up Apache/PHP 8.3.33 and MySQL 8.4.11, healthcheck gates the web container, and the schema seeds on first run.

Two files. After this, the project runs identically on your laptop, on any host, and in CI — which is what "deployable anywhere" actually means.

**`Dockerfile`**

```dockerfile
FROM php:8.3-apache

# Swap pdo_mysql for pdo_pgsql if you chose Postgres
RUN docker-php-ext-install pdo pdo_mysql

# Your app serves from public/, not the repo root
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
      /etc/apache2/sites-available/*.conf \
      /etc/apache2/apache2.conf

RUN a2enmod rewrite

COPY . /var/www/html
WORKDIR /var/www/html

EXPOSE 80
```

**`compose.yaml`** (local development only — never deployed)

```yaml
services:
  web:
    build: .
    ports: ["8080:80"]
    env_file: .env
    volumes: [".:/var/www/html"]   # live reload while editing
    depends_on: [db]

  db:
    image: mysql:8                  # or postgres:16
    environment:
      MYSQL_DATABASE: ${DB_NAME}
      MYSQL_USER: ${DB_USER}
      MYSQL_PASSWORD: ${DB_PASS}
      MYSQL_ROOT_PASSWORD: ${DB_PASS}
    ports: ["3306:3306"]
    volumes:
      - dbdata:/var/lib/mysql
      - ./schema.sql:/docker-entrypoint-initdb.d/schema.sql

volumes:
  dbdata:
```

Also add a **`.dockerignore`** (`.git`, `.env`, `vendor/`, `*.md`) so your build context stays small and your `.env` never ends up baked into an image.

`docker compose up` then gives you web server, PHP, database and schema on `localhost:8080`, reading secrets from `.env`. That's the payoff for Phase 1.

I have not been able to build this Dockerfile — no Docker daemon in my sandbox — so treat it as reviewed but unproven until you run it. The `sed` incantation is the pattern from the official `php:apache` image docs.

**Phase 3 done when:** `docker compose up` serves the front page locally with no CS-network dependency.

---

## Phase 4 — Delete the API layer, fix what remains

**Decision taken:** `public/api/route_api.php` and `public/api/user_api.php` are deleted and rewritten, not repaired. That is the right call — between the OAuth switch (which kills every `internal_username` reference), the schema redesign (which changes every table and column name), and dropping the self-cURL convention, essentially every line was already condemned. There is also no working behaviour to lose: the API is broken today.

Before deleting, write down the four operations they were meant to provide — that list is the spec for the rewrite:

1. Create a route with its ordered coordinate points
2. List all routes with their points (this is what the front page needs)
3. Fetch a single route with its points
4. Upsert a user on login

The old files stay recoverable from git history, as long as you rewrite the existing repo rather than starting a fresh one.

### Shape for the rewrite

Keep HTTP handling and data access separate:

```
public/api/routes.php        parse input, call the repository, return JSON
public/api/users.php         same
src/RouteRepository.php      the SQL — no echo, no header, no $_SESSION
src/UserRepository.php       same
```

The repositories take a `PDO` and return plain arrays. That's what makes Phase 7's tests possible — you can test a repository without an HTTP request, which you cannot do with the current design. Endpoints return JSON with a status code; they don't redirect.

### The bugs in those two files — superseded

The detailed list that was here (unbound `:route_id`, the clobbered `$stmt` inside its own loop, `$result -> $stmt->execute()`, the fall-through `switch`, `echo` before `header()`, the `substr($url, 0, strlen($url) - 13)` path hacking) no longer needs fixing, since the files are going. It's still worth reading once before you delete them — each one is a mistake that's easy to repeat, and the rewrite should avoid all six.

---

The rest of this phase still applies. These are reproducible on `localhost` with no database and no host, roughly in severity order.

### Critical — authentication bypass

**`front_page.php:4`**

```php
$_SESSION['username'] = $_GET['username'];
```

Anyone can visit `front_page.php?username=whoever` and become that user. No token, no check. This is the single worst thing in the codebase, and it's independent of CAS — it would still be a hole after you add OAuth if the line survives. It dies in Phase 5.

### Architectural — the self-cURL pattern

`front_page.php` makes HTTP requests to its **own** API endpoints (`api/user_api.php`, `api/route_api.php`) using cURL. This can't work, for a reason worth understanding: the cURL request is a *separate HTTP request* handled by a *separate PHP process* with a *separate session*, and no session cookie is forwarded. So when `user_api.php` sets `$_SESSION['user_id']`, that write lands in a different session and `front_page.php` never sees it — which is exactly why the code keeps re-checking and re-POSTing.

The fix is to stop making HTTP calls to yourself. Extract the logic into plain functions in `src/` and `require` them. Same code, one process, one session, and roughly 30 lines of cURL boilerplate deleted.

This is the highest-value refactor in the whole project. Everything downstream gets simpler.

### `public/view_route.php`

- **Line 4:** reads `$_SESSION['route_latlong']` with **no `session_start()`** anywhere in the file. Always empty.
- **Line 29:** `const waypoints = '<?php echo $session_value;?>';` — `$session_value` is an *array*. `echo` on an array prints the literal string `Array` and emits a notice. Then `waypoints[0]` is `"A"` and `waypoints.forEach` throws, because strings have no `forEach`. **This page cannot currently work.** Fix: `const waypoints = <?= json_encode($session_value, JSON_THROW_ON_ERROR) ?>;` — note no surrounding quotes.
- **Line 1:** an HTML comment before `<!doctype html>` puts some browsers into quirks mode.
- **Line 10:** title is still `Directions API implementation with hardcoded coords`.

### `public/info.php`

- **Line 22:** `<\form>` — backslash instead of slash. Not a valid closing tag.
- Writes `$_SESSION['nusername']` with **no `session_start()`**.
- `header('Location: ...')` on line 12 fires after the doctype and `<head>` have already been output — headers already sent, redirect fails.
- No `exit` after the redirect.
- This whole file looks like a scratch experiment (`//idk we dont have yet just run with it`). Consider deleting it rather than fixing it.

### `public/front_page.php`

- **Lines 67–68:** PHP inside an HTML comment. Worth understanding: `<!-- <?php echo $_SESSION['username'] ?> -->` **still executes the PHP** — HTML comments hide output from the browser, they don't stop the server running the code. So the undefined-index warnings still fire; you just can't see the result.
- **Line 41:** `<html>` with no `<!DOCTYPE html>` → quirks mode. No `lang` attribute.
- **Line 42:** leftover comment, `Just made this branch so we have can work on the same space`.
- `$ch` reused across three requests without `curl_reset()`; no `curl_close()` anywhere. Moot once the self-cURL pattern goes.

### Cross-cutting

- **`src/Authenticator.php:121,124,139,140`** — `FILTER_SANITIZE_STRING` is deprecated (confirmed: it emits `Deprecated: Constant FILTER_SANITIZE_STRING is deprecated` on PHP 8.4). Moot once CAS is deleted in Phase 5.
- **Nav bars** on `front_page.php`, `profile.php`, `create_route.php`, `view-profile.php` all hardcode `Your username` and all link "Logout"/"Sign Out" to `login.php`, which just shows the login page rather than destroying the session. Extract one nav partial and write a real `logout.php`.
- **`view-profile.php:23`** links to `search_route.php`, which doesn't exist — a guaranteed 404.
- **`profile.php` / `view-profile.php`** are entirely hardcoded mockups (`Harman Raj`, `Harman/Cayden`, avatar initials `JD` and `HC` that don't match the names, `Member since March 2026`). You said this is yours now, so either wire them to the database or delete them until you do — a portfolio repo full of someone else's placeholder names reads badly.
- **No output escaping anywhere.** Once real user data reaches these pages, every `echo` of a route name or bio is an XSS hole. Wrap them: `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')`.

**Suggested order:** self-cURL refactor first (it makes everything else easier), then the API files, then the page files, then the cosmetics.

---

## Phase 5 — Replace CAS with OAuth

Delete `src/Authenticator.php` and `index.php`'s CAS constants entirely — none of it works off-campus and none of it is worth porting.

### The flow, conceptually

1. User clicks "Sign in with GitHub" → you redirect them to GitHub with your `client_id` and a random `state` value you stashed in the session
2. They approve → GitHub redirects back to your `redirect_uri` with a short-lived `code`
3. Your **server** exchanges that `code` plus your `client_secret` for an access token — this happens server-to-server, the browser never sees the secret
4. You call GitHub's user API with the token, get back a stable user id
5. Look up `users` by `(provider, provider_uid)`; create the row if it's a first visit; set `$_SESSION['user_id']`

The `state` parameter is not optional — it's what prevents CSRF on the callback. Compare it on return and reject a mismatch.

### Decision: both providers, behind one interface

An `AuthProvider` interface with `GithubProvider` and `GoogleProvider` implementations. More design up front, but it's the right instinct — the `users` table already stores `(provider, provider_uid)` rather than assuming one source, so the schema supports it with no change.

```
src/Auth/AuthProvider.php     interface: getAuthUrl($state), exchangeCode($code): ProviderUser
src/Auth/GithubProvider.php
src/Auth/GoogleProvider.php
src/Auth/ProviderUser.php     value object: provider, uid, email, displayName, avatarUrl
src/Auth/SessionManager.php   state generation/checking, login, logout
```

Two things to get right:

**Normalise at the boundary.** GitHub and Google return quite different JSON. Each provider converts its response into the same `ProviderUser` before anything else sees it, so the rest of the app never branches on which provider was used.

**Never key a user on email.** Emails change and can be reused; a Google account and a GitHub account with the same address are still two different identities. `(provider, provider_uid)` is the unique key — which is what the schema's `users_provider_uq` constraint enforces. Store email as a display attribute only.

Suggested order: build the interface and get GitHub working end to end first, then add Google as the second implementation. If the abstraction is right, the second one should be almost entirely mechanical — and if it isn't, you'll find out with only one provider's worth of code to change.

Set-up locations: GitHub is Settings → Developer settings → OAuth Apps. Google is the Cloud Console → APIs & Services → Credentials, and needs a consent screen configured; unverified apps show a warning to users until verification is complete.

### New env vars

```
OAUTH_PROVIDER=github
OAUTH_CLIENT_ID=
OAUTH_CLIENT_SECRET=
OAUTH_REDIRECT_URI=http://localhost:8080/auth/callback.php
APP_URL=http://localhost:8080
```

`OAUTH_CLIENT_SECRET` is a **real** secret — unlike the Mapbox token, this one must never reach the browser.

### Files

```
public/auth/login.php      redirect to provider with state
public/auth/callback.php   verify state, exchange code, upsert user, set session
public/auth/logout.php     session_destroy() + redirect
src/Auth.php               the shared logic
src/require_login.php      one-liner guard to include at the top of protected pages
```

Use `league/oauth2-client` via Composer rather than hand-rolling the HTTP calls — it handles state, token exchange and the provider quirks. Hand-rolling OAuth is a good learning exercise but a bad idea in something you deploy.

**Phase 5 done when:** you can sign in with GitHub on `localhost:8080`, a `users` row is created on first login, and `front_page.php?username=anyone` no longer does anything.

---

## Phase 6 — Free hosting

### 6a. The application

Genuinely free options for a Docker container, as of September 2026:

| | Free tier | Cold start | Card? | Notes |
|---|---|---|---|---|
| **Render** | 750 hrs/mo, 512 MB RAM, shared CPU | 30–50 s after ~15 min idle | No | Simplest setup; auto-deploys from GitHub; free Postgres add-on available |
| **Koyeb** | 1 service, 0.1 vCPU, 512 MB, 2 GB SSD | scale-to-zero | No | Slightly more generous CPU story |
| **Google Cloud Run** | 180,000 vCPU-seconds/mo | scale-to-zero, fast | **Yes** | Most generous by far, but card required and noticeably more setup |
| ~~Fly.io~~ | — | — | Yes | Free tier gone for accounts created after Oct 2024 |
| ~~Railway~~ | $5 trial credit only | — | No | No free tier since 2024 |

**Decision taken: Render.** No card, deploys straight from your GitHub repo, and the cold start is acceptable for a portfolio link. Put "may take ~30s to wake" in your README and nobody minds.

Steps:

1. render.com → New → Web Service → connect via the **Git Provider** method and authorise GitHub. This matters: auto-deploy only works through Git Provider, not through a public repo URL or a prebuilt image. It handles private repos fine once authorised, which yours will be.
2. Runtime: **Docker** (it finds your `Dockerfile`)
3. Add every variable from `.env.example` under Environment, with real values
4. Deploy. You get `routeler.onrender.com` with HTTPS
5. Update `OAUTH_REDIRECT_URI` and `APP_URL` to the real domain, and add that domain to your Mapbox token's URL restrictions

#### The port gotcha — expect this one

Render requires your service to bind to host `0.0.0.0` on the port given by the **`PORT` environment variable**, which defaults to `10000`. The base `php:8.3-apache` image listens on port 80, hardcoded in two places: `Listen 80` in `/etc/apache2/ports.conf`, and `<VirtualHost *:80>` in the site config.

Render says it can *usually* auto-detect a service listening on a different port, and if it can't, the deploy fails outright with an error in the logs. "Usually" is a poor thing to build on, so make Apache honour `$PORT`.

The subtlety: `PORT` is only known at **run** time, not build time, so a `RUN sed ...` in the Dockerfile can't do it. You need an entrypoint script that rewrites both files using `${PORT:-80}` and then `exec`s Apache — the `exec` matters, so Apache becomes PID 1 and receives shutdown signals properly. Keep the `EXPOSE` line as documentation; it has no effect on Render either way.

Sanity check locally before deploying: `docker run -e PORT=10000 -p 8080:10000 routeler` should serve on `localhost:8080`.

#### Two sleeping services

You've chosen a free Render service *and* a free Aiven database, and both go to sleep. Render spins down after roughly 15 minutes idle; Aiven powers off after a longer stretch of inactivity, with an email warning first. A visitor arriving cold could therefore wait for the web service to wake *and* hit a database that isn't running.

Nothing to fix, but two things worth doing: say so in the README, and don't let a failed database connection show a stack trace — catch it and render "waking up, try again in a moment." Aiven's power-off needs a manual restart from their dashboard, so the email warning is the one to watch for.

### 6b. The database

Depends on your Phase 2 engine choice:

**If MySQL → Aiven.** Free permanently, no card, 1 GB storage / 1 GB RAM / 1 CPU, daily backups. The catch: it powers off after a period of inactivity, with an email warning first. Fine for a portfolio project, annoying if you forget.

**If Postgres → Neon or Supabase.** Neon gives you database branching (a genuinely useful thing to be able to talk about) and scales to zero, waking on connect. Supabase bundles auth and storage you won't need here but pauses after roughly a week idle.

Steps, whichever you pick:

1. Create the free instance, choose an EU region so latency to Render's EU region is sane
2. Copy the connection details into your host's environment variables
3. Load the schema: `psql "$CONNECTION_STRING" -f schema.sql` (or the `mysql` equivalent)
4. Check whether the provider requires TLS — most managed databases do. If so, add `sslmode=require` to the Postgres DSN, or the `PDO::MYSQL_ATTR_SSL_CA` option for MySQL. This is the single most common "works locally, fails in production" trip-up, so expect it rather than debugging it cold.

**Phase 6 done when:** the deployed URL loads, you can sign in, and a route you create persists across a redeploy.

---

## Phase 7 — CI/CD

Last, deliberately. A pipeline is only worth having once there's something for it to check.

### 7a. Give CI something to run

```bash
composer init --no-interaction --name=leo/routeler
composer require --dev phpunit/phpunit phpstan/phpstan friendsofphp/php-cs-fixer
```

- **PHPStan** — reads your code without running it and reasons about types. Start at `--level=1`; jumping to level 9 on an existing codebase produces a wall of noise you'll learn to ignore. Raise it as you fix things. It would have caught the `$result -> $stmt->execute()` bug.
- **PHP-CS-Fixer** — formatting consistency. Low value solo, high value the moment anyone else touches the repo.
- **PHPUnit** — start with the pure functions from the Phase 4 refactor (the ones that don't touch the database), then add integration tests against a throwaway database in CI.

### 7b. The workflow

`.github/workflows/ci.yml`:

```yaml
name: CI

on:
  push:
    branches: [main]
  pull_request:

jobs:
  test:
    runs-on: ubuntu-latest

    services:
      db:
        image: mysql:8            # or postgres:16
        env:
          MYSQL_ROOT_PASSWORD: ci-only-password
          MYSQL_DATABASE: routeler_test
        ports: ["3306:3306"]
        options: >-
          --health-cmd="mysqladmin ping"
          --health-interval=10s
          --health-retries=5

    steps:
      - uses: actions/checkout@v4

      - name: Set up PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: pdo_mysql
          coverage: none

      - name: Install dependencies
        run: composer install --prefer-dist --no-progress

      - name: Syntax check
        run: find . -path ./vendor -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l

      - name: Static analysis
        run: vendor/bin/phpstan analyse src public --level=1

      - name: Code style
        run: vendor/bin/php-cs-fixer fix --dry-run --diff

      - name: Tests
        env:
          DB_HOST: 127.0.0.1
          DB_NAME: routeler_test
          DB_USER: root
          DB_PASS: ci-only-password
        run: vendor/bin/phpunit

  docker:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - run: docker build -t routeler:ci .
```

Two things worth understanding rather than copying:

- The `services:` block spins up a throwaway database for the life of the job. That's why `ci-only-password` sitting in plain sight is harmless — it exists for ninety seconds inside a machine nobody else can reach.
- `test` and `docker` are separate jobs, so they run **in parallel on separate machines**. Add `needs: test` to the `docker` job if you want it to wait.

I've checked this YAML parses. The steps themselves can't be verified until Composer and the tests exist.

### 7c. CD

Render redeploys on push to `main` by default, which is the simplest working CD and perfectly respectable. If you'd rather gate deploys on tests passing, turn off auto-deploy and add:

`.github/workflows/deploy.yml`:

```yaml
name: Deploy

on:
  workflow_run:
    workflows: [CI]
    branches: [main]
    types: [completed]

jobs:
  deploy:
    if: ${{ github.event.workflow_run.conclusion == 'success' }}
    runs-on: ubuntu-latest
    steps:
      - run: curl -fsS "$DEPLOY_HOOK"
        env:
          DEPLOY_HOOK: ${{ secrets.RENDER_DEPLOY_HOOK }}
```

`RENDER_DEPLOY_HOOK` is the one thing that legitimately belongs in GitHub Actions secrets — a secret the *pipeline* uses. Your database password still doesn't go here.

### 7d. Worth adding once the basics work

- **Branch protection** on `main`: require CI to pass before merge. This is what makes the pipeline load-bearing rather than decorative.
- **Dependabot** (`.github/dependabot.yml`) for Composer updates.
- **A status badge** in the README.

---

## Ordering and rough effort

Your stated order, with one change I'd argue for: Phase 2 (schema) comes before Phase 3 (containerise), because `compose.yaml` needs a `schema.sql` to seed the local database. Everything else follows your list.

| Phase | Effort | Blocks |
|---|---|---|
| 0 — Containment | 20 min + waiting on replies | nothing |
| 1 — Secrets & env vars | 1–2 hrs | 3 |
| 2 — Schema redesign | 2–3 hrs | 3, 4 |
| 3 — Containerise | 2–3 hrs first time | 6, 7 |
| 4 — Bug fixes | 1–2 days | 5 |
| 5 — OAuth | 1 weekend | 6 |
| 6 — Hosting | half a day | 7 |
| 7 — CI/CD | half a day | — |

Phases 0–3 are the ones that convert this from coursework into a deployable project. If you run out of momentum, stopping after Phase 4 still leaves you with something much better than you started with.

## Finally: the README

Yours is still the GitLab template, headed "Getting started — To make it easy for you to get started with GitLab". It's the first thing anyone opening your GitHub sees. Replace it in Phase 6 with: what Routeler does, a screenshot of the map view, the stack, how to run it locally (`cp .env.example .env && docker compose up`), and the live URL.

---

## Attachments

- `docs/schema.mysql.sql` — **the one to use.** Verified as described in Phase 2c.
- `schema.postgres.sql` — reference only, delivered in chat. Not in the repo.

## What I need from you

1. ~~MySQL or Postgres~~ — **decided: MySQL on Aiven**
2. **Does the Phase 2a schema need changes** before it's committed?
3. ~~GitHub or Google for OAuth~~ — **decided: both, behind one interface**
4. **Force-push the existing repo, or start a fresh one**
