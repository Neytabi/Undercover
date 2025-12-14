<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['player_id'])) {
  http_response_code(401);
  echo json_encode(['success' => false, 'error' => 'Unauthorized']);
  exit;
}

$player_id = (int)$_SESSION['player_id'];
$room_id = isset($_POST['room_id']) ? (int)$_POST['room_id'] : null;
$turn_time_seconds = isset($_POST['turn_time_seconds']) ? (int)$_POST['turn_time_seconds'] : null;
$anonymize_votes = isset($_POST['anonymize_votes']) ? (int)$_POST['anonymize_votes'] : null; // 0/1
$undercover_count = isset($_POST['undercover_count']) ? (int)$_POST['undercover_count'] : null;

// validation minimale
if (!$room_id) {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => 'room_id manquant']);
  exit;
}

// Vérifier que la room existe et récupérer creator_id
try {
  $stmt = $pdo->prepare("SELECT creator_id FROM rooms WHERE id = ?");
  $stmt->execute([$room_id]);
  $room = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$room) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Room introuvable']);
    exit;
  }

  if ((int)$room['creator_id'] !== $player_id) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Seul le créateur peut modifier ce paramètre']);
    exit;
  }
} catch (PDOException $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'Erreur SQL: ' . $e->getMessage()]);
  exit;
}

// Validation si on reçoit turn_time_seconds
if ($turn_time_seconds !== null && ($turn_time_seconds < 5 || $turn_time_seconds > 120)) {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => 'Valeur hors limites (5-120)']);
  exit;
}

// Si on reçoit undercover_count, validate against number of players
if ($undercover_count !== null) {
  try {
    $stmtPlayers = $pdo->prepare("SELECT COUNT(*) FROM players WHERE room_id = ?");
    $stmtPlayers->execute([$room_id]);
    $players_count = (int)$stmtPlayers->fetchColumn();
    $max_undercover = max(1, (int)floor($players_count / 2)); // règle: max la moitié

    if ($undercover_count < 1 || $undercover_count > $max_undercover) {
      http_response_code(400);
      echo json_encode(['success' => false, 'error' => "undercover_count invalide (valeur entre 1 et $max_undercover attendue)"]);
      exit;
    }
  } catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur SQL: ' . $e->getMessage()]);
    exit;
  }
}

try {
  // Build update dynamique (si nécessaire)
  $updates = [];
  $params = [];
  if ($turn_time_seconds !== null) {
    $updates[] = 'turn_time_seconds = :tts';
    $params[':tts'] = $turn_time_seconds;
  }
  if ($anonymize_votes !== null) {
    $updates[] = 'anonymize_votes = :anon';
    $params[':anon'] = $anonymize_votes ? 1 : 0;
  }
  if ($undercover_count !== null) {
    $updates[] = 'undercover_count = :uc';
    $params[':uc'] = $undercover_count;
  }

  if (empty($updates)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Aucune donnée à enregistrer']);
    exit;
  }

  $params[':id'] = $room_id;
  $sql = "UPDATE rooms SET " . implode(", ", $updates) . " WHERE id = :id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);

  // Build response payload (inclure tout ce qui a été modifié)
  $resp = ['success' => true];
  if ($turn_time_seconds !== null) $resp['turn_time_seconds'] = $turn_time_seconds;
  if ($anonymize_votes !== null) $resp['anonymize_votes'] = $anonymize_votes ? 1 : 0;
  if ($undercover_count !== null) $resp['undercover_count'] = $undercover_count;

  // --- appel au Node pour invalider / mettre à jour le cache (optionnel mais recommandé)
  $node_url = 'http://127.0.0.1:3001/internal/invalidate_room_cache';
  $node_secret = getenv('NODE_SHARED_SECRET') ?: 'change_me_locally';

  $post_fields_arr = ['room_id' => $room_id, 'secret' => $node_secret];
  if ($turn_time_seconds !== null) $post_fields_arr['turn_time_seconds'] = $turn_time_seconds;
  if ($anonymize_votes !== null) $post_fields_arr['anonymize_votes'] = ($anonymize_votes ? 1 : 0);
  if ($undercover_count !== null) $post_fields_arr['undercover_count'] = $undercover_count;

  $post_fields = http_build_query($post_fields_arr);

  // prefer curl if available
  if (function_exists('curl_version')) {
    $ch = curl_init($node_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    $node_response = curl_exec($ch);
    $curl_err = curl_error($ch);
    curl_close($ch);

    // Bloc B: loger le retour du node (utile pour debug)
    if ($node_response === false || $curl_err) {
      error_log("save_room_setting: Erreur contact Node: " . ($curl_err ?: 'no response'));
    } else {
      error_log("save_room_setting: réponse Node: " . $node_response);
    }
  } else {
    $opts = ['http' => ['method' => 'POST', 'header' => "Content-type: application/x-www-form-urlencoded\r\n", 'content' => $post_fields, 'timeout' => 2]];
    $node_response = @file_get_contents($node_url, false, stream_context_create($opts));
    if ($node_response === false) {
      error_log("save_room_setting: impossible de contacter le Node via file_get_contents");
    } else {
      error_log("save_room_setting: réponse Node: " . $node_response);
    }
  }

  // Enfin renvoyer la réponse JSON au client
  echo json_encode($resp);
  exit;
} catch (PDOException $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'Erreur SQL: ' . $e->getMessage()]);
  exit;
}
