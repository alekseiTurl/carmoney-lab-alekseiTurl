# Спека MILEAGE: проверка пробега в расчёте решения по заявке

Дата: 2026-09-29. Задача: MILEAGE.
Замысел: `docs/intent/intent_MILEAGE.md` (ограничения §3.1–3.7,
open questions §4). Интервью: `docs/intent/grill_MILEAGE.md`
(вопросы 1–4, все отвечены).
Суть правила: при пробеге ≤ 400 000 км решение не меняется; при пробеге
≥ 400 001 км approve понижается до review, у пониженной заявки в ответе
API появляется `reason: "high_mileage"`, `approved_limit` = 0 (intent §1).
Здесь — требования и критерии приёмки: что считается сделанным.
Как реализовывать — `docs/plan/plan_MILEAGE.md`.

## 1. Входит / не входит

### Входит

- Правило пробега в расчёте решения по заявке: approve при пробеге
  больше 400 000 км понижается до review (intent §1, §3.1–3.3).
- Поле `reason: "high_mileage"` в ответе API у заявки, пониженной
  по пробегу (intent §1, §3.4; интервью, вопросы 3–4).
- `approved_limit = 0` у пониженной заявки (intent §3.5).
- Поведение при пробеге «отсутствует / null / пустая строка» —
  требования к существующему поведению, на которое опирается
  правило (intent §3.6–3.7).

### Не входит

- Фронтенд: форма заявки, тексты и валидация на клиенте (intent §5).
- Жёсткая валидация пробега: предел 500 000 км и текст её ошибки —
  без изменений (intent §5; код: `backend/src/Domain/ApplicationValidator.php`,
  `backend/config/rules.php`).
- Пороги решения по LTV (approve/review/reject) — без изменений
  (intent §5; код: `backend/src/Domain/DecisionEngine.php`).
- `reason` для решений, не пониженных по пробегу: review по LTV,
  approve, reject (intent §5; интервью, вопрос 4).
- Пересчёт исторических заявок и seed-данных по новому правилу —
  требований в спеке нет, вопрос перенесён (см. §4, OQ-4).

## 2. Требования

Каждое требование атомарно и проверяемо; у каждого числа — источник.

REQ-MILEAGE-01. Порог правила — 400 000 км, граница «не больше»
включительная: при пробеге ≤ 400 000 км правило не срабатывает,
при пробеге ≥ 400 001 км — срабатывает.
Источник: intent §3.1; формулировка «не больше 400 000 км» — ответ
заказчика в интервью (grill, преамбула).

REQ-MILEAGE-02. Срабатывание правила даёт решение review, а не ошибку
валидации: заявка с пробегом выше порога остаётся валидной и доходит
до расчёта решения.
Источник: intent §3.2; формулировка «иначе решение review» — интервью.

REQ-MILEAGE-03. Правило понижает только approve → review.
Источник: intent §3.3; интервью, вопрос 1.

REQ-MILEAGE-04. Решение reject, полученное по LTV, при пробеге
> 400 000 км остаётся reject.
Источник: intent §3.3; интервью, вопрос 1.

REQ-MILEAGE-05. Решение review, полученное по LTV, при пробеге
> 400 000 км остаётся review.
Источник: intent §3.3; интервью, вопрос 1.

REQ-MILEAGE-06. В ответе API заявки, пониженной по пробегу, присутствует
поле `reason` со значением `"high_mileage"`.
Источник: intent §1, §3.4; интервью, вопросы 3–4.

REQ-MILEAGE-07. Поле `reason` отсутствует в ответе API, если заявка не
понижена по пробегу: при approve, reject и review по LTV.
Источник: intent §3.4; интервью, вопрос 4.

REQ-MILEAGE-08. У заявки, пониженной по пробегу, `approved_limit` = 0:
лимит выдаётся только при approve.
Источник: intent §3.5; текущий код `backend/src/Domain/AssessmentService.php`
(лимит = запрошенная сумма при approve, иначе 0) + интервью, вопрос 1
(пониженная заявка — не approve).

