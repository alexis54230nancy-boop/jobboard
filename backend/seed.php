<?php
/*
 * ══════════════════════════════════════════════════════════
 *  DONNÉES DE TEST — remplit la base avec des données fictives
 *
 *  Lancement, depuis backend/ :  php seed.php
 *  Relançable à volonté : il vide les tables avant de les remplir.
 *
 *  Comptes de test (e-mail / mot de passe) :
 *    admin     : chams@example.com  / admin123
 *    recruteur : mathis@example.com / recruteur123   (Lorraine Digital)
 *    recruteur : karim@example.com  / recruteur456   (Studio Pixel Est)
 *    candidat  : alexis@example.com / candidat123
 *    candidat  : john@example.com   / candidat1234
 *    candidat  : lina@example.com   / candidat456
 * ══════════════════════════════════════════════════════════
 */

require __DIR__ . '/database.php';
$db = connecter();


// ─── 1. Vider les tables ──────────────────────────────────
// Dans l'ordre inverse de la création, à cause des clés étrangères.
// sqlite_sequence retient les derniers id : on la vide pour repartir de 1.

foreach (['candidatures', 'offres', 'personnes', 'entreprises', 'categories', 'sqlite_sequence'] as $table) {
    $db->exec("DELETE FROM $table");
}


// ─── 2. Les catégories ────────────────────────────────────
// Pour chaque table, le principe est le même : les données sont dans un tableau,
// et une boucle les insère une par une. On retient l'id de chaque ligne
// (ex. $categories['Finance'] vaut 3) pour s'en servir dans les tables suivantes.

$requete = $db->prepare('INSERT INTO categories (nom) VALUES (:nom)');
$categories = [];

foreach (['Informatique', 'Commerce', 'Finance', 'Marketing', 'Ressources humaines', 'Design'] as $nom) {
    $requete->execute(['nom' => $nom]);
    $categories[$nom] = $db->lastInsertId();
}


// ─── 3. Les entreprises ───────────────────────────────────
// NovaSanté n'a ni ville ni site web : elle sert à tester les champs vides côté front.

