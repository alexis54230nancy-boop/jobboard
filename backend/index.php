<?php
// Routeur de l'API : toutes les requêtes arrivent ici
// (le serveur est lancé avec « php -S localhost:8000 index.php »).
require __DIR__ . '/auth.php';
require __DIR__ . '/database.php';
$db = connecter();
header('Content-Type: application/json; charset=utf-8');

$methode = $_SERVER['REQUEST_METHOD'];

// Le chemin seul, sans les paramètres de requête :
// « /offres?page=2 » devient « /offres », pour pouvoir le comparer aux routes.
$chemin = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($chemin === false) {
    http_response_code(400);
    echo json_encode(['erreur' => 'URL invalide']);
    exit;
}

// Le numéro de page pour la pagination.
// « ?? 1 » donne 1 par défaut quand « ?page= » est absent de l'adresse,
// ce qui évite le warning « Undefined array key ».
$page = (int) ($_GET['page'] ?? 1);
if ($page < 1) {
    $page = 1;
}
$par_page = 10;
$offset = ($page - 1) * $par_page;


if ($methode === 'GET' && $chemin === '/offres') {
    $requete = $db->prepare('SELECT offres.*, entreprises.nom AS entreprise
    FROM offres
    JOIN entreprises ON offres.entreprise_id = entreprises.id
    LIMIT :limite OFFSET :offset');

    $requete->bindValue(':limite', $par_page, PDO::PARAM_INT);
    $requete->bindValue(':offset', $offset, PDO::PARAM_INT);
    $requete->execute();
    $total = (int)$db->query('SELECT COUNT(*) FROM offres')->fetchColumn();

    echo json_encode(['offres' => $requete->fetchAll(), 'page' => $page, 'total' => $total]);
} elseif ($methode === 'GET' && $chemin === '/entreprises') {
    $requete = $db->prepare('SELECT * FROM entreprises LIMIT :limite OFFSET :offset');

    $requete->bindValue(':limite', $par_page, PDO::PARAM_INT);
    $requete->bindValue(':offset', $offset, PDO::PARAM_INT);
    $requete->execute();
    $total = (int)$db->query('SELECT COUNT(*) FROM entreprises')->fetchColumn();

    echo json_encode(['entreprises' => $requete->fetchAll(), 'page' => $page, 'total' => $total]);
} else {
     http_response_code(404);
     echo json_encode(['erreur' => 'Route introuvable']);
}