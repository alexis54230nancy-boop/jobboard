create table categories (
    id integer primary key autoincrement,
    nom text not null
);

create table entreprises (
    id integer primary key autoincrement,
    nom text not null,
    ville text,
    description text,
    site_web text,
    logo_url text
);

create table personnes (
    id integer primary key autoincrement,
    prenom text not null,
    nom text not null,
    email text not null unique,
    telephone text,
    mot_de_passe text not null,
    role text not null default 'candidat'
        check (role in ('admin','recruteur','candidat')),
    entreprise_id integer,
    foreign key (entreprise_id) references entreprises(id) on delete set null
);

create table offres (
    id integer primary key autoincrement,
    titre text not null,
    description text,
    description_courte text not null,
    salaire_min integer,
    salaire_max integer,
    lieu text,
    temps_de_travail text,
    type_de_contrat text,
    date_de_publication text not null default (date('now')),
    categorie_id integer not null,
    entreprise_id integer not null,
    recruteur_id integer,
    foreign key (categorie_id) references categories(id),
    foreign key (entreprise_id) references entreprises(id) on delete cascade,
    foreign key (recruteur_id) references personnes(id) on delete set null
);

create table candidatures (
    id integer primary key autoincrement,
    offre_id integer not null,
    personne_id integer,
    nom text not null,
    prenom text not null,
    email text not null,
    telephone text,
    message text not null,
    email_envoye integer not null default 0,
    date_de_candidature text not null default (date('now')),
    foreign key (offre_id) references offres(id) on delete cascade,
    foreign key (personne_id) references personnes(id) on delete set null
);