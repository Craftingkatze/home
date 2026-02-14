<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Datenverzeichnis erstellen falls nicht vorhanden
$dataDir = __DIR__ . '/game_data';
if (!file_exists($dataDir)) {
    mkdir($dataDir, 0777, true);
}

$sessionsFile = $dataDir . '/sessions.json';
$movesFile = $dataDir . '/moves.json';
$leaderboardFile = $dataDir . '/leaderboard.json';

// JSON Daten laden
function loadJSON($file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        return json_decode($content, true) ?: [];
    }
    return [];
}

// JSON Daten speichern
function saveJSON($file, $data) {
    return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

// POST Daten empfangen
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['action'])) {
    echo json_encode(['success' => false, 'message' => 'Keine Action angegeben']);
    exit;
}

$action = $data['action'];
$response = ['success' => false];

switch ($action) {
    case 'create_session':
        // Neue Spielsession erstellen
        $sessions = loadJSON($sessionsFile);
        
        // Check if session already exists with different host
        if (isset($sessions[$data['gameCode']])) {
            $response = [
                'success' => false,
                'message' => 'Spielcode bereits vergeben'
            ];
            break;
        }
        
        $initialPlayers = [];
        if (!isset($data['hostOnlyWatch']) || !$data['hostOnlyWatch']) {
            $initialPlayers = [
                [
                    'id' => $data['hostId'],
                    'name' => $data['hostName'],
                    'score' => 0,
                    'clicks' => 0,
                    'finished' => false,
                    'finishTime' => null,
                    'online' => true,
                    'lastActivity' => $data['timestamp'],
                    'joinedAt' => $data['timestamp']
                ]
            ];
        }
        
        // Generate card layout
        $cardLayout = generateCardLayout($data['gameCode']);
        
        $session = [
            'gameCode' => $data['gameCode'],
            'hostId' => $data['hostId'],
            'hostName' => $data['hostName'],
            'playMode' => $data['playMode'] ?? 'turns',
            'hostOnlyWatch' => $data['hostOnlyWatch'] ?? false,
            'createdAt' => $data['timestamp'],
            'players' => $initialPlayers,
            'currentPlayerIndex' => 0,
            'matchedPairs' => 0,
            'gameStarted' => false,
            'gameFinished' => false,
            'status' => 'waiting',
            'cardLayout' => $cardLayout,
            'lastActivity' => $data['timestamp']
        ];
        
        $sessions[$data['gameCode']] = $session;
        saveJSON($sessionsFile, $sessions);
        
        $response = [
            'success' => true,
            'message' => 'Session erstellt',
            'gameCode' => $data['gameCode'],
            'cardLayout' => $cardLayout
        ];
        break;
        
    case 'start_game':
        // Spiel starten
        $sessions = loadJSON($sessionsFile);
        
        if (isset($sessions[$data['gameCode']])) {
            $session = &$sessions[$data['gameCode']];
            
            // Nur Host kann Spiel starten
            if ($session['hostId'] !== $data['playerId']) {
                $response = [
                    'success' => false,
                    'message' => 'Nur der Host kann das Spiel starten'
                ];
                break;
            }
            
            // Mindestens 1 Spieler benötigt (außer hostOnlyWatch)
            if (empty($session['players']) && !$session['hostOnlyWatch']) {
                $response = [
                    'success' => false,
                    'message' => 'Keine Spieler im Spiel'
                ];
                break;
            }
            
            $session['gameStarted'] = true;
            $session['status'] = 'playing';
            $session['gameStartTime'] = $data['timestamp'];
            $session['lastActivity'] = $data['timestamp'];
            
            // Reset alle Spieler für neues Spiel
            foreach ($session['players'] as &$player) {
                $player['score'] = 0;
                $player['clicks'] = 0;
                $player['finished'] = false;
                $player['finishTime'] = null;
                $player['online'] = true;
                $player['lastActivity'] = $data['timestamp'];
            }
            
            saveJSON($sessionsFile, $sessions);
            
            $response = [
                'success' => true,
                'message' => 'Spiel gestartet',
                'cardLayout' => $session['cardLayout']
            ];
        } else {
            $response = [
                'success' => false,
                'message' => 'Spiel nicht gefunden'
            ];
        }
        break;
        
    case 'end_game':
        // Spiel beenden und auswerten
        $sessions = loadJSON($sessionsFile);
        
        if (isset($sessions[$data['gameCode']])) {
            $session = &$sessions[$data['gameCode']];
            
            // Nur Host kann Spiel beenden
            if ($session['hostId'] !== $data['playerId']) {
                $response = [
                    'success' => false,
                    'message' => 'Nur der Host kann das Spiel beenden'
                ];
                break;
            }
            
            $session['gameFinished'] = true;
            $session['status'] = 'finished';
            $session['gameEndTime'] = $data['timestamp'];
            $session['lastActivity'] = $data['timestamp'];
            
            // Auswertung basierend auf Paaren und Klicks
            $players = $session['players'];
            foreach ($players as &$player) {
                if (!isset($player['clicks'])) {
                    $player['clicks'] = 0;
                }
                // Berechne Effizienz: Paare pro Klick (höher ist besser)
                $player['efficiency'] = $player['clicks'] > 0 ? $player['score'] / $player['clicks'] : 0;
            }
            
            // Sortiere Spieler: 1. Paare (höher), 2. Effizienz (höher), 3. Klicks (niedriger)
            usort($players, function($a, $b) {
                if ($b['score'] !== $a['score']) {
                    return $b['score'] - $a['score'];
                }
                if ($b['efficiency'] !== $a['efficiency']) {
                    return $b['efficiency'] - $a['efficiency'];
                }
                return $a['clicks'] - $b['clicks'];
            });
            
            $session['players'] = $players;
            $session['finalRanking'] = $players;
            
            // Update permanent leaderboard
            updateLeaderboard($players, $data['gameCode']);
            
            saveJSON($sessionsFile, $sessions);
            
            $response = [
                'success' => true,
                'message' => 'Spiel beendet',
                'finalRanking' => $players
            ];
        } else {
            $response = [
                'success' => false,
                'message' => 'Spiel nicht gefunden'
            ];
        }
        break;
        
    case 'join_session':
        // Einem Spiel beitreten
        $sessions = loadJSON($sessionsFile);
        
        if (isset($sessions[$data['gameCode']])) {
            $session = &$sessions[$data['gameCode']];
            
            // Prüfe ob Spiel bereits beendet
            if ($session['gameFinished']) {
                $response = [
                    'success' => false,
                    'message' => 'Spiel bereits beendet'
                ];
                break;
            }
            
            // Prüfen ob Spieler bereits dabei ist
            $playerExists = false;
            $playerIndex = -1;
            foreach ($session['players'] as $index => $player) {
                if ($player['id'] === $data['playerId']) {
                    $playerExists = true;
                    $playerIndex = $index;
                    break;
                }
            }
            
            if (!$playerExists) {
                // Prüfe ob Spiel bereits gestartet - wenn ja, kann man nicht mehr joinen
                if ($session['gameStarted']) {
                    $response = [
                        'success' => false,
                        'message' => 'Spiel bereits gestartet - Beitritt nicht mehr möglich'
                    ];
                    break;
                }
                
                $session['players'][] = [
                    'id' => $data['playerId'],
                    'name' => $data['playerName'],
                    'score' => 0,
                    'clicks' => 0,
                    'finished' => false,
                    'finishTime' => null,
                    'online' => true,
                    'lastActivity' => $data['timestamp'],
                    'joinedAt' => $data['timestamp']
                ];
            } else {
                // Spieler existiert bereits - Rejoin
                if ($playerIndex >= 0) {
                    $session['players'][$playerIndex]['online'] = true;
                    $session['players'][$playerIndex]['lastActivity'] = $data['timestamp'];
                }
            }
            
            $session['lastActivity'] = $data['timestamp'];
            saveJSON($sessionsFile, $sessions);
            
            $response = [
                'success' => true,
                'message' => 'Spiel beigetreten',
                'players' => $session['players'],
                'playMode' => $session['playMode'] ?? 'turns',
                'gameStarted' => $session['gameStarted'] ?? false,
                'cardLayout' => $session['cardLayout'] ?? null,
                'hostOnlyWatch' => $session['hostOnlyWatch'] ?? false,
                'isHost' => ($session['hostId'] === $data['playerId'])
            ];
        } else {
            $response = [
                'success' => false,
                'message' => 'Spiel nicht gefunden'
            ];
        }
        break;
        
    case 'save_move':
        // Spielzug speichern
        $moves = loadJSON($movesFile);
        $sessions = loadJSON($sessionsFile);
        
        if (!isset($sessions[$data['gameCode']])) {
            $response = [
                'success' => false,
                'message' => 'Spiel nicht gefunden'
            ];
            break;
        }
        
        $session = &$sessions[$data['gameCode']];
        
        // Prüfe ob Spiel läuft
        if (!$session['gameStarted'] || $session['gameFinished']) {
            $response = [
                'success' => false,
                'message' => 'Spiel ist nicht aktiv'
            ];
            break;
        }
        
        // Prüfe ob Spieler im Spiel ist
        $playerFound = false;
        foreach ($session['players'] as &$player) {
            if ($player['id'] === $data['playerId']) {
                $playerFound = true;
                break;
            }
        }
        
        if (!$playerFound) {
            $response = [
                'success' => false,
                'message' => 'Spieler nicht im Spiel'
            ];
            break;
        }
        
        $move = [
            'gameCode' => $data['gameCode'],
            'playerId' => $data['playerId'],
            'playerName' => $data['playerName'],
            'success' => $data['success'],
            'matchedPairs' => $data['matchedPairs'],
            'remainingCards' => $data['remainingCards'],
            'clicks' => $data['clicks'] ?? 0,
            'finished' => $data['finished'] ?? false,
            'finishTime' => $data['finishTime'] ?? null,
            'timestamp' => $data['timestamp']
        ];
        
        if (!isset($moves[$data['gameCode']])) {
            $moves[$data['gameCode']] = [];
        }
        $moves[$data['gameCode']][] = $move;
        
        saveJSON($movesFile, $moves);
        
        // Session State aktualisieren
        foreach ($session['players'] as &$player) {
            if ($player['id'] === $data['playerId']) {
                $player['score'] = $data['matchedPairs'];
                $player['clicks'] = $data['clicks'] ?? $player['clicks'];
                $player['finished'] = $data['finished'] ?? $player['finished'];
                $player['finishTime'] = $data['finishTime'] ?? $player['finishTime'];
                $player['online'] = true;
                $player['lastActivity'] = $data['timestamp'];
                break;
            }
        }
        
        $session['currentPlayerIndex'] = $data['currentPlayerIndex'] ?? 0;
        $session['lastUpdate'] = $data['timestamp'];
        $session['lastActivity'] = $data['timestamp'];
        
        saveJSON($sessionsFile, $sessions);
        
        $response = [
            'success' => true,
            'message' => 'Zug gespeichert'
        ];
        break;
        
    case 'get_state':
        // Aktuellen Spielstand abrufen
        $sessions = loadJSON($sessionsFile);
        
        if (isset($sessions[$data['gameCode']])) {
            $session = &$sessions[$data['gameCode']];
            
            // Markiere inaktive Spieler als offline (wenn länger als 30 Sekunden kein Update)
            $currentTime = strtotime($data['timestamp'] ?? date('c'));
            foreach ($session['players'] as &$player) {
                if (isset($player['lastActivity'])) {
                    $lastActivity = strtotime($player['lastActivity']);
                    if ($currentTime - $lastActivity > 30) {
                        $player['online'] = false;
                    }
                } else {
                    $player['online'] = false;
                }
            }
            
            // Clean up very old sessions (older than 24 hours)
            if (isset($session['createdAt'])) {
                $createdTime = strtotime($session['createdAt']);
                if ($currentTime - $createdTime > 86400) { // 24 hours
                    unset($sessions[$data['gameCode']]);
                    saveJSON($sessionsFile, $sessions);
                    $response = [
                        'success' => false,
                        'message' => 'Session abgelaufen'
                    ];
                    break;
                }
            }
            
            // Update session last activity
            $session['lastActivity'] = date('c', $currentTime);
            saveJSON($sessionsFile, $sessions);
            
            $response = [
                'success' => true,
                'state' => $session
            ];
        } else {
            $response = [
                'success' => false,
                'message' => 'Spiel nicht gefunden'
            ];
        }
        break;
        
    case 'get_leaderboard':
        // Leaderboard abrufen
        $leaderboard = loadJSON($leaderboardFile);
        
        // Sortiere nach Gewinnrate und Anzahl Spiele
        usort($leaderboard, function($a, $b) {
            if ($a['winRate'] === $b['winRate']) {
                return $b['gamesPlayed'] - $a['gamesPlayed'];
            }
            return $b['winRate'] - $a['winRate'];
        });
        
        $response = [
            'success' => true,
            'leaderboard' => array_slice($leaderboard, 0, 50) // Top 50
        ];
        break;
        
    case 'update_activity':
        // Spieleraktivität aktualisieren
        $sessions = loadJSON($sessionsFile);
        
        if (isset($sessions[$data['gameCode']])) {
            $session = &$sessions[$data['gameCode']];
            $session['lastActivity'] = $data['timestamp'];
            
            foreach ($session['players'] as &$player) {
                if ($player['id'] === $data['playerId']) {
                    $player['lastActivity'] = $data['timestamp'];
                    $player['online'] = true;
                    break;
                }
            }
            
            saveJSON($sessionsFile, $sessions);
            
            $response = ['success' => true];
        } else {
            $response = ['success' => false, 'message' => 'Spiel nicht gefunden'];
        }
        break;
        
    case 'rejoin_session':
        // Session wieder beitreten
        $sessions = loadJSON($sessionsFile);
        
        if (isset($sessions[$data['gameCode']])) {
            $session = &$sessions[$data['gameCode']];
            
            // Find player
            $playerFound = false;
            foreach ($session['players'] as &$player) {
                if ($player['id'] === $data['playerId']) {
                    $player['online'] = true;
                    $player['lastActivity'] = $data['timestamp'];
                    $playerFound = true;
                    break;
                }
            }
            
            if (!$playerFound) {
                // Player not in session, try to join normally
                if (!$session['gameStarted']) {
                    $session['players'][] = [
                        'id' => $data['playerId'],
                        'name' => $data['playerName'],
                        'score' => 0,
                        'clicks' => 0,
                        'finished' => false,
                        'finishTime' => null,
                        'online' => true,
                        'lastActivity' => $data['timestamp'],
                        'joinedAt' => $data['timestamp']
                    ];
                } else {
                    $response = [
                        'success' => false,
                        'message' => 'Spieler nicht gefunden und Spiel bereits gestartet'
                    ];
                    break;
                }
            }
            
            $session['lastActivity'] = $data['timestamp'];
            saveJSON($sessionsFile, $sessions);
            
            $response = [
                'success' => true,
                'message' => 'Wieder beigetreten',
                'players' => $session['players'],
                'playMode' => $session['playMode'] ?? 'turns',
                'gameStarted' => $session['gameStarted'] ?? false,
                'cardLayout' => $session['cardLayout'] ?? null,
                'hostOnlyWatch' => $session['hostOnlyWatch'] ?? false,
                'isHost' => ($session['hostId'] === $data['playerId'])
            ];
        } else {
            $response = [
                'success' => false,
                'message' => 'Spiel nicht gefunden'
            ];
        }
        break;
        
    default:
        $response = [
            'success' => false,
            'message' => 'Unbekannte Action: ' . $action
        ];
}

