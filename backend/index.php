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


// Routes d'authentification : les comptes utilisent la table personnes existante.
if ($methode === 'POST' && $chemin === '/connexion') {
    $donnees = lireJson();
    $email = trim((string) ($donnees['email'] ?? ''));
    $motDePasse = (string) ($donnees['password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $motDePasse === '') {
        erreur(400, 'Adresse e-mail ou mot de passe manquant/invalide');
    }

    $requete = $db->prepare('SELECT id, prenom, nom, email, mot_de_passe, role, entreprise_id FROM personnes WHERE email = :email LIMIT 1');
    $requete->execute(['email' => $email]);
    $personne = $requete->fetch();

    if (!$personne || !password_verify($motDePasse, $personne['mot_de_passe'])) {
        erreur(401, 'Adresse e-mail ou mot de passe incorrect');
    }

    session_regenerate_id(true);
    unset($personne['mot_de_passe']);
    $_SESSION['utilisateur'] = $personne;
    repondre(200, ['message' => 'Connexion réussie', 'utilisateur' => $personne]);
}

// Renvoie le compte actuellement connecté (ou 401).
if ($methode === 'GET' && $chemin === '/session') {
    if (!isset($_SESSION['utilisateur'])) {
        erreur(401, 'Aucun utilisateur connecté');
    }
    repondre(200, ['utilisateur' => $_SESSION['utilisateur']]);
}

// Déconnexion et suppression de la session.
if ($methode === 'POST' && $chemin === '/deconnexion') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $parametres = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $parametres['path'], $parametres['domain'], $parametres['secure'], $parametres['httponly']);
    }
    session_destroy();
    repondre(200, ['message' => 'Déconnexion réussie']);
}

// Création de compte public : seul le rôle candidat peut être choisi depuis l'inscription.
if ($methode === 'POST' && $chemin === '/inscription') {
    $donnees = lireJson();
    $prenom = trim((string) ($donnees['prenom'] ?? ''));
    $nom = trim((string) ($donnees['nom'] ?? ''));
    $email = trim((string) ($donnees['email'] ?? ''));
    $motDePasse = (string) ($donnees['password'] ?? '');

    if ($prenom === '' || $nom === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($motDePasse) < 8) {
        erreur(400, 'Renseigne le prénom, le nom, un e-mail valide et un mot de passe de 8 caractères minimum');
    }
    $requete = $db->prepare('SELECT id FROM personnes WHERE email = :email');
    $requete->execute(['email' => $email]);
    if ($requete->fetch()) {
        erreur(409, 'Cet e-mail est déjà utilisé');
    }

    $requete = $db->prepare('INSERT INTO personnes (prenom, nom, email, mot_de_passe, role) VALUES (:prenom, :nom, :email, :mot_de_passe, :role)');
    $requete->execute([
        'prenom' => $prenom,
        'nom' => $nom,
        'email' => $email,
        'mot_de_passe' => password_hash($motDePasse, PASSWORD_DEFAULT),
        'role' => 'candidat'
    ]);
    $personne = [
        'id' => (int) $db->lastInsertId(),
        'prenom' => $prenom,
        'nom' => $nom,
        'email' => $email,
        'role' => 'candidat',
        'entreprise_id' => null
    ];
    session_regenerate_id(true);
    $_SESSION['utilisateur'] = $personne;
    repondre(201, ['message' => 'Compte créé avec succès', 'utilisateur' => $personne]);
}

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
    // Une exception : un recruteur connecté a le droit de créer une offre (POST /offres)
    $recruteur = $table === 'offres' && $methode === 'POST' && $id === null
        && ($_SESSION['utilisateur']['role'] ?? '') === 'recruteur';

    if (!$recruteur && ($methode !== 'GET' || ($desc['lecture'] ?? '') === 'admin')) {
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
        $donnees = lireJson();

        // Un recruteur publie pour SON entreprise : l'entreprise et le recruteur de l'offre
        // viennent de son compte, pas du formulaire. Il ne peut pas publier pour une autre.
        if ($recruteur) {
            $compte = lire($db, 'SELECT * FROM personnes', 'personnes', (int) $_SESSION['utilisateur']['id']);
            if (!$compte || $compte['role'] !== 'recruteur' || !$compte['entreprise_id']) {
                erreur(403, "Votre compte recruteur n'est rattaché à aucune entreprise");
            }
            $donnees['entreprise_id'] = $compte['entreprise_id'];
            $donnees['recruteur_id'] = $compte['id'];
        }

        $valeurs = verifier($db, $desc, $donnees, $liens);
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
