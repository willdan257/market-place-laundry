<?php

namespace App\Services\AI;

class NousProvider extends AbstractAIProvider
{
    public const DEFAULT_MODEL = 'anthropic/claude-haiku-5.5';

    public function generate(string $systemPrompt, array $messages, int $maxTokens): string
    {
        $model = $this->model !== '' ? $this->model : self::DEFAULT_MODEL;
        $url = 'https://inference-api.nousresearch.com/v1/chat/completions';

        $apiMessages = [
            ['role' => 'system', 'content' => $systemPrompt]
        ];

        foreach ($messages as $m) {
            $apiMessages[] = [
                'role' => $m['role'] === 'assistant' ? 'assistant' : 'user',
                'content' => $m['content']
            ];
        }

        $data = $this->postJson(
            $url,
            [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json'
            ],
            [
                'model' => $model,
                'messages' => $apiMessages,
                'temperature' => 0.2,
                'max_tokens' => $maxTokens
            ]
        );

        $reply = $data['choices'][0]['message']['content'] ?? '';
        $text = trim((string) $reply);

        if ($text === '') {
            throw $this->emptyResponse();
        }

        return $text;
    }
}
