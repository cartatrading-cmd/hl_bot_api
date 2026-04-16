# hl_bot_api — API Laravel

Backend REST du système de copy trading Hyperliquid.  
Gère le cycle de vie des bots (démarrage / arrêt / surveillance), l'ingestion des logs et de l'état, et expose les données au frontend.

> Documentation complète du système : voir `hl_bot/README.md`

---

## Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link
php artisan serve
```

## Variables d'environnement clés

| Variable | Description |
|----------|-------------|
| `BOT_DIR` | Chemin absolu du dépôt `hl_bot` (ex : `C:\hl_bot`) |
| `PYTHON_BIN` | Exécutable Python (ex : `python` ou chemin absolu vers venv) |
| `BOT_API_TOKEN` | Bearer token partagé avec le bot Python |
| `DB_CONNECTION` | `pgsql` |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | Connexion PostgreSQL |

## Endpoints principaux

| Méthode | Route | Description |
|---------|-------|-------------|
| `GET` | `/api/bot-configs` | Liste des bots avec statut process |
| `POST` | `/api/bot-configs` | Créer un bot |
| `PUT` | `/api/bot-configs/{id}` | Modifier un bot |
| `DELETE` | `/api/bot-configs/{id}` | Supprimer un bot |
| `POST` | `/api/bot-configs/{id}/start` | Démarrer le process Python |
| `POST` | `/api/bot-configs/{id}/stop` | Arrêter le process proprement |
| `GET` | `/api/bot-configs/{id}/state` | Dernier snapshot d'état |
| `GET` | `/api/bot-configs/{id}/state/history` | Historique des snapshots |
| `GET` | `/api/bot-configs/{id}/logs` | Logs paginés |
| `POST` | `/api/bot/logs` | Ingestion logs (appelé par Python, Bearer auth) |
| `POST` | `/api/bot/state` | Ingestion état (appelé par Python, Bearer auth) |
