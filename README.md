# hansulmusic

## Member sign-in

The home page modal supports email/password sign-in and account creation.
New accounts are active immediately; email verification and social sign-in
are deferred. The consolidated setup script
`database/setup/001_member_auth.sql` adds password-hash storage and
marketing-consent fields to the existing `members` table. Existing accounts
without a password cannot sign in until a password recovery process is added.

The PHP-FPM service must have these environment variables configured:

- `DB_HOST` and `DB_NAME` (defaults: `db` and `hansul_db`)
- `DB_USER` and `DB_PASSWORD`

Internal page, stylesheet, and script paths are generated from the PHP
request path, so the site can be served from either the domain root or a
subdirectory such as `/new`.

Passwords are stored using PHP `password_hash()` and checked using
`password_verify()`. Sign-in state is session-based; choosing Sign out clears
the current session. Password recovery and email verification are not yet
available.

The account form has an optional, unchecked opt-in for email updates about
new releases, events, and promotions. Existing members default to not
consenting. Signed-in members can click their greeting in the navigation to
review their status and withdraw consent. This stores consent preferences;
email campaign delivery is not yet implemented.

## Concert video list

The Concert Videos and YouTube pages read from the unified `videos` table in
MariaDB. `video_type` identifies the page list (`1` = Concert Videos,
`2` = YouTube), while `role` classifies music (`1` = Compositions,
`2` = Music Arranged). These meanings are recorded as SQL column comments in
the table definition. `video_title` is optional.

The numbered SQL files under `database/setup/` are a consolidated baseline
for a new site database where the application's base `members` table already
has `member_id` and `email` columns. Run them once, in order, using the
hosting provider's database manager:

1. `001_member_auth.sql` — add password and marketing-consent fields to `members`.
2. `002_videos.sql` — create and seed the unified `videos` table.
3. `003_shop_products.sql` — create and seed `products` and `product_assets`.
4. `004_hymnal_catalog.sql` — create the final shared hymnal/praise-song table.
5. `005_seed_hymnal.sql` — load the hymn catalog.
6. `006_seed_praise_song.sql` — load the praise-song catalog.

These files replace the old incremental migration history; they are not
upgrade scripts. Do not run them against an existing database or re-run them
after setup, because the schema and seed rows already exist there. Existing
site databases need no changes for this file cleanup. The PHP pages use the
same `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD` environment variables
as member sign-in.

To add a video later, insert its YouTube URL in the database manager:

Insert videos for the Concert Videos page with `video_type=1`, and for the
YouTube menu page with `video_type=2`. Set `role=1` for Compositions or
`role=2` for Music Arranged. Both numeric fields are required. You can
provide an optional title; if omitted, the page uses a numbered fallback
title:

```sql
INSERT INTO videos (video_type, role, video_title, video_url, sort_order, is_active)
VALUES (2, 1, 'Video title', 'https://youtu.be/VIDEO_ID', 1, 1);
```

Use the next `sort_order` number within each type to place the video at the end.
Set `is_active` to `0` to hide a video without deleting its row. The pages
accept standard YouTube watch, short, live, and embed URLs; other hosts or
invalid video IDs are not embedded. Video entries are still managed directly
in the hosting provider's database manager.

## Shop products

`003_shop_products.sql` creates and seeds the `products` and `product_assets`
tables. `products.is_active=1` means the product is displayed in the shop;
`is_active=0` hides it while retaining the database row. The shop page reads
active products in `sort_order` order. Product kind is `product_type` (1 = score, 2 = audio), one product per kind; it also links the English shop copy in `product-localization.php`. For an existing database, run `database/setup/007_drop_product_slug.sql` once as a DB admin to drop the old `slug` column. Product records, cover images, preview
audio, downloadable files, and score preview images can be managed at
`/admin-products.php`; the footer's small dot links to this page.
Concert/YouTube videos, hymnals, and praise songs are managed at
`/admin-media.php` with the same login (shared code in `admin-auth.php`);
rows are never deleted, and unchecking "진열 상태" sets `is_active = 0`.

Create a hidden file named `.admin-products-login` in the project root beside
`docker-compose.yml` and the `html/` directory. Put the administrator login
name on the first line and the `password_hash()` value of the password on the second line, with no
other lines. Docker Compose mounts this file read-only at
`/var/www/.admin-products-login` in the PHP container; create the file before
starting or recreating that service. On the standard PHP image, make it
readable to the PHP-FPM worker while restricting other users, for example:
`sudo chown 33:33 .admin-products-login && sudo chmod 600 .admin-products-login`.
Keep this server-only file outside the source repository and do not place it
under the public `html/` directory. The admin page fails closed if the file is
missing or invalid, and protects changes with a session and CSRF token.

Product files live under `product-media/` — covers in `product-media/covers/`, score preview images in `product-media/scores/`, preview audio in `product-media/music/`, downloads in `product-media/scoreszip/` (scores) or `product-media/music/` (audio products) and require the PHP-FPM user to be
able to create and write files in the site directory. Set PHP-FPM's
`upload_max_filesize` to at least `100M` and `post_max_size` to at least `120M`
to allow the largest supported single upload. Covers and score previews accept
JPG, PNG, or WebP; preview audio accepts MP3, WAV, M4A, or OGG; downloads
accept PDF or ZIP for scores and audio or ZIP for audio products. Filenames are randomized and their relative paths are saved
in the product tables. Existing score-preview labels and order can be edited;
images can be added. The admin page does not delete products, preview records,
or file associations; to stop showing a product, uncheck its display status
(`is_active=0`). Replacing a file preserves the old file on disk.

## Hymnal parts

`004_hymnal_catalog.sql`, `005_seed_hymnal.sql`, and
`006_seed_praise_song.sql` create and populate the shared `hymnal` table.
The page reads active rows directly from this table. For hymns, `hymnal_type`
is `1` for 찬송가, `2` for 새찬송가, and `3` for 영문찬송가. The seed data
imports hymn and video links from the supplied Hansul Music pages.

Administrators can add or edit rows directly in the database manager. For
example, register an item with soprano and chorus links like this:

```sql
INSERT INTO hymnal (
    hymnal_type, hymn_number, hymn_title, soprano_url, chorus_url, sort_order
) VALUES (
    1, 1, '만복의 근원 하나님', 'https://youtu.be/VIDEO_ID_S',
    'https://youtu.be/VIDEO_ID_C', 1
);
```

For 새찬송가 or 영문찬송가 index entries, use `video_url` for the single
video link. Set `is_active` to `0` to hide a row without deleting it.

## Praise song parts

The `hymnal` table is shared: `catalog_type=1` is hymnal and `catalog_type=2` is
praise song. Praise-song `hymnal_type` values select the page tab:
`1` = seasonal categories, `2` = Korean alphabetical list, and `3` = English
alphabetical list. `section_title` stores the seasonal group name. SATBC,
piano, and numbered S/T/B part links are stored in the corresponding URL
columns. The seed imports 98 seasonal, 53 Korean alphabetical, and 45 English
alphabetical entries.

Use `praise-song-parts.php` to browse, search, and sort the active praise-song
rows. The seasonal tab also filters by `section_title`. Administrators can
add or edit rows directly in the database manager; set `is_active` to `0` to
hide an entry without deleting it.
