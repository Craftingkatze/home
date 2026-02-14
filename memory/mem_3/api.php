<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Datenverzeichnis
$dataDir = __DIR__ . '/game_data';
if (!file_exists($dataDir)) {
    mkdir($dataDir, 0777, true);
}

// Alte Lobbies löschen (älter als 1 Stunde)
function cleanOldLobbies() {
    global $dataDir;
    $files = glob($dataDir . '/*.json');
    $now = time();
    foreach ($files as $file) {
        if ($now - filemtime($file) > 3600) {
            unlink($file);
        }
    }
}

cleanOldLobbies();

// Request verarbeiten
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

function getLobbyFile($code) {
    global $dataDir;
    return $dataDir . '/' . preg_replace('/[^A-Z0-9]/', '', $code) . '.json';
}

function loadLobby($code) {
    $file = getLobbyFile($code);
    if (file_exists($file)) {
        return json_decode(file_get_contents($file), true);
    }
    return null;
}

function saveLobby($code, $data) {
    $file = getLobbyFile($code);
    file_put_contents($file, json_encode($data));
    return true;
}

// Actions
switch ($action) {
    case 'createLobby':
        $lobbyCode = $input['lobbyCode'];
        $lobby = [
            'lobbyCode' => $lobbyCode,
            'hostId' => $input['hostId'],
            'players' => [],
            'gameStarted' => false,
            'gameEnded' => false,
            'timeRemaining' => 180,
            'startTime' => null
        ];
        saveLobby($lobbyCode, $lobby);
        echo json_encode(['success' => true, 'lobby' => $lobby]);
        break;

    case 'joinLobby':
        $lobbyCode = $input['lobbyCode'];
        $lobby = loadLobby($lobbyCode);
        
        if (!$lobby) {
            echo json_encode(['success' => false, 'error' => 'Lobby not found']);
            break;
        }

        $lobby['players'][$input['playerId']] = [
            'name' => $input['playerName'],
            'credits' => 20,
            'isHost' => false
        ];
        
        saveLobby($lobbyCode, $lobby);
        echo json_encode(['success' => true, 'lobby' => $lobby]);
        break;

    case 'getLobby':
        $lobbyCode = $input['lobbyCode'];
        $lobby = loadLobby($lobbyCode);
        
        if (!$lobby) {
            echo json_encode(['success' => false, 'error' => 'Lobby not found']);
            break;
        }

        // Update timer
        if ($lobby['gameStarted'] && !$lobby['gameEnded'] && $lobby['startTime']) {
            $elapsed = time() - $lobby['startTime'];
            $lobby['timeRemaining'] = max(0, 180 - $elapsed);
            
            if ($lobby['timeRemaining'] <= 0) {
                $lobby['gameEnded'] = true;
                saveLobby($lobbyCode, $lobby);
            }
        }

        echo json_encode(['success' => true, 'lobby' => $lobby]);
        break;

    case 'startGame':
        $lobbyCode = $input['lobbyCode'];
        $lobby = loadLobby($lobbyCode);
        
        if ($lobby) {
            $lobby['gameStarted'] = true;
            $lobby['startTime'] = time();
            $lobby['timeRemaining'] = 180;
            saveLobby($lobbyCode, $lobby);
            echo json_encode(['success' => true, 'lobby' => $lobby]);
        } else {
            echo json_encode(['success' => false]);
        }
        break;

    case 'endGame':
        $lobbyCode = $input['lobbyCode'];
        $lobby = loadLobby($lobbyCode);
        
        if ($lobby) {
            $lobby['gameEnded'] = true;
            saveLobby($lobbyCode, $lobby);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
        break;

    case 'updateCredits':
        $lobbyCode = $input['lobbyCode'];
        $lobby = loadLobby($lobbyCode);
        
        if ($lobby && isset($lobby['players'][$input['playerId']])) {
            $lobby['players'][$input['playerId']]['credits'] = $input['credits'];
            saveLobby($lobbyCode, $lobby);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
        break;

    case 'leaveLobby':
        $lobbyCode = $input['lobbyCode'];
        $lobby = loadLobby($lobbyCode);
        
        if ($lobby) {
            unset($lobby['players'][$input['playerId']]);
            saveLobby($lobbyCode, $lobby);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
        break;

    case 'deleteLobby':
        $lobbyCode = $input['lobbyCode'];
        $file = getLobbyFile($lobbyCode);
        if (file_exists($file)) {
            unlink($file);
        }
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}
?>