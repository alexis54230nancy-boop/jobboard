<?php
/*
 * ══════════════════════════════════════════════════════════
 *  ROUTEUR DE L'API — toutes les requêtes arrivent ici
 *  Lancement, depuis backend/ :  php -S localhost:8000 index.php
 *
 *  Routes disponibles :
 *    GET  /offres          liste paginée des offres
 *    GET  /offres/{id}     détail d'une offre
 *    GET  /entreprises     liste paginée des entreprises
 * ══════════════════════════════════════════════════════════
 */


// ─── 1. Préparation ───────────────────────────────────────

require __DIR__ . '/auth.php';       // exigerAdmin() : protège les routes admin
require __DIR__ . '/database.php';   // connecter() : connexion préréglée à la base
$db = connecter();

// Toutes les réponses sont en JSON (accents compris)
header('Content-Type: application/json; charset=utf-8');


// ─── 2. Lecture de la requête ─────────────────────────────

// La méthode : GET, POST, PUT ou DELETE
$methode = $_SERVER['REQUEST_METHOD'];

// Le chemin seul, sans les paramètres : « /offres?page=2 » → « /offres »
$chemin = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($chemin === false) {
    http_response_code(400);
    echo json_encode(['erreur' => 'URL invalide']);
    exit;
}

// La pagination : « ?page=2 » → page 2 ; absente ou invalide → page 1
$page = (int) ($_GET['page'] ?? 1);
if ($page < 1) {
    $page = 1;
}
$par_page = 10;                       // éléments par page
$offset = ($page - 1) * $par_page;    // éléments à sauter avant la page demandée


// ─── 3. Aiguillage vers la bonne route ────────────────────
// PHP teste les routes dans l'ordre et s'arrête à la première qui correspond.

if ($methode === 'GET' && $chemin === '/offres') {
    // ▶ GET /offres — liste paginée des offres (job board)

    // Le JOIN ajoute le nom de l'entreprise à chaque offre.
    // Tri : les plus récentes d'abord ; à date égale, la dernière ajoutée.
    $requete = $db->prepare('SELECT offres.*, entreprises.nom AS entreprise
    FROM offres
    JOIN entreprises ON offres.entreprise_id = entreprises.id
    ORDER BY offres.date_de_publication DESC, offres.id DESC
    LIMIT :limite OFFSET :offset');

    $requete->bindValue(':limite', $par_page, PDO::PARAM_INT);
    $requete->bindValue(':offset', $offset, PDO::PARAM_INT);
    $requete->execute();

    // Le total permet au front de calculer le nombre de pages
    $total = (int)$db->query('SELECT COUNT(*) FROM offres')->fetchColumn();

    echo json_encode(['offres' => $requete->fetchAll(), 'page' => $page, 'total' => $total]);
} elseif ($methode === 'GET' && preg_match('#^/offres/(\d+)$#', $chemin, $morceaux)) {
    // ▶ GET /offres/{id} — détail d'une offre (« En savoir plus »)

    // capture l'id : « /offres/3 » → $morceaux[1] vaut '3'
    $id = (int) $morceaux[1];
    $requete = $db->prepare('SELECT offres.*, entreprises.nom AS entreprise
    FROM offres
    JOIN entreprises ON offres.entreprise_id = entreprises.id
    WHERE offres.id = :id');

    $requete->bindValue(':id', $id, PDO::PARAM_INT);
    $requete->execute();
    $offre = $requete->fetch();   // une seule ligne, ou false si l'id n'existe pas

    if ($offre) {
        echo json_encode($offre);
    } else {
        http_response_code(404);
        echo json_encode(['erreur' => 'Offre non trouvée']);
    }
} elseif ($methode === 'GET' && $chemin === '/entreprises') {
    // ▶ GET /entreprises — liste paginée des entreprises

    // Tri alphabétique, comme un annuaire
    $requete = $db->prepare('SELECT * FROM entreprises
    ORDER BY nom ASC
    LIMIT :limite OFFSET :offset');

    $requete->bindValue(':limite', $par_page, PDO::PARAM_INT);
    $requete->bindValue(':offset', $offset, PDO::PARAM_INT);
    $requete->execute();
    $total = (int)$db->query('SELECT COUNT(*) FROM entreprises')->fetchColumn();

    echo json_encode(['entreprises' => $requete->fetchAll(), 'page' => $page, 'total' => $total]);
} else {
    // ▶ Aucune route ne correspond
    http_response_code(404);
    echo json_encode(['erreur' => 'Route introuvable']);
}
