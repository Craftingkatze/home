<?php
// VBB API abfragen
function getNextDeparture() {
    $url = 'https://v6.vbb.transport.rest/stops/900056104/departures?duration=120&linesOfStops=false&remarks=false&language=de';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_USERAGENT, 'U2-Display/1.0');
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return $response ? json_decode($response, true) : null;
}

// Daten verarbeiten
$data = getNextDeparture();
$now = time();
$walkTime = 3 * 60; // 5 Minuten in Sekunden
$travelTime = 22 * 60; // 22 Minuten in Sekunden
$targetArrival = strtotime('08:00:00');

$nextDeparture = null;
$departures = [];

if ($data && isset($data['departures'])) {
    foreach ($data['departures'] as $dep) {
        if (($dep['line']['name'] ?? '') === 'U2' && 
            strpos($dep['direction'] ?? '', 'Pankow') !== false &&
            ($dep['plannedPlatform'] ?? '2') === '2' &&
            !($dep['cancelled'] ?? false)) {
            
            $depTime = strtotime($dep['when'] ?? $dep['plannedWhen']);
            $minutesUntil = floor(($depTime - $now) / 60);
            
            // Nur nächste 3 Stunden
            if ($minutesUntil >= -2 && $minutesUntil <= 180) {
                $arrivalTime = $depTime + $travelTime;
                $leaveTime = $depTime - $walkTime;
                
                $departures[] = [
                    'departure' => $depTime,
                    'departure_str' => date('H:i', $depTime),
                    'leave' => $leaveTime,
                    'leave_str' => date('H:i', $leaveTime),
                    'arrival' => $arrivalTime,
                    'arrival_str' => date('H:i', $arrivalTime),
                    'minutes_until' => $minutesUntil,
                    'delay' => $dep['delay'] ?? 0,
                    'makes_it' => $arrivalTime <= $targetArrival
                ];
            }
        }
    }
}

// Sortieren und nächste Abfahrt finden
usort($departures, function($a, $b) {
    return $a['departure'] <=> $b['departure'];
});

$nextDeparture = $departures[0] ?? null;

// Für Countdown: Zeit bis zum Losgehen
if ($nextDeparture) {
    $secondsUntilLeave = max(0, $nextDeparture['leave'] - $now);
    $leaveTimeUnix = $nextDeparture['leave']; // Unix timestamp für JavaScript
    
    // Progress für PHP (wird mit JS aktualisiert)
    $totalSeconds = $walkTime; // 5 Minuten = 300 Sekunden
    $progressPercent = min(100, (($totalSeconds - $secondsUntilLeave) / $totalSeconds) * 100);
    
    // Dynamische Aktualisierungsrate für Seite
    if ($secondsUntilLeave <= 0) {
        $refreshSeconds = 5;
    } elseif ($secondsUntilLeave <= 60) {
        $refreshSeconds = 10;
    } elseif ($secondsUntilLeave <= 300) {
        $refreshSeconds = 30;
    } else {
        $refreshSeconds = 60;
    }
} else {
    $leaveTimeUnix = 0;
    $refreshSeconds = 30;
}

