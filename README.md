# Maringotka na Závadke

## Requirements
- Docker Desktop (macOS) or Docker Engine + Compose v2 plugin

## First start
```bash
cp .env.example .env
# IMPORTANT: replace POSTGRES_PASSWORD in .env with a unique local and strong password.
docker compose config --quiet
docker compose up --build -d
docker compose ps
curl -fsS http://127.0.0.1:8080/
docker compose exec -T php php bin/check-db.php
```

Expected outputs:

```text
Maringotka development environment OK
PostgreSQL connectivity OK
```

If port 8080 is taken, change HTTP_PORT in `.env` and use that port in curl. The HTTP port is bound to 127.0.0.1 only. PostgreSQL has **no published port**; php connects to `db:5432` on the internal Docker network.

## Useful commands
```bash
docker compose logs --tail=100 web php db
docker compose exec -T php php -v
docker compose exec -T php php -m
docker compose exec -T php composer --version
docker compose exec -T db psql -U maringotka -d maringotka -c 'SELECT version();'
docker compose down
```

`docker compose down` retains the database volume; `docker compose down -v` **permanently deletes the local DB volume**.

## Logging and security boundary
- Development PHP errors go to the PHP container stderr (`docker compose logs php`). There is deliberately no production error notification in this commit;
- `.env` is ignored by Git; never commit actual credentials.
- The Nginx document root points to `/var/www/html/www` only. Root application directories are not web-accessible.
- PHP `display_errors=On` is **development-only**. These Compose/PHP/Nginx configs are **not** the production configuration.
- No exposed DB port; HTTP reachable only from local host.
- PHP application files are bind-mounted for convenient editing; changes take effect without rebuilding (Dockerfile/ini changes require rebuild).

