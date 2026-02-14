<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FWSK Vertretungsplan API</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            margin-bottom: 30px;
            text-align: center;
        }
        
        .header h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 2.5em;
        }
        
        .header p {
            color: #666;
            font-size: 1.1em;
        }
        
        .api-info {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }
        
        .api-info h2 {
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        
        .endpoint {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin: 15px 0;
            border-radius: 0 8px 8px 0;
        }
        
        .endpoint-code {
            background: #2d3748;
            color: #e2e8f0;
            padding: 12px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            margin: 10px 0;
            overflow-x: auto;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            margin: 5px;
        }
        
        .btn:hover {
            background: #5a67d8;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .data-display {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-top: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            display: none;
        }
        
        .data-display.active {
            display: block;
        }
        
        .substitution-item {
            background: #f8f9fa;
            padding: 15px;
            margin: 10px 0;
            border-radius: 8px;
            border-left: 4px solid #4CAF50;
        }
        
        .substitution-item.cancelled {
            border-left-color: #f44336;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: bold;
            margin-left: 10px;
        }
        
        .status-online {
            background: #4CAF50;
            color: white;
        }
        
        .status-offline {
            background: #f44336;
            color: white;
        }
        
        .class-badge {
            background: #667eea;
            color: white;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 0.9em;
            margin: 2px;
            display: inline-block;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .header {
                padding: 20px;
            }
            
            .header h1 {
                font-size: 2em;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏫 FWSK Vertretungsplan API</h1>
            <p>Eine einfache API zur Abfrage des Vertretungsplans</p>
            <div id="apiStatus" style="margin-top: 15px;"></div>
        </div>
        
        <div class="api-info">
            <h2>📡 API Endpoints</h2>
            
            <div class="endpoint">
                <strong>GET /api.php?endpoint=substitutions</strong>
                <p>Gibt alle Vertretungsdaten im JSON-Format zurück</p>
                <div class="endpoint-code">
                    <?php echo $_SERVER['HTTP_HOST'] ?>/api.php?endpoint=substitutions
                </div>
                <button class="btn" onclick="fetchData('substitutions')">Daten abrufen</button>
                <button class="btn" onclick="copyToClipboard('<?php echo $_SERVER['HTTP_HOST'] ?>/api.php?endpoint=substitutions')">URL kopieren</button>
            </div>
            
            <div class="endpoint">
                <strong>GET /api.php?endpoint=classes</strong>
                <p>Liste aller betroffenen Klassen</p>
                <div class="endpoint-code">
                    <?php echo $_SERVER['HTTP_HOST'] ?>/api.php?endpoint=classes
                </div>
                <button class="btn" onclick="fetchData('classes')">Klassen anzeigen</button>
            </div>
            
            <div class="endpoint">
                <strong>GET /api.php?endpoint=status</strong>
                <p>API Statusinformationen</p>
                <div class="endpoint-code">
                    <?php echo $_SERVER['HTTP_HOST'] ?>/api.php?endpoint=status
                </div>
                <button class="btn" onclick="fetchData('status')">Status prüfen</button>
            </div>
        </div>
        
        <div id="dataDisplay" class="data-display">
            <!-- Hier werden die Daten angezeigt -->
        </div>
    </div>
    
    <script>
        // API Basis-URL
        const apiBase = 'api.php';
        
        // Status prüfen
        async function checkApiStatus() {
            try {
                const response = await fetch(`${apiBase}?endpoint=status`);
                const data = await response.json();
                
                const statusElement = document.getElementById('apiStatus');
                if (data.success) {
                    statusElement.innerHTML = `<span class="status-badge status-online">API Online ✓</span>`;
                } else {
                    statusElement.innerHTML = `<span class="status-badge status-offline">API Offline ✗</span>`;
                }
            } catch (error) {
                document.getElementById('apiStatus').innerHTML = 
                    `<span class="status-badge status-offline">Verbindungsfehler ✗</span>`;
            }
        }
        
        // Daten abrufen
        async function fetchData(endpoint) {
            const display = document.getElementById('dataDisplay');
            display.innerHTML = '<div class="loading">⏳ Lade Daten...</div>';
            display.classList.add('active');
            
            try {
                const response = await fetch(`${apiBase}?endpoint=${endpoint}`);
                const data = await response.json();
                
                display.innerHTML = formatData(data, endpoint);
            } catch (error) {
                display.innerHTML = `
                    <div class="error">
                        <h3>❌ Fehler beim Abrufen der Daten</h3>
                        <p>${error.message}</p>
                    </div>
                `;
            }
        }
        
        // Daten formatieren für die Anzeige
        function formatData(data, endpoint) {
            if (!data.success) {
                return `
                    <div class="error">
                        <h3>❌ Fehler</h3>
                        <p>${data.error || 'Unbekannter Fehler'}</p>
                    </div>
                `;
            }
            
            let html = '';
            
            switch(endpoint) {
                case 'substitutions':
                    html += `<h2>📅 Vertretungsplan</h2>`;
                    
                    if (data.cached) {
                        html += `<p><small>⚠️ Gecachte Daten (${data.cache_age} Sekunden alt)</small></p>`;
                    }
                    
                    if (data.data?.date) {
                        html += `<h3>Datum: ${data.data.date}</h3>`;
                    }
                    
                    if (data.data?.statistics) {
                        const stats = data.data.statistics;
                        html += `
                            <div style="background: #e8f4fd; padding: 15px; border-radius: 8px; margin: 20px 0;">
                                <strong>Statistik:</strong><br>
                                📚 Klassen: ${stats.total_classes}<br>
                                📝 Vertretungen: ${stats.total_substitutions}<br>
                                🎓 Gruppiert: ${stats.grouped_classes}
                            </div>
                        `;
                    }
                    
                    if (data.data?.classes?.length > 0) {
                        html += `<h3>Betroffene Klassen:</h3>`;
                        data.data.classes.forEach(cls => {
                            html += `<span class="class-badge">${cls}</span>`;
                        });
                    }
                    
                    if (data.data?.by_class?.length > 0) {
                        html += `<h3 style="margin-top: 30px;">Vertretungen nach Klasse:</h3>`;
                        data.data.by_class.forEach(cls => {
                            html += `
                                <div style="margin: 25px 0; padding: 20px; background: #f5f7fa; border-radius: 10px;">
                                    <h4 style="color: #667eea; margin-bottom: 15px;">🎓 ${cls.class}</h4>
                            `;
                            
                            if (cls.substitutions?.length > 0) {
                                cls.substitutions.forEach(sub => {
                                    const isCancelled = sub.status === 'Entfällt';
                                    html += `
                                        <div class="substitution-item ${isCancelled ? 'cancelled' : ''}">
                                            ${isCancelled ? '❌' : '🔄'} 
                                            <strong>${sub.time}</strong> | 
                                            ${sub.subject} - 
                                            <strong>${sub.status}</strong>
                                        </div>
                                    `;
                                });
                            } else {
                                html += `<p>Keine Vertretungen für diese Klasse.</p>`;
                            }
                            
                            html += `</div>`;
                        });
                    }
                    break;
                    
                case 'classes':
                    html += `<h2>📚 Alle Klassen</h2>`;
                    if (data.classes?.length > 0) {
                        html += `<p>Insgesamt: ${data.total} Klassen</p>`;
                        data.classes.forEach(cls => {
                            html += `<span class="class-badge">${cls}</span>`;
                        });
                    }
                    break;
                    
                case 'status':
                    html += `<h2>📊 API Status</h2>`;
                    html += `
                        <div style="background: #f0f9ff; padding: 20px; border-radius: 10px;">
                            <p><strong>Service:</strong> ${data.service}</p>
                            <p><strong>Version:</strong> ${data.version}</p>
                            <p><strong>Zeitstempel:</strong> ${new Date(data.timestamp).toLocaleString('de-DE')}</p>
                        </div>
                        <h3 style="margin-top: 20px;">Verfügbare Endpoints:</h3>
                    `;
                    
                    for (const [endpoint, description] of Object.entries(data.endpoints)) {
                        html += `
                            <div style="margin: 10px 0; padding: 10px; background: #f8f9fa; border-radius: 5px;">
                                <code>${endpoint}</code><br>
                                <small>${description}</small>
                            </div>
                        `;
                    }
                    break;
            }
            
            // JSON-Rohdaten anzeigen
            html += `
                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
                    <h4>📄 JSON Rohdaten:</h4>
                    <div style="background: #2d3748; color: #e2e8f0; padding: 15px; border-radius: 8px; overflow-x: auto;">
                        <pre style="font-size: 12px;">${JSON.stringify(data, null, 2)}</pre>
                    </div>
                </div>
            `;
            
            return html;
        }
        
        // In Zwischenablage kopieren
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert('URL in Zwischenablage kopiert!');
            });
        }
        
        // API Status beim Laden prüfen
        document.addEventListener('DOMContentLoaded', checkApiStatus);
    </script>
</body>
</html>