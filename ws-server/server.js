const express = require('express');
const cors = require('cors');
const WebSocket = require('ws');
const axios = require('axios');

const app = express();
app.use(cors());
app.use(express.urlencoded({ extended: true }));
app.use(express.json());

const PORT = 3001;
// Assure-toi que cette URL correspond bien à ton serveur web (Apache/Nginx)
const PHP_API_BASE = 'http://127.0.0.1/api'; 

const server = app.listen(PORT, '::');
const NODE_SHARED_SECRET = process.env.NODE_SHARED_SECRET || 'change_me_locally';
const wss = new WebSocket.Server({ server });

const rooms = {};          
const wsInfo = new Map();  
const turnTimers = {};     
const turnForRoom = {};    
const TURN_MS_DEFAULT = 30000; 
const turnSettingsCache = {}; 
const closeTimers = {}; 

// --- GESTION TIMERS DE FERMETURE (Nettoyage) ---

function cancelCloseTimer(room_id) {
  if (closeTimers[room_id]) {
    clearTimeout(closeTimers[room_id]);
    delete closeTimers[room_id];
  }
}

async function scheduleSimpleClose(room_id) {
  if (closeTimers[room_id]) return;
  try {
    const resPlayers = await axios.get(`${PHP_API_BASE}/get_players_in_room.php`, { params: { room_id } });
    const list = (resPlayers.data && resPlayers.data.players) ? resPlayers.data.players : [];
    const connectedPlayers = list.filter(p => Number(p.is_connected) === 1);
    
    // Si des joueurs sont encore connectés, on n'annule pas la room
    if (connectedPlayers.length > 0) return;

    console.log(`Room ${room_id} vide. Fermeture programmée dans 15s...`);

    closeTimers[room_id] = setTimeout(async () => {
      try {
        // Double check avant suppression finale
        const resPlayers2 = await axios.get(`${PHP_API_BASE}/get_players_in_room.php`, { params: { room_id } });
        const list2 = (resPlayers2.data && resPlayers2.data.players) ? resPlayers2.data.players : [];
        const connectedPlayers2 = list2.filter(p => Number(p.is_connected) === 1);

        if (connectedPlayers2.length === 0) {
          console.log(`Fermeture définitive de la room ${room_id}`);
          clearRoomTimer(room_id);
          if (rooms[room_id]) delete rooms[room_id];
          if (turnSettingsCache[room_id]) delete turnSettingsCache[room_id];
          // Appel à l'API PHP pour marquer la room comme fermée en BDD
          await axios.post(`${PHP_API_BASE}/close_room.php`, new URLSearchParams({ room_id }));
        }
      } catch (err) { console.error('Erreur close:', err.message); }
      finally {
        if (closeTimers[room_id]) { clearTimeout(closeTimers[room_id]); delete closeTimers[room_id]; }
      }
    }, 15000);
  } catch (err) { console.error('Erreur scheduleSimpleClose:', err.message); }
}

// --- UTILITAIRES ---

function broadcast(room_id, payload) {
  if (!rooms[room_id]) return;
  const message = JSON.stringify(payload);
  rooms[room_id].forEach(client => {
    if (client.readyState === WebSocket.OPEN) client.send(message);
  });
}

function clearRoomTimer(room_id) {
  if (turnTimers[room_id]) { 
    clearTimeout(turnTimers[room_id]); 
    delete turnTimers[room_id]; 
  }
  // On ne supprime pas turnForRoom ici pour permettre la vérification anti-race-condition
}

// --- ENDPOINTS HTTP (Appelés par PHP) ---

app.post('/internal/vote_submitted', async (req, res) => {
  try {
    const { room_id, secret } = req.body || {};
    
    // Vérification basique de sécurité (IP locale ou secret partagé)
    const ip = req.ip || req.socket.remoteAddress || '';
    const fromLocalhost = ip.includes('127.0.0.1') || ip.includes('::1') || req.hostname === 'localhost';
    if (secret !== NODE_SHARED_SECRET && !fromLocalhost) return res.status(403).json({ error: 'Forbidden' });

    const resStatus = await axios.get(`${PHP_API_BASE}/get_vote_status.php`, { params: { room_id } });
    const totalVotes = resStatus.data?.totalVotes ?? 0;
    const totalAlive = resStatus.data?.totalAlive ?? 0;

    broadcast(room_id, { type: 'vote_update', totalVotes, totalAlive });

    if (totalVotes >= totalAlive && totalAlive > 0) {
      // CORRECTIF URL : Chemin absolu vers l'API qui redirigera ensuite vers la vue publique
      broadcast(room_id, {
        type: 'voting_complete',
        message: 'Tous les joueurs ont voté.',
        redirect_url: `/api/results.php?room_id=${room_id}` 
      });
    }
    return res.json({ success: true, totalVotes, totalAlive });
  } catch (err) {
    console.error('Erreur vote_submitted:', err.message);
    return res.status(500).json({ error: err.message });
  }
});

