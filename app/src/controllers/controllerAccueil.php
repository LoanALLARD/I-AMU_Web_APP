<?php
namespace Controller;

class controllerAccueil{
    public function index(){
        require dirname(__DIR__). '/views/accueilView.php';
    }
}