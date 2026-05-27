<?php
namespace Controller;

class controllerAccueil{
    public function index(){
        var_dump(dirname(__DIR__,2));
        require dirname(__DIR__,2). '/views/accueilView.php';
    }
}