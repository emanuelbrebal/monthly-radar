<?php

use App\Services\Radar\DossierValidationException;
use App\Services\Radar\DossierValidator;

function validDossierJson(): string
{
    return json_encode([
        'campos' => [
            'board' => ['valor' => 'FGV', 'fonte' => ['url' => 'https://banca.gov.br/edital', 'confianca' => 'high']],
            'it_vacancies' => ['valor' => 10, 'fonte' => ['url' => 'https://banca.gov.br/edital', 'confianca' => 'high']],
        ],
        'eventos_extra' => [
            ['tipo' => 'result', 'data' => '2026-05-01', 'rotulo' => 'divulgação do resultado'],
        ],
    ], JSON_UNESCAPED_UNICODE);
}

it('accepts a dossier that matches the schema', function () {
    $dossier = (new DossierValidator)->validate(validDossierJson());

    expect($dossier['campos']['board']['valor'])->toBe('FGV')
        ->and($dossier['eventos_extra'][0]['tipo'])->toBe('result');
});

it('rejects invalid json', function () {
    (new DossierValidator)->validate('não é json');
})->throws(DossierValidationException::class);

it('rejects a field outside the allowed schema', function () {
    $json = json_encode([
        'campos' => [
            'salario_bruto' => ['valor' => 5000, 'fonte' => ['url' => 'https://x.com', 'confianca' => 'medium']],
        ],
    ]);

    (new DossierValidator)->validate($json);
})->throws(DossierValidationException::class);

it('rejects a campo without fonte.url', function () {
    $json = json_encode([
        'campos' => [
            'board' => ['valor' => 'FGV', 'fonte' => ['confianca' => 'high']],
        ],
    ]);

    (new DossierValidator)->validate($json);
})->throws(DossierValidationException::class);

it('rejects an invalid confianca value', function () {
    $json = json_encode([
        'campos' => [
            'board' => ['valor' => 'FGV', 'fonte' => ['url' => 'https://x.com', 'confianca' => 'certeza absoluta']],
        ],
    ]);

    (new DossierValidator)->validate($json);
})->throws(DossierValidationException::class);

it('rejects eventos_extra with a bad date format', function () {
    $json = json_encode([
        'campos' => [],
        'eventos_extra' => [
            ['tipo' => 'result', 'data' => '01/05/2026'],
        ],
    ]);

    (new DossierValidator)->validate($json);
})->throws(DossierValidationException::class);

it('accepts an empty campos object when nothing was found', function () {
    $dossier = (new DossierValidator)->validate(json_encode(['campos' => []]));

    expect($dossier['campos'])->toBe([]);
});
