<?php
require_once __DIR__ . '/../includes/db.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

if (!isset($_POST['room_id'])) {
  http_response_code(400);
  echo json_encode(['error' => 'room_id manquant']);
  exit;
}

$room_id = $_POST['room_id'];

try {
  // Fermeture sans condition de state
  $stmt = $pdo->prepare("UPDATE rooms SET state = 'closed' WHERE id = ?");
  $stmt->execute([$room_id]);

  echo json_encode(['success' => true, 'room_id' => $room_id]);

} catch (PDOException $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Erreur SQL : ' . $e->getMessage()]);
}
