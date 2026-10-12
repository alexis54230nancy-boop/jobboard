<?php
/*
 * ══════════════════════════════════════════════════════════
 *  API DU JOB BOARD — toutes les requêtes arrivent ici
 *
 *  Lancement, depuis backend/ :  php -S localhost:8000 index.php
 *  Le front :  http://localhost:8000          L'API :  http://localhost:8000/offres
 *
 *  L'idée : les 5 tables se gèrent de la même façon. On les DÉCRIT une seule fois
 *  (section 4), et un seul code fournit les 5 routes de chacune (section 6) :
 *
 *    GET    /{table}         liste paginée   ?page=2  ?par_page=20  + filtres
 *    GET    /{table}/{id}    détail
 *    POST   /{table}         créer           admin
 *    PUT    /{table}/{id}    modifier        admin
 *    DELETE /{table}/{id}    supprimer       admin
 *
 *    {table} :  offres, entreprises, categories   → lecture ouverte à tous
 *               personnes, candidatures           → lecture réservée à l'admin
 *    Filtres :  /offres?entreprise_id=3   /offres?categorie_id=2   /candidatures?offre_id=1
 *
 *  Une route à part (section 5) :
 *    POST   /offres/{id}/candidatures   postuler à une offre   public
 *
 *  Codes :  200 OK · 201 créé · 204 supprimé · 400 données invalides
 *           401 non connecté · 403 pas admin · 404 introuvable · 409 conflit
 *  Erreurs :  toujours au format { "erreur": "…" }
 * ══════════════════════════════════════════════════════════
 */


// ─── 1. Les outils ────────────────────────────────────────

// Les avertissements PHP vont dans le terminal du serveur, pas au milieu du JSON
ini_set('display_errors', '0');

// Envoie la réponse en JSON avec son code HTTP, puis arrête le script
function repondre($code, $donnees = null)
{
    http_response_code($code);
    if ($donnees !== null) {
        echo json_encode($donnees, JSON_UNESCAPED_UNICODE);   // garde les accents lisibles
    }
    exit;
}

// Envoie une erreur, toujours au même format : { "erreur": "…" }
function erreur($code, $message)
{
    repondre($code, ['erreur' => $message]);
}

// Une erreur imprévue (bug, base inaccessible…) → 500 en JSON, le détail reste dans le terminal
set_exception_handler(function ($exception) {
    error_log($exception->getMessage());
    erreur(500, 'Erreur interne du serveur');
});

// Lit le JSON envoyé dans le corps de la requête (POST et PUT)
function lireJson()
{
    $donnees = json_decode(file_get_contents('php://input'), true);
    if (!is_array($donnees)) {
        erreur(400, 'Le corps de la requête doit être du JSON');
    }
    return $donnees;
}

// Renvoie la ligne n°$id d'une table, ou false si elle n'existe pas.
// $select et $table sont toujours écrits par nous, jamais par l'utilisateur.
function lire($db, $select, $table, $id)
{
    $requete = $db->prepare("$select WHERE $table.id = :id");
    $requete->bindValue(':id', $id, PDO::PARAM_INT);
    $requete->execute();
    return $requete->fetch();
}

// Insère une ligne et renvoie son id. implode() assemble les noms des colonnes :
// ['nom', 'ville'] → INSERT INTO entreprises (nom, ville) VALUES (:nom, :ville)
function inserer($db, $table, $valeurs)
{
    $colonnes = array_keys($valeurs);
    $requete = $db->prepare(
        "INSERT INTO $table (" . implode(', ', $colonnes) . ') VALUES (:' . implode(', :', $colonnes) . ')'
    );
    $requete->execute($valeurs);
    return (int) $db->lastInsertId();
}

