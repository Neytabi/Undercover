<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /index.php');
    exit();
}

$code = strtoupper(trim($_POST['code']));
$name = trim($_POST['name']);

if (empty($code) || empty($name)) {
    die("Code ou nom manquant");
}

// Vérifie si la room existe
$stmt = $pdo->prepare("SELECT id, state FROM rooms WHERE code = :code");
$stmt->execute(['code' => $code]);
$room = $stmt->fetch();

if (!$room || $room['state'] === 'closed') {
    showErrorPage("Room non trouvée ou fermée");
    exit();
}

// ✅ Autoriser uniquement les rooms en attente
if ($room['state'] !== 'waiting') {
    showErrorPage("Impossible de rejoindre : la partie a déjà commencé.");
    exit();
}


$room_id = $room['id'];

// ⚠️ Vérifie si un joueur avec ce nom existe déjà dans la room
$stmt = $pdo->prepare("SELECT id FROM players WHERE room_id = :room_id AND name = :name");
$stmt->execute([
    'room_id' => $room_id,
    'name'    => $name
]);
$existingPlayer = $stmt->fetch();

if ($existingPlayer) {
    // Réutiliser le joueur existant (ex: rechargement)
    $player_id = $existingPlayer['id'];

    // ⚠️ Le marquer reconnecté
    $pdo->prepare("UPDATE players SET is_connected = 1 WHERE id = :id")
        ->execute(['id' => $player_id]);
} else {
    // Nouveau joueur
    $stmt = $pdo->prepare("INSERT INTO players (room_id, name, is_connected) VALUES (:room_id, :name, 1)");
    $stmt->execute([
        'room_id' => $room_id,
        'name'    => $name
    ]);
    $player_id = $pdo->lastInsertId();
}

// Stockage en session
$_SESSION['room_id']   = $room_id;
$_SESSION['player_id'] = $player_id;

// Redirection vers la salle d’attente
header("Location: /waiting_room.php");
exit();

// page d'erreur

function showErrorPage($message) {
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
      <meta charset="UTF-8" />
      <title>Oups !</title>
      <style>
        body {
          font-family: 'Arial', sans-serif;
          text-align: center;
          padding: 50px;
          background: #f9f9f9;
          color: #333;
        }
        h1 {
          font-size: 3rem;
          margin-bottom: 20px;
          color: #e74c3c;
        }
        p {
          font-size: 1.2rem;
          margin-bottom: 40px;
        }
        button {
          background: #3498db;
          border: none;
          padding: 15px 30px;
          color: white;
          font-size: 1.1rem;
          cursor: pointer;
          border-radius: 8px;
          transition: background 0.3s ease;
        }
        button:hover {
          background: #2980b9;
        }
        .funny-face {
          font-size: 5rem;
          margin-bottom: 30px;
        }
      </style>
    </head>
    <body>
      <div class="funny-face">😵‍💫</div>
      <h1>Oups !</h1>
      <p><?= htmlspecialchars($message) ?></p>
      <button onclick="window.location.href='/index.php'">Revenir en lieu sûr</button>
    </body>
    </html>
    <?php
}
?>