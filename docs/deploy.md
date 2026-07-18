# Деплой и данные

- Данные — один файл SQLite в volume `tracker-data` (`/data/tracker.sqlite`).
- Резервная копия на ходу: `docker compose exec api php -r '(new PDO("sqlite:/data/tracker.sqlite"))->exec("VACUUM INTO \"/data/backup.sqlite\"");'`.
- Переносить между серверами удобнее через `Экспорт CSV` / `Импорт CSV`, но контакты, журнал и собеседования переносятся только копией базы.
- Веб — статическая сборка за nginx; адрес API зашивается при сборке аргументом `API_BASE`, а у API нужно указать `CORS_ORIGIN` с адресом веба.
- За HTTPS-прокси (Caddy, Traefik) включайте `API_TOKEN`: в базе личные данные.
