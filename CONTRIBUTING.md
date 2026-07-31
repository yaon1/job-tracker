# Как внести вклад

```bash
make api   # API на :8000
make web   # интерфейс на :3000
make test lint
cd api && composer smoke
```

- API без фреймворка: маршрут в `api/src/App.php`, логика в репозиториях, тест в `api/tests`, описание в `api/openapi.json` (тест `OpenApiTest` проверяет соответствие).
- Схема БД меняется только новой миграцией в конце списка в `Database.php`.
- PHPStan на уровне max без baseline; данные из запросов проверяются явно, без приведений типов.
- Коммиты в формате [Conventional Commits](https://www.conventionalcommits.org/ru/).
