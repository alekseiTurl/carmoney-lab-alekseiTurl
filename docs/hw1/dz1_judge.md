# Независимая проверка MILEAGE

Дата: 2026-09-29. Сравнение: `origin/main...HEAD` ветки `hw1/dz1-alekseiTurl` (`8859fb8`). Проверены `AGENTS.md`, intent, grill, spec, plan, изменённый PHP-код, валидатор, LTV-решение, контроллер, репозиторий и тесты. Указанные тесты находятся в `tests/Unit/AssessmentServiceTest.php` и `tests/Unit/ApplicationValidatorTest.php`.

| Требование | Проверка и доказательство | Итог |
|---|---|---|
| REQ-MILEAGE-01 | `AssessmentServiceTest::testApprovesMileageBelowReviewThresholdWithoutReason`, `testApprovesMileageAtReviewThresholdWithoutReason`, `testDowngradesApproveToReviewAboveMileageThresholdWithReason`; HTTP `/api/ltv`: 399999/400000 → approve, 400001 → review | Покрыто |
| REQ-MILEAGE-02 | `AssessmentServiceTest::testDowngradesApproveToReviewAboveMileageThresholdWithReason`; HTTP 400001 → 200/review, не 422 | Покрыто |
| REQ-MILEAGE-03 | `AssessmentServiceTest::testDowngradesApproveToReviewAboveMileageThresholdWithReason`, `testKeepsRejectForHighMileageWithoutReason`, `testKeepsLtvReviewForHighMileageWithoutReason`; условие `APPROVE` в `AssessmentService::assess()` | Покрыто |
| REQ-MILEAGE-04 | `AssessmentServiceTest::testKeepsRejectForHighMileageWithoutReason`; HTTP 400001, LTV 95 → reject без `reason` | Покрыто |
| REQ-MILEAGE-05 | `AssessmentServiceTest::testKeepsLtvReviewForHighMileageWithoutReason`; HTTP 400001, LTV 75 → review без `reason` | Покрыто |
| REQ-MILEAGE-06 | `AssessmentServiceTest::testDowngradesApproveToReviewAboveMileageThresholdWithReason`, `testDowngradesApproveAtHardMileageLimitWithReason`; HTTP `/api/ltv` 400001/500000 → `reason: high_mileage`; вручную проверены условные вставки в оба ответа `ApplicationController` | Покрыто; HTTP `/api/applications` не вызван, поскольку создаёт запись |
| REQ-MILEAGE-07 | `AssessmentServiceTest` проверяет отсутствие ключа при 399999, 400000, LTV review/reject и пустой строке; HTTP `/api/ltv` подтверждает отсутствие поля в этих ответах; контроллер включает ключ только при его наличии в результате | Покрыто |
| REQ-MILEAGE-08 | `AssessmentServiceTest::testDowngradesApproveToReviewAboveMileageThresholdWithReason`; HTTP 400001/500000 → `approved_limit: 0`; лимит вычисляется после понижения решения | Покрыто |
| REQ-MILEAGE-09 | `AssessmentServiceTest::testDowngradesApproveAtHardMileageLimitWithReason`, `ApplicationValidatorTest::testRejectsMileageAboveHardLimit`; HTTP 500000 → 200/review, 500001 → 422 | Покрыто |
| REQ-MILEAGE-10 | `ApplicationValidatorTest::testRejectsMissingMileageField`, `testRejectsNullMileage`; HTTP для обоих случаев → 422; контроллер ловит `ValidationException` и возвращает `errors`, валидатор кладёт туда `mileage` | Покрыто; тело HTTP-ошибки не удалось прочитать |
| REQ-MILEAGE-11 | `ApplicationValidatorTest::testAcceptsEmptyMileageStringAndNormalisesToZero`, `AssessmentServiceTest::testTreatsEmptyMileageStringAsZeroAndApprovesWithoutReason`; HTTP пустая строка → 200/approve без `reason` | Покрыто |

## Замечания и пределы проверки

- Дефектов, внесённых изменением, не обнаружено. Все пять запрошенных границ — 399999, 400000, 400001, 500000, 500001 — покрыты тестами и подтверждены HTTP-статусом/ответом в доступной части ручной проверки. `reason` возникает только при понижении approve → review; LTV review и reject остаются прежними.
- `POST /api/applications` не проверялся живым запросом, поскольку он сохраняет заявку. Его HTTP 201 и состав ответа подтверждены чтением `ApplicationController::create()`, но исполняемого HTTP-теста в репозитории нет. Для 422 по 500001, `null` и отсутствующему полю удалось увидеть статус, но не тело: после запросов локальный сервис перестал отвечать. Наличие ключа `mileage` подтверждают unit-тесты и код валидатора/контроллера.
- PHPUnit и PHP lint локально не запускались: `php` и `vendor/bin/phpunit` отсутствуют, а установленный `docker` не поддерживает `docker compose` и не даёт прочитать конфигурацию. `git diff --check origin/main...HEAD` завершился без замечаний. Это ограничение среды, не результат падения тестов.
- Лишних требований в REQ-MILEAGE-01..11 относительно intent и ответов grill не найдено. Изменения затрагивают только порог, правило, `reason`, wiring и тесты; существующие LTV-пороги и жёсткий предел 500000 не изменены. Открытый вопрос о пересчёте исторических заявок явно оставлен вне scope.
- История соблюдает порядок test-first: `78821c1` (`test: cover MILEAGE boundaries before implementation`) стоит перед `8859fb8` (`feat: downgrade high-mileage approvals to review`). Посторонний неотслеживаемый каталог `.kilo/plans/` существовал до проверки; он не менялся.

**Вердикт:** принять реализацию MILEAGE с оговоркой об ограниченной исполняемой проверке HTTP и недоступном в этой среде PHPUnit/lint. Критичных или иных подтверждённых дефектов нет.
