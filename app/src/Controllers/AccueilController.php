<?php
namespace Controllers;

class AccueilController{
    public function index(): void {
        $this->render('accueil', ['titrePage' => 'Accueil']);
    }
}