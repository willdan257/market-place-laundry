<?php

namespace App\Middlewares;

use App\Helpers\Session;
use App\Helpers\Response;

class AuthMiddleware
{
    public function handle(): bool
    {
        if (!Session::has('user')) {
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            $path = parse_url($uri, PHP_URL_PATH) ?? '';

            if (str_starts_with($path, '/admin')) {
                Session::setFlash('error', 'Silakan login sebagai administrator terlebih dahulu.');
                Response::redirect('/admin/login');
                return false;
            }

            Session::setFlash('error', 'Silakan login terlebih dahulu untuk mengakses halaman tersebut.');
            Response::redirect('/login');
            return false;
        }
        return true;
    }
}
