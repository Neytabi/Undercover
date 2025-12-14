const express = require('express');
const cors = require('cors');
const WebSocket = require('ws');
const axios = require('axios');

const app = express();
app.use(cors());
app.use(express.urlencoded({ extended: true }));
app.use(express.json());

const PORT = 3001;
const PHP_API_BASE = 'http://127.0.0.1/api';
const server = app.listen(PORT, '::'); // écoute sur toutes les interfaces IPv6
// endpoint interne pour invalider / mettre à jour le cache de settings par room
// Sécurité : envoi un secret depuis PHP (ou appelle uniquement depuis localhost).
const NODE_SHARED_SECRET = process.env.NODE_SHARED_SECRET || 'change_me_locally';
const wss = new WebSocket.Server({ server });
const rooms = {};          // room_id => Set<WebSocket>
const wsInfo = new Map();  // ws => { room_id, player_id }
const turnTimers = {};     // room_id => timeoutId
const turnForRoom = {};    // room_id => player_id (qui est censé jouer)
const TURN_MS_DEFAULT = 30000; // fallback si pas de valeur en DB
const turnSettingsCache = {}; // room_id => { ms, ts }
const closeTimers = {}; // room_id => timeoutId

function cancelCloseTimer(room_id) {
  if (closeTimers[room_id]) {
    clearTimeout(closeTimers[room_id]);
    delete closeTimers[room_id];
    console.log(`Annulé: fermeture programmée pour room ${room_id}`);
  }
}

async function scheduleSimpleClose(room_id) {
  // Si un timer est déjà programmé, on ne programme pas un nouveau (simple protection)
  if (closeTimers[room_id]) return;

  try {
    // 1) vérif immédiate
    const resPlayers = await axios.get(`${PHP_API_BASE}/get_players_in_room.php`, { params: { room_id } });
    const list = (resPlayers.data && resPlayers.data.players) ? resPlayers.data.players : [];
    const connectedPlayers = list.filter(p => Number(p.is_connected) === 1);

    if (connectedPlayers.length > 0) {
      console.log(`Room ${room_id} encore ${connectedPlayers.length} joueur(s) — pas de fermeture`);
      return;
    }

    // 2) attendre 15s
    closeTimers[room_id] = setTimeout(async () => {
      try {
        // re-check après 15s
        const resPlayers2 = await axios.get(`${PHP_API_BASE}/get_players_in_room.php`, { params: { room_id } });
        const list2 = (resPlayers2.data && resPlayers2.data.players) ? resPlayers2.data.players : [];
        const connectedPlayers2 = list2.filter(p => Number(p.is_connected) === 1);

        if (connectedPlayers2.length === 0) {
          console.log(`Room ${room_id} vide après 15s -> fermeture`);
          // nettoyage local
          clearRoomTimer(room_id);
          if (rooms[room_id]) delete rooms[room_id];
          if (turnSettingsCache[room_id]) delete turnSettingsCache[room_id];
          // fermeture côté PHP
          await axios.post(`${PHP_API_BASE}/close_room.php`, new URLSearchParams({ room_id }));
        } else {
          console.log(`Room ${room_id} réoccupée (${connectedPlayers2.length}) — fermeture annulée`);
        }
      } catch (err) {
        console.error('Erreur vérif finale fermeture:', err.message);
      } finally {
        // supprimer le timer (quel que soit le résultat)
        if (closeTimers[room_id]) {
          clearTimeout(closeTimers[room_id]);
          delete closeTimers[room_id];
        }
      }
    }, 15000);

    console.log(`Fermeture programmée dans 15s pour room ${room_id}`);
  } catch (err) {
    console.error('Erreur scheduleSimpleClose:', err.message);
  }
}


function broadcast(room_id, payload) {
  if (!rooms[room_id]) return;
  const message = JSON.stringify(payload);
  rooms[room_id].forEach(client => {
    if (client.readyState === WebSocket.OPEN) client.send(message);
  });
}

