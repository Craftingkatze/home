<?php
// webhook_receiver_form.php
$config = [
    'tsv_file' => 'form_responses.tsv',
    'json_file' => 'form_responses.json',
    'max_file_size' => 10 * 1024 * 1024,
    'log_errors' => true
];

function log_error($message) {
    error_log(date('Y-m-d H:i:s') . " - Webhook Error: " . $message . "\n", 3, 'webhook_errors.log');
}

function log_access($message) {
    error_log(date('Y-m-d H:i:s') . " - Webhook Access: " . $message . "\n", 3, 'webhook_access.log');
}

// Funktion zum Extrahieren der Feldwerte basierend auf Label-Keywords
function extract_field_values($fields) {
    $field_values = [
        'email' => '',
        'name' => '',
        'ingame_name' => '',
        'skill' => '',
        'rating_previous' => '',
        'zag' => '',
        'x' => '',
        'y' => ''
    ];
    
    foreach ($fields as $field) {
        $label = $field['label'] ?? '';
        $value = $field['value'] ?? '';
        
        // Email-Feld (enthält "3€" oder "Email")
        if (strpos($label, '3€') !== false || stripos($label, 'email') !== false) {
            $field_values['email'] = $value;
        }
        // Name-Feld
        elseif (trim($label) === 'Name' || strpos($label, 'Name') !== false) {
            $field_values['name'] = $value;
        }
        // Ingame Name-Feld
        elseif (strpos($label, 'Ingame Name') !== false || strpos($label, 'Ingame') !== false) {
            $field_values['ingame_name'] = $value;
        }
        // Skill-Feld
        elseif (strpos($label, 'Minecraft Skill') !== false || strpos($label, 'Skill') !== false) {
            $field_values['skill'] = $value;
        }
        // Rating Previous-Feld
        elseif (strpos($label, 'Scuffed Attack 1') !== false || stripos($label, 'wie gut') !== false) {
            $field_values['rating_previous'] = $value;
        }
        // ZAG-Feld (falls vorhanden)
        elseif (stripos($label, 'zag') !== false) {
            $field_values['zag'] = $value;
        }
        // X-Koordinate (falls vorhanden)
        elseif (trim($label) === 'X' || stripos($label, 'x coordinate') !== false) {
            $field_values['x'] = $value;
        }
        // Y-Koordinate (falls vorhanden)
        elseif (trim($label) === 'Y' || stripos($label, 'y coordinate') !== false) {
            $field_values['y'] = $value;
        }
    }
    
    return $field_values;
}

// Funktion zum Formatieren der Daten im exakten gewünschten Format
function format_form_data($webhook_data) {
    $fields = $webhook_data['data']['fields'] ?? [];
    $field_values = extract_field_values($fields);
    
    // Datum formatieren: von "2025-10-26T14:26:41.000Z" zu "2025-10-26 14:26:41"
    $created_at = $webhook_data['data']['createdAt'] ?? '';
    if (!empty($created_at)) {
        $datetime = DateTime::createFromFormat('Y-m-d\TH:i:s.v\Z', $created_at);
        if ($datetime) {
            $formatted_date = $datetime->format('Y-m-d H:i:s');
        } else {
            // Fallback falls Format nicht passt
            $formatted_date = date('Y-m-d H:i:s', strtotime($created_at));
        }
    } else {
        $formatted_date = '';
    }
    
    // Exaktes Format: submissionId respondentId createdAt email name ingame_name skill rating_previous zag x y
    $formatted = [
        $webhook_data['data']['submissionId'] ?? '',
        $webhook_data['data']['respondentId'] ?? '',
        $formatted_date,
        $field_values['email'],
        $field_values['name'],
        $field_values['ingame_name'],
        $field_values['skill'] !== '' ? $field_values['skill'] : '', // Leer wenn null/leer
        $field_values['rating_previous'] !== '' ? $field_values['rating_previous'] : '', // Rating Previous erhalten
        $field_values['zag'] !== '' ? $field_values['zag'] : '', // ZAG - immer leer falls nicht gegeben
        $field_values['x'] !== '' ? $field_values['x'] : '', // X - immer leer falls nicht gegeben
        $field_values['y'] !== '' ? $field_values['y'] : '', // Y - immer leer falls nicht gegeben
    ];
    
    return $formatted;
}

// Funktion zum Konvertieren in TSV-Format
function convert_to_tsv($data) {
    // Datenzeile im exakten gewünschten Format
    $values = array_map(function($value) {
        if ($value === null) {
            return '';
        }
        return str_replace(["\t", "\r", "\n"], ' ', (string)$value);
    }, $data);
    
    return implode("\t", $values) . "\n";
}

