# Job Board

Un site d'offres d'emploi : les visiteurs consultent les offres et postulent, et un administrateur gère toutes les données.

- **Back end** : une API REST en PHP, sans framework, avec une base SQLite.
- **Front end** : HTML, CSS et JavaScript, qui lit toutes ses données dans l'API avec `fetch()`.

## Installation et lancement

Il faut **PHP 8** (testé avec PHP 8.2), avec l'extension `pdo_sqlite`, incluse dans XAMPP. Rien d'autre à installer.

Depuis le dossier `backend/` :

```bash
# 1. Créer la base et ses tables (une seule fois)
php -r '$db = new PDO("sqlite:jobboard.db"); $db->exec(file_get_contents("schema.sql")); echo "OK";'

# 2. La remplir avec les données de test (relançable à volonté)
php seed.php

# 3. Lancer le serveur
php -S localhost:8000 index.php
```

Puis ouvrir **http://localhost:8000**. Le même serveur envoie le front et répond à l'API : par exemple, http://localhost:8000/offres renvoie les offres en JSON.

Pour repartir de données propres, relancer `php seed.php`. Après une modification de `schema.sql`, supprimer `jobboard.db` et refaire les étapes 1 et 2.

## Comptes de test

Créés par `seed.php` :

| Rôle | E-mail | Mot de passe |
|---|---|---|
| Admin | chams@example.com | admin123 |
| Recruteur (Lorraine Digital) | mathis@example.com | recruteur123 |
| Recruteur (Studio Pixel Est) | karim@example.com | recruteur456 |
| Candidat | alexis@example.com | candidat123 |
| Candidat | john@example.com | candidat1234 |
| Candidat | lina@example.com | candidat456 |

## Organisation du projet

```
backend/
├── schema.sql     les tables de la base
├── seed.php       les données de test
├── database.php   connecter() : la connexion à la base
├── auth.php       exigerAdmin() : la protection des routes admin
└── index.php      l'API : toutes les requêtes arrivent ici
frontend/          les pages, le CSS et le JavaScript
```

`jobboard.db`, la base elle-même, n'est pas dans Git : chacun la crée sur son poste avec les étapes ci-dessus.

## Base de données

Cinq tables, décrites dans `backend/schema.sql`. `*` = obligatoire, `→` = clé étrangère.

| Table | Colonnes |
|---|---|
| `categories` | id, nom* |
| `entreprises` | id, nom*, ville, description, site_web, logo_url |
| `personnes` | id, prenom*, nom*, email* (unique), telephone, mot_de_passe*, role* (`admin`, `recruteur` ou `candidat`), entreprise_id → entreprises |
| `offres` | id, titre*, description_courte*, description, salaire_min, salaire_max, lieu, temps_de_travail, type_de_contrat, date_de_publication*, categorie_id* → categories, entreprise_id* → entreprises, recruteur_id → personnes |
| `candidatures` | id, offre_id* → offres, personne_id → personnes, nom*, prenom*, email*, telephone, message*, email_envoye*, date_de_candidature* |

Les liens entre les tables :

- une **entreprise** publie plusieurs **offres**, et une **catégorie** en regroupe plusieurs ;
- une **offre** reçoit plusieurs **candidatures** ;
- une **personne** peut être le recruteur d'une offre, travailler pour une entreprise, et envoyer des candidatures.

Quelques choix :

- **Une seule table `personnes`** pour les admins, les recruteurs et les candidats, distingués par `role`.
- **Les coordonnées du candidat sont copiées dans la candidature.** Une personne sans compte peut ainsi postuler : `personne_id` reste alors vide.
- **Le salaire tient en deux nombres**, `salaire_min` et `salaire_max` : identiques pour un salaire fixe, vides s'il n'est pas précisé.
- **Les mots de passe sont hachés** avec `password_hash()`, jamais stockés en clair.
- **Les dates se remplissent toutes seules** à la création.

Ce qui se passe quand on supprime une ligne :

| On supprime… | Conséquence |
|---|---|
| une entreprise | Ses offres et leurs candidatures sont supprimées. Ses recruteurs gardent leur compte, sans entreprise. |
| une offre | Ses candidatures sont supprimées. |
| une personne | Ses offres et ses candidatures restent, sans lien vers elle. |
| une catégorie | Refusé tant que des offres l'utilisent (409). |

## API

Toute l'API est dans `backend/index.php`. Les cinq tables se gèrent de la même façon : elles sont **décrites une seule fois**, dans le tableau `$ressources` (champs obligatoires, champs facultatifs, droit de lecture, tri), et **un seul code** fournit les mêmes cinq routes pour chacune.

### Les cinq routes de chaque table

`{table}` vaut `offres`, `entreprises`, `categories`, `personnes` ou `candidatures`.

| Méthode | Route | Rôle | Succès |
|---|---|---|---|
| GET | `/{table}` | Liste paginée | 200 |
| GET | `/{table}/{id}` | Détail | 200 |
| POST | `/{table}` | Créer | 201 |
| PUT | `/{table}/{id}` | Modifier (envoyer tous les champs) | 200 |
| DELETE | `/{table}/{id}` | Supprimer | 204 |

**Accès.** Tout le monde peut lire les offres, les entreprises et les catégories. Lire les personnes et les candidatures, et toute création, modification ou suppression, est réservé à l'admin.

**Pagination.** `?page=2` pour la page, `?par_page=20` pour sa taille (10 par défaut, 100 au plus).

**Filtres.** `/offres?entreprise_id=3`, `/offres?categorie_id=2`, `/candidatures?offre_id=1`.

Une liste a toujours cette forme :

```json
{ "offres": [ { "id": 1, "titre": "Développeur web PHP", "entreprise": "Lorraine Digital", "categorie": "Informatique", "…": "…" } ], "page": 1, "total": 26 }
```

Chaque offre porte le nom de son entreprise et de sa catégorie, et chaque candidature le titre de son offre. Le mot de passe d'une personne n'est jamais renvoyé.

### Postuler

| Méthode | Route | Accès | Succès |
|---|---|---|---|
| POST | `/offres/{id}/candidatures` | Public | 201 |

```json
{ "nom": "Martin", "prenom": "Alice", "email": "alice@example.com", "telephone": "06 12 34 56 87", "message": "Votre offre m'intéresse." }
```

Un candidat **connecté** n'envoie que `message` : ses coordonnées sont reprises de son compte.

### Erreurs

Une erreur a toujours la forme `{ "erreur": "Le champ titre est requis" }`.

| Code | Signification |
|---|---|
| 400 | Champ obligatoire manquant ou valeur invalide (nombre, e-mail, `id` inexistant) |
| 401 | Non connecté |
| 403 | Connecté, mais pas admin |
| 404 | Route inconnue, ou `id` inexistant |
| 409 | Conflit : e-mail déjà utilisé, ou élément encore utilisé par d'autres lignes |

## Authentification

> **À compléter (B).** Les routes de connexion, d'inscription et de gestion du compte.

Les routes admin appellent `exigerAdmin()` (`backend/auth.php`), qui lit `$_SESSION['utilisateur']`. La connexion doit y ranger au moins `id` et `role`. « Postuler » s'en sert aussi pour reconnaître un candidat connecté.

## Pages

> **À compléter (Alexis).** Le job board, la connexion, le compte et l'administration.
