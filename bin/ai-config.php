<?php
// CLI script to safely export AI settings to internal consumers (Node.js chatbot server)
// Strictly non-destructive and local execution only.

require_once __DIR__ . '/../app/Helpers/Env.php';
require_once __DIR__ . '/../app/Models/BaseModel.php';
require_once __DIR__ . '/../app/Models/Setting.php';

use App\Models\Setting;
use App\Helpers\Env;

$dbAiEnabled = Setting::get('ai_enabled');
$dbAiProvider = Setting::get('ai_provider');
$dbAiModel = Setting::get('ai_model');
$dbAiApiKey = Setting::get('ai_api_key', true);

$provider = !empty($dbAiProvider) ? strtolower($dbAiProvider) : strtolower((string) Env::get('AI_PROVIDER', 'gemini'));
$apiKey = !empty($dbAiApiKey) ? trim($dbAiApiKey) : trim((string) Env::get('AI_API_KEY', ''));
$model = !empty($dbAiModel) ? trim($dbAiModel) : trim((string) Env::get('AI_MODEL', ''));
$enabled = ($dbAiEnabled !== null) ? ($dbAiEnabled === '1') : (!empty($apiKey));

echo json_encode([
    'success'  => true,
    'enabled'  => $enabled,
    'provider' => $provider,
    'model'    => $model,
    'api_key'  => $apiKey,
], JSON_UNESCAPED_SLASHES);

