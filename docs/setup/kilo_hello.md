готов

1. Учебный сервис предварительной оценки заявки на заем под ПТС: принимает заявку, считает LTV и возвращает решение; модель: stg-litellm/training-2026-09-gpt-5.6-terra.
2. Makefile: `make up`, `make down`, `make ps`, `make logs`, `make install`, `make test`, `make lint`, `make seed`, `make help`; docker-compose.yml запускает сервисы `backend` и `db` (MySQL 8), backend слушает порт 8080, db проброшена на 3307 по умолчанию.
3. Решение `approve` / `review` / `reject` считается в папке `backend/src/Domain`, файл `DecisionEngine.php`.
