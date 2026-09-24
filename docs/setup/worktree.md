# Как считается решение approve / review / reject

## Участвующие файлы

- `backend/src/Domain/AssessmentService.php` — оркестратор: валидация → LTV → решение
- `backend/src/Domain/ApplicationValidator.php` — проверка полей заявки по `rules.php`, нормализация входа
- `backend/src/Domain/VinValidator.php` — формат VIN (длина 17, A-Z/0-9, без I/O/Q)
- `backend/src/Domain/VehicleAge.php` — возраст авто = текущий год − год выпуска
- `backend/src/Domain/LtvCalculator.php` — LTV = сумма / оценочная стоимость × 100 (2 знака)
- `backend/src/Domain/DecisionEngine.php` — единственный, кто выдаёт approve/review/reject
- `backend/src/Domain/ValidationException.php` — ошибка валидации (заявка не доходит до расчёта)
- `backend/config/rules.php` — все пороги: VIN, авто (год/возраст/пробег), сумма, срок, LTV

## Порядок вызовов (`AssessmentService::assess`)

1. `ApplicationValidator::validate($payload)`:
   - `VinValidator::isValid($vin)` — VIN;
   - `VehicleAge::inYears($year)` — для проверок возраста;
   - год (`min_year`, не в будущем, `max_age_years`), пробег (`max_mileage_km`),
     `market_value > 0`, сумма (`amount.min/max`), срок (`term.min/max`);
   - при ошибках — `throw ValidationException`, заявка до расчёта не доходит;
   - возвращает нормализованный массив: `vin, year, mileage, market_value, requested_amount, term_months`.
2. `LtvCalculator::calculate($input['requested_amount'], $input['market_value'])` → float LTV в процентах.
3. `DecisionEngine::decide($ltv)` — пороги из `rules.php` (`ltv.approve_max = 60.0`, `ltv.review_max = 85.0`):
   - LTV < 60 → approve;
   - 60 <= LTV <= 85 → review;
   - LTV > 85 → reject.
4. Сборка ответа: `vehicle_age` (через `VehicleAge::inYears`), `ltv`, `decision`,
   `approved_limit` (сумма при approve, иначе 0).

Важно: `DecisionEngine::decide` принимает только float LTV — решение в текущем коде
зависит исключительно от LTV, никаких других факторов движок не видит.

## Куда встанет правило «пробег ≤ 400 000 км, иначе review»

Требует изменений в двух местах:

1. `backend/config/rules.php` — добавить порог, например `'review_mileage_km' => 400000`
   в секцию `vehicle` (по конвенции числа не хардкодим).
2. `DecisionEngine` — основное место. Сигнатура `decide(float $ltv)` не позволяет передать
   пробег: нужно расширить вход (`decide(float $ltv, int $mileage)` или весь `$input`) и добавить
   в `decide` проверку наравне с LTV: `mileage > 400000` → REVIEW. Открытый вопрос спеки:
   при LTV-отказе (reject) и большом пробеге остаётся reject или становится review —
   приоритет нужно определить.
3. `AssessmentService::assess` — передать `$input['mileage']` в движок (строка 33);
   конструктор `DecisionEngine` — принять новый порог из конфига.

### Что уже есть из входных данных

- Нормализованный `mileage` (int, км) уже есть в `$input` после
  `ApplicationValidator::validate` и возвращается им же — пробег доступен в
  `AssessmentService` в момент вызова `decide`.
- Инфраструктура конфига: движок уже получает пороги LTV из `rules.php` через
  конструктор — новый порог ляжет в ту же схему.

### Чего не хватает

- Механизма передачи пробега в `DecisionEngine` (сейчас на входе только `float $ltv`).
- Порога «review по пробегу» в `rules.php`.
- Логики приоритета: в коде нет ничего, что комбинирует несколько условий решения
  (единственный фактор — LTV).
- Тестов на такое правило нет.

## Что уже проверяется про пробег

- `ApplicationValidator::validate`, строки 43–46: пробег приводится к int
  (`(int)($payload['mileage'] ?? -1)`) и проверяется диапазон `0 <= mileage <= 500000`
  (`rules.php` → `vehicle.max_mileage_km = 500000`). Нарушение → `ValidationException`,
  а не решение.
- Нормализованный `mileage` включается в `$input` и попадает в ответ как `input.mileage`.
- Всё. На решение approve/review/reject пробег сейчас не влияет никак: в `LtvCalculator`,
  `DecisionEngine` и `AssessmentService` (кроме передачи в `input`) он не участвует.
  Порога 400 000 в коде и конфиге нет; справочник `ltv_by_age` к пробегу отношения не имеет.
