<?php

declare(strict_types=1);

namespace CarMoneyLab\Domain;

/**
 * Предварительная оценка заявки: валидация -> LTV -> решение -> лимит.
 *
 * Лимит сейчас равен запрошенной сумме при approve и нулю в остальных случаях.
 * Расчёт лимита по максимальному LTV для возраста авто (справочник
 * rules.ltv_by_age) — задача LOAN-12, она ещё не сделана.
 *
 * После решения по LTV применяется правило пробега: если пробег выше
 * review_mileage_km и решение было approve, оно понижается до review и
 * в результат добавляется reason='high_mileage'. У остальных решений
 * ключа reason нет.
 */
final class AssessmentService
{
    public const REASON_HIGH_MILEAGE = 'high_mileage';

    public function __construct(
        private readonly ApplicationValidator $validator,
        private readonly LtvCalculator $ltvCalculator,
        private readonly DecisionEngine $decisionEngine,
        private readonly VehicleAge $vehicleAge,
        private readonly int $reviewMileageKm,
    ) {
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{vehicle_age:int,ltv:float,decision:string,approved_limit:int,input:array<string,mixed>,reason?:string}
     */
    public function assess(array $payload): array
    {
        $input = $this->validator->validate($payload);

        $ltv = $this->ltvCalculator->calculate($input['requested_amount'], $input['market_value']);
        $decision = $this->decisionEngine->decide($ltv);

        $reason = null;
        if ($decision === DecisionEngine::APPROVE && $input['mileage'] > $this->reviewMileageKm) {
            $decision = DecisionEngine::REVIEW;
            $reason = self::REASON_HIGH_MILEAGE;
        }

        $result = [
            'vehicle_age' => $this->vehicleAge->inYears($input['year']),
            'ltv' => $ltv,
            'decision' => $decision,
            'approved_limit' => $decision === DecisionEngine::APPROVE ? $input['requested_amount'] : 0,
            'input' => $input,
        ];

        if ($reason !== null) {
            $result['reason'] = $reason;
        }

        return $result;
    }
}
