<?php
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

$room_id = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;
if (!$room_id) {
  http_response_code(400);
  echo json_encode(['error' => 'room_id manquant']);
  exit;
}

try {
  $stmt = $pdo->prepare("
    SELECT
      COALESCE(turn_time_seconds, 30) AS turn_time_seconds,
      COALESCE(anonymize_votes, 0) AS anonymize_votes,
      COALESCE(undercover_count, 1) AS undercover_count
    FROM rooms
    WHERE id = ?
  ");
  $stmt->execute([$room_id]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$row) {
    http_response_code(404);
    echo json_encode(['error' => 'room introuvable']);
    exit;
  }

  echo json_encode([
    'success' => true,
    'turn_time_seconds' => (int)$row['turn_time_seconds'],
    'anonymize_votes'   => (int)$row['anonymize_votes'],
    'undercover_count'  => (int)$row['undercover_count']
  ]);
} catch (PDOException $e) {
  // log pour debug côté serveur
  error_log("get_room_setting error for room_id={$room_id}: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => $e->getMessage()]);
}