app.post('/internal/invalidate_room_cache', (req, res) => {
  console.log('/internal/invalidate_room_cache body:', req.body);
  try {
    const { room_id, turn_time_seconds, anonymize_votes, undercover_count, secret } = req.body || {};

    // basic security: secret match OR req from localhost
    const ip = req.ip || req.socket.remoteAddress || '';
    const fromLocalhost = ip.includes('127.0.0.1') || ip.includes('::1') || req.hostname === 'localhost';

    if (secret !== NODE_SHARED_SECRET && !fromLocalhost) {
      return res.status(403).json({ error: 'Forbidden' });
    }

    if (!room_id) return res.status(400).json({ error: 'room_id manquant' });

    // 1) Mise à jour du cache du turn_time (si fourni)
    if (typeof turn_time_seconds !== 'undefined' && turn_time_seconds !== null) {
      const sec = parseInt(turn_time_seconds, 10);
      const ms = (!isNaN(sec) && sec > 0) ? sec * 1000 : TURN_MS_DEFAULT;
      turnSettingsCache[room_id] = { ms, ts: Date.now() };
      broadcast(room_id, { type: 'turn_time_updated', turn_time_seconds: sec });
      console.log(`Cache mis à jour pour room ${room_id} -> ${sec}s`);
    }

    // 2) Préparer un payload unifié pour les settings de room (anonymize + undercover_count)
    const roomSettingsPayload = {};
    let hasRoomSettings = false;

    if (typeof anonymize_votes !== 'undefined' && anonymize_votes !== null) {
      roomSettingsPayload.anonymize_votes = parseInt(anonymize_votes, 10) ? 1 : 0;
      hasRoomSettings = true;
    }
    if (typeof undercover_count !== 'undefined' && undercover_count !== null) {
      roomSettingsPayload.undercover_count = parseInt(undercover_count, 10) || 1;
      hasRoomSettings = true;
    }

    if (hasRoomSettings) {
      // broadcast unique pour mettre à jour tous les clients en une fois
      broadcast(room_id, { type: 'room_settings_updated', ...roomSettingsPayload });
      console.log(`room_settings_updated broadcast for room ${room_id}:`, roomSettingsPayload);
    }

    // 3) Si aucun champ de cache/settings n'est fourni -> invalidation
    if (typeof turn_time_seconds === 'undefined' && typeof anonymize_votes === 'undefined' && typeof undercover_count === 'undefined') {
      delete turnSettingsCache[room_id];
      console.log(`Cache invalidé pour room ${room_id}`);
      return res.json({ success: true, invalidated: true });
    }

    return res.json({ success: true });
  } catch (err) {
    console.error('Erreur internal/invalidate_room_cache:', err);
    return res.status(500).json({ error: err.message });
  }
});


function clearRoomTimer(room_id) {
  if (turnTimers[room_id]) {
    clearTimeout(turnTimers[room_id]);
    delete turnTimers[room_id];
  }
  delete turnForRoom[room_id];
}

app.post('/internal/vote_submitted', async (req, res) => {
  try {
    const { room_id, secret } = req.body || {};

    // sécurité comme ailleurs
    const ip = req.ip || req.socket.remoteAddress || '';
    const fromLocalhost = ip.includes('127.0.0.1') || ip.includes('::1') || req.hostname === 'localhost';
    if (secret !== NODE_SHARED_SECRET && !fromLocalhost) {
      return res.status(403).json({ error: 'Forbidden' });
    }
    if (!room_id) return res.status(400).json({ error: 'room_id manquant' });

    // get_vote_status.php doit renvoyer { totalVotes, totalAlive }
    const resStatus = await axios.get(`${PHP_API_BASE}/get_vote_status.php`, { params: { room_id } });
    const totalVotes = resStatus.data && resStatus.data.totalVotes != null ? resStatus.data.totalVotes : 0;
    const totalAlive = resStatus.data && resStatus.data.totalAlive != null ? resStatus.data.totalAlive : 0;

    broadcast(room_id, { type: 'vote_update', totalVotes, totalAlive });

    if (totalVotes === totalAlive) {
      broadcast(room_id, {
        type: 'voting_complete',
        message: 'Tous les joueurs ont voté.',
        redirect_url: `/api/results.php?room_id=${room_id}`
      });
    }

    return res.json({ success: true, totalVotes, totalAlive });
  } catch (err) {
    console.error('Erreur /internal/vote_submitted:', err.message);
    return res.status(500).json({ error: err.message });
  }
});

app.post('/internal/start_game', async (req, res) => {
  try {
    const { room_id, secret } = req.body || {};
    const ip = req.ip || req.socket.remoteAddress || '';
    const fromLocalhost = ip.includes('127.0.0.1') || ip.includes('::1') || req.hostname === 'localhost';

    if (secret !== NODE_SHARED_SECRET && !fromLocalhost) {
      return res.status(403).json({ error: 'Forbidden' });
    }
    if (!room_id) return res.status(400).json({ error: 'room_id manquant' });

    // Broadcast immédiat à tous les clients de la room : passe en phase 'game'
    broadcast(room_id, {
      type: 'start_game',
      message: 'La partie commence',
      redirect_url: `game.php?room_id=${room_id}`
    });

    console.log(`start_game broadcast pour room ${room_id}`);
    return res.json({ success: true });
  } catch (err) {
    console.error('Erreur /internal/start_game:', err.message);
    return res.status(500).json({ error: err.message });
  }
});


