<?php

namespace App\Controllers;

use Base\View;

class HomeController
{
    public function index(): string
    {
        return View::render('home.html.twig', [
            'title' => 'Document Management System',
            'message' => 'Welcome to the DMS',
        ]);
    }
}