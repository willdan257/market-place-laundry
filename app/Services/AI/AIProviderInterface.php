<?php

namespace App\Services\AI;

interface AIProviderInterface
{
    /**
     * @param string $systemPrompt Instruksi sistem (aturan yang membatasi AI)
     * @param array  $messages     [['role' => 'user'|'assistant', 'content' => string], ...]
     *                             Pesan pertama harus 'user', dan role harus bergantian.
     * @return string Teks jawaban
     * @throws AIException
     */
    public function generate(string $systemPrompt, array $messages, int $maxTokens): string;
}