wss.on('connection', (ws) => {
  ws.on('message', async (msg) => {
    let data;
    try {
      data = JSON.parse(msg);
    } catch {
      ws.send(JSON.stringify({ type: 'error', message: 'Invalid JSON' }));
      return;
    }

    const { type, room_id, player_id } = data || {};

    try {
      if (type === 'join_room') {
        wsInfo.set(ws, { room_id, player_id });
        console.log('Client joined room', room_id, 'player', player_id);
        if (!rooms[room_id]) rooms[room_id] = new Set();
        rooms[room_id].add(ws);

        // annule fermeture programmée (si elle existe)
        cancelCloseTimer(room_id);

        await axios.post(`${PHP_API_BASE}/reconnect.php`, new URLSearchParams({ player_id, room_id }));

        const res = await axios.get(`${PHP_API_BASE}/get_players_in_room.php`, { params: { room_id } });
        const players = (res.data && res.data.players) ? res.data.players : [];
        broadcast(room_id, { type: 'update_players', players });

        // send current room settings to everyone in the room (so new clients get anonymize flag etc.)
        try {
          const resSettings = await axios.get(`${PHP_API_BASE}/get_room_setting.php`, { params: { room_id } });
          const settings = resSettings.data || {};
          // expect { turn_time_seconds: 30, anonymize_votes: 0/1, undercover_count: 1 }
          const payload = {};
          if (typeof settings.anonymize_votes !== 'undefined') payload.anonymize_votes = Number(settings.anonymize_votes) ? 1 : 0;
          if (typeof settings.undercover_count !== 'undefined') payload.undercover_count = Number(settings.undercover_count) || 1;
          if (Object.keys(payload).length > 0) {
            broadcast(room_id, { type: 'room_settings_updated', ...payload });
          }
          if (typeof settings.turn_time_seconds !== 'undefined') {
            broadcast(room_id, { type: 'turn_time_updated', turn_time_seconds: Number(settings.turn_time_seconds) });
          }
        } catch (err) {
          console.warn('Impossible de récupérer settings room:', err.message);
        }

        ws.send(JSON.stringify({ type: 'info', message: `Rejoint la room ${room_id}` }));
      }
      else if (type === 'send_hint') {
        // stoppe le timer du joueur courant
        clearRoomTimer(room_id);

        const res = await axios.post(`${PHP_API_BASE}/submit_hint.php`, new URLSearchParams(data));
        broadcast(room_id, { type: 'new_hint', player_id, hint: data.hint });

        const room_state = res.data && res.data.room_state ? res.data.room_state : null;
        const next_player = res.data && res.data.next_player ? res.data.next_player : null;

        if (room_state === 'voting') {
          broadcast(room_id, { type: 'start_voting', message: 'Phase de vote.' });
          clearRoomTimer(room_id);
        } else if (next_player) {
          broadcast(room_id, { type: 'update_turn', next_player });
          safeStartTurnTimer(room_id, next_player, { force: true });
        }
      }
      else if (type === 'get_game_state') {
        console.log('get_game_state reçu pour', room_id, player_id);
        try {
          const res = await axios.get(`${PHP_API_BASE}/get_game_state.php`, {
            params: { room_id, player_id }
          });

          ws.send(JSON.stringify({ type: 'game_state', ...(res.data || {}) }));

          const youAreTurn = !!(res.data && res.data.you_are_turn);
          const room_state = res.data && res.data.room_state ? res.data.room_state : null;

          if (room_state === 'voting') {
            clearRoomTimer(room_id);
          } else if (youAreTurn) {
            // Ne reset pas si déjà sur le bon joueur
            safeStartTurnTimer(room_id, player_id, { force: false });
          }
        } catch (err) {
          console.error('Erreur get_game_state:', err.message);
          ws.send(JSON.stringify({ type: 'error', message: 'Erreur get_game_state' }));
        }
      }
      else if (type === 'vote_submitted') {
        console.log(`Vote soumis par joueur ${player_id} dans la room ${room_id}`);
        try {
          const res = await axios.get(`${PHP_API_BASE}/get_vote_status.php`, { params: { room_id } });
          const totalVotes = res.data && res.data.totalVotes != null ? res.data.totalVotes : 0;
          const totalAlive = res.data && res.data.totalAlive != null ? res.data.totalAlive : 0;

          broadcast(room_id, { type: 'vote_update', totalVotes, totalAlive });

          if (totalVotes === totalAlive) {
            broadcast(room_id, {
              type: 'voting_complete',
              message: 'Tous les joueurs ont voté.',
              redirect_url: `results.php?room_id=${room_id}`
            });
          }
        } catch (err) {
          console.error('Erreur lors de la mise à jour des votes:', err.message);
        }
      }
    } catch (err) {
      console.error('Erreur traitement message:', err.message);
      ws.send(JSON.stringify({ type: 'error', message: 'Erreur serveur' }));
    }
  });

  ws.on('close', async () => {
    // on ne peut pas utiliser room_id/player_id ici directement (on les récupère depuis wsInfo)
    const info = wsInfo.get(ws);
    if (!info) {
      console.log('WebSocket fermé : info ws introuvable');
      return;
    }

    const { room_id, player_id } = info;
    console.log(`WebSocket fermé pour un client room : ${room_id}, player : ${player_id}`);

    // supprimer l'info immédiatement
    wsInfo.delete(ws);

    try {
      // 1) marquer déconnecté
      await axios.post(`${PHP_API_BASE}/disconnect.php`, new URLSearchParams({ player_id, room_id }));

      // 2) mettre à jour la liste clients + broadcast
      if (rooms[room_id]) {
        rooms[room_id].delete(ws);
        const resPlayers = await axios.get(`${PHP_API_BASE}/get_players_in_room.php`, { params: { room_id } });
        const players = (resPlayers.data && resPlayers.data.players) ? resPlayers.data.players : [];
        broadcast(room_id, { type: 'update_players', players });
      }

      // 3) programmé simple de fermeture si room vide (vérification immédiate + re-check après 15s)
      if (room_id && rooms[room_id] && rooms[room_id].size === 0) {
        scheduleSimpleClose(room_id);
      }

    } catch (err) {
      console.error('Erreur fermeture:', err.message);
    }
  });
});

