<?php
/**
 * FWSK Vertretungsplan API
 * Gibt Vertretungsdaten im spezifischen JSON-Format aus
 * URL: https://craftingkatze.de/fwsk-vertretungen/api.php
 */

// CORS-Header für Cross-Origin-Anfragen
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=utf-8");

// Bei OPTIONS-Anfragen (Preflight) sofort antworten
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// API-Endpunkt-Konfiguration
define('SUBSTITUTION_URL', 'https://fwsk.edupage.org/substitution/');
define('CACHE_FILE', dirname(__FILE__) . '/fwsk_cache.json');
define('CACHE_DURATION', 300); // 5 Minuten Cache
define('BASE_URL', 'https://craftingkatze.de/fwsk-vertretungen/');

/**
 * Holt den HTML-Inhalt von der EduPage-Website
 */
function fetchSubstitutionData() {
    $options = [
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36\r\n",
            'timeout' => 15,
            'ignore_errors' => true
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ];
    
    $context = stream_context_create($options);
    
    try {
        $html = @file_get_contents(SUBSTITUTION_URL, false, $context);
        if ($html === false) {
            $error = error_get_last();
            throw new Exception($error['message'] ?? 'Unknown error fetching data');
        }
        return $html;
    } catch (Exception $e) {
        error_log('Fetch error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Extrahiert die Stunde/Periodenangabe aus der Beschreibung
 */
function extractPeriod($subject) {
    // Suche nach Mustern wie "2.FS", "HU - HU", "(6.FS - 7.FS)" etc.
    if (preg_match('/\((?:(\d+\.FS(?:\s*-\s*\d+\.FS)?)|(HU(?:\s*-\s*HU)?))\)/i', $subject, $matches)) {
        if (!empty($matches[1])) {
            return '(' . trim($matches[1]) . ')';
        }
        if (!empty($matches[2])) {
            return '(' . trim($matches[2]) . ')';
        }
    }
    
    // Suche nach Perioden ohne Klammern am Anfang
    if (preg_match('/^(\d+\.FS(?:\s*-\s*\d+\.FS)?|HU(?:\s*-\s*HU)?)[\s:,.-]+/i', $subject, $matches)) {
        return trim($matches[1]);
    }
    
    return '';
}

/**
 * Bereinigt das Subject von Periodenangaben
 */
function cleanSubject($subject) {
    // Entferne Periodenangaben am Anfang
    $subject = preg_replace('/^(?:\d+\.FS(?:\s*-\s*\d+\.FS)?|HU(?:\s*-\s*HU)?)[\s:,.-]+/i', '', $subject);
    
    // Entferne eingeklammerte Perioden
    $subject = preg_replace('/\s*\([^)]*\)/', '', $subject);
    
    // Entferne führende/folgende Satzzeichen
    $subject = preg_replace('/^[\s,:.-]+|[\s,:.-]+$/', '', $subject);
    
    return trim($subject);
}

/**
 * Parst die Vertretungsdaten aus dem HTML
 */
function parseSubstitutionData($html) {
    $result = [
        'school' => 'Freie Waldorfschule Kreuzberg e.V.',
        'date' => date('Y-m-d'),
        'timestamp' => date('d.m.Y H:i:s'),
        'fetch_timestamp' => date('c'),
        'url' => SUBSTITUTION_URL,
        'classes' => [],
        'summary' => [
            'total_classes' => 0,
            'total_changes' => 0
        ]
    ];
    
    // Datum extrahieren (dd.mm.yyyy) und in YYYY-MM-DD konvertieren
    if (preg_match('/\b(\d{2})\.(\d{2})\.(\d{4})\b/', $html, $dateMatch)) {
        $day = $dateMatch[1];
        $month = $dateMatch[2];
        $year = $dateMatch[3];
        $result['date'] = $year . '-' . $month . '-' . $day;
        $result['timestamp'] = $day . '.' . $month . '.' . $year . ' ' . date('H:i:s');
    }
    
    // Nach Klassen gruppieren
    $classBlocksPattern = '/(Klasse\s+\d+[abAB]?)(.*?)(?=Klasse\s+\d+[abAB]?|$)/s';
    preg_match_all($classBlocksPattern, $html, $blocks, PREG_SET_ORDER);
    
    $totalChanges = 0;
    
    foreach ($blocks as $block) {
        $className = trim($block[1]);
        $blockContent = $block[2];
        
        // Extrahiere Vertretungen für diese Klasse
        $pattern = '/(\d{2}:\d{2}-\d{2}:\d{2}),?\s*([^"<>]+?)\s*-\s*(Vertretung|Entfällt)/u';
        preg_match_all($pattern, $blockContent, $substitutions, PREG_SET_ORDER);
        
        if (!empty($substitutions)) {
            $classChanges = [];
            
            foreach ($substitutions as $sub) {
                $time = trim($sub[1]);
                $subject = trim($sub[2]);
                $changeType = $sub[3];
                
                // Extrahiere Periodenangabe
                $period = extractPeriod($subject);
                
                // Bereinige das Subject
                $cleanSubjectText = cleanSubject($subject);
                
                // Bestimme den Typ
                $type = ($changeType === 'Entfällt') ? 'remove' : 'change';
                
                // Korrigiere Umlaute in change_type
                $displayChangeType = ($changeType === 'Entfällt') ? 'Entfällt' : $changeType;
                
                // Baue Info-String
                $info = "{$time}, {$cleanSubjectText} - {$displayChangeType}";
                
                // Füge Änderung hinzu
                $classChanges[] = [
                    'type' => $type,
                    'change_type' => $displayChangeType,
                    'period' => $period,
                    'info' => $info,
                    'time' => $time,
                    'subject' => $cleanSubjectText
                ];
                
                $totalChanges++;
            }
            
            // Füge Klasse mit Änderungen hinzu
            $result['classes'][] = [
                'class' => $className,
                'changes' => $classChanges
            ];
        }
    }
    
    // Sortiere Klassen: Zuerst numerisch, dann alphabetisch
    usort($result['classes'], function($a, $b) {
        preg_match('/(\d+)([abAB]?)/', $a['class'], $matchA);
        preg_match('/(\d+)([abAB]?)/', $b['class'], $matchB);
        
        $numA = isset($matchA[1]) ? (int)$matchA[1] : 0;
        $numB = isset($matchB[1]) ? (int)$matchB[1] : 0;
        
        if ($numA !== $numB) {
            return $numA - $numB;
        }
        
        $letterA = isset($matchA[2]) ? strtoupper($matchA[2]) : '';
        $letterB = isset($matchB[2]) ? strtoupper($matchB[2]) : '';
        
        return strcmp($letterA, $letterB);
    });
    
    // Aktualisiere Zusammenfassung
    $result['summary']['total_classes'] = count($result['classes']);
    $result['summary']['total_changes'] = $totalChanges;
    
    return $result;
}

/**
 * Liest gecachte Daten
 */
function readCache() {
    if (!file_exists(CACHE_FILE)) {
        return null;
    }
    
    $cacheTime = filemtime(CACHE_FILE);
    if (time() - $cacheTime > CACHE_DURATION) {
        return null; // Cache abgelaufen
    }
    
    $cachedData = file_get_contents(CACHE_FILE);
    $data = json_decode($cachedData, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return null; // Invalid JSON
    }
    
    return $data;
}

/**
 * Schreibt Daten in Cache
 */
function writeCache($data) {
    $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $result = file_put_contents(CACHE_FILE, $jsonData);
    
    if ($result === false) {
        error_log('Failed to write cache file. Check permissions for: ' . CACHE_FILE);
        return false;
    }
    
    return true;
}

/**
 * Haupt-API-Funktion
 */
function handleApiRequest() {
    $response = [
        'success' => false,
        'error' => null,
        'cached' => false,
        'data' => null
    ];
    
    // Prüfe, ob gecachte Daten verfügbar sind
    $cachedData = readCache();
    if ($cachedData !== null) {
        $response['success'] = true;
        $response['cached'] = true;
        $response['cache_age'] = time() - filemtime(CACHE_FILE);
        $response['data'] = $cachedData;
        return $response;
    }
    
    // Neue Daten abrufen
    $html = fetchSubstitutionData();
    
    if ($html === null) {
        $response['error'] = 'Konnte keine Daten von der Quelle abrufen';
        return $response;
    }
    
    if (strlen($html) < 100) {
        $response['error'] = 'Die erhaltenen Daten sind zu kurz oder ungültig';
        return $response;
    }
    
    // Daten parsen
    $parsedData = parseSubstitutionData($html);
    
    if (!empty($parsedData['classes'])) {
        // In Cache schreiben
        if (writeCache($parsedData)) {
            $response['success'] = true;
            $response['cached'] = false;
            $response['data'] = $parsedData;
        } else {
            $response['error'] = 'Konnte Daten nicht im Cache speichern';
            $response['data'] = $parsedData; // Trotzdem Daten zurückgeben
        }
    } else {
        $response['error'] = 'Keine Vertretungsdaten gefunden';
    }
    
    return $response;
}

/**
 * Alternative Funktion: Gibt nur die strukturierten Daten zurück (ohne success/cache-Metadaten)
 */
function getStructuredData() {
    $apiResponse = handleApiRequest();
    
    if ($apiResponse['success'] && $apiResponse['data']) {
        return $apiResponse['data'];
    } else {
        // Fallback: Leere Struktur zurückgeben
        return [
            'school' => 'Freie Waldorfschule Kreuzberg e.V.',
            'date' => date('Y-m-d'),
            'timestamp' => date('d.m.Y H:i:s'),
            'fetch_timestamp' => date('c'),
            'url' => SUBSTITUTION_URL,
            'classes' => [],
            'summary' => [
                'total_classes' => 0,
                'total_changes' => 0
            ],
            'error' => $apiResponse['error'] ?? 'Keine Daten verfügbar'
        ];
    }
}

/**
 * Erstellt die vollständige URL für einen Endpunkt
 */
function getApiUrl($endpoint = '', $params = []) {
    $url = BASE_URL . 'api.php';
    
    if ($endpoint) {
        $params['endpoint'] = $endpoint;
    }
    
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    
    return $url;
}

// API-Endpunkte handhaben
$endpoint = $_GET['endpoint'] ?? 'substitutions';
$format = $_GET['format'] ?? 'structured';
$callback = $_GET['callback'] ?? null; // Für JSONP

switch ($endpoint) {
    case 'substitutions':
        if ($format === 'raw') {
            // Mit Metadaten (success, cached, etc.)
            $data = handleApiRequest();
        } else {
            // Nur die strukturierten Daten (wie im Beispiel)
            $data = getStructuredData();
        }
        break;
        
    case 'classes':
        $structuredData = getStructuredData();
        $data = [
            'success' => true,
            'timestamp' => date('c'),
            'classes' => array_map(function($class) {
                return $class['class'];
            }, $structuredData['classes']),
            'count' => count($structuredData['classes'])
        ];
        break;
        
    case 'status':
        $structuredData = getStructuredData();
        $data = [
            'success' => true,
            'service' => 'FWSK Vertretungsplan API',
            'version' => '1.2',
            'timestamp' => date('c'),
            'base_url' => BASE_URL,
            'source_url' => SUBSTITUTION_URL,
            'last_update' => $structuredData['timestamp'],
            'statistics' => $structuredData['summary'],
            'cache_enabled' => true,
            'cache_duration' => CACHE_DURATION . ' seconds',
            'endpoints' => [
                getApiUrl('substitutions') => 'Alle Vertretungsdaten (strukturiert)',
                getApiUrl('substitutions', ['format' => 'raw']) => 'Daten mit Metadaten',
                getApiUrl('classes') => 'Liste der betroffenen Klassen',
                getApiUrl('status') => 'API Statusinformation',
                getApiUrl('summary') => 'Zusammenfassung'
            ],
            'documentation' => [
                'json_structure' => 'Siehe /api.php?endpoint=substitutions',
                'update_interval' => 'Automatisch alle 5 Minuten',
                'cors' => 'Cross-Origin-Anfragen erlaubt'
            ]
        ];
        break;
        
    case 'summary':
        $structuredData = getStructuredData();
        $data = [
            'success' => true,
            'school' => $structuredData['school'],
            'date' => $structuredData['date'],
            'timestamp' => $structuredData['timestamp'],
            'summary' => $structuredData['summary'],
            'url' => $structuredData['url'],
            'api_url' => getApiUrl('substitutions')
        ];
        break;
        
    case 'help':
    case 'docs':
        $data = [
            'success' => true,
            'title' => 'FWSK Vertretungsplan API Dokumentation',
            'version' => '1.2',
            'base_url' => BASE_URL,
            'description' => 'API für den Vertretungsplan der Freien Waldorfschule Kreuzberg',
            'endpoints' => [
                [
                    'endpoint' => 'substitutions',
                    'method' => 'GET',
                    'description' => 'Holt alle Vertretungsdaten',
                    'url' => getApiUrl('substitutions'),
                    'parameters' => [
                        'format' => 'optional: structured (default) oder raw'
                    ]
                ],
                [
                    'endpoint' => 'classes',
                    'method' => 'GET',
                    'description' => 'Liste aller Klassen mit Vertretungen',
                    'url' => getApiUrl('classes')
                ],
                [
                    'endpoint' => 'status',
                    'method' => 'GET',
                    'description' => 'API Statusinformation',
                    'url' => getApiUrl('status')
                ],
                [
                    'endpoint' => 'summary',
                    'method' => 'GET',
                    'description' => 'Zusammenfassung der Vertretungen',
                    'url' => getApiUrl('summary')
                ]
            ],
            'example_usage' => [
                'javascript' => 'fetch("' . getApiUrl('substitutions') . '").then(r => r.json()).then(console.log)',
                'curl' => 'curl "' . getApiUrl('substitutions') . '"',
                'python' => 'import requests; r = requests.get("' . getApiUrl('substitutions') . '"); print(r.json())'
            ],
            'structure_example' => getStructuredData()
        ];
        break;
        
    default:
        // Standard: Strukturierte Daten zurückgeben
        $data = getStructuredData();
        break;
}

// JSON-Ausgabe
$jsonOutput = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

// JSONP Support
if ($callback && preg_match('/^[a-zA-Z_$][a-zA-Z0-9_$]*$/', $callback)) {
    header('Content-Type: application/javascript; charset=utf-8');
    echo $callback . '(' . $jsonOutput . ');';
} else {
    echo $jsonOutput;
}
?>