$requete = $db->prepare('INSERT INTO entreprises (nom, ville, description, site_web)
    VALUES (:nom, :ville, :description, :site_web)');
$entreprises = [];

foreach ([
    ['nom' => 'Lorraine Digital', 'ville' => 'Nancy', 'site_web' => 'https://www.lorraine-digital.example',
     'description' => 'Agence web de 25 personnes : sites et applications sur mesure pour les PME du Grand Est.'],
    ['nom' => 'Studio Pixel Est', 'ville' => 'Metz', 'site_web' => 'https://www.studio-pixel-est.example',
     'description' => 'Studio indépendant de jeux vidéo : 12 passionnés et deux jeux déjà publiés.'],
    ['nom' => 'Banque des Trois Frontières', 'ville' => 'Thionville', 'site_web' => 'https://www.banque-3f.example',
     'description' => 'Banque régionale au service des particuliers et des entreprises depuis 1954.'],
    ['nom' => 'GreenWatt Énergie', 'ville' => 'Strasbourg', 'site_web' => 'https://www.greenwatt.example',
     'description' => "Fournisseur d'électricité verte et installateur de panneaux solaires."],
    ['nom' => 'Maison Colin', 'ville' => 'Épinal', 'site_web' => 'https://www.maison-colin.example',
     'description' => 'Pâtisserie familiale devenue une marque nationale, avec 80 salariés.'],
    ['nom' => 'NovaSanté', 'ville' => null, 'site_web' => null,
     'description' => "Start-up de télémédecine, dont toute l'équipe travaille à distance."],
] as $entreprise) {
    $requete->execute($entreprise);
    $entreprises[$entreprise['nom']] = $db->lastInsertId();
}


// ─── 4. Les personnes ─────────────────────────────────────
// Le mot de passe n'est jamais stocké en clair : password_hash() en enregistre une empreinte.

$requete = $db->prepare('INSERT INTO personnes (prenom, nom, email, telephone, mot_de_passe, role, entreprise_id)
    VALUES (:prenom, :nom, :email, :telephone, :mot_de_passe, :role, :entreprise_id)');
$personnes = [];   // e-mail → la personne, avec son id

foreach ([
    ['prenom' => 'Chams', 'nom' => 'Epitech', 'email' => 'chams@example.com', 'telephone' => '06 12 34 56 78',
     'mot_de_passe' => 'admin123', 'role' => 'admin', 'entreprise' => null],
    ['prenom' => 'Mathis', 'nom' => 'Epitech', 'email' => 'mathis@example.com', 'telephone' => '06 12 34 56 79',
     'mot_de_passe' => 'recruteur123', 'role' => 'recruteur', 'entreprise' => 'Lorraine Digital'],
    ['prenom' => 'Karim', 'nom' => 'Benali', 'email' => 'karim@example.com', 'telephone' => '06 12 34 56 83',
     'mot_de_passe' => 'recruteur456', 'role' => 'recruteur', 'entreprise' => 'Studio Pixel Est'],
    ['prenom' => 'Alexis', 'nom' => 'Epitech', 'email' => 'alexis@example.com', 'telephone' => '06 12 34 56 80',
     'mot_de_passe' => 'candidat123', 'role' => 'candidat', 'entreprise' => null],
    ['prenom' => 'John', 'nom' => 'Epitech', 'email' => 'john@example.com', 'telephone' => '06 12 34 56 81',
     'mot_de_passe' => 'candidat1234', 'role' => 'candidat', 'entreprise' => null],
    ['prenom' => 'Lina', 'nom' => 'Durand', 'email' => 'lina@example.com', 'telephone' => '06 12 34 56 84',
     'mot_de_passe' => 'candidat456', 'role' => 'candidat', 'entreprise' => null],
] as $personne) {
    $requete->execute([
        'prenom'        => $personne['prenom'],
        'nom'           => $personne['nom'],
        'email'         => $personne['email'],
        'telephone'     => $personne['telephone'],
        'mot_de_passe'  => password_hash($personne['mot_de_passe'], PASSWORD_DEFAULT),
        'role'          => $personne['role'],
        'entreprise_id' => $personne['entreprise'] ? $entreprises[$personne['entreprise']] : null,
    ]);
    $personne['id'] = $db->lastInsertId();
    $personnes[$personne['email']] = $personne;
}


// ─── 5. Les offres ────────────────────────────────────────
// 26 offres, pour montrer la pagination (10 par page). Les cas particuliers à tester côté front :
//   salaire absent → « selon profil » ; salaire identique deux fois → salaire fixe ;
//   description, lieu ou temps de travail absents → champs vides.
// « il_y_a » : le nombre de jours depuis la publication (0 = aujourd'hui).

$offres = [
    // Lorraine Digital
    ['titre' => 'Développeur web PHP', 'categorie' => 'Informatique', 'entreprise' => 'Lorraine Digital', 'recruteur' => 'mathis@example.com',
     'contrat' => 'CDI', 'temps' => 'Temps plein', 'lieu' => 'Nancy', 'salaire' => [32000, 38000], 'il_y_a' => 0,
     'courte' => 'Rejoins une équipe de 6 développeurs pour faire évoluer les applications web de nos clients.',
     'description' => "Tu développes de nouvelles fonctionnalités en PHP et SQL, tu participes aux revues de code et tu échanges directement avec les clients. Deux jours de télétravail par semaine."],
    ['titre' => 'Développeur front-end JavaScript', 'categorie' => 'Informatique', 'entreprise' => 'Lorraine Digital', 'recruteur' => 'mathis@example.com',
     'contrat' => 'CDI', 'temps' => 'Temps plein', 'lieu' => 'Nancy', 'salaire' => [30000, 36000], 'il_y_a' => 2,
     'courte' => 'Crée des interfaces rapides et accessibles en HTML, CSS et JavaScript.',
     'description' => "Tu intègres les maquettes de nos designers, tu branches les pages sur nos API et tu veilles à l'accessibilité des sites."],
    ['titre' => 'Stagiaire développement web', 'categorie' => 'Informatique', 'entreprise' => 'Lorraine Digital', 'recruteur' => 'mathis@example.com',
     'contrat' => 'Stage', 'temps' => 'Temps plein', 'lieu' => 'Nancy', 'il_y_a' => 4,
     'courte' => 'Stage de 4 à 6 mois : participe à de vrais projets clients, encadré par un développeur senior.',
     'description' => 'Tu découvres toutes les étapes d\'un projet web, de la maquette à la mise en ligne. Gratification légale et tickets restaurant.'],
    ['titre' => 'Chef de projet digital', 'categorie' => 'Marketing', 'entreprise' => 'Lorraine Digital',
     'contrat' => 'CDI', 'temps' => 'Temps plein', 'lieu' => 'Nancy', 'salaire' => [38000, 45000], 'il_y_a' => 6,
     'courte' => 'Pilote 4 à 5 projets web en parallèle, du cahier des charges à la livraison.',
     'description' => "Tu es l'interlocuteur principal des clients : tu cadres leurs besoins, tu planifies le travail de l'équipe et tu suis le budget."],
    ['titre' => 'Alternant développeur full stack', 'categorie' => 'Informatique', 'entreprise' => 'Lorraine Digital', 'recruteur' => 'mathis@example.com',
     'contrat' => 'Alternance', 'temps' => 'Temps plein', 'lieu' => 'Nancy', 'salaire' => [12000, 15000], 'il_y_a' => 9,
     'courte' => 'Alternance de 12 ou 24 mois, côté front comme côté back.'],
    ['titre' => 'UX/UI designer', 'categorie' => 'Design', 'entreprise' => 'Lorraine Digital',
     'contrat' => 'CDD', 'temps' => 'Temps partiel', 'lieu' => 'Nancy', 'salaire' => [28000, 32000], 'il_y_a' => 12,
     'courte' => 'CDD de 8 mois à 80 % : conçois les parcours et les maquettes de nos applications.',
     'description' => 'Tu mènes des ateliers avec les utilisateurs, tu dessines les maquettes et tu fais vivre notre bibliothèque de composants.'],

    // Studio Pixel Est
    ['titre' => 'Game designer', 'categorie' => 'Design', 'entreprise' => 'Studio Pixel Est', 'recruteur' => 'karim@example.com',
     'contrat' => 'CDI', 'temps' => 'Temps plein', 'lieu' => 'Metz', 'salaire' => [30000, 35000], 'il_y_a' => 1,
     'courte' => 'Imagine les mécaniques de notre prochain jeu de stratégie.',
     'description' => "Tu écris les règles du jeu, tu équilibres la difficulté et tu testes tes idées chaque semaine avec l'équipe."],
    ['titre' => 'Développeur gameplay C++', 'categorie' => 'Informatique', 'entreprise' => 'Studio Pixel Est', 'recruteur' => 'karim@example.com',
     'contrat' => 'CDI', 'temps' => 'Temps plein', 'lieu' => 'Metz', 'salaire' => [34000, 42000], 'il_y_a' => 3,
     'courte' => 'Programme les personnages, les combats et les outils de nos designers.',
     'description' => 'Tu travailles en C++ sur notre moteur maison, en lien direct avec les game designers et les artistes.'],
    ['titre' => 'Artiste 3D', 'categorie' => 'Design', 'entreprise' => 'Studio Pixel Est', 'recruteur' => 'karim@example.com',
     'contrat' => 'CDI', 'temps' => 'Temps plein', 'lieu' => 'Metz', 'salaire' => [29000, 34000], 'il_y_a' => 8,
     'courte' => 'Modélise et texture les décors et les personnages de nos jeux.'],
    ['titre' => 'Community manager', 'categorie' => 'Marketing', 'entreprise' => 'Studio Pixel Est',
     'contrat' => 'CDD', 'temps' => 'Temps partiel', 'lieu' => 'Metz', 'salaire' => [24000, 24000], 'il_y_a' => 13,
     'courte' => 'Anime notre communauté de joueurs sur Discord et les réseaux sociaux.',
     'description' => 'Tu prépares les annonces, tu réponds aux joueurs et tu remontes leurs retours à l\'équipe. CDD de 6 mois, à mi-temps.'],
    ['titre' => 'Testeur QA jeux vidéo', 'categorie' => 'Informatique', 'entreprise' => 'Studio Pixel Est', 'recruteur' => 'karim@example.com',
     'contrat' => 'Stage', 'temps' => 'Temps plein', 'lieu' => 'Metz', 'il_y_a' => 17,
     'courte' => 'Stage de 3 mois : traque les bugs avant la sortie du jeu.'],

    // Banque des Trois Frontières
    ['titre' => 'Analyste financier', 'categorie' => 'Finance', 'entreprise' => 'Banque des Trois Frontières',
     'contrat' => 'CDI', 'temps' => 'Temps plein', 'lieu' => 'Thionville', 'salaire' => [40000, 60000], 'il_y_a' => 2,
     'courte' => 'Analyse les comptes des entreprises clientes et évalue leurs demandes de financement.'],
    ['titre' => 'Conseiller clientèle', 'categorie' => 'Commerce', 'entreprise' => 'Banque des Trois Frontières',
     'contrat' => 'CDI', 'temps' => 'Temps plein', 'lieu' => 'Thionville', 'salaire' => [26000, 30000], 'il_y_a' => 5,
     'courte' => 'Accompagne un portefeuille de 400 clients particuliers au quotidien.',
     'description' => 'Tu reçois les clients en agence, tu les conseilles sur leurs projets et tu leur proposes les produits adaptés.'],
    ['titre' => 'Contrôleur de gestion', 'categorie' => 'Finance', 'entreprise' => 'Banque des Trois Frontières',
     'contrat' => 'CDI', 'temps' => 'Temps plein', 'lieu' => 'Thionville', 'salaire' => [38000, 46000], 'il_y_a' => 10,
     'courte' => 'Suis les budgets des agences et prépare les tableaux de bord de la direction.',
     'description' => 'Tu construis les budgets annuels avec les directeurs d\'agence et tu expliques les écarts chaque mois.'],
    ['titre' => 'Chargé de recrutement', 'categorie' => 'Ressources humaines', 'entreprise' => 'Banque des Trois Frontières',
     'contrat' => 'CDD', 'temps' => 'Temps plein', 'lieu' => 'Thionville', 'salaire' => [30000, 34000], 'il_y_a' => 15,
     'courte' => 'CDD de 12 mois : recrute les futurs conseillers de nos 18 agences.'],
    ['titre' => 'Alternant en comptabilité', 'categorie' => 'Finance', 'entreprise' => 'Banque des Trois Frontières',
     'contrat' => 'Alternance', 'temps' => 'Temps plein', 'lieu' => 'Thionville', 'salaire' => [11000, 13000], 'il_y_a' => 21,
     'courte' => 'Alternance de 24 mois au sein du service comptable.',
     'description' => 'Tu saisis les écritures, tu participes aux clôtures mensuelles et tu prépares ton diplôme en parallèle.'],

    // GreenWatt Énergie
    ['titre' => 'Commercial terrain', 'categorie' => 'Commerce', 'entreprise' => 'GreenWatt Énergie',
     'contrat' => 'CDI', 'temps' => 'Temps plein', 'lieu' => 'Strasbourg', 'salaire' => [25000, 40000], 'il_y_a' => 1,
     'courte' => 'Fais découvrir nos offres solaires aux particuliers du Bas-Rhin.',
     'description' => 'Tu rencontres les clients chez eux, tu réalises les études de rentabilité et tu signes les contrats. Salaire fixe et primes, voiture de fonction.'],
    ['titre' => 'Responsable marketing', 'categorie' => 'Marketing', 'entreprise' => 'GreenWatt Énergie',
     'contrat' => 'CDI', 'temps' => 'Temps plein', 'lieu' => 'Strasbourg', 'salaire' => [42000, 50000], 'il_y_a' => 7,
     'courte' => 'Construis la stratégie marketing et encadre une équipe de 3 personnes.',
     'description' => "Tu définis les campagnes de l'année, tu gères le budget et tu mesures l'effet de chaque action."],
    ['titre' => 'Technicien de maintenance informatique', 'categorie' => 'Informatique', 'entreprise' => 'GreenWatt Énergie',
     'contrat' => 'CDI', 'temps' => 'Temps plein', 'lieu' => 'Strasbourg', 'salaire' => [27000, 31000], 'il_y_a' => 11,
     'courte' => 'Assure le bon fonctionnement des postes, du réseau et des serveurs du siège.'],
    ['titre' => 'Assistant ressources humaines', 'categorie' => 'Ressources humaines', 'entreprise' => 'GreenWatt Énergie',
     'contrat' => 'CDD', 'temps' => 'Temps partiel', 'lieu' => 'Strasbourg', 'salaire' => [22000, 22000], 'il_y_a' => 18,
     'courte' => 'CDD de 6 mois à mi-temps : gère les dossiers du personnel et la paie.',
     'description' => 'Tu prépares les contrats, tu suis les absences et tu réponds aux questions des salariés.'],

    // Maison Colin
    ['titre' => 'Responsable e-commerce', 'categorie' => 'Commerce', 'entreprise' => 'Maison Colin',
     'contrat' => 'CDI', 'temps' => 'Temps plein', 'lieu' => 'Épinal', 'salaire' => [35000, 42000], 'il_y_a' => 3,
     'courte' => 'Développe les ventes de notre boutique en ligne, en France et en Europe.',
     'description' => 'Tu gères le catalogue, les promotions et la relation avec le transporteur, et tu suis les ventes chaque semaine.'],
    ['titre' => 'Chargé de communication', 'categorie' => 'Marketing', 'entreprise' => 'Maison Colin',
     'contrat' => 'Stage', 'temps' => 'Temps plein', 'lieu' => 'Épinal', 'il_y_a' => 19,
     'courte' => 'Stage de 6 mois : raconte nos pâtisseries en photos, en vidéos et en mots.'],
    ['titre' => 'Comptable', 'categorie' => 'Finance', 'entreprise' => 'Maison Colin',
     'contrat' => 'CDI', 'temps' => 'Temps partiel', 'lieu' => 'Épinal', 'salaire' => [28000, 32000], 'il_y_a' => 23,
     'courte' => 'Tiens la comptabilité de la maison, à 80 %.',
     'description' => 'Tu enregistres les factures, tu prépares les déclarations de TVA et tu travailles avec notre expert-comptable.'],

    // NovaSanté : tout en télétravail, donc sans lieu
    ['titre' => 'Développeur mobile', 'categorie' => 'Informatique', 'entreprise' => 'NovaSanté',
     'contrat' => 'CDI', 'temps' => 'Temps plein', 'salaire' => [35000, 55000], 'il_y_a' => 0,
     'courte' => "Développe notre application de téléconsultation, utilisée par 40 000 patients.",
     'description' => 'Tu fais évoluer les applications iOS et Android, en télétravail complet, avec une équipe de 8 développeurs.'],
    ['titre' => 'Data analyst', 'categorie' => 'Informatique', 'entreprise' => 'NovaSanté',
     'contrat' => 'Freelance', 'il_y_a' => 14,
     'courte' => 'Mission de 3 mois : aide-nous à comprendre comment les patients utilisent notre service.'],
    ['titre' => 'Responsable des ressources humaines', 'categorie' => 'Ressources humaines', 'entreprise' => 'NovaSanté',
     'contrat' => 'CDI', 'temps' => 'Temps plein', 'salaire' => [45000, 52000], 'il_y_a' => 26,
     'courte' => 'Structure les ressources humaines d\'une start-up passée de 10 à 60 salariés.',
     'description' => 'Tu organises les recrutements, tu construis la grille des salaires et tu accompagnes les managers.'],
];

$requete = $db->prepare('INSERT INTO offres
        (titre, description_courte, description, salaire_min, salaire_max, lieu, temps_de_travail,
         type_de_contrat, date_de_publication, categorie_id, entreprise_id, recruteur_id)
    VALUES
        (:titre, :description_courte, :description, :salaire_min, :salaire_max, :lieu, :temps_de_travail,
         :type_de_contrat, :date_de_publication, :categorie_id, :entreprise_id, :recruteur_id)');
$offres_ids = [];   // titre → id

foreach ($offres as $offre) {
    // « ?? null » : la valeur si elle existe dans le tableau, sinon rien
    $requete->execute([
        'titre'               => $offre['titre'],
        'description_courte'  => $offre['courte'],
        'description'         => $offre['description'] ?? null,
        'salaire_min'         => $offre['salaire'][0] ?? null,
        'salaire_max'         => $offre['salaire'][1] ?? null,
        'lieu'                => $offre['lieu'] ?? null,
        'temps_de_travail'    => $offre['temps'] ?? null,
        'type_de_contrat'     => $offre['contrat'],
        'date_de_publication' => date('Y-m-d', strtotime('-' . $offre['il_y_a'] . ' days')),
        'categorie_id'        => $categories[$offre['categorie']],
        'entreprise_id'       => $entreprises[$offre['entreprise']],
        'recruteur_id'        => isset($offre['recruteur']) ? $personnes[$offre['recruteur']]['id'] : null,
    ]);
    $offres_ids[$offre['titre']] = $db->lastInsertId();
}


// ─── 6. Les candidatures ──────────────────────────────────
// Avec « compte » : le candidat est connecté, ses coordonnées viennent de son compte.
// Sans « compte » : il n'en a pas, et personne_id reste vide.
// « Développeur web PHP » reçoit 3 candidatures, et Alexis postule à 2 offres.

$candidatures = [
    ['offre' => 'Développeur web PHP', 'compte' => 'alexis@example.com', 'email_envoye' => 1,
     'message' => "Je développe en PHP depuis deux ans et votre agence m'intéresse beaucoup. Je suis disponible dès maintenant."],
    ['offre' => 'Développeur web PHP', 'compte' => 'john@example.com',
     'message' => 'Votre annonce correspond exactement à ce que je recherche. Mon CV est à votre disposition.'],
    ['offre' => 'Développeur web PHP', 'prenom' => 'Jason', 'nom' => 'Martin', 'email' => 'jason.martin@example.com', 'telephone' => '06 12 34 56 82',
     'message' => "Actuellement en poste à Metz, je souhaite me rapprocher de Nancy. Pouvons-nous en discuter ?"],
    ['offre' => 'Développeur front-end JavaScript', 'compte' => 'alexis@example.com',
     'message' => "J'ai réalisé plusieurs sites en JavaScript pendant ma formation, je serais ravi de vous les présenter."],
    ['offre' => 'Game designer', 'compte' => 'john@example.com', 'email_envoye' => 1,
     'message' => 'Passionné de jeux de stratégie, je conçois des jeux de plateau depuis cinq ans.'],
    ['offre' => 'Game designer', 'prenom' => 'Sofia', 'nom' => 'Moreau', 'email' => 'sofia.moreau@example.com', 'telephone' => null,
     'message' => "J'ai joué à vos deux jeux et j'aimerais beaucoup rejoindre l'équipe."],
    ['offre' => 'Analyste financier', 'compte' => 'lina@example.com',
     'message' => "Diplômée d'un master en finance, je recherche mon premier poste en banque."],
    ['offre' => 'Conseiller clientèle', 'compte' => 'lina@example.com',
     'message' => 'Je suis également intéressée par ce poste, qui correspond à mon expérience en agence.'],
    ['offre' => 'Commercial terrain', 'prenom' => 'Emma', 'nom' => 'Leroy', 'email' => 'emma.leroy@example.com', 'telephone' => '06 12 34 56 85',
     'message' => "Dix ans d'expérience dans la vente à domicile : je connais bien le Bas-Rhin."],
    ['offre' => 'Développeur mobile', 'prenom' => 'Hugo', 'nom' => 'Petit', 'email' => 'hugo.petit@example.com', 'telephone' => '06 12 34 56 86',
     'message' => 'Développeur Android depuis quatre ans, le télétravail complet me convient parfaitement.'],
];

$requete = $db->prepare('INSERT INTO candidatures (offre_id, personne_id, nom, prenom, email, telephone, message, email_envoye)
    VALUES (:offre_id, :personne_id, :nom, :prenom, :email, :telephone, :message, :email_envoye)');

foreach ($candidatures as $candidature) {
    // Avec un compte, les coordonnées sont celles de la personne ; sinon, celles de la candidature
    $compte = isset($candidature['compte']) ? $personnes[$candidature['compte']] : null;
    $candidat = $compte ?? $candidature;

    $requete->execute([
        'offre_id'     => $offres_ids[$candidature['offre']],
        'personne_id'  => $compte['id'] ?? null,
        'nom'          => $candidat['nom'],
        'prenom'       => $candidat['prenom'],
        'email'        => $candidat['email'],
        'telephone'    => $candidat['telephone'],
        'message'      => $candidature['message'],
        'email_envoye' => $candidature['email_envoye'] ?? 0,
    ]);
}


// ─── 7. Le résumé ─────────────────────────────────────────

echo "Base remplie :\n";
foreach (['categories', 'entreprises', 'personnes', 'offres', 'candidatures'] as $table) {
    echo "  $table : " . $db->query("SELECT COUNT(*) FROM $table")->fetchColumn() . "\n";
}
