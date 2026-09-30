# job-tracker

**Канбан-трекер откликов на вакансии.** Когда откликаешься на десятки вакансий, быстро теряешь нить: куда писал, кто ответил, когда следующее интервью и сколько там платят. Трекер держит всё на одной доске и показывает воронку: сколько откликов дошло до собеседований и офферов.

- **Импорт с hh.ru по ссылке:** компания, должность, вилка, опыт, график и город подтягиваются автоматически; дубликаты отсекаются.
- **Канбан с drag-and-drop:** Хочу откликнуться → Отклик → Собеседование → Оффер / Отказ. Статус можно менять и с клавиатуры.
- **История статусов и воронка.** Конверсия в интервью считается честно: отказ после собеседования всё равно засчитывается как дошедший до интервью.
- **Собеседования в календаре:** назначенные интервью доступны iCal-лентой, которую можно подписать в Google/Apple Calendar, с напоминанием за 30 минут.
- **Напоминания:** у карточки есть дата следующего шага, панель «Пора напомнить о себе» собирает просроченные.
- **Контакты, журнал, теги, резюме:** кто вёл процесс, что и когда происходило, какую версию резюме отправили.
- **Аналитика по каналам:** конверсия в интервью по источникам и медиана дней до ответа компании.
- **Self-hosted:** всё хранится в одном файле SQLite.

## Пример использования

```bash
docker compose up -d
docker compose exec api php bin/seed.php   # по желанию: демо-данные, чтобы посмотреть интерфейс
open http://localhost:3000
```

Или через API:

```bash
# добавить вакансию с hh.ru
curl -X POST localhost:8000/api/import/hh -d '{"url":"https://hh.ru/vacancy/123456"}'

# перевести в «Собеседование»
curl -X PATCH localhost:8000/api/applications/1/status -d '{"status":"interview"}'

# воронка
curl localhost:8000/api/stats
# {"byStatus":{...},"funnel":{"applied":12,"interview":4,"offer":1},"responseRate":33.3}
```

> api.hh.ru больше не отвечает на анонимные запросы: для импорта нужен токен приложения, см. [инструкцию](docs/hh-token.md). Без токена ручное добавление работает как обычно.

## API

Полное описание — [`api/openapi.json`](api/openapi.json) (OpenAPI 3.1; тест следит, чтобы в нём был каждый маршрут).

| Метод | Путь | |
|---|---|---|
| `GET` | `/api/applications?status=&q=&tag=&source=&min_salary=&sort=&limit=&offset=` | Список с фильтрами, сортировкой и пагинацией |
| `POST` / `PUT` / `DELETE` | `/api/applications[/{id}]` | Создание (409 при дубликате, `?force=1` — всё равно), изменение, удаление |
| `PATCH` | `/api/applications/{id}/status` | Сменить статус |
| `GET` | `/api/applications/{id}/history` | История статусов |
| `GET` / `POST` / `DELETE` | `/api/applications/{id}/contacts[/{contact}]` | Контакты |
| `GET` / `POST` | `/api/applications/{id}/activity` | Журнал |
| `PUT` | `/api/applications/{id}/tags`, `GET /api/tags` | Теги |
| `POST` | `/api/applications/{id}/interviews`, `DELETE /api/interviews/{id}`, `GET /api/interviews` | Собеседования |
| `GET` | `/api/interviews.ics` | Календарь (iCalendar) |
| `GET` | `/api/reminders` | Наступившие следующие шаги |
| `GET` / `POST` | `/api/export.csv`, `/api/import.csv` | CSV (импорт всё-или-ничего) |
| `POST` | `/api/import/hh` | Импорт по ссылке hh.ru |
| `GET` | `/api/stats` | Воронка, источники, время ответа |
| `GET` | `/health` | Проверка живости (без токена) |

## Доступ по токену

Если трекер открыт в интернет, задайте `API_TOKEN`:

```bash
API_TOKEN=$(openssl rand -hex 24) docker compose up -d
```

Веб-интерфейс сам спросит токен и запомнит его в браузере. Календарь подписывается по ссылке `…/api/interviews.ics?token=…` (кнопка «Подписаться в календаре» подставляет её сама).

## Календарь собеседований

В Google Calendar: «Другие календари» → «Добавить по URL» → ссылка из панели «Ближайшие собеседования». В Apple Calendar: «Файл» → «Новая подписка на календарь».

## Перенос из таблицы

`Импорт CSV` принимает файл с колонками `company` и `position` (в любом порядке; необязательные — `status`, `url`, `salary_from`, `salary_to`, `currency`, `next_action_at`, `resume`, `notes`). Пример: [`examples/import.csv`](examples/import.csv). Ошибка в любой строке отменяет весь импорт и называет номер строки.

## Горячие клавиши

`N` — новый отклик, `/` — поиск. На телефоне доска листается по одной колонке.

## Ещё

- [Токен hh.ru](docs/hh-token.md)
- [Деплой и резервные копии](docs/deploy.md)

## Устройство

```
api/   PHP 8.4 без фреймворка: роутер, PDO + SQLite с миграциями, PHPStan max, PHPUnit
web/   Nuxt 4 (SPA): канбан, нативный drag-and-drop, <dialog>, тёмная тема
```

## Разработка

```bash
cd api && composer install && composer serve      # http://localhost:8000
cd web && npm i && npm run dev                    # http://localhost:3000
cd api && composer test && composer analyse && composer smoke
cd api && php bin/seed.php        # демо-данные в пустую базу
make lint                         # php-cs-fixer, ESLint, Prettier
```

| Переменная (api) | По умолчанию | |
|---|---|---|
| `DATABASE_PATH` | `api/data.sqlite` | Файл БД |
| `CORS_ORIGIN` | `http://localhost:3000` | Адрес фронтенда |
| `HH_ACCESS_TOKEN` | — | Токен приложения hh.ru |
| `API_TOKEN` | — | Если задан, API требует `Authorization: Bearer …` |

## Лицензия

MIT
