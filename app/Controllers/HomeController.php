<?php

namespace App\Controllers;

use App\Helpers\Session;

class HomeController extends BaseController
{
    public function index(): void
    {
        $user = Session::get('user');
        
        $this->render('home', [
            'title' => 'Marketplace Laundry - Solusi Laundry Praktis & Terpercaya',
            'user' => $user
        ]);
    }

    public function notFound(): void
    {
        http_response_code(404);
        $this->render('404', [
            'title' => '404 - Halaman Tidak Ditemukan'
        ]);
    }
}
