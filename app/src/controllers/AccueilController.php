<?php
namespace Controller;

class AccueilController{
    public function index(){
        require dirname(__DIR__). '/views/accueilView.php';
    }
}