// Kartenlayout generieren
function generateCardLayout($gameCode) {
    $emojis = ['🎮', '🎯', '🎨', '🎭', '🎪', '🎸', '🎹', '🎺', '🎻', '🎲', '🎰', '🎳', '🏀', '⚽', '🏈', '⚾', '🎾', '🏐', '🏉', '🎱', '🏓', '🏸', '🏒', '🏑', '🥊', '🥋', '🎿', '🛹', '🎯', '🎮', '🎪', '🎨'];
    
    $cardPairs = [];
    for ($i = 0; $i < 32; $i++) {
        $cardPairs[] = $emojis[$i];
        $cardPairs[] = $emojis[$i];
    }
    
    // Use gameCode as seed for consistent shuffling
    $seed = crc32($gameCode);
    srand($seed);
    
    // Fisher-Yates shuffle
    for ($i = count($cardPairs) - 1; $i > 0; $i--) {
        $j = rand(0, $i);
        $temp = $cardPairs[$i];
        $cardPairs[$i] = $cardPairs[$j];
        $cardPairs[$j] = $temp;
    }
    
    return $cardPairs;
}

// Leaderboard aktualisieren
function updateLeaderboard($players, $gameCode) {
    global $leaderboardFile;
    $leaderboard = loadJSON($leaderboardFile);
    
    // Sort by pairs found (descending), then by clicks (ascending)
    usort($players, function($a, $b) {
        if ($b['score'] !== $a['score']) {
            return $b['score'] - $a['score'];
        }
        return $a['clicks'] - $b['clicks'];
    });
    
    $winner = $players[0];
    
    // Aktualisiere Stats für jeden Spieler
    foreach ($players as $index => $player) {
        $playerName = $player['name'];
        $isWinner = ($index === 0);
        
        // Finde Spieler in Leaderboard
        $playerIndex = -1;
        foreach ($leaderboard as $idx => $entry) {
            if ($entry['name'] === $playerName) {
                $playerIndex = $idx;
                break;
            }
        }
        
        if ($playerIndex >= 0) {
            // Update existierender Spieler
            $leaderboard[$playerIndex]['gamesPlayed']++;
            $leaderboard[$playerIndex]['totalPairs'] += $player['score'];
            $leaderboard[$playerIndex]['totalClicks'] = ($leaderboard[$playerIndex]['totalClicks'] ?? 0) + $player['clicks'];
            if ($isWinner) {
                $leaderboard[$playerIndex]['wins']++;
            }
            
            $leaderboard[$playerIndex]['winRate'] = 
                ($leaderboard[$playerIndex]['wins'] / $leaderboard[$playerIndex]['gamesPlayed']) * 100;
            $leaderboard[$playerIndex]['avgPairs'] = 
                $leaderboard[$playerIndex]['totalPairs'] / $leaderboard[$playerIndex]['gamesPlayed'];
            $leaderboard[$playerIndex]['avgClicks'] = 
                $leaderboard[$playerIndex]['totalClicks'] / $leaderboard[$playerIndex]['gamesPlayed'];
            $leaderboard[$playerIndex]['lastPlayed'] = date('Y-m-d H:i:s');
        } else {
            // Neuer Spieler
            $leaderboard[] = [
                'name' => $playerName,
                'gamesPlayed' => 1,
                'wins' => $isWinner ? 1 : 0,
                'totalPairs' => $player['score'],
                'totalClicks' => $player['clicks'],
                'winRate' => $isWinner ? 100 : 0,
                'avgPairs' => $player['score'],
                'avgClicks' => $player['clicks'],
                'lastPlayed' => date('Y-m-d H:i:s')
            ];
        }
    }
    
    saveJSON($leaderboardFile, $leaderboard);
}

echo json_encode($response);
?>