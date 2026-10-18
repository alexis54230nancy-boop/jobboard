# Back end : base de données et API

Ma partie du projet (rôle A, Chams) : la base SQLite qui stocke les données, et l'API REST en PHP, sans framework, que le front appelle avec `fetch()`.

## Les fichiers

| Fichier | Rôle |
|---|---|
| `schema.sql` | Les 5 tables, leurs liens et leurs règles de suppression |
| `seed.php` | Les données de test : 6 catégories, 6 entreprises, 6 comptes, 26 offres, 10 candidatures |
| `database.php` | `connecter()` : la connexion PDO, avec les clés étrangères activées |
| `index.php` | Le point d'entrée : il suit chaque requête, de sa lecture à la réponse |
| `tables.php` | La description des 5 tables (`$ressources`) |
| `outils.php` | Les fonctions : `repondre()`, `erreur()`, `lire()`, `inserer()`, `verifier()`, `reglesPersonne()` |
| `front.php` | Envoie les fichiers de `frontend/`, pour que le site et l'API aient la même adresse |

`auth.php` et les routes de connexion (`/connexion`, `/inscription`, `/session`, `/deconnexion`) sont la partie de B.

## Lancer

Depuis la racine du dépôt :

```bash
php scripts/init-db.php     # crée la base et la remplit, seulement si elle n'existe pas
cd backend
php -S localhost:8000 index.php
```

`php seed.php` (dans `backend/`) remet les données de test, **en effaçant tout le reste**.

## La base de données

| Table | Pointe vers |
|---|---|
| `categories`, `entreprises` | — |
| `personnes` (admin, recruteur ou candidat) | son entreprise, pour un recruteur |
| `offres` | sa catégorie, son entreprise, son recruteur |
| `candidatures` | l'offre visée, et le compte du candidat s'il en a un |

Mes choix :

- **Une seule table `personnes`**, avec un `role`, au lieu de trois tables qui répéteraient les mêmes colonnes.
- **Les coordonnées sont copiées dans la candidature** : on peut postuler sans compte.
- **Les suppressions sont écrites dans le schéma** (`ON DELETE`) : supprimer une entreprise supprime ses offres et leurs candidatures. Une catégorie encore utilisée ne peut pas être supprimée (409).
- **Mots de passe hachés** (`password_hash`), e-mail unique.

Diagramme et détails : [`docs/BASE_DE_DONNEES.md`](../docs/BASE_DE_DONNEES.md).

## L'API

Les 5 tables se gèrent de la même façon. Chacune est **décrite une seule fois** dans `tables.php` (champs obligatoires et facultatifs, droit de lecture, tri), et **un seul code** dans `index.php` lui fournit ses 5 routes :

| Méthode | Route | Rôle | Succès |
|---|---|---|---|
| GET | `/{table}?page=2&par_page=10` | Liste paginée | 200 |
| GET | `/{table}/{id}` | Détail | 200 |
| POST | `/{table}` | Créer | 201 |
| PUT | `/{table}/{id}` | Modifier (envoyer tous les champs) | 200 |
| DELETE | `/{table}/{id}` | Supprimer | 204 |

- **Accès** : tout le monde lit les offres, les entreprises et les catégories. Le reste est réservé à l'admin, sauf deux exceptions : **postuler** (`POST /offres/{id}/candidatures`, public) et **publier une offre** pour un recruteur connecté, toujours pour sa propre entreprise.
- **Filtres** : `/offres?entreprise_id=3`, `/offres?categorie_id=2`, `/candidatures?offre_id=1`.
- **Réponses** : une liste a la forme `{ "offres": [...], "page": 1, "total": 26 }`, une erreur `{ "erreur": "..." }`.
- **Codes** : 400 donnée invalide · 401 non connecté · 403 pas le droit · 404 introuvable · 409 conflit.

Exemples complets : [`docs/API.md`](../docs/API.md).

## Le trajet d'une requête dans `index.php`

1. Lire la méthode et le chemin.
2. Si le chemin désigne un fichier du front, l'envoyer et s'arrêter.
3. Passer en JSON, ouvrir la session, se connecter à la base, charger `tables.php`.
4. Routes de connexion (B), puis la route pour postuler.
5. Sinon, reconnaître la table avec `preg_match`, vérifier le droit (`exigerAdmin()`), vérifier que la ligne existe, puis exécuter l'action selon la méthode.
6. Rien ne correspond : 404.

## Sécurité

- Toutes les valeurs passent par des **requêtes préparées** ; les noms de tables et de colonnes viennent de `tables.php`, jamais de l'utilisateur.
- Les routes admin sont protégées **côté serveur** : cacher un bouton dans le front ne suffit pas.
- Le mot de passe n'est jamais renvoyé par l'API.

## Limites connues

- `PUT /personnes/{id}` sans `role` remet le compte en `candidat` : il faut toujours envoyer le rôle (la page admin le fait).
- Aucun e-mail n'est réellement envoyé : `email_envoye` sert de suivi pour l'admin.
