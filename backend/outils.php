<?php
// Les outils : les fonctions utilisées par les routes de index.php.

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

// Vérifie les données d'un formulaire d'après la description de la table (tables.php).
// Un problème → 400, et le script s'arrête. Sinon : les valeurs prêtes à être enregistrées.
function verifier($db, $description, $donnees, $liens)
{
    $valeurs = [];

    foreach (array_merge($description['obligatoires'], $description['facultatifs']) as $champ) {
        $valeur = $donnees[$champ] ?? null;   // champ absent du formulaire → null

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
            // Une liste ou true/false, là où on attend un texte ou un nombre
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

        $valeurs[$champ] = $valeur;   // le champ est bon : on le garde
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
    $requete->execute(['email' => $valeurs['email'], 'id' => $id ?? 0]);   // création : pas d'id, 0 n'écarte personne
    if ($requete->fetch()) {
        erreur(409, 'Cet e-mail est déjà utilisé');
    }

    // Le mot de passe n'est jamais stocké en clair : on enregistre son empreinte (hash).
    // Obligatoire à la création ; en modification, s'il est vide, on garde l'ancien.
    if ($valeurs['mot_de_passe'] === null) {
        if ($id === null) {
            erreur(400, 'Le champ mot_de_passe est requis');
        }
        unset($valeurs['mot_de_passe']);   // retiré des valeurs : l'UPDATE ne touchera pas à cette colonne
    } else {
        $valeurs['mot_de_passe'] = password_hash((string) $valeurs['mot_de_passe'], PASSWORD_DEFAULT);
    }

    return $valeurs;
}
