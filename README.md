# F24 Interview Challenge

React frontend and a minimal PHP API using Nginx and MariaDB.

## Requirements

- Docker Desktop
- Docker Compose

No host PHP, Composer, Nginx, or MariaDB installation is required.

## Start the application

The default values work without additional configuration. To customize ports or credentials, copy `.env.example` to `.env`.

```powershell
Copy-Item .env.example .env
```

Build and start all services:

```powershell
docker compose up -d --build
```

The database migration runs automatically. Example records are optional:

```powershell
docker compose exec app php database/seed.php
```

The application is available at `http://localhost:8080`. The API is available under `/api`:

```powershell
Invoke-RestMethod http://localhost:8080/api/health
```

Expected response:

```json
{
  "status": "ok",
  "database": "connected"
}
```

## Endpoints

```text
GET    /api/health
GET    /api/folders/{id}/entries?limit=50
POST   /api/folders
DELETE /api/folders/{id}
GET    /api/files?name={name}&folder_id={optional-folder-id}
GET    /api/files/suggestions?prefix={prefix}&folder_id={optional-folder-id}
POST   /api/files
DELETE /api/files/{id}
```

Exact search uses `name`. Prefix suggestions use `prefix` and return at most ten files. Add `folder_id` to either request to search within one folder; omit it to search across all files.

Folder entries support `folder_after_id` and `file_after_id` cursors returned by the previous response. The maximum page size is 100.

Create a folder and a file:

```powershell
Invoke-RestMethod -Method Post -Uri http://localhost:8080/api/folders -ContentType application/json -Body '{"parent_id":1,"name":"Pictures"}'
Invoke-RestMethod -Method Post -Uri http://localhost:8080/api/files -ContentType application/json -Body '{"folder_id":1,"name":"notes.txt"}'
```

Useful commands:

```powershell
docker compose logs -f
docker compose down
```

MariaDB data is stored in the named `mariadb_data` Docker volume.

To permanently remove all local database data and start clean:

```powershell
docker compose down -v
docker compose up -d --build
```

## Development mode

The Compose environment runs in debug mode. React uses the Vite development server, PHP uses its development configuration, and both source directories are mounted into their containers.

Nginx is the only public service:

```text
/      → React
/api/* → PHP-FPM → MariaDB
```