// Cache für 10 Sekunden
header('Cache-Control: max-age=10');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <!-- Dynamische Aktualisierungsrate -->
    <meta http-equiv="refresh" content="<?php echo $refreshSeconds; ?>">
    <title>U2 Losgehzeit</title>
    <style>
        /* Reset */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }
        
        html, body {
            width: 100%;
            height: 100%;
            overflow: hidden;
            background: #ffffff;
            color: #000000;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            font-weight: 400;
        }
        
        /* Kindle Display Anpassung */
        .container {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            max-width: 600px;
            margin: 0 auto;
        }
        
        /* Header */
        .header {
            text-align: center;
            margin-bottom: 40px;
            width: 100%;
        }
        
        .station {
            font-size: 24px;
            font-weight: 400;
            color: #000000;
            margin-bottom: 8px;
        }
        
        .direction {
            font-size: 18px;
            font-weight: 300;
            color: #000000;
            opacity: 0.7;
        }
        
        /* Main Time Display */
        .time-display {
            text-align: center;
            margin-bottom: 50px;
            width: 100%;
        }
        
        .departure-time {
            font-size: 64px;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 16px;
            font-variant-numeric: tabular-nums;
            color: #000000;
        }
        
        .leave-time {
            font-size: 28px;
            font-weight: 400;
            color: #000000;
            opacity: 0.9;
            margin-bottom: 8px;
        }
        
        .arrival-info {
            font-size: 18px;
            color: #000000;
            opacity: 0.6;
        }
        
        /* Countdown Section */
        .countdown-section {
            width: 100%;
            margin-bottom: 50px;
            text-align: center;
        }
        
        .countdown-label {
            font-size: 18px;
            color: #000000;
            opacity: 0.7;
            margin-bottom: 16px;
        }
        
        .countdown {
            font-size: 56px;
            font-weight: 700;
            text-align: center;
            letter-spacing: 2px;
            font-variant-numeric: tabular-nums;
            margin-bottom: 24px;
            color: #000000;
        }
        
        /* Progress Bar */
        .progress-container {
            width: 100%;
            height: 6px;
            background: rgba(0, 0, 0, 0.1);
            border-radius: 3px;
            overflow: hidden;
            margin: 0 auto 12px auto;
        }
        
        .progress-bar {
            height: 100%;
            background: #000000;
            border-radius: 3px;
            width: <?php echo $progressPercent ?? 0; ?>%;
            transition: width 1s linear;
        }
        
        /* Progress Labels */
        .progress-labels {
            display: flex;
            justify-content: space-between;
            width: 100%;
            font-size: 14px;
            color: #000000;
            opacity: 0.5;
            margin-bottom: 8px;
        }
        
        .time-left-label {
            font-size: 16px;
            color: #000000;
            opacity: 0.7;
            margin-top: 8px;
        }
        
        .urgent {
            color: #ff0000;
            animation: pulse 1s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        /* Next Departures */
        .next-departures {
            width: 100%;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-top: 40px;
        }
        
        .next-item {
            text-align: center;
            padding: 15px 10px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.02);
        }
        
        .next-time {
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 4px;
            font-variant-numeric: tabular-nums;
            color: #000000;
        }
        
        .next-leave {
            font-size: 14px;
            color: #000000;
            opacity: 0.5;
        }
        
        /* Status Indicators */
        .status {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-top: 30px;
        }
        
        .status-item {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            background: rgba(0, 0, 0, 0.05);
            color: #000000;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }
        
        /* Footer */
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 13px;
            color: #000000;
            opacity: 0.4;
            width: 100%;
        }
        
        /* Responsive */
        @media (max-height: 700px) {
            .header { margin-bottom: 30px; }
            .time-display { margin-bottom: 40px; }
            .departure-time { font-size: 56px; }
            .countdown { font-size: 48px; }
            .countdown-section { margin-bottom: 40px; }
        }
        
        @media (max-width: 480px) {
            .departure-time { font-size: 48px; }
            .leave-time { font-size: 24px; }
            .countdown { font-size: 40px; }
            .next-departures {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            .container { padding: 15px; }
        }
        
        @media (max-height: 600px) {
            .next-departures { display: none; }
            .status { margin-top: 20px; }
        }
        
        /* Border only design */
        .border-top {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: rgba(0, 0, 0, 0.1);
        }
        
        .border-bottom {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: rgba(0, 0, 0, 0.1);
        }
        
        .border-left {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: 1px;
            background: rgba(0, 0, 0, 0.1);
        }
        
        .border-right {
            position: fixed;
            top: 0;
            right: 0;
            bottom: 0;
            width: 1px;
            background: rgba(0, 0, 0, 0.1);
        }
        
        /* Click to refresh overlay */
        .click-refresh {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
            z-index: 1000;
        }
        
        /* Refresh indicator */
        .refresh-indicator {
            position: fixed;
            bottom: 10px;
            right: 10px;
            font-size: 11px;
            color: rgba(0, 0, 0, 0.2);
            z-index: 1001;
            font-variant-numeric: tabular-nums;
        }
    </style>
</head>
<body>
    <!-- Click overlay for manual refresh -->
    <a href="?t=<?php echo time(); ?>" class="click-refresh" title="Seite aktualisieren"></a>
    
    <!-- Border lines -->
    <div class="border-top"></div>
    <div class="border-bottom"></div>
    <div class="border-left"></div>
    <div class="border-right"></div>
    
    <div class="container">
        <?php if ($nextDeparture): ?>
            
           
            <div class="time-display">
                <div class="departure-time"><?php echo $nextDeparture['leave_str']; ?></div>
                <div class="leave-time">Abfahrt: <?php echo $nextDeparture['departure_str']; ?></div>
                <div class="arrival-info">
                    Ankunft: <?php echo $nextDeparture['arrival_str']; ?>
                    <?php if ($nextDeparture['makes_it']): ?>
                        • Vor 8:00
                    <?php else: ?>
                        • Nach 8:00
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="countdown-section">
                <div class="countdown-label">Zeit bis zum Losgehen</div>
                <div id="countdown" class="countdown"><?php 
                    $minutes = floor($secondsUntilLeave / 60);
                    $seconds = $secondsUntilLeave % 60;
                    echo sprintf('%02d:%02d', $minutes, $seconds);
                ?></div>
                
                <div class="progress-container">
                    <div id="progressBar" class="progress-bar"></div>
                </div>
                
                <div class="progress-labels">
                    <span>Jetzt</span>
                    <span>Los um <?php echo $nextDeparture['leave_str']; ?></span>
                </div>
                
                <div id="timeLeftLabel" class="time-left-label">
                    <?php 
                    $minutesLeft = floor($secondsUntilLeave / 60);
                    if ($secondsUntilLeave <= 0) {
                        echo "JETZT LOSGEHEN!";
                    } elseif ($minutesLeft > 0) {
                        echo "Noch " . $minutesLeft . " Minute" . ($minutesLeft != 1 ? 'n' : '');
                    } else {
                        echo "Noch " . $secondsUntilLeave . " Sekunden";
                    }
                    ?>
                </div>
            </div>
            
            <?php if (count($departures) > 1 && !(isset($_GET['compact']) && $_GET['compact'] == '1')): ?>
                <div class="next-departures">
                    <?php for ($i = 1; $i <= min(3, count($departures) - 1); $i++): ?>
                        <?php $dep = $departures[$i]; ?>
                        <div class="next-item">
                            <div class="next-time"><?php echo $dep['departure_str']; ?></div>
                            <div class="next-leave">Los: <?php echo $dep['leave_str']; ?></div>
                        </div>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
            
            <div class="status">
                <?php if ($nextDeparture['makes_it']): ?>
                    <div class="status-item">Vor 8 Uhr</div>
                <?php endif; ?>
                
                <?php if ($nextDeparture['delay'] > 0): ?>
                    <div class="status-item">+<?php echo $nextDeparture['delay']; ?> min</div>
                <?php else: ?>
                    <div class="status-item">Pünktlich</div>
                <?php endif; ?>
                
                <div class="status-item">
                    <span id="currentTime"><?php echo date('H:i:s'); ?></span>
                    <?php if ($refreshSeconds <= 10): ?>
                        ⚡
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="header">
                <div class="station">Keine Verbindung</div>
                <div class="direction">Bitte versuche es erneut</div>
            </div>
            <div class="time-display">
                <div class="departure-time">--:--</div>
                <div class="leave-time">Losgehzeit: --:--</div>
            </div>
            <div class="footer">
                Prüfe Internetverbindung • Tippe zum Aktualisieren
            </div>
        <?php endif; ?>
        
        <div class="footer">
            <span id="lastUpdate">Aktualisierung: <?php echo date('H:i:s'); ?></span>
            <?php if ($nextDeparture && $secondsUntilLeave > 0): ?>
                • Nächste in <span id="refreshCounter"><?php echo $refreshSeconds; ?></span>s
            <?php endif; ?>
            <?php if (isset($_GET['compact']) && $_GET['compact'] == '1'): ?>
                • <a href="?compact=0&t=<?php echo time(); ?>" style="color: rgba(0,0,0,0.4); text-decoration: none;">Vollständige Ansicht</a>
            <?php elseif (isset($_GET['compact']) && $_GET['compact'] == '0'): ?>
                • <a href="?compact=1&t=<?php echo time(); ?>" style="color: rgba(0,0,0,0.4); text-decoration: none;">Kompakte Ansicht</a>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="refresh-indicator">
        <span id="refreshIndicator"><?php echo $refreshSeconds; ?></span>s
    </div>
  <button id="fullscreenBtn" class="fullscreen-btn">Vollbild</button>

    <script>
        // Echtzeit Countdown
        <?php if ($nextDeparture): ?>
        const leaveTimeUnix = <?php echo $leaveTimeUnix; ?>;
        const walkTime = 300; // 5 Minuten in Sekunden
        let serverTimeOffset = 0;
        
        function updateCountdown() {
            // Berechne verbleibende Zeit
            const now = Math.floor(Date.now() / 1000) + serverTimeOffset;
            const remainingSeconds = Math.max(0, leaveTimeUnix - now);
            
            // Countdown formatieren
            const minutes = Math.floor(remainingSeconds / 60);
            const seconds = remainingSeconds % 60;
            const countdownStr = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            // Fortschrittsbalken
            const progressPercent = Math.min(100, ((walkTime - remainingSeconds) / walkTime) * 100);
            
            // Texte aktualisieren
            document.getElementById('countdown').textContent = countdownStr;
            document.getElementById('progressBar').style.width = `${progressPercent}%`;
            document.getElementById('refreshIndicator').textContent = Math.min(60, Math.max(5, Math.ceil(remainingSeconds / 30) * 5));
            
            // Zeit-Label aktualisieren
            const timeLeftLabel = document.getElementById('timeLeftLabel');
            if (remainingSeconds <= 0) {
                timeLeftLabel.textContent = "JETZT LOSGEHEN!";
                timeLeftLabel.className = 'time-left-label urgent';
            } else if (minutes > 0) {
                timeLeftLabel.textContent = `Noch ${minutes} Minute${minutes !== 1 ? 'n' : ''}`;
                timeLeftLabel.className = 'time-left-label';
            } else {
                timeLeftLabel.textContent = `Noch ${remainingSeconds} Sekunden`;
                timeLeftLabel.className = 'time-left-label';
                
                // Bei weniger als 30 Sekunden blinkend machen
                if (remainingSeconds <= 30) {
                    timeLeftLabel.className = 'time-left-label urgent';
                }
            }
            
            // Aktuelle Zeit aktualisieren
            const currentTime = new Date();
            document.getElementById('currentTime').textContent = 
                currentTime.toLocaleTimeString('de-DE', {hour12: false});
        }
        
        // Sekundentakt aktualisieren
        setInterval(updateCountdown, 1000);
        
        // Sofort einmal ausführen
        updateCountdown();
        <?php endif; ?>
        
        // Seite alle X Sekunden neu laden basierend auf verbleibender Zeit
        let refreshCounter = <?php echo $refreshSeconds; ?>;
        
        function updateRefreshCounter() {
            if (refreshCounter > 0) {
                refreshCounter--;
                document.getElementById('refreshCounter').textContent = refreshCounter;
            } else {
                // Seite neu laden, aber nicht sofort, wenn Countdown aktiv
                <?php if ($nextDeparture && $secondsUntilLeave > 60): ?>
                if (confirm('Seite neu laden? Countdown läuft noch.')) {
                    location.reload();
                } else {
                    refreshCounter = 30;
                }
                <?php else: ?>
                location.reload();
                <?php endif; ?>
            }
        }
        
        // Refresh-Zähler nur starten wenn Countdown mehr als 1 Minute
        <?php if ($nextDeparture && $secondsUntilLeave > 60): ?>
        setInterval(updateRefreshCounter, 1000);
        <?php endif; ?>
        
        // Letzte Aktualisierungszeit
        document.getElementById('lastUpdate').textContent = `Aktualisierung: ${new Date().toLocaleTimeString('de-DE', {hour12: false})}`;
    </script>
</body>
</html>