<?php

use App\Services\Radar\DossierValidationException;
use App\Services\Radar\RefreshValidator;
use Illuminate\Support\Carbon;

function refreshJson(array $campos, array $extra = []): string
{
    return json_encode(['campos' => $campos, ...$extra], JSON_UNESCAPED_UNICODE);
}

function refreshField(mixed $valor, string $data = '2026-10-10'): array
{
    return ['valor' => $valor, 'fonte' => ['url' => 'https://orgao.gov.br/x', 'confianca' => 'high', 'data' => $data]];
}

beforeEach(function () {
    $this->validator = new RefreshValidator;
    $this->today = Carbon::parse('2026-10-17');
});

it('accepts an empty review (nothing changed)', function () {
    $review = $this->validator->validate('{"campos": {}, "novidades": []}', $this->today);

    expect($review['campos'])->toBe([]);
});

it('accepts status, money and dates in the documented formats', function () {
    $review = $this->validator->validate(refreshJson([
        'status' => refreshField('exam_done'),
        'salary_min_cents' => refreshField(1803352),
        'exam_date' => refreshField('2026-12-13'),
    ], ['novidades' => ['Prova aplicada em 09/08/2026.']]), $this->today);

    expect($review['campos'])->toHaveCount(3);
});

it('rejects owner fields', function () {
    $this->validator->validate(refreshJson(['enrollment_status' => refreshField('enrolled')]), $this->today);
})->throws(DossierValidationException::class, 'enrollment_status');

it('rejects a source without a date or with a future date', function () {
    expect(fn () => $this->validator->validate(refreshJson([
        'board' => ['valor' => 'FCC', 'fonte' => ['url' => 'https://orgao.gov.br/x', 'confianca' => 'high']],
    ]), $this->today))->toThrow(DossierValidationException::class);

    expect(fn () => $this->validator->validate(refreshJson([
        'board' => refreshField('FCC', '2026-10-20'),
    ]), $this->today))->toThrow(DossierValidationException::class);
});

it('rejects values outside the enums and formats', function (string $field, mixed $value) {
    $this->validator->validate(refreshJson([$field => refreshField($value)]), $this->today);
})->throws(DossierValidationException::class)->with([
    'status fora do enum' => ['status', 'aberto'],
    'dinheiro em reais' => ['salary_min_cents', '18.033,52'],
    'data fora do formato' => ['exam_date', '13/12/2026'],
    'regime inventado' => ['regime', 'cargo_em_comissao'],
]);

it('rejects text that is not JSON', function () {
    $this->validator->validate('Aqui está o JSON: {}', $this->today);
})->throws(DossierValidationException::class, 'JSON inválido');