app.post('/internal/start_game', async (req, res) => {
  const { room_id } = req.body || {};
  // URL relative propre : comme le joueur est dans /public/waiting_room.php, game.php est juste à côté
  broadcast(room_id, { type: 'start_game', redirect_url: `game.php?room_id=${room_id}` });
  return res.json({ success: true });
});

app.post('/internal/game_reset', (req, res) => {
  try {
    const { room_id } = req.body || {};
    if (!room_id) return res.status(400).json({ error: 'room_id manquant' });

    broadcast(room_id, {
      type: 'game_reset',
      message: 'La partie recommence !',
      redirect_url: `waiting_room.php?room_id=${room_id}`
    });
    
    clearRoomTimer(room_id);
    return res.json({ success: true });
  } catch (err) {
    return res.status(500).json({ error: err.message });
  }
});

app.post('/internal/invalidate_room_cache', (req, res) => {
    // ... (Code cache inchangé, fonctionnel)
    try {
      const { room_id, turn_time_seconds, anonymize_votes, undercover_count } = req.body || {};
      if (!room_id) return res.status(400).json({ error: 'room_id manquant' });
  
      if (typeof turn_time_seconds !== 'undefined' && turn_time_seconds !== null) {
        const sec = parseInt(turn_time_seconds, 10);
        const ms = (!isNaN(sec) && sec > 0) ? sec * 1000 : TURN_MS_DEFAULT;
        turnSettingsCache[room_id] = { ms, ts: Date.now() };
        broadcast(room_id, { type: 'turn_time_updated', turn_time_seconds: sec });
      }
      
      const roomSettingsPayload = {};
      if (typeof anonymize_votes !== 'undefined' && anonymize_votes !== null) roomSettingsPayload.anonymize_votes = parseInt(anonymize_votes, 10) ? 1 : 0;
      if (typeof undercover_count !== 'undefined' && undercover_count !== null) roomSettingsPayload.undercover_count = parseInt(undercover_count, 10) || 1;
  
      if (Object.keys(roomSettingsPayload).length > 0) {
        broadcast(room_id, { type: 'room_settings_updated', ...roomSettingsPayload });
      }
      return res.json({ success: true });
    } catch (err) { return res.status(500).json({ error: err.message }); }
});

// --- WEBSOCKET ---

wss.on('connection', (ws) => {
  ws.on('message', async (msg) => {
    let data;
    try { data = JSON.parse(msg); } catch { return; }
    const { type, room_id, player_id } = data || {};

    if (type === 'join_room') {
      wsInfo.set(ws, { room_id, player_id });
      if (!rooms[room_id]) rooms[room_id] = new Set();
      rooms[room_id].add(ws);
      cancelCloseTimer(room_id);
      
      // Reconnexion PHP
      await axios.post(`${PHP_API_BASE}/reconnect.php`, new URLSearchParams({ player_id, room_id }));
      
      // Update players
      const res = await axios.get(`${PHP_API_BASE}/get_players_in_room.php`, { params: { room_id } });
      const players = (res.data && res.data.players) ? res.data.players : [];
      broadcast(room_id, { type: 'update_players', players });
    }
    
    else if (type === 'send_hint') {
        // L'utilisateur envoie un hint manuellement
        clearRoomTimer(room_id); // On coupe le timer Node immédiatement

        // SÉCURITÉ : On récupère l'ID via le socket (trust) plutôt que le message
        const info = wsInfo.get(ws);
        const trustedPlayerId = info ? info.player_id : player_id;
        
        try {
            // CORRECTION: Ajout du "secret" pour passer le check PHP
            const payload = {
                room_id: room_id,
                player_id: trustedPlayerId,
                hint: data.hint,
                secret: NODE_SHARED_SECRET
            };

            const res = await axios.post(`${PHP_API_BASE}/submit_hint.php`, new URLSearchParams(payload));
            
            // On diffuse le hint seulement si PHP a validé
            broadcast(room_id, { type: 'new_hint', player_id: trustedPlayerId, hint: data.hint });

            const room_state = res.data?.room_state;
            const next_player = res.data?.next_player;

            if (room_state === 'voting') {
              broadcast(room_id, { type: 'start_voting', message: 'Phase de vote.' });
              clearRoomTimer(room_id);
            } else if (next_player) {
              broadcast(room_id, { type: 'update_turn', next_player });
              safeStartTurnTimer(room_id, next_player, { force: true });
            }
        } catch (e) {
            console.error("Erreur submit_hint:", e.message);
            // Optionnel : notifier le client d'une erreur
        }
    }
    
    else if (type === 'get_game_state') {
        try {
            const res = await axios.get(`${PHP_API_BASE}/get_game_state.php`, { params: { room_id, player_id } });
            ws.send(JSON.stringify({ type: 'game_state', ...(res.data || {}) }));
            
            if (res.data && res.data.you_are_turn) {
                // Si le joueur se reconnecte et que c'est son tour, on relance/vérifie le timer
                safeStartTurnTimer(room_id, player_id);
            }
        } catch (e) { console.error("Erreur get_game_state", e.message); }
    }
  });

  ws.on('close', async () => {
    const info = wsInfo.get(ws);
    if (!info) return;
    const { room_id, player_id } = info;
    wsInfo.delete(ws);

    try {
        await axios.post(`${PHP_API_BASE}/disconnect.php`, new URLSearchParams({ player_id, room_id }));
        if (rooms[room_id]) {
            rooms[room_id].delete(ws);
            const resPlayers = await axios.get(`${PHP_API_BASE}/get_players_in_room.php`, { params: { room_id } });
            broadcast(room_id, { type: 'update_players', players: resPlayers.data?.players || [] });
            
            if (rooms[room_id].size === 0) scheduleSimpleClose(room_id);
        }
    } catch (e) { console.error(e.message); }
  });
});

