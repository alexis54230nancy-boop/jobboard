# API du Job Board

Tout ce que le front peut demander au serveur, avec un exemple pour chaque cas.

- **Adresse** : `http://localhost:8000` (voir le [README](../README.md) pour lancer le serveur).
- **Format** : toutes les réponses sont en JSON. Les requêtes `POST` et `PUT` envoient du JSON, avec l'en-tête `Content-Type: application/json`.
- **Code** : les routes sont dans [`backend/index.php`](../backend/index.php), la description des tables dans [`backend/tables.php`](../backend/tables.php).

## Toutes les routes

`{table}` vaut `offres`, `entreprises`, `categories`, `personnes` ou `candidatures`.

| Méthode | Route | Rôle | Succès |
|---|---|---|---|
| GET | `/{table}` | Liste paginée | 200 |
| GET | `/{table}/{id}` | Détail | 200 |
| POST | `/{table}` | Créer | 201 |
| PUT | `/{table}/{id}` | Modifier | 200 |
| DELETE | `/{table}/{id}` | Supprimer | 204 |
| POST | `/offres/{id}/candidatures` | Postuler à une offre | 201 |

## Qui a le droit

| Action | `offres`, `entreprises`, `categories` | `personnes`, `candidatures` |
|---|---|---|
| Lire (GET) | Tout le monde | Admin |
| Créer, modifier, supprimer | Admin | Admin |

`POST /offres/{id}/candidatures` est ouvert à tout le monde : c'est la seule écriture publique.

## Les champs de chaque table

Ce sont les champs à envoyer dans un `POST` ou un `PUT`. Un champ inconnu est ignoré.

| Table | Obligatoires | Facultatifs |
|---|---|---|
| `offres` | titre, description_courte, categorie_id, entreprise_id | description, salaire_min, salaire_max, lieu, temps_de_travail, type_de_contrat, recruteur_id |
| `entreprises` | nom | ville, description, site_web, logo_url |
| `categories` | nom | |
| `personnes` | prenom, nom, email | telephone, mot_de_passe, role, entreprise_id |
| `candidatures` | offre_id, nom, prenom, email, message | personne_id, telephone, email_envoye |

Pour une personne :

- `mot_de_passe` est obligatoire à la création. Dans une modification, on peut le laisser vide : l'ancien est gardé.
- `role` vaut `admin`, `recruteur` ou `candidat` (`candidat` par défaut).
- Le mot de passe est haché avant d'être enregistré, et il n'est jamais renvoyé.

## Exemples

### Lister

```
GET /offres?page=1&par_page=1
```

Réponse `200` :

```json
{
  "offres": [
    {
      "id": 24,
      "titre": "Développeur mobile",
      "description": "Tu fais évoluer les applications iOS et Android, en télétravail complet, avec une équipe de 8 développeurs.",
      "description_courte": "Développe notre application de téléconsultation, utilisée par 40 000 patients.",
      "salaire_min": 35000,
      "salaire_max": 55000,
      "lieu": null,
      "temps_de_travail": "Temps plein",
      "type_de_contrat": "CDI",
      "date_de_publication": "2026-10-12",
      "categorie_id": 1,
      "entreprise_id": 6,
      "recruteur_id": null,
      "entreprise": "NovaSanté",
      "categorie": "Informatique"
    }
  ],
  "page": 1,
  "total": 26
}
```

À retenir :

- La liste est rangée sous le nom de la table (`offres`, `categories`…), avec `page` et `total` à côté.
- `total` compte toutes les lignes, pas seulement celles de la page. Le nombre de pages vaut donc `total / par_page`, arrondi au-dessus.
- `?par_page=` vaut 10 par défaut et 100 au plus.
- Les offres sortent de la plus récente à la plus ancienne. Chacune porte le **nom** de son entreprise et de sa catégorie, en plus de leur `id`.
- Un champ facultatif laissé vide vaut `null`.

Les filtres se mettent aussi dans l'adresse :

```
GET /offres?entreprise_id=3
GET /offres?categorie_id=2
GET /candidatures?offre_id=1
```

### Lire un élément

```
GET /entreprises/1
```

Réponse `200` :

```json
{
  "id": 1,
  "nom": "Lorraine Digital",
  "ville": "Nancy",
  "description": "Agence web de 25 personnes : sites et applications sur mesure pour les PME du Grand Est.",
  "site_web": "https://www.lorraine-digital.example",
  "logo_url": null
}
```

### Créer (admin)

```
POST /offres
```

```json
{
  "titre": "Développeur web PHP",
  "description_courte": "Rejoins une équipe de 6 développeurs.",
  "categorie_id": 1,
  "entreprise_id": 1,
  "lieu": "Nancy",
  "salaire_min": 32000,
  "salaire_max": 38000
}
```

Réponse `201` : la ligne enregistrée, au même format que `GET /offres/{id}`, avec son nouvel `id`.

### Modifier (admin)

```
PUT /offres/1
```

On envoie **tous** les champs, pas seulement celui qui change : un champ facultatif absent est vidé. Réponse `200` : la ligne modifiée.

### Supprimer (admin)

```
DELETE /offres/1
```

Réponse `204`, sans contenu. Ce que la suppression entraîne dans les autres tables est décrit dans [BASE_DE_DONNEES.md](BASE_DE_DONNEES.md).

### Postuler

```
POST /offres/1/candidatures
```

```json
{
  "nom": "Martin",
  "prenom": "Alice",
  "email": "alice@example.com",
  "telephone": "06 12 34 56 87",
  "message": "Votre offre m'intéresse."
}
```

Réponse `201` : la candidature enregistrée, avec son `id`.

Un candidat **connecté** n'envoie que `message` : son nom, son prénom, son e-mail et son téléphone sont repris de son compte.

## Erreurs

Une erreur a toujours la même forme, avec un message lisible :

```json
{ "erreur": "Le champ nom est requis" }
```

| Code | Quand | Exemple de message |
|---|---|---|
| 400 | Champ obligatoire manquant, ou valeur invalide (nombre, e-mail, `id` lié inexistant) | `Le champ nom est requis` |
| 401 | Route admin appelée sans être connecté | `Connexion requise` |
| 403 | Connecté, mais pas admin | `Accès réservé aux administrateurs` |
| 404 | `id` inexistant, ou adresse inconnue | `Élément non trouvé`, `Route introuvable` |
| 409 | E-mail déjà utilisé, ou élément encore utilisé par d'autres lignes | |

Côté front, il suffit donc de tester `reponse.ok`, puis d'afficher `donnees.erreur`.

## Essayer

Les routes `GET` publiques s'ouvrent directement dans le navigateur, par exemple http://localhost:8000/offres?par_page=2.

Pour les routes admin, il faut d'abord être connecté : la connexion range `id` et `role` dans la session PHP, et l'API les relit à chaque requête.
