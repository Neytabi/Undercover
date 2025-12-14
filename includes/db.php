<?php
$host = 'localhost';
$db   = 'undercover'; // nom de ta base
$user = 'root';
$pass = ''; // mot de passe par défaut WAMP

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}
?>
