document.querySelector("#login-form").addEventListener("submit", (event) => {
  event.preventDefault();
  document.querySelector("#login-message").textContent = "Connexion disponible apres integration de l'API.";
});