REQ-MILEAGE-09. Правило действует только на заявки, прошедшие
существующую валидацию пробега — диапазон 0..500 000 км. Пробег вне
диапазона отклоняется валидацией (HTTP 422) и до правила не доходит.
Источник: intent §3.6; код `backend/src/Domain/ApplicationValidator.php`;
предел 500 000 км — `backend/config/rules.php` (`vehicle.max_mileage_km`).

REQ-MILEAGE-10. Если поле `mileage` отсутствует в заявке или равно
`null`, заявка отклоняется ошибкой валидации: HTTP 422, в списке ошибок
есть ключ `mileage`; расчёт решения не выполняется.
Источник: intent §3.6; код `backend/src/Domain/ApplicationValidator.php`
(отсутствие или null → −1, −1 не проходит проверку диапазона).

REQ-MILEAGE-11. Пустая строка в поле `mileage` трактуется как 0 км:
заявка проходит валидацию и доходит до расчёта решения. Существующее
поведение, валидация не меняется.
Источник: intent §3.7; интервью, вопрос 2; код
`backend/src/Domain/ApplicationValidator.php` (`(int) ''` = 0).

## 3. Критерии приёмки

Общее для всех AC:

- Заявка валидна по остальным полям (VIN, год, стоимость, сумма, срок)
  по `backend/config/rules.php`.
- LTV задаётся парой «запрошенная сумма / оценочная стоимость»
  (LTV = сумма / стоимость × 100, `backend/src/Domain/LtvCalculator.php`).
  Примеры: 500 000 / 1 000 000 → 50.0; 750 000 / 1 000 000 → 75.0;
  950 000 / 1 000 000 → 95.0; суммы в пределах `amount.min`–`amount.max`
  (rules.php: 50 000–2 000 000).
- Зоны LTV: < 65.0 — approve; 65.0..85.0 — review по LTV; > 85.0 — reject
  (`ltv.approve_max = 65.0`, `ltv.review_max = 85.0` — rules.php;
  `backend/src/Domain/DecisionEngine.php`). Значения 50.0 / 75.0 / 95.0
  выбраны внутри зон, не на границах: на `approve_max` в коде есть
  существенное расхождение `<` / `<=` (plan, «Риски», п. 5) — чинить его
  в задачу не входит.
- Эндпоинты: `POST /api/ltv` — расчёт решения без сохранения, успех
  HTTP 200; `POST /api/applications` — приём заявки, успех HTTP 201;
  ошибка валидации — HTTP 422 с объектом `errors`
  (`backend/src/Http/ApplicationController.php`, `backend/src/Support/Json.php`).
- Граничные значения порога — AC-01–AC-03: ровно перед порогом,
  ровно на пороге, первое за порогом (399 999 / 400 000 / 400 001,
  docs/spec/README.md).

AC-MILEAGE-01 (REQ-MILEAGE-01, REQ-MILEAGE-07).
Given: пробег 399 999 км, LTV 50.0 (зона approve).
When: `POST /api/ltv`.
Then: HTTP 200, `decision: "approve"`, `approved_limit` = запрошенной
сумме, поля `reason` в ответе нет.

AC-MILEAGE-02 (REQ-MILEAGE-01, REQ-MILEAGE-07).
Given: пробег 400 000 км — ровно порог, LTV 50.0.
When: `POST /api/ltv`.
Then: HTTP 200, `decision: "approve"`, `approved_limit` = запрошенной
сумме, поля `reason` нет — граница «не больше» включительная.

AC-MILEAGE-03 (REQ-MILEAGE-01, REQ-MILEAGE-02, REQ-MILEAGE-03,
REQ-MILEAGE-06, REQ-MILEAGE-08).
Given: пробег 400 001 км — первое значение за порогом, LTV 50.0.
When: `POST /api/ltv`.
Then: HTTP 200 (не 422), `decision: "review"`, `approved_limit: 0`,
`reason: "high_mileage"`.

AC-MILEAGE-04 (REQ-MILEAGE-06, REQ-MILEAGE-08).
Given: пробег 400 001 км, LTV 50.0.
When: `POST /api/applications`.
Then: HTTP 201, `decision: "review"`, `approved_limit: 0`,
`reason: "high_mileage"`.

