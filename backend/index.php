<?php
/*
 * API DU JOB BOARD — Point d'entrée unique
 *
 * Lancement, depuis backend/ :  php -S localhost:8000 index.php
 *
 * index.php   le trajet d'une requête (ce fichier)
 * tables.php  la description des 5 tables
 * outils.php  les fonctions : repondre(), erreur(), verifier()…
 * front.php   l'envoi des pages de frontend/
 */

// Réglages des erreurs : une erreur imprévue → 500 en JSON, le détail reste dans le terminal
ini_set('display_errors', '0');
set_exception_handler(function ($e) {
    error_log($e->getMessage());
    erreur(500, 'Erreur interne du serveur');
});

require __DIR__ . '/outils.php';

// 1. Lecture de la requête
$methode = $_SERVER['REQUEST_METHOD'];                         // GET, POST, PUT ou DELETE
$chemin = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);    // « /offres?page=2 » → « /offres »
if (!is_string($chemin)) {
    erreur(400, 'URL invalide');
}

// 2. Gestion du Frontend : si l'adresse désigne un fichier de frontend/, il est envoyé et on s'arrête là
require __DIR__ . '/front.php';

// 3. Initialisation de l'API (JSON)
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/auth.php';       // exigerAdmin() : 401 si non connecté, 403 si pas admin
require __DIR__ . '/database.php';   // connecter() : la connexion à la base
$db = connecter();
require __DIR__ . '/tables.php';     // $ressources et $liens : la fiche de chaque table


// 4. Route spécifique : Postuler à une offre (POST /offres/{id}/candidatures) — public
if ($methode === 'POST' && preg_match('#^/offres/(\d+)/candidatures$#', $chemin, $m)) {
    // « /offres/3/candidatures » → $m[1] vaut '3'. L'offre doit exister → sinon 404
    $offre_id = (int) $m[1];
    if (!lire($db, 'SELECT id FROM offres', 'offres', $offre_id)) {
        erreur(404, 'Offre non trouvée');
    }

    // Ces 3 valeurs sont décidées par le serveur, pas par le visiteur
    $donnees = lireJson();
    $donnees['offre_id'] = $offre_id;
    $donnees['personne_id'] = null;
    $donnees['email_envoye'] = 0;

    // Reprise des infos si le candidat est connecté : il n'envoie alors que son message
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


// 5. Routes CRUD génériques pour toutes les tables
$tables = implode('|', array_keys($ressources));   // « offres|entreprises|categories|… »

if (preg_match('#^/(' . $tables . ')(?:/(\d+))?$#', $chemin, $m)) {
    // « /offres/3 » → $table = 'offres', $id = 3      « /offres » → $table = 'offres', $id = null
    $table = $m[1];
    $id = isset($m[2]) ? (int) $m[2] : null;
    $desc = $ressources[$table];                           // la fiche de cette table (tables.php)
    $select = $desc['select'] ?? "SELECT * FROM $table";

    // Gestion des droits d'accès : lire une table publique est ouvert à tous, tout le reste est réservé à l'admin
    if ($methode !== 'GET' || ($desc['lecture'] ?? '') === 'admin') {
        exigerAdmin();
    }

    // Vérification de l'existence de la ressource si un ID est fourni
    if ($id !== null && !lire($db, $select, $table, $id)) {
        erreur(404, 'Élément non trouvé');
    }

    // GET /{table} — Liste paginée avec filtres
    if ($methode === 'GET' && $id === null) {
        $conditions = [];
        $parametres = [];

        // « /offres?entreprise_id=3 » → WHERE offres.entreprise_id = 3
        foreach ($desc['filtres'] ?? [] as $filtre) {
            if (isset($_GET[$filtre]) && $_GET[$filtre] !== '') {
                $conditions[] = "$table.$filtre = :$filtre";
                $parametres[$filtre] = (int) $_GET[$filtre];
            }
        }
        $where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';

        // Page 3 avec 10 éléments par page → on saute les 20 premiers
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $par_page = min(100, max(1, (int) ($_GET['par_page'] ?? 10)));
        $offset = ($page - 1) * $par_page;

        $requete = $db->prepare("$select$where ORDER BY {$desc['tri']} LIMIT :limite OFFSET :offset");
        foreach ($parametres as $filtre => $valeur) {
            $requete->bindValue(":$filtre", $valeur, PDO::PARAM_INT);
        }
        $requete->bindValue(':limite', $par_page, PDO::PARAM_INT);
        $requete->bindValue(':offset', $offset, PDO::PARAM_INT);
        $requete->execute();

        // Le total permet au front de calculer le nombre de pages
        $total = $db->prepare("SELECT COUNT(*) FROM $table$where");
        $total->execute($parametres);

        // La réponse : { "offres": [ … ], "page": 1, "total": 26 }
        repondre(200, [
            $table => $requete->fetchAll(),
            'page' => $page,
            'total' => (int) $total->fetchColumn()
        ]);
    }

    // GET /{table}/{id} — Détail
    if ($methode === 'GET') {
        repondre(200, lire($db, $select, $table, $id));
    }

    // POST /{table} — Créer
    if ($methode === 'POST' && $id === null) {
        $valeurs = verifier($db, $desc, lireJson(), $liens);
        if ($table === 'personnes') {
            $valeurs = reglesPersonne($db, $valeurs, null);
        }
        $id = inserer($db, $table, $valeurs);
        repondre(201, lire($db, $select, $table, $id));   // 201 = créé ; on renvoie la ligne enregistrée
    }

    // PUT /{table}/{id} — Modifier
    if ($methode === 'PUT' && $id !== null) {
        $valeurs = verifier($db, $desc, lireJson(), $liens);
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

    // DELETE /{table}/{id} — Supprimer
    if ($methode === 'DELETE' && $id !== null) {
        // Un admin ne supprime pas son propre compte : il ne pourrait plus se connecter
        if ($table === 'personnes' && $id === (int) ($_SESSION['utilisateur']['id'] ?? 0)) {
            erreur(409, 'Vous ne pouvez pas supprimer votre propre compte');
        }

        // Les suppressions en cascade sont décidées dans schema.sql (ON DELETE …)
        try {
            $requete = $db->prepare("DELETE FROM $table WHERE id = :id");
            $requete->execute(['id' => $id]);
        } catch (PDOException $e) {
            // 23000 : la base refuse, car d'autres lignes en dépendent → 409 (conflit)
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            erreur(409, 'Suppression impossible : cet élément est encore utilisé');
        }

        repondre(204);   // 204 = fait, rien à renvoyer
    }
}


// 6. Aucune route ne correspond
erreur(404, 'Route introuvable');
