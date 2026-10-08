<?php
// appelle la fonction connecter() pour se connecter à la base de données
require __DIR__ . '/database.php';
$db = connecter();

// Supprime toutes les données des tables(on vide dans l'ordre inverse de la création, à cause des clés étrangères)
$db->exec('DELETE FROM candidatures');
$db->exec('DELETE FROM offres');
$db->exec('DELETE FROM personnes');
$db->exec('DELETE FROM entreprises');
$db->exec('DELETE FROM categories');
$db->exec('DELETE FROM sqlite_sequence');

// Insère des données dans la table categories
$requete = $db->prepare('INSERT INTO categories (nom) VALUES (:nom)');

$requete->execute([
    'nom' => 'Commerce'
]);
$commerce_id = $db->lastInsertId();

$requete->execute([
    'nom' => 'Informatique']);
$informatique_id = $db->lastInsertId();

$requete->execute([
    'nom' => 'Finance'
]);
$finance_id = $db->lastInsertId();

// Insère des données dans la table entreprises
$requete = $db->prepare('INSERT INTO entreprises (nom, ville, description, site_web, logo_url) VALUES (:nom, :ville, :description, :site_web, :logo_url)');

$requete->execute([
    ':nom' => 'Entreprise A',
    ':ville' => 'Paris',
    ':description' => 'Description de l\'entreprise A',
    ':site_web' => 'https://entreprise-a.com',
    ':logo_url' => 'https://entreprise-a.com/logo.png',
]);
$entreprise_a_id = $db->lastInsertId();

$requete->execute([
    ':nom' => 'Entreprise B',
    ':ville' => null,
    ':description' => 'Description de l\'entreprise B',
    ':site_web' => 'https://entreprise-b.com',
    ':logo_url' => 'https://entreprise-b.com/logo.png'
]);
$entreprise_b_id = $db->lastInsertId();

$requete->execute([
    ':nom' => 'Entreprise C',
    ':ville' => 'Marseille',
    ':description' => 'Description de l\'entreprise C',
    ':site_web' => 'https://entreprise-c.com',
    ':logo_url' => 'https://entreprise-c.com/logo.png'
]);
$entreprise_c_id = $db->lastInsertId();

// Comptes de test (e-mail / mot de passe) :
//   admin     : chams@example.com  / admin123
//   recruteur : mathis@example.com / recruteur123
//   candidat  : alexis@example.com / candidat123
//   candidat  : john@example.com   / candidat1234

// Insère des données dans la table personnes
$requete = $db->prepare('INSERT INTO personnes (nom, prenom, email, telephone, mot_de_passe, role, entreprise_id) VALUES (:nom, :prenom, :email, :telephone, :mot_de_passe, :role, :entreprise_id)');

$requete->execute([
    ':nom' => 'Epitech',
    ':prenom' => 'Chams',
    ':email' => 'chams@example.com',
    ':telephone' => '06 12 34 56 78',
    ':mot_de_passe' => password_hash('admin123', PASSWORD_DEFAULT),
    ':role' => 'admin',
    ':entreprise_id' => null
]);
$personne_admin_id = $db->lastInsertId();

$requete->execute([
    ':nom' => 'Epitech',
    ':prenom' => 'Mathis',
    ':email' => 'mathis@example.com',
    ':telephone' => '06 12 34 56 79',
    ':mot_de_passe' => password_hash('recruteur123', PASSWORD_DEFAULT),
    ':role' => 'recruteur',
    ':entreprise_id' => $entreprise_a_id
]);
$personne_recruteur_id = $db->lastInsertId();

$requete->execute([
    ':nom' => 'Epitech',
    ':prenom' => 'Alexis',
    ':email' => 'alexis@example.com',
    ':telephone' => '06 12 34 56 80',
    ':mot_de_passe' => password_hash('candidat123', PASSWORD_DEFAULT),
    ':role' => 'candidat',
    ':entreprise_id' => null
]);
$personne_alexis_id = $db->lastInsertId();

$requete->execute([
    ':nom' => 'Epitech',
    ':prenom' => 'John',
    ':email' => 'john@example.com',
    ':telephone' => '06 12 34 56 81',
    ':mot_de_passe' => password_hash('candidat1234', PASSWORD_DEFAULT),
    ':role' => 'candidat',
    ':entreprise_id' => null
]);
$personne_john_id = $db->lastInsertId();