AC-MILEAGE-05 (REQ-MILEAGE-04, REQ-MILEAGE-07).
Given: пробег 400 001 км, LTV 95.0 — reject по LTV.
When: `POST /api/ltv`.
Then: HTTP 200, `decision: "reject"`, поля `reason` нет — высокий
пробег не ослабляет reject.

AC-MILEAGE-06 (REQ-MILEAGE-05, REQ-MILEAGE-07, REQ-MILEAGE-08).
Given: пробег 400 001 км, LTV 75.0 — review по LTV.
When: `POST /api/ltv`.
Then: HTTP 200, `decision: "review"`, `approved_limit: 0`, поля
`reason` нет — review по LTV правилом не помечается.

AC-MILEAGE-07 (REQ-MILEAGE-01, REQ-MILEAGE-09).
Given: пробег 500 000 км — верхняя граница валидации, LTV 50.0.
When: `POST /api/ltv`.
Then: HTTP 200, `decision: "review"`, `approved_limit: 0`,
`reason: "high_mileage"` — правило действует во всей зоне
400 001..500 000.

AC-MILEAGE-08 (REQ-MILEAGE-09).
Given: пробег 500 001 км — за пределом валидации.
When: `POST /api/ltv`.
Then: HTTP 422, `errors` содержит ключ `mileage`, решения в ответе
нет — правило не расширяет жёсткую валидацию.

AC-MILEAGE-09 (REQ-MILEAGE-10).
Given: поле `mileage` в заявке отсутствует.
When: `POST /api/ltv`.
Then: HTTP 422, `errors` содержит ключ `mileage`, решения в ответе нет.

AC-MILEAGE-10 (REQ-MILEAGE-10).
Given: `mileage: null`.
When: `POST /api/ltv`.
Then: HTTP 422, `errors` содержит ключ `mileage`, решения в ответе нет.

AC-MILEAGE-11 (REQ-MILEAGE-11, REQ-MILEAGE-07).
Given: `mileage: ""`, LTV 50.0.
When: `POST /api/ltv`.
Then: HTTP 200 (не 422), `decision: "approve"`, поля `reason` нет —
пустая строка трактуется как 0 км.

## 4. Open questions из intent

OQ-1 (intent §4.1). Понижает ли правило только approve (не reject)?
Статус: **закрыт** — интервью, вопрос 1: только approve → review;
reject и review по LTV не меняются. Требования: REQ-MILEAGE-03,
REQ-MILEAGE-04, REQ-MILEAGE-05.

OQ-2 (intent §4.2). Пустая строка в пробеге — 422-ошибка или 0 км?
Статус: **закрыт** — интервью, вопрос 2: оставить как есть, 0 км;
валидация не меняется. Требование: REQ-MILEAGE-11, критерий
AC-MILEAGE-11.

OQ-3 (intent §4.3). Нужна ли причина review в ответе API и когда
она появляется?
Статус: **закрыт** — интервью, вопросы 3–4: `reason: "high_mileage"`,
только при понижении по пробегу. Требования: REQ-MILEAGE-06,
REQ-MILEAGE-07.

OQ-4 (intent §4.4). Пересчитывать ли по новому правилу исторические
заявки и seed-данные?
Статус: **перенесён** — остаётся открытым, уходит заказчику до
реализации задачи MILEAGE; спека требований о пересчёте не содержит.
Допущение плана — «не пересчитывать» (`docs/plan/plan_MILEAGE.md`,
«Вопросы», п. 4) — заказчиком не подтверждено; если ответ изменится,
открывается отдельная задача.

## 5. Соответствие intent

| Требование | Ограничение intent |
|---|---|
| REQ-MILEAGE-01 | §3.1 |
| REQ-MILEAGE-02 | §3.2 |
| REQ-MILEAGE-03, REQ-MILEAGE-04, REQ-MILEAGE-05 | §3.3 |
| REQ-MILEAGE-06, REQ-MILEAGE-07 | §1, §3.4 |
| REQ-MILEAGE-08 | §3.5 |
| REQ-MILEAGE-09, REQ-MILEAGE-10 | §3.6 |
| REQ-MILEAGE-11 | §3.7 |

Каждое требование — переформулировка ограничения из intent §3;
требований, которых не было в intent, спека не содержит.
