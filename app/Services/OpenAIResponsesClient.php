<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAIResponsesClient
{
    public function generateTextResponse(string $instructions, string $input): string
    {
        $configuration = config('services.openai');
        $apiKey = $configuration['api_key'];
        $model = $configuration['model'];
        if ($configuration['enabled'] !== true || ! is_string($apiKey) || $apiKey === '' || ! is_string($model) || $model === '') {
            throw new RuntimeException('OpenAI no está habilitado o requiere credenciales y modelo.');
        }

        $response = Http::withToken($apiKey)->acceptJson()->asJson()->timeout(30)->post('https://api.openai.com/v1/responses', [
            'model' => $model,
            'instructions' => $instructions,
            'input' => $input,
            'store' => false,
            'max_output_tokens' => 1200,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('OpenAI no pudo generar una respuesta.');
        }

        $text = collect($response->json('output', []))
            ->where('type', 'message')
            ->flatMap(fn (array $item): array => $item['content'] ?? [])
            ->where('type', 'output_text')
            ->pluck('text')
            ->filter(fn (mixed $part): bool => is_string($part))
            ->implode("\n");

        if ($text === '') {
            throw new RuntimeException('OpenAI devolvió una respuesta sin texto.');
        }

        return $text;
    }
}
