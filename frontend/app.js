console.log("app.js is running");

const offres = [
  {
    id: 1,
    titre: "Développeur Web",
    entreprise: "OpenClassrooms",
    lieu: "Paris",
    description: "Nous recherchons un développeur web passionné pour rejoindre notre équipe dynamique. Vous travaillerez sur des projets innovants et aurez l'opportunité de développer vos compétences dans un environnement stimulant.",

  },
  {
    id: 2,
    titre: "Analyste cybersécurité",
    entreprise: "Armée de Terre",
    lieu: "Strasbourg",
    description: "Nous recherchons un analyste cybersécurité pour rejoindre notre équipe. Vous participerez à l'analyse des menaces et à la mise en place de solutions de protection."
  },
  {
    id: 3,
    titre: "Ingénieur DevOps",
    entreprise: "Microsoft",
    lieu: "Lyon",
    description: "Nous recherchons un ingénieur DevOps pour rejoindre notre équipe. Vous travaillerez sur l'automatisation des processus et la gestion des environnements de déploiement."
  },
  {
    id: 4,
    titre: "DevSecOps",
    entreprise: "UBS",
    lieu: "Basel (CH)",
    description: "Nous recherchons un DevSecOps pour rejoindre notre équipe. Vous serez responsable de l'intégration de la sécurité dans le cycle de vie du développement logiciel."
  }
];

const offersList = document.querySelector("#offers-list");

for (const offre of offres) {
  offersList.innerHTML += `
    <article>
      <h2>${offre.titre}</h2>
      <p>${offre.entreprise} - ${offre.lieu}</p>
      <button type="button" data-id="${offre.id}">En savoir plus</button>
    </article>
  `;
}

for (const offre of offres) {
  console.log(offre.titre + " - " + offre.entreprise + " - " + offre.lieu);
}

const boutons = document.querySelectorAll("[data-id]");

for (const bouton of boutons) {
  bouton.addEventListener("click", () => {
    const id = Number(bouton.dataset.id);
    const offreChoisie = offres.find((offre) => offre.id === id);
    if (!offreChoisie) return;
    const detail = document.querySelector("#offer-detail");
    const titre = document.createElement("h2");
    titre.textContent = offreChoisie.titre;
    const description = document.createElement("p");
    description.textContent = offreChoisie.description;
    detail.replaceChildren(titre, description);
    detail.hidden = false;
  });
}

const formulaire = document.querySelector("#apply-form");

formulaire.addEventListener("submit", (event) => {
  event.preventDefault();

  document.querySelector("#apply-message").textContent = "API de candidature non connectee.";
});