// Funktion zum Speichern der JSON-Daten (behält alle vorherigen Responses)
function save_json_data($webhook_data, $json_file) {
    // Existierende Daten laden
    $existing_data = [];
    if (file_exists($json_file)) {
        $existing_content = file_get_contents($json_file);
        if (!empty($existing_content)) {
            $existing_data = json_decode($existing_content, true) ?: [];
            // Sicherstellen, dass es ein Array ist
            if (!is_array($existing_data)) {
                $existing_data = [];
            }
        }
    }
    
    // Neue Daten hinzufügen
    $submission_id = $webhook_data['data']['submissionId'] ?? '';
    $event_id = $webhook_data['eventId'] ?? '';
    
    // Prüfen ob Eintrag bereits existiert (basierend auf submissionId UND eventId)
    $entry_exists = false;
    foreach ($existing_data as $index => $entry) {
        $existing_submission_id = $entry['data']['submissionId'] ?? '';
        $existing_event_id = $entry['eventId'] ?? '';
        
        if ($existing_submission_id === $submission_id && $existing_event_id === $event_id) {
            // Gleiche Submission und Event-ID - überschreiben
            $existing_data[$index] = $webhook_data;
            $entry_exists = true;
            break;
        } elseif ($existing_submission_id === $submission_id) {
            // Gleiche Submission-ID aber andere Event-ID - als neue Response behandeln
            // Nichts tun, wird als neuer Eintrag hinzugefügt
        }
    }
    
    if (!$entry_exists) {
        // Neuen Eintrag hinzufügen (am Ende des Arrays)
        $existing_data[] = $webhook_data;
    }
    
    // JSON speichern
    $json_result = file_put_contents($json_file, json_encode($existing_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    if ($json_result === false) {
        throw new Exception('Could not write to JSON file');
    }
    
    return true;
}

// Funktion zum TSV-Header management
function ensure_tsv_header($tsv_file, $header) {
    if (!file_exists($tsv_file) || filesize($tsv_file) === 0) {
        // Neue Datei oder leere Datei - Header schreiben
        file_put_contents($tsv_file, $header . "\n", LOCK_EX);
    }
}

function send_json_response($status_code, $data) {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Hauptverarbeitung
try {
    // Nur POST requests erlauben
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        send_json_response(405, [
            'status' => 'error',
            'message' => 'Method Not Allowed'
        ]);
    }
    
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // JSON-Daten lesen
    $input = file_get_contents('php://input');
    $webhook_data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON: ' . json_last_error_msg());
    }
    
    // Daten validieren
    if (!isset($webhook_data['data']['fields'])) {
        throw new Exception('Missing form fields in data');
    }
    
    // TSV-Header sicherstellen
    $tsv_header = "submissionId\trespondentId\tcreatedAt\temail\tname\tingame_name\tskill\trating_previous\tzag\tx\ty";
    ensure_tsv_header($config['tsv_file'], $tsv_header);
    
    // 1. JSON-Daten speichern (alle vorherigen Responses bleiben erhalten)
    save_json_data($webhook_data, $config['json_file']);
    
    // 2. Formulardaten im exakten gewünschten Format verarbeiten
    $formatted_data = format_form_data($webhook_data);
    
    // In TSV speichern (APPEND Mode - fügt neue Zeile hinzu ohne alte zu löschen)
    $tsv_data = convert_to_tsv($formatted_data);
    $result = file_put_contents($config['tsv_file'], $tsv_data, FILE_APPEND | LOCK_EX);
    
    if ($result === false) {
        throw new Exception('Could not write to TSV file');
    }
    
    // Erfolgsmeldung
    log_access("Data saved - JSON and TSV: " . implode(" | ", $formatted_data) . " from IP: $client_ip");
    
    // Response-Daten für Debugging
    $response_data = [
        'status' => 'success',
        'message' => 'Data saved in both JSON and TSV format',
        'data_saved' => implode(" | ", $formatted_data),
        'timestamp' => date('c'),
        'json_saved' => true,
        'tsv_saved' => true,
        'submission_id' => $webhook_data['data']['submissionId'] ?? '',
        'event_id' => $webhook_data['eventId'] ?? '',
        'total_fields_received' => count($webhook_data['data']['fields'] ?? [])
    ];
    
    send_json_response(200, $response_data);
    
} catch (Exception $e) {
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    log_error($e->getMessage() . " from IP: $client_ip");
    
    send_json_response(500, [
        'status' => 'error',
        'message' => 'Internal server error: ' . $e->getMessage(),
        'timestamp' => date('c')
    ]);
}
?>