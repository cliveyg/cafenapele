# cafenapele
A dockerized version of [TastyIgniter](https://github.com/tastyigniter/TastyIgniter), served by nginx and PHP-FPM, with MySQL 8 in a separate container. Uses multi-arch images, so it runs on a Raspberry Pi 4 (64-bit OS recommended) as well as amd64.

## Usage
```sh
cp .env.example .env     # edit passwords, and set APP_URL to e.g. http://<pi-ip>:8080
docker compose up -d --build
docker compose exec app php artisan igniter:install   # one-off: set up DB tables and admin user
```
When prompted for the database use host `db`, port `3306` and the credentials from `.env`.
Then open `APP_URL` (admin at `/admin`). The first build on a Pi 4 takes several minutes.

The app's database startup check does not verify MySQL's TLS certificate, because the default MySQL image uses a self-signed certificate. Use a trusted Docker network; this check does not authenticate the database server.

## Persistence
Named volumes keep data across restarts/rebuilds: `db_data` (MySQL), `app_storage` (storage, uploads and the app `.env`/APP_KEY), `app_extensions`, `app_themes`, and `app_public` (public assets shared with nginx). On startup the app refreshes the bundled public files in `app_public` without deleting other files.
`docker compose down` keeps them; `docker compose down -v` deletes them.

## Staff landing page
The Orange theme's home page replaces the address search with a View Menu button and hides the locations footer link. Other pages and saved theme settings are unchanged. These view overrides are built into the app image; rebuild the app to update them.

## POS prototype (not ready for use)
This branch contains an incomplete POS prototype. Do not deploy it or use it for real orders: marking an order paid currently fails because TastyIgniter attempts to email the customer, and stock deduction, concurrent edits, and checkout blocking have not been fully verified. No staff-only access control is provided; the entire application must be protected externally.