// Vérifie les données d'un formulaire d'après la description de la table (section 4).
// Un problème → 400, et le script s'arrête. Sinon : les valeurs prêtes à être enregistrées.
function verifier($db, $description, $donnees, $liens)
{
    $valeurs = [];

    foreach (array_merge($description['obligatoires'], $description['facultatifs']) as $champ) {
        $valeur = $donnees[$champ] ?? null;

        // On retire les espaces autour des textes (sauf d'un mot de passe) ; un texte vide = rien
        if (is_string($valeur) && $champ !== 'mot_de_passe') {
            $valeur = trim($valeur);
        }
        if ($valeur === '') {
            $valeur = null;
        }

        if ($champ === 'email_envoye') {
            $valeur = empty($valeur) ? 0 : 1;   // 1 = oui, 0 = non
        } elseif ($valeur === null) {
            if (in_array($champ, $description['obligatoires'])) {
                erreur(400, "Le champ $champ est requis");
            }
        } elseif (is_array($valeur) || is_bool($valeur)) {
            erreur(400, "Le champ $champ a une valeur invalide");
        } elseif (isset($liens[$champ]) || strpos($champ, 'salaire_') === 0) {
            // Un « _id » ou un salaire : un nombre entier positif…
            if (filter_var($valeur, FILTER_VALIDATE_INT) === false || $valeur < 0) {
                erreur(400, "Le champ $champ doit être un nombre entier positif");
            }
            // … et, pour un « _id », une ligne qui existe dans la table pointée
            if (isset($liens[$champ]) && !lire($db, 'SELECT id FROM ' . $liens[$champ], $liens[$champ], $valeur)) {
                erreur(400, "$champ ne correspond à aucun élément existant");
            }
        } elseif ($champ === 'email' && filter_var($valeur, FILTER_VALIDATE_EMAIL) === false) {
            erreur(400, "L'adresse e-mail n'est pas valide");
        }

        $valeurs[$champ] = $valeur;
    }

    return $valeurs;
}

// Les règles propres aux personnes. $id vaut null pour une création.
function reglesPersonne($db, $valeurs, $id)
{
    // Le rôle : « candidat » par défaut, et seulement une des 3 valeurs du schéma
    $valeurs['role'] = $valeurs['role'] ?? 'candidat';
    if (!in_array($valeurs['role'], ['admin', 'recruteur', 'candidat'], true)) {
        erreur(400, 'role doit valoir admin, recruteur ou candidat');
    }

    // L'e-mail est unique : déjà pris par quelqu'un d'AUTRE → 409 (conflit)
    $requete = $db->prepare('SELECT id FROM personnes WHERE email = :email AND id != :id');
    $requete->execute(['email' => $valeurs['email'], 'id' => $id ?? 0]);
    if ($requete->fetch()) {
        erreur(409, 'Cet e-mail est déjà utilisé');
    }

    // Le mot de passe n'est jamais stocké en clair : on enregistre son empreinte (hash).
    // Obligatoire à la création ; en modification, s'il est vide, on garde l'ancien.
    if ($valeurs['mot_de_passe'] === null) {
        if ($id === null) {
            erreur(400, 'Le champ mot_de_passe est requis');
        }
        unset($valeurs['mot_de_passe']);
    } else {
        $valeurs['mot_de_passe'] = password_hash((string) $valeurs['mot_de_passe'], PASSWORD_DEFAULT);
    }

    return $valeurs;
}


// ─── 2. Lecture de la requête ─────────────────────────────

// La méthode : GET, POST, PUT ou DELETE
$methode = $_SERVER['REQUEST_METHOD'];

// Le chemin seul, sans les paramètres : « /offres?page=2 » → « /offres »
$chemin = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (!is_string($chemin)) {
    erreur(400, 'URL invalide');
}


// ─── 3. Le front ──────────────────────────────────────────
// « / », « /admin.html », « /style.css »… : les fichiers de frontend/ sont envoyés tels quels.
// Le front et l'API ont ainsi la même adresse : fetch('/offres') et le cookie de session marchent.

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


// ─── 4. La description des tables ─────────────────────────

// À partir d'ici, toutes les réponses sont en JSON
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/auth.php';       // exigerAdmin() : 401 si non connecté, 403 si pas admin
require __DIR__ . '/database.php';   // connecter() : connexion préréglée à la base
$db = connecter();

