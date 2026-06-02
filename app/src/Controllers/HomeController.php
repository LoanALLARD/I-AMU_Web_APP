<?php
namespace Controllers;

class HomeController
{
    public function index(): void
    {
        require dirname(__DIR__) . '/Views/pages/Auth/login.php';
    }
}