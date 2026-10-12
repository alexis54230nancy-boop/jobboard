<?php
// La description des 5 tables, lue par index.php et par verifier() (outils.php).
// Ajouter une table à l'API = ajouter sa fiche ici : aucune route à écrire.

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
