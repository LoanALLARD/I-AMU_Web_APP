<?php
namespace Controllers;

class AccueilController
{
    public function index(): void
    {
        require dirname(__DIR__) . '/Views/Page/accueilView.php';
    }
}