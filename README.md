# Job Board

Un site d'offres d'emploi : les visiteurs consultent les offres et postulent, et un administrateur gère toutes les données.

- **Back end** : une API REST en PHP, sans framework, avec une base SQLite.
- **Front end** : HTML, CSS et JavaScript. Le job board utilise l'API avec `fetch()` ; les pages d'authentification et d'administration restent à relier.

## Installation et lancement

Il faut **PHP 8** (testé avec PHP 8.2), avec l'extension `pdo_sqlite`, incluse dans XAMPP. Rien d'autre à installer.

Depuis la racine du dépôt (le dossier qui contient `frontend/` et `backend/`) :

```bash
# 1. Créer et remplir la base uniquement si elle n'existe pas
php scripts/init-db.php

# 2. Lancer le serveur
cd backend
php -S localhost:8000 index.php
```

Puis ouvrir **http://localhost:8000**. Le même serveur envoie le front et répond à l'API : par exemple, http://localhost:8000/offres renvoie les offres en JSON.

Laisser ce terminal ouvert pendant l'utilisation du site ; `Ctrl+C` arrête le serveur. Si le port 8000 est déjà occupé, choisir par exemple 8001 dans la commande et dans l'adresse du navigateur.

`scripts/init-db.php` conserve une base existante. **Attention : `php seed.php`, exécuté dans `backend/`, efface toutes les données avant de recréer les exemples.** Ne l'utiliser que pour réinitialiser volontairement une base de test, après sauvegarde si nécessaire. Une modification de `schema.sql` ne met pas automatiquement à jour une base existante.

## Comptes de test

Créés par `seed.php`. Les comptes existent dans la base, mais les routes de connexion et d'inscription restent à intégrer : les formulaires ne permettent pas encore de se connecter.

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
scripts/
└── init-db.php    initialise uniquement une base absente
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

### Front end (Alexis)

Le front est réalisé en **HTML, CSS et JavaScript, sans framework**. Le serveur PHP sert aussi les fichiers du dossier `frontend/` : il faut ouvrir le site avec `http://localhost:8000/`, pas directement avec un fichier HTML. Aucun serveur Node séparé n'est nécessaire.

| Fichier | Rôle et état actuel |
|---|---|
| `frontend/index.html` | Liste des offres, recherche, pagination, panneau de détail et formulaire de candidature. |
| `frontend/app.js` | Appels à l'API, création des cartes, événements et gestion du chargement, des succès et des erreurs. |
| `frontend/style.css` | Style commun, police Space Grotesk avec une police de secours, grille responsive et adaptation du formulaire aux petits écrans. |
| `frontend/login.html` et `login.js` | Interface de connexion. Un message précise qu'aucune connexion n'est effectuée. |
| `frontend/register.html` et `register.js` | Interface d'inscription et vérification des champs et de la confirmation du mot de passe. Aucun compte n'est créé pour le moment. |
| `frontend/admin.html` et `admin.js` | Aperçu avec des candidatures fictives, clairement signalées. Pas encore de gestion des données réelles. |

### Liaison avec l'API

| Action dans la page | Requête envoyée par `app.js` |
|---|---|
| Afficher les offres ou changer de page | `GET /offres?page=1&par_page=6` (le numéro de page varie) |
| Cliquer sur « En savoir plus » | `GET /offres/{id}` |
| Envoyer une candidature | `POST /offres/{id}/candidatures` avec un corps JSON |

La liste utilise le tableau `offres` de la réponse, ainsi que `page` et `total` pour la pagination. Les cartes affichent le titre, l'entreprise, le lieu et la description courte. Les textes reçus sont insérés avec `textContent`.

Fonctionnalités reliées :

- Pagination de **six offres par page**, avec boutons Précédent et Suivant.
- Recherche par titre, entreprise ou ville **dans la page chargée uniquement**, pas dans toute la base.
- Détail affiché dans la même page, **sans rechargement ni popup**. Un texte de remplacement est prévu si le lieu ou la description manque.
- Candidature sans compte avec prénom, nom, e-mail, message et téléphone facultatif. L'offre visée est déterminée par l'URL de la requête.
- Confirmation seulement après une réponse de succès de l'API, avec la référence de la candidature. Le formulaire est désactivé pendant l'envoi et les champs sont conservés si l'envoi n'est pas confirmé.
- Messages de chargement et d'erreur, avec un bouton Réessayer pour les offres. Aucune liste fictive ne remplace discrètement une API indisponible.
- Navigation au clavier et styles de focus ; le titre Job Board permet de revenir à la liste.

### À intégrer avec l'équipe

- Les routes d'inscription, de connexion et de déconnexion, puis la gestion du compte. Aucune page de profil fonctionnelle n'est encore présente.
- L'administration réelle et ses opérations protégées. Masquer une page ou un bouton ne remplace pas le contrôle des droits côté serveur.
- L'envoi d'e-mail : une candidature enregistrée ne signifie pas qu'un e-mail a été envoyé (`email_envoye` reste à zéro sur cette route).

Ne pas utiliser `POST /personnes` comme inscription publique : cette route est réservée à l'administrateur. Voir aussi [`frontend/README.md`](frontend/README.md) pour les détails du front.

### Vérification manuelle du job board

1. Lancer le serveur et vérifier que les offres viennent de l'API. Avec une base nouvellement initialisée par le seed actuel, 26 offres sont disponibles.
2. Passer à la page suivante, revenir à la précédente et rechercher un titre présent sur la page affichée.
3. Ouvrir puis fermer un détail et vérifier que la page ne se recharge pas.
4. Envoyer une candidature fictive dans une base de test et vérifier la confirmation avec sa référence.
5. Arrêter le serveur, déclencher un chargement et vérifier le message d'erreur. Relancer le serveur puis cliquer sur Réessayer.
6. Vérifier l'affichage sur un petit écran et parcourir les contrôles avec la touche Tab.
