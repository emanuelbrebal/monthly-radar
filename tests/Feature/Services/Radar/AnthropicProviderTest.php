<?php

use App\Services\Radar\Llm\AnthropicProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['radar.llm.api_key' => 'sk-test', 'radar.llm.base_url' => 'https://api.anthropic.test/v1/messages']);
});

it('is unavailable without an api key', function () {
    config(['radar.llm.api_key' => null]);

    expect((new AnthropicProvider)->available())->toBeFalse();
});

it('is available with an api key configured', function () {
    expect((new AnthropicProvider)->available())->toBeTrue();
});

it('parses text, tokens and web search count from the messages response', function () {
    Http::fake([
        'api.anthropic.test/*' => Http::response([
            'content' => [
                ['type' => 'server_tool_use', 'name' => 'web_search'],
                ['type' => 'text', 'text' => '{"campos":{}}'],
            ],
            'usage' => [
                'input_tokens' => 1200,
                'output_tokens' => 340,
                'server_tool_use' => ['web_search_requests' => 2],
            ],
        ], 200),
    ]);

    $response = (new AnthropicProvider)->complete('system prompt', 'input do concurso', 5);

    expect($response->text)->toBe('{"campos":{}}')
        ->and($response->inputTokens)->toBe(1200)
        ->and($response->outputTokens)->toBe(340)
        ->and($response->webSearches)->toBe(2);

    Http::assertSent(function (Request $request) {
        return $request->hasHeader('x-api-key', 'sk-test')
            && $request['tools'][0]['type'] === 'web_search_20250305'
            && $request['tools'][0]['max_uses'] === 5;
    });
});

it('throws when the api responds with an error status', function () {
    Http::fake([
        'api.anthropic.test/*' => Http::response(['error' => ['message' => 'overloaded']], 529),
    ]);

    (new AnthropicProvider)->complete('system', 'input', 5);
})->throws(RuntimeException::class);