async function getTurnMsForRoom(room_id) {
  // cache simple: 60s
  const now = Date.now();
  if (turnSettingsCache[room_id] && (now - turnSettingsCache[room_id].ts) < 10 * 1000) {
    return turnSettingsCache[room_id].ms;
  }

  try {
    const res = await axios.get(`${PHP_API_BASE}/get_room_setting.php`, { params: { room_id } });
    // get_room_settings.php doit renvoyer JSON { turn_time_seconds: 30 }
    const seconds = res.data && typeof res.data.turn_time_seconds !== 'undefined' ? parseInt(res.data.turn_time_seconds, 10) : null;
    const ms = (seconds && !isNaN(seconds)) ? seconds * 1000 : TURN_MS_DEFAULT;
    turnSettingsCache[room_id] = { ms, ts: Date.now() };
    return ms;
  } catch (err) {
    console.warn('Impossible de récupérer turn_time pour room', room_id, err.message);
    return TURN_MS_DEFAULT;
  }
}

// rend startTurnTimer async pour récupérer la durée par room
async function startTurnTimer(room_id, player_id, opts = { force: false }) {
  if (!opts.force && turnTimers[room_id] && turnForRoom[room_id] === player_id) return;

  clearRoomTimer(room_id);
  turnForRoom[room_id] = player_id;

  const ms = await getTurnMsForRoom(room_id);

  turnTimers[room_id] = setTimeout(async () => {
    console.log(`⏱ Temps écoulé pour joueur ${player_id} dans room ${room_id} (timeout ${ms}ms)`);
    try {
      const res = await axios.post(
        `${PHP_API_BASE}/submit_hint.php`,
        new URLSearchParams({ room_id, player_id, hint: '⏱ Temps écoulé' })
      );

      const next_player = res.data && res.data.next_player ? res.data.next_player : null;
      const room_state  = res.data && res.data.room_state ? res.data.room_state : null;

      broadcast(room_id, { type: 'new_hint', player_id, hint: '⏱ Temps écoulé' });

      if (room_state === 'voting') {
        broadcast(room_id, { type: 'start_voting', message: 'Phase de vote.' });
        clearRoomTimer(room_id);
        return;
      }

      if (next_player) {
        broadcast(room_id, { type: 'update_turn', next_player });
        // on force pour éviter double fetch inutile
        startTurnTimer(room_id, next_player, { force: true });
      } else {
        clearRoomTimer(room_id);
      }
    } catch (err) {
      console.error('Erreur timer serveur :', err.message);
      clearRoomTimer(room_id);
    }
  }, ms);
}
function safeStartTurnTimer(room_id, player_id, opts = { force: false }) {
  startTurnTimer(room_id, player_id, opts).catch(err => {
    console.error('Erreur startTurnTimer:', err);
  });
}
