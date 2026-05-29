<?php
namespace Controllers;

class AccueilController{
    public function index(){
        require dirname(__DIR__) . '/Views/Page/accueilView.php';
    }
}