<?php

namespace Tests\Feature;

use App\Services\OpenAIResponsesClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class OpenAIResponsesClientTest extends TestCase
{
    public function test_openai_responses_client_uses_server_credentials_without_persisting_the_response(): void
    {
        config(['services.openai' => ['enabled' => true, 'api_key' => 'test-secret', 'model' => 'gpt-test']]);
        Http::fake(['https://api.openai.com/v1/responses' => Http::response([
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Respuesta de prueba']]]],
        ])]);

        $result = app(OpenAIResponsesClient::class)->generateTextResponse('Responde en español.', 'Pregunta general');

        $this->assertSame('Respuesta de prueba', $result);
        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer test-secret')
            && $request['model'] === 'gpt-test'
            && $request['store'] === false);
    }

    public function test_openai_client_is_disabled_by_default(): void
    {
        config(['services.openai' => ['enabled' => false, 'api_key' => null, 'model' => null]]);
        Http::fake();

        try {
            app(OpenAIResponsesClient::class)->generateTextResponse('Instrucciones', 'Consulta');
            $this->fail('Se esperaba que el cliente deshabilitado rechazara la solicitud.');
        } catch (RuntimeException) {
            Http::assertNothingSent();
        }
    }
}
