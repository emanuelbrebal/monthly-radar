<?php

it('fails with an unknown source key', function () {
    $this->artisan('radar:source', ['key' => 'nao_existe'])
        ->assertFailed();
});

it('reports the documented reason when provas_de_ti is run', function () {
    $this->artisan('radar:source', ['key' => 'provas_de_ti', '--dry' => true])
        ->assertFailed();
});
