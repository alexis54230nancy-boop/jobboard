document.querySelector("#login-form").addEventListener("submit", (event) => {
  event.preventDefault();
  document.querySelector("#login-message").textContent = "Connexion indisponible pour le moment. Aucune connexion effectuée.";
});
