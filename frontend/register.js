const formulaire = document.querySelector("#register-form");
formulaire.addEventListener("submit", (event) => {
  event.preventDefault();
  const donnees = new FormData(formulaire);
  const message = document.querySelector("#register-message");
  if (donnees.get("password") !== donnees.get("confirmPassword")) {
    message.textContent = "Les mots de passe doivent correspondre.";
    return;
  }
  message.textContent = "Inscription disponible apres integration de l'API.";
});
