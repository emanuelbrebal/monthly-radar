<?php

namespace App\Services\Radar;

use Illuminate\Support\Facades\Validator;
use JsonException;

/**
 * Valida a resposta bruta do LlmProvider contra o schema de
 * docs/agents/dossie.md. Puro (sem I/O) — recebe string, devolve array
 * validado ou lança DossierValidationException com as mensagens pra
 * EnrichContestJob reanexar no retry.
 */
class DossierValidator
{
    /**
     * Campos que o agente pode preencher em `campos` — ver
     * docs/agents/dossie.md → "Campos permitidos".
     */
    private const array ALLOWED_FIELDS = [
        'board',
        'registration_start',
        'registration_end',
        'exam_date',
        'fee_cents',
        'salary_min_cents',
        'salary_max_cents',
        'it_vacancies',
        'regime',
        'edital_url',
        'registration_url',
        'locations',
    ];

    /**
     * @return array<string, mixed>
     */
    public function validate(string $rawText): array
    {
        $decoded = $this->decode($rawText);

        $unknownFields = array_diff(array_keys($decoded['campos'] ?? []), self::ALLOWED_FIELDS);

        if ($unknownFields !== []) {
            throw new DossierValidationException(['Campo(s) fora do schema: '.implode(', ', $unknownFields)]);
        }

        $rules = [
            'campos' => ['present', 'array'],
            'campos.*.valor' => ['present'],
            'campos.*.fonte' => ['required', 'array'],
            'campos.*.fonte.url' => ['required', 'string', 'url'],
            'campos.*.fonte.confianca' => ['required', 'string', 'in:high,medium,low'],
            'eventos_extra' => ['sometimes', 'array'],
            'eventos_extra.*.tipo' => ['required_with:eventos_extra', 'string', 'in:fee_deadline,result,other'],
            'eventos_extra.*.data' => ['required_with:eventos_extra', 'date_format:Y-m-d'],
            'eventos_extra.*.rotulo' => ['sometimes', 'nullable', 'string'],
        ];

        $validator = Validator::make($decoded, $rules);

        if ($validator->fails()) {
            throw new DossierValidationException($validator->errors()->all());
        }

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $rawText): array
    {
        try {
            $decoded = json_decode(trim($rawText), associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new DossierValidationException(["JSON inválido: {$e->getMessage()}"]);
        }

        if (! is_array($decoded)) {
            throw new DossierValidationException(['A resposta precisa ser um objeto JSON.']);
        }

        return $decoded;
    }
}
