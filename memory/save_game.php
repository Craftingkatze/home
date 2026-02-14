<?php
// save_game.php

// Error-Handling aktivieren für Debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Header setzen
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// JSON-Daten empfangen
$input = file_get_contents('php://input');

if (empty($input)) {
    echo json_encode(['success' => false, 'message' => 'Keine Daten empfangen']);
    exit;
}

$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'JSON Fehler: ' . json_last_error_msg()]);
    exit;
}

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Ungültige JSON Daten']);
    exit;
}

try {
    // Basis-Informationen aus den Daten
    $playerName = isset($data['summary']['playerName']) ? trim($data['summary']['playerName']) : 'Unbekannt';
    $sessionId = isset($data['summary']['sessionId']) ? $data['summary']['sessionId'] : 'unknown';
    $date = date('Y-m-d');
    $timestamp = date('Y-m-d H:i:s');
    
    // EINE Datei für alle Ergebnisse
    $filename = "memory_statistics.csv";
    $backupDir = "memory_backups/";
    
    // Verzeichnis erstellen, falls nicht existiert
    if (!file_exists($backupDir)) {
        if (!mkdir($backupDir, 0755, true)) {
            throw new Exception("Backup-Verzeichnis konnte nicht erstellt werden");
        }
    }
    
    // Sicherstellen, dass der Dateiname sicher ist
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
    
    // Zusammenfassung vorbereiten
    $summary = $data['summary'];
    
    // Sicherstellen, dass alle erforderlichen Felder vorhanden sind
    $requiredFields = [
        'playerName', 'sessionId', 'date', 'startTime', 'endTime',
        'gameDuration', 'totalRounds', 'successfulRounds', 'unsuccessfulRounds',
        'successRate', 'matchedPairs', 'gameCompleted'
    ];
    
    foreach ($requiredFields as $field) {
        if (!isset($summary[$field])) {
            throw new Exception("Fehlendes Feld in Zusammenfassung: " . $field);
        }
    }
    
    // CSV-Zeile für dieses Spiel erstellen
    $csvRow = sprintf(
        '"%s","%s","%s","%s","%s",%d,%d,%d,%d,%.2f,%d,"%s","%s"' . "\n",
        htmlspecialchars($playerName, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($sessionId, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($summary['date'], ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($summary['startTime'], ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($summary['endTime'], ENT_QUOTES, 'UTF-8'),
        intval($summary['gameDuration']),
        intval($summary['totalRounds']),
        intval($summary['successfulRounds']),
        intval($summary['unsuccessfulRounds']),
        floatval($summary['successRate']),
        intval($summary['matchedPairs']),
        $summary['gameCompleted'] ? 'Ja' : 'Nein',
        $timestamp
    );
    
    // Prüfen, ob Datei existiert und Header benötigt wird
    $needsHeader = !file_exists($filename) || filesize($filename) == 0;
    
    // In die Hauptdatei schreiben (anfügen)
    $fileHandle = @fopen($filename, 'a');
    
    if ($fileHandle === false) {
        throw new Exception("Datei konnte nicht geöffnet werden: $filename");
    }
    
    if ($needsHeader) {
        // Header schreiben
        $header = "Spieler,Session-ID,Spieldatum,Startzeit,Endzeit,Spieldauer(Sek),Gesamtrunden,Erfolgreiche,Erfolglose,Erfolgsrate(%),Gefundene_Paare,Abgeschlossen,Erfasst_am\n";
        if (fwrite($fileHandle, $header) === false) {
            fclose($fileHandle);
            throw new Exception("Header konnte nicht geschrieben werden");
        }
    }
    
    if (fwrite($fileHandle, $csvRow) === false) {
        fclose($fileHandle);
        throw new Exception("Daten konnten nicht geschrieben werden");
    }
    
    fclose($fileHandle);
    
    // Backup erstellen (separate Datei für dieses Spiel mit allen Details)
    $backupContent = "=== MEMORY SPIEL DETAILS ===\n";
    $backupContent .= "Spieler: " . $playerName . "\n";
    $backupContent .= "Session-ID: " . $sessionId . "\n";
    $backupContent .= "Datum: " . $date . "\n";
    $backupContent .= "Zeitstempel: " . $timestamp . "\n\n";
    
    $backupContent .= "=== SPIEL ZUSAMMENFASSUNG ===\n";
    $backupContent .= "Spieler,Session-ID,Datum,Startzeit,Endzeit,Spieldauer(Sek),Gesamtrunden,Erfolgreiche,Erfolglose,Erfolgsrate(%),Paare,Abgeschlossen\n";
    
    $backupContent .= sprintf(
        '"%s","%s","%s","%s","%s",%d,%d,%d,%d,%.2f,%d,"%s"' . "\n\n",
        htmlspecialchars($playerName, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($sessionId, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($summary['date'], ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($summary['startTime'], ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($summary['endTime'], ENT_QUOTES, 'UTF-8'),
        intval($summary['gameDuration']),
        intval($summary['totalRounds']),
        intval($summary['successfulRounds']),
        intval($summary['unsuccessfulRounds']),
        floatval($summary['successRate']),
        intval($summary['matchedPairs']),
        $summary['gameCompleted'] ? 'Ja' : 'Nein'
    );
    
    // Detaillierte Runden ins Backup (falls vorhanden)
    if (!empty($data['rounds']) && is_array($data['rounds'])) {
        $backupContent .= "=== DETAILLIERTE RUNDEN ===\n";
        $backupContent .= "Runde,Ergebnis,Karte1,Karte2,Gefundene_Paare,Verbleibende_Karten,Theoretische_P(Paar)%,Zeitstempel\n";
        
        foreach ($data['rounds'] as $round) {
            $backupContent .= sprintf(
                '%d,%s,"%s","%s",%d,%d,%.2f,"%s"' . "\n",
                intval($round['round'] ?? 0),
                htmlspecialchars($round['result'] ?? '', ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($round['card1'] ?? '', ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($round['card2'] ?? '', ENT_QUOTES, 'UTF-8'),
                intval($round['pairsFound'] ?? 0),
                intval($round['cardsRemaining'] ?? 0),
                floatval($round['theoreticalProb'] ?? 0),
                htmlspecialchars($round['timestamp'] ?? '', ENT_QUOTES, 'UTF-8')
            );
        }
    }
    
    // Backup-Datei speichern
    $backupFilename = $backupDir . "memory_" . date('Y-m-d_His') . "_" . preg_replace('/[^a-zA-Z0-9_-]/', '', $sessionId) . ".csv";
    
    if (file_put_contents($backupFilename, $backupContent) === false) {
        throw new Exception("Backup konnte nicht gespeichert werden");
    }
    
    // Erfolgreiche Antwort
    echo json_encode([
        'success' => true,
        'message' => 'Daten erfolgreich gespeichert',
        'filename' => $filename,
        'backupFile' => basename($backupFilename),
        'timestamp' => $timestamp,
        'gamesCount' => countGamesInFile($filename)
    ]);
    
} catch (Exception $e) {
    // Log error for debugging
    error_log("Memory Game Error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Fehler: ' . $e->getMessage(),
        'error_details' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}

// Hilfsfunktion: Anzahl der Spiele in der Datei zählen
function countGamesInFile($filename) {
    if (!file_exists($filename)) {
        return 0;
    }
    
    try {
        $lines = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if ($lines === false) {
            return 0;
        }
        
        $count = count($lines) - 1; // Minus Header
        
        // Falls noch kein Header existiert oder negative Zahl
        if ($count < 0) {
            $count = 0;
        }
        
        return $count;
    } catch (Exception $e) {
        return 0;
    }
}
?>