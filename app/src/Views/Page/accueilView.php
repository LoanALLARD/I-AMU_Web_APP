<?php
$titrePage = "Accueil";
// 1. On allume l'enregistreur (Output Buffer)
ob_start(); 
?>

<h1>Bienvenue sur la page d'accueil !</h1>
<h2>Test script serveur 2</h2>

<?php 
// 2. On arrête l'enregistreur et on vide la cassette dans la variable $content
$content = ob_get_clean(); 

// 3. On appelle le Layout qui va utiliser cette variable $content
require __DIR__ . '/../Layout/main.php';
?>
