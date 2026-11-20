# Jovisco API (Portfolio & Showroom CMS)

Native PHP 8 REST API for the portfolio CMS of the Jovisco Nuxt site
(`C:\Users\Hp\Documents\VUE\jovisco`). PHP owns the database, validation, authentication and
image processing. The frontend talks to it only over REST.

## Setup (XAMPP)

1. `copy config.example.php config.php`. Set `DB_*`, a random `JWT_SECRET`, and the site's
   origins in `$_allowedOrigins`.
2. `php bin/migrate.php`. This creates the database if needed, then the tables, the 9 initial
   categories and the default showroom settings. It is safe to re-run; each migration applies once.
3. `php bin/admin.php create --email=you@example.com --name="Your Name"`. It prompts for a
   password of at least 10 characters. There is no public sign-up.
4. Nuxt: `NUXT_PUBLIC_API_BASE=http://localhost/jovisco/api` is the default.

Other admin commands: `php bin/admin.php list | password | disable | enable | delete --email=…`.

`php bin/compute-focal.php` recomputes the automatic focal point of images that weren't set by hand. Add `--all` to also overwrite manual points.

## Layout

| Path | Contents |
|---|---|
| `index.php` | Front controller. Errors are logged, never printed. |
| `routes/api.php` | Every route. |
| `src/Core` | Router, Request, Response, Validator, Jwt, RateLimiter, Logger, ImageProcessor, Slug. |
| `src/Middleware/AdminAuth.php` | JWT (aud `jovisco-admin`, 8 h, revocable via `token_version`). |
| `src/Repositories` | PDO, prepared statements only. |
| `src/Services` | Rules: publishing, slugs, featured images, file handling. |
| `src/Controllers` | Thin HTTP layer. |
| `migrations/*.sql`, `bin/` | Schema and CLI tools. |
| `uploads/` | Processed images. The only public folder; `.htaccess` disables PHP and serves images only. |
| `storage/` | Rate-limit state (denied over HTTP). |

## Conventions

- **Responses** use one envelope: `{ success, message, data, errors? }`.
  - 422 errors are keyed by field.
  - 401 means sign in again.
  - 404 and 405 behave as usual.
  - Invalid JSON returns 400.
- **Route matching:** `{id}` and `{…Id}` match digits only, so `/portfolio/categories` never matches `/portfolio/{id}`.
- **Uploads:**
  - The extension is ignored. The real MIME type, from finfo and getimagesize (which must agree), must be JPEG, PNG or WebP.
  - Size limit: `PORTFOLIO_MAX_UPLOAD_MB`. Pixel limit: 10,000 px per side and 40 MP.
  - Each image is decoded and re-encoded to WebP, which strips EXIF, GPS and any payload. Phone photos are rotated upright.
  - Three variants are saved under a random name: `large` (1920 px), `medium` (1024 px) and `thumb` (480 px).
  - The database stores relative paths only.
  - Deleting an image, project or category deletes its files.
- **Publishing:**
  - New projects are drafts.
  - Publishing requires at least one image.
  - `published_at` keeps the first publication date.
  - Public endpoints only ever return `published` projects and `active` categories.
  - Public responses are cached for 60 s, so changes go live within a minute without a frontend deploy.
- **Featured image:** exactly one per project with images, kept in sync by `ImageRepository::syncFeatured`.
- **Deleting a category** keeps its projects: `ON DELETE SET NULL` makes them uncategorised.

## Endpoints

Every `/api/admin/*` endpoint except `auth/login` needs `Authorization: Bearer <token>`.

**Admin auth**
- `POST /api/admin/auth/login`
- `GET /api/admin/auth/me`
- `POST /api/admin/auth/logout`

**Admin dashboard and settings**
- `GET /api/admin/stats`
- `GET /api/admin/settings`, `PUT /api/admin/settings`

**Admin projects**
- `GET /api/admin/portfolio` with `?page&per_page&search&category=<slug|none>&status&featured&sort=order|updated|newest|oldest|title`
- `POST /api/admin/portfolio`
- `GET`, `PUT` and `DELETE /api/admin/portfolio/{id}`
- `PATCH /api/admin/portfolio/{id}/publish` with `{status}`
- `PATCH /api/admin/portfolio/{id}/feature` with `{is_featured}`
- `PUT /api/admin/portfolio/reorder` with `{ids}`
- `GET /api/admin/portfolio/featured`
- `PUT /api/admin/portfolio/featured/reorder` with `{ids}`

**Admin images**
- `POST /api/admin/portfolio/{id}/images`: multipart `images[]` plus an optional `alt_text[]`
- `PUT /api/admin/portfolio/{id}/images/reorder` with `{ids}`
- `PUT /api/admin/portfolio/images/{imageId}` with `{alt_text?, is_featured?, focal_x?, focal_y?, focal_reset?}`
  - The focal point (0–1 from left/top) is what stays visible when a photo is cropped.
  - It is computed on upload (`ImageProcessor::focalPoint`).
  - `focal_x`/`focal_y` set it by hand (`focal_source = manual`); `focal_reset: true` recomputes it.
- `DELETE /api/admin/portfolio/images/{imageId}`

**Admin categories**
- `GET` and `POST /api/admin/portfolio/categories`
- `PUT` and `DELETE /api/admin/portfolio/categories/{id}`
- `PUT /api/admin/portfolio/categories/reorder`
- `POST` and `DELETE /api/admin/portfolio/categories/{id}/image`

**Admin 3D model** (template + measurements; the frontend builds the geometry)
- `GET /api/admin/portfolio/{id}/model`: `null` when the project has none
- `PUT /api/admin/portfolio/{id}/model` with `{template: wardrobe|kitchen|tv-wall|shelving, template_version, params: {…}, default_finish, show_in_showroom}`
  - `params` must be a flat object of at most 20 scalar values, under 2 KB.
  - Ranges are clamped by the frontend builder.
- `DELETE /api/admin/portfolio/{id}/model`
- Admin project lists include `has_model`.

**Public**
- `GET /api/portfolio` with `?page&per_page&category&search&sort=order|newest|oldest`
- `GET /api/portfolio/{slug}`: includes `images`, `related` and `model` (`{template, furniture_slug}` or null)
- `GET /api/showroom/models`: 3D models of published projects with `show_in_showroom` (template, params, default finish, photos)
- `GET /api/portfolio/categories`
- `GET /api/portfolio/featured?limit`
- `GET /api/portfolio/latest?limit`
- `GET /api/portfolio/settings`
- `GET /api/portfolio/sitemap`

## Notes

- **Origin:** this folder started as a copy of another project's skeleton.
  - `composer.json` and `composer.lock` still name that project and its packages (PHPMailer, dompdf). The CMS uses neither, and no `vendor/` is needed.
  - `config.leasepro-backup.php` is the original config, kept as a backup. Apache blocks it over HTTP. Delete it once you no longer need it.
- **Rate limits:**
  - Admin login: 10 per 15 minutes per IP.
  - Public reads: 300 per minute per IP.
  - Because Nuxt SSR calls come from the Nuxt server's IP, raise the public limit if traffic grows.
