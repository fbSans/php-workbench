<?php

namespace App\Controllers;

use Base\Response;
use Base\View;

use function Helpers\view;

class HomeController
{
    public function index(): Response
    {
        return view('home.html.twig', [
            'title' => 'Document Management System',
            'message' => 'Welcome to the DMS',
        ]);
    }
}