// --- GESTION DU TEMPS DE TOUR ---

async function getTurnMsForRoom(room_id) {
    if (turnSettingsCache[room_id] && (Date.now() - turnSettingsCache[room_id].ts) < 10000) {
      return turnSettingsCache[room_id].ms;
    }
    try {
      const res = await axios.get(`${PHP_API_BASE}/get_room_setting.php`, { params: { room_id } });
      const sec = parseInt(res.data?.turn_time_seconds, 10);
      const ms = (sec && !isNaN(sec)) ? sec * 1000 : TURN_MS_DEFAULT;
      turnSettingsCache[room_id] = { ms, ts: Date.now() };
      return ms;
    } catch { return TURN_MS_DEFAULT; }
}

async function startTurnTimer(room_id, player_id, opts = { force: false }) {
    // Si un timer tourne déjà pour ce joueur, on ne fait rien (sauf si force=true)
    if (!opts.force && turnTimers[room_id] && turnForRoom[room_id] === player_id) return;
  
    clearRoomTimer(room_id);
    turnForRoom[room_id] = player_id; // On note à qui est le tour
    const ms = await getTurnMsForRoom(room_id);
  
    console.log(`⏳ Timer lancé room ${room_id} pour joueur ${player_id} (${ms}ms)`);

    turnTimers[room_id] = setTimeout(async () => {
      // CORRECTIF RACE CONDITION :
      // On vérifie si c'est TOUJOURS le tour de ce joueur.
      // Si entre temps PHP a changé le tour, turnForRoom aura changé.
      if (turnForRoom[room_id] !== player_id) {
          console.log(`🛑 Timer annulé room ${room_id} : le tour a changé.`);
          return; 
      }

      console.log(`⏱ Temps écoulé room ${room_id} pour joueur ${player_id}`);
      
      try {
        // CORRECTION: Ajout du secret ici aussi
        const payload = { 
            room_id, 
            player_id, 
            hint: '⏱ Temps écoulé',
            secret: NODE_SHARED_SECRET
        };
        const res = await axios.post(`${PHP_API_BASE}/submit_hint.php`, new URLSearchParams(payload));
        broadcast(room_id, { type: 'new_hint', player_id, hint: '⏱ Temps écoulé' });
  
        if (res.data?.room_state === 'voting') {
          broadcast(room_id, { type: 'start_voting' });
          clearRoomTimer(room_id);
        } else if (res.data?.next_player) {
          broadcast(room_id, { type: 'update_turn', next_player: res.data.next_player });
          startTurnTimer(room_id, res.data.next_player, { force: true });
        }
      } catch (err) { 
          console.error("Erreur timeout submit:", err.message);
          clearRoomTimer(room_id); 
      }
    }, ms);
}
  
function safeStartTurnTimer(room_id, player_id, opts) {
    startTurnTimer(room_id, player_id, opts).catch(console.error);
}