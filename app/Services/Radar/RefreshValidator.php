<?php

namespace App\Services\Radar;

use App\Enums\EditalStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use JsonException;

/**
 * Valida a resposta bruta do agente de revisão contra o schema de
 * docs/agents/revisao.md. Puro (sem I/O) — mesmo contrato de
 * DossierValidator: devolve o array validado ou lança
 * DossierValidationException com as mensagens pro retry.
 */
class RefreshValidator
{
    /**
     * Campos que a revisão pode mudar — os do dono (situação, maleta,
     * descarte, anotações) ficam de fora de propósito.
     */
    public const array ALLOWED_FIELDS = [
        'status',
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

    private const array DATE_FIELDS = ['registration_start', 'registration_end', 'exam_date'];

    private const array INTEGER_FIELDS = ['fee_cents', 'salary_min_cents', 'salary_max_cents', 'it_vacancies'];

    /**
     * @return array{campos: array<string, array{valor: mixed, fonte: array{url: string, confianca: string, data: string}}>, eventos_extra?: array<int, array<string, mixed>>, novidades?: array<int, string>}
     */
    public function validate(string $rawText, ?Carbon $today = null): array
    {
        $decoded = $this->decode($rawText);
        $today = ($today ?? Carbon::now())->toDateString();

        $unknownFields = array_diff(array_keys((array) ($decoded['campos'] ?? [])), self::ALLOWED_FIELDS);

        if ($unknownFields !== []) {
            throw new DossierValidationException(['Campo(s) fora do schema: '.implode(', ', $unknownFields)]);
        }

        $rules = [
            'campos' => ['present', 'array'],
            'campos.*.valor' => ['present'],
            'campos.*.fonte' => ['required', 'array'],
            'campos.*.fonte.url' => ['required', 'string', 'url'],
            'campos.*.fonte.confianca' => ['required', 'string', 'in:high,medium,low'],
            'campos.*.fonte.data' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$today],
            'campos.status.valor' => ['sometimes', Rule::enum(EditalStatus::class)],
            'campos.regime.valor' => ['sometimes', 'in:clt,statutory,temporary'],
            'campos.board.valor' => ['sometimes', 'string'],
            'campos.edital_url.valor' => ['sometimes', 'url'],
            'campos.registration_url.valor' => ['sometimes', 'url'],
            'campos.locations.valor' => ['sometimes', 'array'],
            'campos.locations.valor.*' => ['string'],
            'eventos_extra' => ['sometimes', 'array'],
            'eventos_extra.*.tipo' => ['required', 'string', 'in:fee_deadline,result,other'],
            'eventos_extra.*.data' => ['required', 'date_format:Y-m-d'],
            'eventos_extra.*.rotulo' => ['sometimes', 'nullable', 'string'],
            'novidades' => ['sometimes', 'array', 'max:5'],
            'novidades.*' => ['string'],
        ];

        foreach (self::DATE_FIELDS as $field) {
            $rules["campos.{$field}.valor"] = ['sometimes', 'date_format:Y-m-d'];
        }

        foreach (self::INTEGER_FIELDS as $field) {
            $rules["campos.{$field}.valor"] = ['sometimes', 'integer', 'min:0'];
        }

        $validator = Validator::make($decoded, $rules, [
            'campos.*.fonte.data.before_or_equal' => 'fonte.data é a data de publicação da fonte e não pode ser futura (:attribute).',
        ]);

        if ($validator->fails()) {
            throw new DossierValidationException($validator->errors()->all());
        }

        /** @var array{campos: array<string, array{valor: mixed, fonte: array{url: string, confianca: string, data: string}}>, eventos_extra?: array<int, array<string, mixed>>, novidades?: array<int, string>} $decoded */
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
