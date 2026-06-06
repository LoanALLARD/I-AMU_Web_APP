<?php
namespace Controllers;

class HomeController
{
    public function index(): void
    {
        require dirname(__DIR__) . '/Views/Page/Auth/login.php';
    }
}