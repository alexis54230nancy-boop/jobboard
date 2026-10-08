<?php
session_start();
// Si l'utilisateur n'est pas admin, la fonction répond et arrête tout ;
// sinon, elle ne fait rien et la route continue normalement.
function exigerAdmin()
{
    // Personne n'est connecté → 401
    if (!isset($_SESSION['utilisateur'])) {
        http_response_code(401);
        echo json_encode(['erreur' => 'Connexion requise']);
        exit;
    }

    // Connecté, mais pas admin → 403
    if ($_SESSION['utilisateur']['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['erreur' => 'Accès réservé aux administrateurs']);
        exit;
    }
}
