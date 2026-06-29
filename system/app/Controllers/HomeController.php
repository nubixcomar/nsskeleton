<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\Controller;
use Core\Env;
use Core\Response;

final class HomeController extends Controller
{
    public function index(): Response
    {
        return $this->view('home', [
            'appName' => \App\Services\AppSettings::name(),
            'env'     => Env::get('APP_ENV', 'local'),
        ]);
    }
}