// Pour chaque table :
//   obligatoires / facultatifs : les champs qu'un formulaire peut remplir
//   lecture : 'public' (tout le monde peut lire) ou 'admin'
//   tri     : l'ordre des listes
//   select  : la requête de lecture, si elle n'est pas un simple « SELECT * » (facultatif)
//   filtres : les colonnes utilisables dans l'adresse, ex. /offres?entreprise_id=3 (facultatif)
$ressources = [
    'offres' => [
        'obligatoires' => ['titre', 'description_courte', 'categorie_id', 'entreprise_id'],
        'facultatifs'  => ['description', 'salaire_min', 'salaire_max', 'lieu', 'temps_de_travail', 'type_de_contrat', 'recruteur_id'],
        'lecture'      => 'public',
        'tri'          => 'offres.date_de_publication DESC, offres.id DESC',   // les plus récentes d'abord
        // Les JOIN ajoutent le nom de l'entreprise et celui de la catégorie
        'select'       => 'SELECT offres.*, entreprises.nom AS entreprise, categories.nom AS categorie
            FROM offres
            JOIN entreprises ON entreprises.id = offres.entreprise_id
            JOIN categories ON categories.id = offres.categorie_id',
        'filtres'      => ['entreprise_id', 'categorie_id'],
    ],
    'entreprises' => [
        'obligatoires' => ['nom'],
        'facultatifs'  => ['ville', 'description', 'site_web', 'logo_url'],
        'lecture'      => 'public',
        'tri'          => 'entreprises.nom COLLATE NOCASE, entreprises.id',   // alphabétique
    ],
    'categories' => [
        'obligatoires' => ['nom'],
        'facultatifs'  => [],
        'lecture'      => 'public',
        'tri'          => 'categories.nom COLLATE NOCASE, categories.id',
    ],
    'personnes' => [
        'obligatoires' => ['prenom', 'nom', 'email'],
        'facultatifs'  => ['telephone', 'mot_de_passe', 'role', 'entreprise_id'],
        'lecture'      => 'admin',   // données personnelles
        'tri'          => 'personnes.nom COLLATE NOCASE, personnes.prenom COLLATE NOCASE, personnes.id',
        // Toutes les colonnes SAUF mot_de_passe, qui n'est jamais renvoyé
        'select'       => 'SELECT id, prenom, nom, email, telephone, role, entreprise_id FROM personnes',
    ],
    'candidatures' => [
        'obligatoires' => ['offre_id', 'nom', 'prenom', 'email', 'message'],
        'facultatifs'  => ['personne_id', 'telephone', 'email_envoye'],
        'lecture'      => 'admin',
        'tri'          => 'candidatures.date_de_candidature DESC, candidatures.id DESC',
        // Le JOIN ajoute le titre de l'offre visée
        'select'       => 'SELECT candidatures.*, offres.titre AS offre
            FROM candidatures
            JOIN offres ON offres.id = candidatures.offre_id',
        'filtres'      => ['offre_id'],
    ],
];

// Vers quelle table pointe chaque colonne « _id » (pour vérifier que la ligne existe)
$liens = [
    'categorie_id'  => 'categories',
    'entreprise_id' => 'entreprises',
    'recruteur_id'  => 'personnes',
    'personne_id'   => 'personnes',
    'offre_id'      => 'offres',
];


// ─── 5. La route à part : postuler ────────────────────────
// Chaque route se termine par repondre() ou erreur(), qui arrêtent le script.
// Dans les modèles de chemin, (\d+) capture l'id : « /offres/3/… » → $morceaux[1] vaut '3'.

if ($methode === 'POST' && preg_match('#^/offres/(\d+)/candidatures$#', $chemin, $morceaux)) {
    // ▶ POST /offres/{id}/candidatures — postuler à une offre (public)

    // L'offre vient de l'adresse, et elle doit exister → sinon 404
    $offre_id = (int) $morceaux[1];
    if (!lire($db, 'SELECT id FROM offres', 'offres', $offre_id)) {
        erreur(404, 'Offre non trouvée');
    }

    $donnees = lireJson();
    $donnees['offre_id'] = $offre_id;
    $donnees['personne_id'] = null;   // reste vide si le candidat n'a pas de compte
    $donnees['email_envoye'] = 0;

    // Candidat connecté : ses coordonnées viennent de son compte,
    // il n'a pas à les ressaisir (seul le message est alors nécessaire).
    if (isset($_SESSION['utilisateur']['id'])) {
        $compte = lire($db, 'SELECT * FROM personnes', 'personnes', (int) $_SESSION['utilisateur']['id']);
        if ($compte) {
            $donnees['personne_id'] = $compte['id'];
            foreach (['nom', 'prenom', 'email', 'telephone'] as $champ) {
                if (empty($donnees[$champ])) {
                    $donnees[$champ] = $compte[$champ];
                }
            }
        }
    }

    $id = inserer($db, 'candidatures', verifier($db, $ressources['candidatures'], $donnees, $liens));
    repondre(201, lire($db, 'SELECT * FROM candidatures', 'candidatures', $id));
}


// ─── 6. Les 5 routes communes à toutes les tables ─────────

// Le modèle reconnaît « /offres », « /entreprises/3 »… : le nom d'une table, puis un id facultatif
$tables = implode('|', array_keys($ressources));   // « offres|entreprises|categories|… »

