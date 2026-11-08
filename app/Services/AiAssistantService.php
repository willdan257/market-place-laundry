<?php

namespace App\Services;

use App\Helpers\Env;

class AiAssistantService
{
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = Env::get('AI_API_KEY', '');
    }

    public function ask(string $prompt): string
    {
        if (empty($this->apiKey)) {
            return "AI Assistant API Key belum dikonfigurasi pada environment.";
        }
        return "Fitur AI Assistant siap diintegrasikan.";
    }
}
