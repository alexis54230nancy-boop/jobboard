<?php
// L'envoi du front, appelé par index.php.
// « / », « /admin.html », « /style.css »… : les fichiers de frontend/ sont envoyés tels quels.
// Le front et l'API ont ainsi la même adresse : fetch('/offres') et le cookie de session marchent.
// Si l'adresse ne désigne aucun fichier du front, ce code ne fait rien : index.php continue vers l'API.

$types = [
    'html' => 'text/html; charset=utf-8',
    'css'  => 'text/css; charset=utf-8',
    'js'   => 'application/javascript; charset=utf-8',
    'png'  => 'image/png',
    'jpg'  => 'image/jpeg',
    'svg'  => 'image/svg+xml',
    'ico'  => 'image/x-icon',
];
$dossier = realpath(__DIR__ . '/../frontend');
$fichier = realpath($dossier . ($chemin === '/' ? '/index.html' : rawurldecode($chemin)));
$extension = strtolower(pathinfo((string) $fichier, PATHINFO_EXTENSION));

// Sécurité : le fichier doit exister, se trouver DANS frontend/ et avoir un type autorisé
if (
    $methode === 'GET' && $dossier && $fichier && is_file($fichier)
    && strpos($fichier, $dossier . DIRECTORY_SEPARATOR) === 0 && isset($types[$extension])
) {
    header('Content-Type: ' . $types[$extension]);
    readfile($fichier);
    exit;
}