// Insère des données dans la table offres
$requete = $db->prepare('INSERT INTO offres (titre, description, description_courte, salaire_min, salaire_max, temps_de_travail, type_de_contrat, lieu, recruteur_id, entreprise_id, categorie_id) VALUES (:titre, :description, :description_courte, :salaire_min, :salaire_max, :temps_de_travail, :type_de_contrat, :lieu, :recruteur_id, :entreprise_id, :categorie_id)');

$requete->execute([
    ':titre' => 'Développeur Web',
    ':description' => 'Nous recherchons un développeur web passionné pour rejoindre notre équipe.',
    ':description_courte' => 'Développeur Web',
    ':salaire_min' => 30000,
    ':salaire_max' => 50000,
    ':temps_de_travail' => 'Temps plein',
    ':type_de_contrat' => 'CDI',
    ':lieu' => 'Paris',
    ':recruteur_id' => $personne_recruteur_id,
    ':entreprise_id' => $entreprise_a_id,
    ':categorie_id' => $informatique_id
]);
$offre_dev_web_id = $db->lastInsertId();

$requete->execute([
    ':titre' => 'Commercial',
    ':description' => 'Nous recherchons un commercial expérimenté pour développer notre portefeuille clients.',
    ':description_courte' => 'Commercial',
    ':salaire_min' => 25000,
    ':salaire_max' => 40000,
    ':temps_de_travail' => 'Temps plein',
    ':type_de_contrat' => 'CDI',
    ':lieu' => 'Lyon',
    ':recruteur_id' => null,
    ':entreprise_id' => $entreprise_b_id,
    ':categorie_id' => $commerce_id
]);
$offre_commercial_id = $db->lastInsertId();

$requete->execute([
    ':titre' => 'Analyste Financier',
    ':description' => null,
    ':description_courte' => 'Analyste Financier',
    ':salaire_min' => 40000,
    ':salaire_max' => 60000,
    ':temps_de_travail' => null,
    ':type_de_contrat' => 'CDI',
    ':lieu' => null,
    ':recruteur_id' => null,
    ':entreprise_id' => $entreprise_c_id,
    ':categorie_id' => $finance_id
]);
$offre_analyste_financier_id = $db->lastInsertId();


$requete->execute([
    ':titre' => 'Développeur Mobile',
    ':description' => 'Nous recherchons un développeur mobile pour créer des applications innovantes.',
    ':description_courte' => 'Développeur Mobile',
    ':salaire_min' => 35000,
    ':salaire_max' => 55000,
    ':temps_de_travail' => 'Temps plein',
    ':type_de_contrat' => 'CDI',
    ':lieu' => 'Toulouse',
    ':recruteur_id' => $personne_recruteur_id,
    ':entreprise_id' => $entreprise_a_id,
    ':categorie_id' => $informatique_id
]);
$offre_dev_mobile_id = $db->lastInsertId();

// Insère des données dans la table candidatures
$requete = $db->prepare('INSERT INTO candidatures (personne_id, offre_id, nom, prenom, email, telephone, message) VALUES (:personne_id, :offre_id, :nom, :prenom, :email, :telephone, :message)');

$requete->execute([
    ':personne_id' => $personne_alexis_id,
    ':offre_id' => $offre_dev_web_id,
    ':nom' => 'Epitech',
    ':prenom' => 'Alexis',
    ':email' => 'alexis@example.eu',
    ':telephone' => '06 12 34 56 78',
    ':message' => 'Je suis très intéressé par cette opportunité.'
]);

$requete->execute([
    ':personne_id' => $personne_alexis_id,
    ':offre_id' => $offre_commercial_id,
    ':nom' => 'Epitech',
    ':prenom' => 'Alexis',
    ':email' => 'alexis@example.eu',
    ':telephone' => '06 12 34 56 78',
    ':message' => 'Je suis très intéressé par cette opportunité.'
]);

$requete->execute([
    ':personne_id' => null,
    ':offre_id' => $offre_analyste_financier_id,
    ':nom' => 'Epitech',
    ':prenom' => 'Jason',
    ':email' => 'jason@example.eu',
    ':telephone' => '06 12 34 56 82',
    ':message' => 'Je suis très intéressé par cette opportunité.'
]);

$requete->execute([
    ':personne_id' => $personne_john_id,
    ':offre_id' => $offre_dev_web_id,
    ':nom' => 'Epitech',
    ':prenom' => 'John',
    ':email' => 'john@example.eu',
    ':telephone' => '06 12 34 56 781',
    ':message' => 'Je suis très intéressé par cette opportunité.'
]);

echo "Base de données initialisée avec succès !";