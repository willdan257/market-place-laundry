<?php

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Session;

abstract class BaseController
{
    protected function render(string $view, array $data = [], string $layout = 'main'): void
    {
        extract($data);
        
        $flashSuccess = $data['flashSuccess'] ?? Session::getFlash('success');
        $flashError = $data['flashError'] ?? Session::getFlash('error');
        $flashWarning = $data['flashWarning'] ?? Session::getFlash('warning');

        $viewPath = __DIR__ . "/../Views/pages/{$view}.php";
        
        if (!file_exists($viewPath)) {
            http_response_code(404);
            echo "View file [{$view}] not found.";
            return;
        }

        ob_start();
        require $viewPath;
        $content = ob_get_clean();

        $layoutPath = __DIR__ . "/../Views/layouts/{$layout}.php";
        if (file_exists($layoutPath)) {
            require $layoutPath;
        } else {
            echo $content;
        }
    }

    protected function json(mixed $data, int $statusCode = 200): void
    {
        Response::json($data, $statusCode);
    }

    protected function redirect(string $url): void
    {
        Response::redirect($url);
    }
}
