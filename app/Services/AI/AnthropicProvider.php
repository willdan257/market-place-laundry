<?php

namespace App\Services\AI;

class AnthropicProvider extends AbstractAIProvider
{
    public const DEFAULT_MODEL = 'claude-haiku-5-5';

    public function generate(string $systemPrompt, array $messages, int $maxTokens): string
    {
        $data = $this->postJson(
            'https://api.anthropic.com/v1/messages',
            [
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01',
            ],
            [
                'model'       => $this->model !== '' ? $this->model : self::DEFAULT_MODEL,
                'max_tokens'  => $maxTokens,
                'temperature' => 0.2,
                'system'      => $systemPrompt,
                'messages'    => array_map(
                    fn($m) => ['role' => $m['role'], 'content' => $m['content']],
                    $messages
                ),
            ]
        );

        $text = '';
        foreach (($data['content'] ?? []) as $block) {
            if (($block['type'] ?? '') === 'text' && isset($block['text'])) {
                $text .= $block['text'];
            }
        }

        $text = trim($text);
        if ($text === '') {
            throw $this->emptyResponse();
        }

        return $text;
    }
}
