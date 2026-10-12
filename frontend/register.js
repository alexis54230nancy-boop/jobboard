const formulaire = document.querySelector("#register-form");
formulaire.addEventListener("submit", (event) => {
  event.preventDefault();
  const donnees = new FormData(formulaire);
  const message = document.querySelector("#register-message");
  if (!donnees.get("prenom").trim() || !donnees.get("nom").trim()) {
    message.textContent = "Renseigne ton prénom et ton nom.";
    return;
  }
  if (donnees.get("password") !== donnees.get("confirmPassword")) {
    message.textContent = "Les mots de passe doivent correspondre.";
    formulaire.elements.confirmPassword.focus();
    return;
  }
  message.textContent = "Formulaire vérifié. Aucun compte créé : inscription indisponible pour le moment.";
});
