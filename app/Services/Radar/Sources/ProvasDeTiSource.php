<?php

namespace App\Services\Radar\Sources;

/**
 * Verificado na T2 (16/09/2026): provasdeti.com.br/concursosdeti/abertos é
 * uma SPA React sem HTML no servidor (`<div id="root"></div>` vazio,
 * conteúdo só via /static/js/bundle.js). robots.txt permite tudo
 * (`Disallow:` vazio), mas isso não muda o problema: sem headless browser
 * (proibido — CLAUDE.md → "O que não fazer") não tem o que selecionar.
 * Nenhuma API pública encontrada no bundle pra consumir direto.
 *
 * Desativada por decisão do usuário (T2): config('radar.sources.provas_de_ti.enabled')
 * fica false até um substituto server-rendered aparecer. fetch() lança
 * SourceRequiresBrowserException pra deixar o motivo explícito caso alguém
 * ligue enabled=true por engano.
 */
class ProvasDeTiSource implements ContestSource
{
    public function key(): string
    {
        return 'provas_de_ti';
    }

    public function enabled(): bool
    {
        return (bool) config('radar.sources.provas_de_ti.enabled', false);
    }

    public function fetch(): iterable
    {
        throw new SourceRequiresBrowserException(
            'provas_de_ti é uma SPA React sem HTML no servidor — precisa de headless browser, que o projeto não usa. Fonte desativada em config/radar.php.',
        );
    }
}
