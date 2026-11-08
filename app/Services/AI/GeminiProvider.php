<?php

namespace App\Services\AI;

class GeminiProvider extends AbstractAIProvider
{
    public const DEFAULT_MODEL = 'gemini-3.5-flash-lite';

    public function generate(string $systemPrompt, array $messages, int $maxTokens): string
    {
        $model = preg_replace('/[^a-zA-Z0-9._-]/', '', $this->model !== '' ? $this->model : self::DEFAULT_MODEL);
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

        $contents = [];
        foreach ($messages as $m) {
            $contents[] = [
                'role'  => $m['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $m['content']]],
            ];
        }

        $data = $this->postJson($url, ['x-goog-api-key: ' . $this->apiKey], [
            'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
            'contents'          => $contents,
            'generationConfig'  => [
                'temperature'     => 0.2,
                'maxOutputTokens' => $maxTokens,
            ],
        ]);

        $parts = $data['candidates'][0]['content']['parts'] ?? [];
        $text = '';
        foreach ($parts as $part) {
            if (isset($part['text']) && is_string($part['text'])) {
                $text .= $part['text'];
            }
        }

        $text = trim($text);
        if ($text === '') {
            throw $this->emptyResponse();
        }

        return $text;
    }
}
