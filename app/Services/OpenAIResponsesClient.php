<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

final class OpenAIResponsesClient
{
    /**
     * @return array{json: array<string,mixed>, raw: string, model: string}
     */
    public function createJsonSchema(string $instructions, string $input, array $schema, string $schemaName): array
    {
        $apiKey = (string) config('openai.api_key', '');
        $baseUrl = rtrim((string) config('openai.base_url', 'https://api.openai.com'), '/');
        $model = (string) config('openai.model', 'gpt-4o-mini');

        if ($apiKey === '') {
            throw new \RuntimeException('OPENAI_API_KEY is not set.');
        }

        $payload = [
            'model' => $model,
            'input' => [
                ['role' => 'system', 'content' => $instructions],
                ['role' => 'user', 'content' => $input],
            ],
            'max_output_tokens' => (int) config('openai.max_output_tokens', 1200),

            // NOTE: jangan kirim temperature untuk reasoning models (contoh GPT-5)
            // 'temperature' => 0.6,

            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => $schemaName,
                    'strict' => true,
                    'schema' => $schema,
                ],
            ],
        ];

        // GPT-5 = reasoning model → pakai reasoning.effort (tanpa temperature)
        if (str_starts_with($model, 'gpt-5')) {
            $payload['reasoning'] = [
                'effort' => (string) config('openai.reasoning_effort', 'medium'),
            ];
        }

        $res = Http::withToken($apiKey)
            ->acceptJson()
            ->timeout((int) config('openai.timeout_seconds', 30))
            ->post($baseUrl . '/v1/responses', $payload);

        if (!$res->ok()) {
            throw new \RuntimeException("OpenAI request failed: HTTP {$res->status()} — {$res->body()}");
        }

        $data = $res->json();

        $raw = $this->extractOutputText($data);

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Failed to parse model JSON output.');
        }

        return [
            'json' => $decoded,
            'raw' => $raw,
            'model' => $model,
        ];
    }

    /**
     * @param mixed $data
     */
    private function extractOutputText($data): string
    {
        if (!is_array($data)) {
            return '';
        }

        if (isset($data['output_text']) && is_string($data['output_text'])) {
            return $data['output_text'];
        }

        $parts = [];
        $output = $data['output'] ?? null;
        if (!is_array($output)) {
            return '';
        }

        foreach ($output as $item) {
            if (!is_array($item)) continue;
            if (($item['type'] ?? null) !== 'message') continue;

            $content = $item['content'] ?? null;
            if (!is_array($content)) continue;

            foreach ($content as $c) {
                if (!is_array($c)) continue;
                if (($c['type'] ?? null) !== 'output_text') continue;

                $text = $c['text'] ?? null;
                if (is_string($text) && $text !== '') {
                    $parts[] = $text;
                }
            }
        }

        return implode('', $parts);
    }
}