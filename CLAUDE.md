# map-yoid — Project Context

## Overview
Public-facing station map for `map.yoidpower.com`.
Shows all **online** YOID charging kiosks on an interactive Google Map with search and marker clustering.
Station data is read directly from the shared MySQL database (`mysql-db` container on `yoidpower_backend` network).

## Server & Deployment
- **GitHub repo:** https://github.com/vikgr/map-yoid.git
- **Server path:** `/home/devops/map-yoid`
- **Deploy command:** `cd /home/devops/map-yoid && git pull origin main && docker compose up -d --build`
- **Branch:** `main`
- **Docker:** PHP 8.2 Apache container, no volumes (code baked into image on build)
- **Domain:** `map.yoidpower.com` (SSL via letsencrypt companion)

## Tech Stack
- **Backend:** PHP 8.2 + Apache (Docker)
- **Database:** MySQL 8.0 — shared `mysql-db` container, accessed over `yoidpower_backend` network
- **Frontend:** Single-file PHP page, vanilla JS, Google Maps JS API
- **Clustering:** `@googlemaps/markerclusterer@2` (CDN)
- **Config:** `.env` file

## Server Docker Networks
| Network | Purpose |
|---|---|
| `yoidpower_proxy-tier` | nginx-proxy → SSL termination → public internet |
| `yoidpower_backend` | Internal DB access (`mysql-db`, `php-apache-partners`) |

Both networks are declared `external: true` in `docker-compose.yml` — they are owned by other stacks already running on the server.

## Project Structure
```
public/
  index.php   — Full map page (HTML + JS, reads from api.php)
  api.php     — JSON endpoint: queries MySQL for online stations
               Falls back to 3 demo stations when DB_HOST is not set
docker/
  Dockerfile  — php:8.2-apache + pdo_mysql extension
  apache.conf — DocumentRoot /var/www/html/public
docker-compose.yml  — production config (VIRTUAL_HOST, networks)
.env.example        — all supported env vars with comments
deploy.sh           — one-shot deploy script for the server
CLAUDE.md           — this file
```

## Key Route
| Method | Path | Description |
|---|---|---|
| `GET` | `/` | Map page (index.php) |
| `GET` | `api.php` | JSON station list |
| `GET` | `api.php?online=1` | JSON — online stations only |

## API Response Shape
```json
{
  "source": "db",
  "stations": [
    {
      "id": 306,
      "title": "Nayax B.V.",
      "serial": "GT042250901224",
      "online": true,
      "lat": 52.25325,
      "lng": 4.63545,
      "address": "Pondweg 7, 2153 PK Nieuw-Vennep, Netherlands"
    }
  ]
}
```
`source` is `"db"` when MySQL is used, `"demo"` when DB is not configured or unreachable.

## Demo Mode
When `DB_HOST` is empty or MySQL is unreachable the API automatically returns
3 hardcoded demo stations so the map works immediately:
- **Nayax B.V.** — Pondweg 7, Nieuw-Vennep
- **Savor Restaurant** — Nieuwstraat 20, Sluis
- **YOID Office** — Zomerdijk 70, Maassluis

Replace with live DB by filling in `.env`.

## Environment Variables (`.env`)
```
# MySQL — mysql-db container on yoidpower_backend
DB_HOST=mysql-db
DB_NAME=your_database_name
DB_USER=your_db_user
DB_PASS=your_db_password

# Station table — adjust column names to match partners DB schema
DB_STATIONS_TABLE=stations
DB_COL_ID=station_id
DB_COL_TITLE=station_location_title
DB_COL_ONLINE=station_is_online
DB_COL_ADDRESS=station_address_data      # URL-encoded JSON with geometry.lat/lng
DB_COL_SERIAL=station_serial_number
DB_ACTIVE_FILTER=station_is_active

# Google Maps
GOOGLE_MAPS_KEY=AIzaSyB27M0Sl9zDxwED92C1s3XxAZK0seAJSF4
```

## MySQL station_address_data format
The `station_address_data` column stores URL-encoded JSON (Google Places output):
```json
{
  "geometry": { "lat": 52.253, "lng": 4.635 },
  "formatted_address": "Pondweg 7, 2153 PK Nieuw-Vennep, Netherlands",
  "htmlAddress": "..."
}
```

## Map Design
- **Style:** Full greyscale (saturation -100, custom lightness per layer)
- **Markers:** Purple teardrop pins (`#7C3AED`) — online stations only
- **Clusters:** Purple bubbles with white count, outer glow ring
- **Search:** Google Places autocomplete (European countries)
- **Search pin:** Black teardrop (neutral)

## Related Containers on the Same Server
| Container | Image | Notes |
|---|---|---|
| `php-apache-partners` | `yoidpower-php-apache:latest` | Partners portal — same MySQL DB |
| `mysql-db` | `mysql:8.0` | Shared DB — on `yoidpower_backend` |
| `nginx-proxy` | `jwilder/nginx-proxy` | Routes `map.yoidpower.com` to this container |
| `letsencrypt` | `jrcs/letsencrypt-nginx-proxy-companion` | Auto SSL |

## Owner
- **Developer:** Vik Niniadis
- **Git user:** vikgr
