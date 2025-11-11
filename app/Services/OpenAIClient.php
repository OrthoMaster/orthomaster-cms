<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class OpenAIClient
{
    public function __construct(
        private string $apiKey = '',
        private string $baseUrl = '',
        private string $model = ''
    ) {
        $this->apiKey  = $this->apiKey  ?: config('services.openai.key');
        $this->baseUrl = $this->baseUrl ?: config('services.openai.base_url');
        $this->model   = $this->model   ?: config('services.openai.model');
    }

    public function chat(array $messages, array $options = []): string
    {
        // messages: [['role'=>'system'|'user'|'assistant','content'=>'...'], ...]
        $payload = array_merge([
            'model'    => $this->model,
            'input'    => $messages,
        ], $options);

        $resp = Http::withToken($this->apiKey)
            ->baseUrl($this->baseUrl)
            ->asJson()
            ->post('/responses', $payload)
            ->throw();

        // Responses API returns 'output' (array); get first text
        $data = $resp->json();
        $text = $data['output'][0]['content'][0]['text'] ?? $data['output_text'] ?? '';

        return (string) $text;
    }
}