if (preg_match('#^/(' . $tables . ')(?:/(\d+))?$#', $chemin, $morceaux)) {
    $table = $morceaux[1];
    $id = isset($morceaux[2]) ? (int) $morceaux[2] : null;
    $description = $ressources[$table];
    $select = $description['select'] ?? "SELECT * FROM $table";

    // Accès : lire une table publique est ouvert à tous ; tout le reste est réservé à l'admin
    if ($methode !== 'GET' || $description['lecture'] === 'admin') {
        exigerAdmin();
    }

    // Une adresse avec un id désigne une ligne qui doit exister → sinon 404
    if ($id !== null && !lire($db, $select, $table, $id)) {
        erreur(404, 'Élément non trouvé');
    }

    if ($methode === 'GET' && $id === null) {
        // ▶ GET /{table} — liste paginée

        // Les filtres de l'adresse : « /offres?entreprise_id=3 » → WHERE offres.entreprise_id = 3
        $conditions = [];
        $parametres = [];
        foreach ($description['filtres'] ?? [] as $filtre) {
            if (isset($_GET[$filtre]) && $_GET[$filtre] !== '') {
                $conditions[] = "$table.$filtre = :$filtre";
                $parametres[$filtre] = (int) $_GET[$filtre];
            }
        }
        $where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';

        // La pagination : « ?page=2 » ; absente ou invalide → page 1 ; 10 éléments par page (100 au plus)
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $par_page = min(100, max(1, (int) ($_GET['par_page'] ?? 10)));

        $requete = $db->prepare("$select$where ORDER BY {$description['tri']} LIMIT :limite OFFSET :offset");
        foreach ($parametres as $filtre => $valeur) {
            $requete->bindValue(":$filtre", $valeur, PDO::PARAM_INT);
        }
        $requete->bindValue(':limite', $par_page, PDO::PARAM_INT);
        $requete->bindValue(':offset', ($page - 1) * $par_page, PDO::PARAM_INT);   // éléments à sauter
        $requete->execute();

        // Le total permet au front de calculer le nombre de pages
        $total = $db->prepare("SELECT COUNT(*) FROM $table$where");
        $total->execute($parametres);

        repondre(200, [$table => $requete->fetchAll(), 'page' => $page, 'total' => (int) $total->fetchColumn()]);
    }

    if ($methode === 'GET') {
        // ▶ GET /{table}/{id} — détail
        repondre(200, lire($db, $select, $table, $id));
    }

    if ($methode === 'POST' && $id === null) {
        // ▶ POST /{table} — créer
        $valeurs = verifier($db, $description, lireJson(), $liens);
        if ($table === 'personnes') {
            $valeurs = reglesPersonne($db, $valeurs, null);
        }

        $id = inserer($db, $table, $valeurs);
        repondre(201, lire($db, $select, $table, $id));   // 201 = créé ; on renvoie la ligne enregistrée
    }

    if ($methode === 'PUT' && $id !== null) {
        // ▶ PUT /{table}/{id} — modifier (tous les champs sont renvoyés)
        $valeurs = verifier($db, $description, lireJson(), $liens);
        if ($table === 'personnes') {
            $valeurs = reglesPersonne($db, $valeurs, $id);
        }

        // ['nom', 'ville'] → UPDATE entreprises SET nom = :nom, ville = :ville WHERE id = :id
        $colonnes = [];
        foreach (array_keys($valeurs) as $colonne) {
            $colonnes[] = "$colonne = :$colonne";
        }
        $requete = $db->prepare("UPDATE $table SET " . implode(', ', $colonnes) . ' WHERE id = :id');
        $requete->execute($valeurs + ['id' => $id]);

        repondre(200, lire($db, $select, $table, $id));
    }

    if ($methode === 'DELETE' && $id !== null) {
        // ▶ DELETE /{table}/{id} — supprimer

        // Un admin ne supprime pas son propre compte : il ne pourrait plus se connecter
        if ($table === 'personnes' && $id === (int) ($_SESSION['utilisateur']['id'] ?? 0)) {
            erreur(409, 'Vous ne pouvez pas supprimer votre propre compte');
        }

        // Les suppressions en cascade sont décidées dans schema.sql (ON DELETE …)
        try {
            $requete = $db->prepare("DELETE FROM $table WHERE id = :id");
            $requete->execute(['id' => $id]);
        } catch (PDOException $exception) {
            // 23000 : la base refuse, car d'autres lignes en dépendent
            // (ex. une catégorie encore utilisée par des offres) → 409 (conflit)
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }
            erreur(409, 'Suppression impossible : cet élément est encore utilisé');
        }

        repondre(204);   // 204 = fait, rien à renvoyer
    }
}


// ─── 7. Aucune route ne correspond ────────────────────────

erreur(404, 'Route introuvable');
