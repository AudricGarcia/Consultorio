<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhatsAppCloudApi
{
    public function isConfigured(): bool
    {
        $configuration = config('services.whatsapp');

        return $configuration['template_approved'] === true
            && is_string($configuration['token']) && $configuration['token'] !== ''
            && is_string($configuration['phone_number_id']) && preg_match('/^\d+$/', $configuration['phone_number_id']) === 1
            && is_string($configuration['graph_version']) && preg_match('/^v\d{1,3}\.\d$/', $configuration['graph_version']) === 1
            && is_string($configuration['template_name']) && $configuration['template_name'] !== ''
            && is_string($configuration['template_language']) && $configuration['template_language'] !== '';
    }

    /** @param array<string, mixed> $templateParameters */
    public function sendTemplate(string $recipient, array $templateParameters): string
    {
        $configuration = config('services.whatsapp');
        if (! $this->isConfigured()) {
            throw new RuntimeException('La configuración de WhatsApp está incompleta.');
        }

        $version = $configuration['graph_version'];
        $phoneNumberId = $configuration['phone_number_id'];
        $templateName = $configuration['template_name'];
        $language = $configuration['template_language'];
        $token = $configuration['token'];

        $components = [];
        if ($templateParameters !== []) {
            $components[] = [
                'type' => 'body',
                'parameters' => array_map(fn (string $value): array => ['type' => 'text', 'text' => $value], array_values($templateParameters)),
            ];
        }

        /** @var PendingRequest $request */
        $request = Http::withToken($token)->acceptJson()->asJson()->timeout(10);
        $response = $request->post("https://graph.facebook.com/{$version}/{$phoneNumberId}/messages", [
            'messaging_product' => 'whatsapp',
            'to' => $recipient,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $language],
                ...($components === [] ? [] : ['components' => $components]),
            ],
        ]);

        $messageId = $response->json('messages.0.id');
        if (! $response->successful() || ! is_string($messageId) || $messageId === '') {
            throw new RuntimeException('WhatsApp no confirmó la recepción del recordatorio.');
        }

        return $messageId;
    }
}
