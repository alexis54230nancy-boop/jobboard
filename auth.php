<?php
session_start();

if (!isset($_SESSION["user"])) {
    header("Location: ../login.php");
    exit;
}

if ($_SESSION["user"]["role"] !== "ADMIN") {
    http_response_code(403);
    die("Accès interdit.");
}
?>