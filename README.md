# cafenapele
A dockerized version of [TastyIgniter](https://github.com/tastyigniter/TastyIgniter), with MySQL 8 in a separate container. Uses multi-arch images, so it runs on a Raspberry Pi 4 (64-bit OS recommended) as well as amd64.

## Usage
```sh
cp .env.example .env     # edit passwords, and set APP_URL to e.g. http://<pi-ip>:8080
docker compose up -d --build
docker compose exec app php artisan igniter:install   # one-off: set up DB tables and admin user
```
When prompted for the database use host `db`, port `3306` and the credentials from `.env`.
Then open `APP_URL` (admin at `/admin`). The first build on a Pi 4 takes several minutes.

## Persistence
Named volumes keep data across restarts/rebuilds: `db_data` (MySQL), `app_storage` (storage, uploads and the app `.env`/APP_KEY), `app_extensions`, `app_themes`.
`docker compose down` keeps them; `docker compose down -v` deletes them.
