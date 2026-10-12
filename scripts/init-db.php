<?php
// Ne jamais reinitialiser une base existante lors d'un simple lancement.
$backend = __DIR__ . '/../backend';
$chemin = $backend . '/jobboard.db';

if (file_exists($chemin)) {
    echo "Base existante conservee.\n";
    exit(0);
}

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    fwrite(STDERR, "Le module PHP pdo_sqlite est requis.\n");
    exit(1);
}

$db = new PDO('sqlite:' . $chemin);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec(file_get_contents($backend . '/schema.sql'));
$db = null;
require $backend . '/seed.php';
echo "\n";
