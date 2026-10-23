<?php

use App\Enums\EditalStatus;
use App\Services\Radar\ContestNormalizer;

beforeEach(function () {
    $this->normalizer = new ContestNormalizer;
});

it('matches an explicit IT role', function (string $text, string $area) {
    expect($this->normalizer->matchedAreas($text))->toContain($area);
})->with([
    'analista de sistemas' => ['Vaga para Analista de Sistemas', 'development'],
    'glued to a salary' => ['20 vagas até R$ 6.000,00Analista de TI, Escrivão', 'development'],
    'full name, accented' => ['Analista de Tecnologia da Informação', 'development'],
    'judiciary specialty' => ['Técnico Judiciário - Informática', 'support'],
    'plural' => ['abre concurso para desenvolvedores', 'development'],
    'tecnico em informatica' => ['Técnico em Informática | Médio', 'support'],
    'data scientist' => ['Cientista de Dados', 'data'],
    'security analyst' => ['Analista de Segurança da Informação', 'security'],
    'network' => ['Analista de Redes', 'infrastructure'],
]);

it('does not match text without an IT role', function (string $text) {
    expect($this->normalizer->matchedAreas($text))->toBe([]);
})->with([
    'organ name' => ['FUNCAMP - Fundação de Desenvolvimento da Unicamp'],
    'organ name, economic' => ['CIDEMA - Consórcio Intermunicipal de Desenvolvimento Econômico'],
    'education role' => ['Diretor, Auxiliar de Desenvolvimento Infantil'],
    'substring of cuidados' => ['Cuidador de idosos: cuidados básicos'],
    'substring of paredes' => ['Pedreiro para paredes e muros'],
    'syllabus mention' => ['Agente Administrativo (noções de informática)'],
    'unrelated database' => ['Banco de Dados Ambientais da secretaria'],
    'legal role' => ['Concurso para Procurador do estado'],
    'administrative support' => ['Técnico de Suporte Administrativo Educacional'],
]);

it('extracts acronym and year from the title', function () {
    expect($this->normalizer->extractOrganAcronymYear('TRF3 2026 — Analista Judiciário'))
        ->toBe(['acronym' => 'TRF', 'year' => 2026]);
});

it('extracts status from keyword signals', function () {
    expect($this->normalizer->extractStatus('Concurso XPTO: inscrições abertas até 10/10'))
        ->toBe(EditalStatus::RegistrationOpen);

    expect($this->normalizer->extractStatus('Concurso XPTO tem edital publicado'))
        ->toBe(EditalStatus::Published);

    expect($this->normalizer->extractStatus('Sem nenhuma informação relevante'))
        ->toBe(EditalStatus::Expected);
});

it('extracts and classifies dates by context', function () {
    $dates = $this->normalizer->extractDates('Inscrições até 10/10/2026. Prova em 06/12/2026.');

    expect($dates)->toBe([
        ['type' => 'registration', 'date' => '2026-10-10'],
        ['type' => 'exam', 'date' => '2026-12-06'],
    ]);
});

it('extracts the official edital pdf url, preferring .gov.br', function () {
    $links = [
        'https://blog.com/edital-resumo.pdf',
        'https://orgao.gov.br/edital-oficial.pdf',
        'https://blog.com/outra-coisa.pdf',
    ];

    expect($this->normalizer->extractEditalUrl($links))->toBe('https://orgao.gov.br/edital-oficial.pdf');
});

it('extracts a state from a slash-separated city/UF pair', function () {
    expect($this->normalizer->extractStates('Prova em Natal/RN')['states'])->toBe(['RN']);
});

it('extracts a state from a hyphenated organ acronym', function () {
    expect($this->normalizer->extractStates('TJ-RN abre concurso')['states'])->toBe(['RN']);
});

it('extracts a state from the capital name by alias', function () {
    expect($this->normalizer->extractStates('Vagas em Brasília')['states'])->toBe(['DF']);
});

it('marks nationwide from a textual signal', function () {
    $result = $this->normalizer->extractStates('Vagas em todo o Brasil');

    expect($result['nationwide'])->toBeTrue();
});

it('marks nationwide when 5 or more distinct states are found', function () {
    $result = $this->normalizer->extractStates('Provas em Natal/RN, Recife/PE, Fortaleza/CE, Salvador/BA e Maceió/AL');

    expect($result['states'])->toHaveCount(5)
        ->and($result['nationwide'])->toBeTrue();
});

it('leaves states empty when the organ has no known location', function () {
    $result = $this->normalizer->extractStates('Banco do Brasil — Agente de Tecnologia');

    expect($result['states'])->toBe([])
        ->and($result['nationwide'])->toBeFalse();
});

it('finds a similar organ name above the similarity threshold', function () {
    $match = $this->normalizer->findSimilarOrgan(
        'Empresa de Tecnologia e Informacoes da Previdencia',
        ['Empresa de Tecnologia e Informações da Previdência Social', 'Petróleo Brasileiro S.A.'],
    );

    expect($match)->toBe('Empresa de Tecnologia e Informações da Previdência Social');
});

it('finds no similar organ below the threshold', function () {
    $match = $this->normalizer->findSimilarOrgan('Dataprev', ['Petróleo Brasileiro S.A.']);

    expect($match)->toBeNull();
});
