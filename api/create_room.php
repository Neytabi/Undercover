<?php
session_start();
// On remonte d'un cran jusqu'à includes/db.php
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

$name = trim($_POST['name']);
if (empty($name)) die("Nom requis");

// Génération du code
$code = strtoupper(substr(md5(uniqid()), 0, 5));

// Création de la room sans creator_id
$stmt = $pdo->prepare("INSERT INTO rooms (code, state, created_at) VALUES (:code, :state, NOW())");
$stmt->execute([
    'code' => $code,
    'state' => 'waiting'
]);

$room_id = $pdo->lastInsertId();

// Ajout du créateur
// Ajout du créateur avec is_connected = 1
$stmt = $pdo->prepare("INSERT INTO players (room_id, name, is_creator, is_connected) VALUES (:room_id, :name, 1, 1)");
$stmt->execute([
    'room_id' => $room_id,
    'name'    => $name
]);
$player_id = $pdo->lastInsertId();

// Mise à jour de la room avec le creator_id
$stmt = $pdo->prepare("UPDATE rooms SET creator_id = :creator_id WHERE id = :room_id");
$stmt->execute([
    'creator_id' => $player_id,
    'room_id' => $room_id
]);

// Stockage en session
$_SESSION['room_id']   = $room_id;
$_SESSION['player_id'] = $player_id;

header("Location: /waiting_room.php");
exit();

