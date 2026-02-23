/**
 * BVG Station Panel - Echtzeit-Abfahrtstafel
 * 
 * Einbindung in index.html:
 *   <script src="station-panel.js"></script>
 * 
 * Wird automatisch initialisiert, sobald die SVG-Karte geladen ist.
 * Stationen in der SVG müssen das Attribut data-station-id oder
 * einen erkennbaren Stations-Text haben (z.B. <title>U Alexanderplatz</title>).
 */

class BVGStationPanel {
  constructor() {
    this.panel = null;
    this.currentStationId = null;
    this.refreshInterval = null;
    this.REFRESH_MS = 30000; // alle 30 Sekunden neu laden

    // BVG-Linienfarben (offiziell)
    this.LINE_COLORS = {
      U1: '#7dad4c', U2: '#da421e', U3: '#16683d',
      U4: '#f0d722', U5: '#7e5330', U6: '#8c6dab',
      U7: '#009bd5', U8: '#224f86', U9: '#f3791d',
      U12: '#000768', S1: '#dd6ca7', S2: '#007734',
      S3: '#0066b3', S5: '#f47216', S7: '#6f4e9c',
      S75: '#6f4e9c', S8: '#55a822', S9: '#f5330c',
      S25: '#007734', S26: '#007734', S41: '#a7653f',
      S42: '#cd8b00', S45: '#cd5e00', S46: '#cd5e00',
      S47: '#cd5e00', BUS: '#a3007c', TRAM: '#cc0000',
    };

    this._buildPanel();
    this._injectStyles();
  }

  // ─── Panel DOM ────────────────────────────────────────────────────────────

  _buildPanel() {
    this.panel = document.createElement('div');
    this.panel.id = 'bvg-station-panel';
    this.panel.innerHTML = `
      <div class="bvg-panel-header">
        <div class="bvg-panel-title">
          <span class="bvg-station-icon">🚇</span>
          <span id="bvg-station-name">Station auswählen</span>
        </div>
        <div class="bvg-panel-controls">
          <button id="bvg-panel-refresh" title="Aktualisieren">↻</button>
          <button id="bvg-panel-close" title="Schließen">✕</button>
        </div>
      </div>
      <div id="bvg-panel-body">
        <p class="bvg-hint">Klicke auf einen Bahnhof in der Karte,<br>um Abfahrten zu sehen.</p>
      </div>
      <div class="bvg-panel-footer">
        <span id="bvg-panel-updated"></span>
      </div>
    `;
    document.body.appendChild(this.panel);

    document.getElementById('bvg-panel-close').addEventListener('click', () => this.hide());
    document.getElementById('bvg-panel-refresh').addEventListener('click', () => {
      if (this.currentStationId) this.loadDepartures(this.currentStationId);
    });
  }

  _injectStyles() {
    const style = document.createElement('style');
    style.textContent = `
      @import url('https://fonts.googleapis.com/css2?family=DIN+Alternate:wght@700&family=IBM+Plex+Mono:wght@400;500&display=swap');

      #bvg-station-panel {
        position: fixed;
        right: 0;
        top: 0;
        height: 100vh;
        width: 340px;
        background: #0d0d0d;
        color: #f5f5f5;
        font-family: 'IBM Plex Mono', monospace;
        display: flex;
        flex-direction: column;
        box-shadow: -4px 0 24px rgba(0,0,0,0.6);
        z-index: 9999;
        transform: translateX(100%);
        transition: transform 0.35s cubic-bezier(0.77, 0, 0.175, 1);
        border-left: 3px solid #f0d722; /* BVG Gelb */
      }

      #bvg-station-panel.visible {
        transform: translateX(0);
      }

      .bvg-panel-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 18px 14px;
        border-bottom: 1px solid #222;
        background: #111;
        flex-shrink: 0;
      }

      .bvg-panel-title {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 0;
      }

      .bvg-station-icon {
        font-size: 20px;
        flex-shrink: 0;
      }

      #bvg-station-name {
        font-family: 'DIN Alternate', 'Arial Black', sans-serif;
        font-size: 16px;
        font-weight: 700;
        letter-spacing: 0.02em;
        color: #f0d722;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 220px;
      }

      .bvg-panel-controls {
        display: flex;
        gap: 8px;
        flex-shrink: 0;
      }

      .bvg-panel-controls button {
        background: #222;
        border: 1px solid #333;
        color: #aaa;
        width: 30px;
        height: 30px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        transition: all 0.15s;
        display: flex;
        align-items: center;
        justify-content: center;
      }

      .bvg-panel-controls button:hover {
        background: #333;
        color: #fff;
      }

      #bvg-panel-body {
        flex: 1;
        overflow-y: auto;
        padding: 12px 0;
        scrollbar-width: thin;
        scrollbar-color: #333 transparent;
      }

      .bvg-hint {
        color: #555;
        text-align: center;
        font-size: 13px;
        margin-top: 40px;
        line-height: 1.8;
      }

      .bvg-loading {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 40px 0;
        gap: 10px;
        color: #555;
        font-size: 13px;
      }

      .bvg-spinner {
        width: 18px;
        height: 18px;
        border: 2px solid #333;
        border-top-color: #f0d722;
        border-radius: 50%;
        animation: bvg-spin 0.7s linear infinite;
      }

      @keyframes bvg-spin {
        to { transform: rotate(360deg); }
      }

      .bvg-error {
        color: #e74c3c;
        text-align: center;
        font-size: 13px;
        padding: 20px;
      }

      /* Abfahrtsliste */
      .bvg-departure-list {
        list-style: none;
        margin: 0;
        padding: 0;
      }

      .bvg-departure-item {
        display: grid;
        grid-template-columns: 52px 1fr 58px;
        align-items: center;
        gap: 10px;
        padding: 10px 18px;
        border-bottom: 1px solid #1a1a1a;
        animation: bvg-fadeIn 0.25s ease both;
        transition: background 0.15s;
      }

      .bvg-departure-item:hover {
        background: #161616;
      }

      @keyframes bvg-fadeIn {
        from { opacity: 0; transform: translateX(8px); }
        to   { opacity: 1; transform: translateX(0); }
      }

      .bvg-line-badge {
        font-family: 'DIN Alternate', 'Arial Black', sans-serif;
        font-size: 13px;
        font-weight: 700;
        padding: 4px 7px;
        border-radius: 4px;
        text-align: center;
        letter-spacing: 0.03em;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        min-width: 40px;
      }

      .bvg-departure-dest {
        font-size: 13px;
        color: #ddd;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
      }

      .bvg-departure-dest small {
        display: block;
        font-size: 11px;
        color: #555;
        margin-top: 2px;
      }

      .bvg-departure-time {
        text-align: right;
        font-size: 14px;
        font-weight: 500;
        white-space: nowrap;
      }

      .bvg-time-now   { color: #f0d722; }
      .bvg-time-soon  { color: #e67e22; }
      .bvg-time-later { color: #aaa; }
      .bvg-time-delay { color: #e74c3c; }

      .bvg-section-label {
        font-size: 10px;
        letter-spacing: 0.15em;
        text-transform: uppercase;
        color: #444;
        padding: 14px 18px 6px;
      }

      .bvg-panel-footer {
        padding: 10px 18px;
        border-top: 1px solid #1a1a1a;
        font-size: 11px;
        color: #444;
        text-align: right;
        flex-shrink: 0;
      }

      /* Station-Klick-Highlight in SVG */
      .bvg-station-hover {
        cursor: pointer;
        transition: opacity 0.15s;
      }
      .bvg-station-hover:hover {
        opacity: 0.7;
      }
    `;
    document.head.appendChild(style);
  }

  // ─── Sichtbarkeit ─────────────────────────────────────────────────────────

  show(stationId, stationName) {
    this.panel.classList.add('visible');
    document.getElementById('bvg-station-name').textContent = stationName;
    this.currentStationId = stationId;
    this.loadDepartures(stationId);

    clearInterval(this.refreshInterval);
    this.refreshInterval = setInterval(() => {
      if (this.currentStationId) this.loadDepartures(this.currentStationId);
    }, this.REFRESH_MS);
  }

  hide() {
    this.panel.classList.remove('visible');
    clearInterval(this.refreshInterval);
    this.currentStationId = null;
  }

  // ─── Daten laden ──────────────────────────────────────────────────────────

  async loadDepartures(stationId) {
    const body = document.getElementById('bvg-panel-body');
    body.innerHTML = `<div class="bvg-loading"><div class="bvg-spinner"></div>Lade Abfahrten…</div>`;

    try {
      const url = `https://v6.vbb.transport.rest/stops/${stationId}/departures?duration=60&results=25&linesOfStops=false&remarks=false`;
      const res = await fetch(url);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();

      const departures = data.departures ?? data; // API gibt departures-Array zurück
      this._renderDepartures(departures);
      document.getElementById('bvg-panel-updated').textContent =
        'Aktualisiert: ' + new Date().toLocaleTimeString('de-DE');

    } catch (err) {
      body.innerHTML = `<p class="bvg-error">⚠ Fehler beim Laden:<br><small>${err.message}</small></p>`;
      console.error('[BVGPanel]', err);
    }
  }

  _renderDepartures(departures) {
    const body = document.getElementById('bvg-panel-body');

    if (!departures || departures.length === 0) {
      body.innerHTML = `<p class="bvg-hint">Keine Abfahrten in den<br>nächsten 60 Minuten.</p>`;
      return;
    }

    const now = Date.now();

    // Gruppieren nach Linientyp (U, S, Bus, Tram …)
    const groups = {};
    departures.forEach(dep => {
      const product = dep.line?.product ?? 'unknown';
      if (!groups[product]) groups[product] = [];
      groups[product].push(dep);
    });

    const productLabels = {
      suburban: '🚊 S-Bahn',
      subway:   '🚇 U-Bahn',
      tram:     '🚋 Straßenbahn',
      bus:      '🚌 Bus',
      ferry:    '⛴ Fähre',
      express:  '🚄 Express',
      regional: '🚂 Regionalzug',
    };

    let html = '';

    Object.entries(groups).forEach(([product, deps]) => {
      html += `<div class="bvg-section-label">${productLabels[product] ?? product}</div>`;
      html += `<ul class="bvg-departure-list">`;

      deps.forEach((dep, i) => {
        const lineName = dep.line?.name ?? '?';
        const direction = dep.direction ?? 'Unbekannt';
        const color = this._lineColor(lineName);
        const textColor = this._contrastColor(color);

        // Abfahrtszeit berechnen
        const plannedMs = new Date(dep.plannedWhen ?? dep.when).getTime();
        const actualMs  = dep.when ? new Date(dep.when).getTime() : plannedMs;
        const delayMin  = dep.delay ? Math.round(dep.delay / 60) : 0;
        const minUntil  = Math.round((actualMs - now) / 60000);

        let timeLabel, timeClass;
        if (minUntil <= 0) {
          timeLabel = 'jetzt';
          timeClass = 'bvg-time-now';
        } else if (minUntil <= 2) {
          timeLabel = `${minUntil} Min`;
          timeClass = 'bvg-time-soon';
        } else {
          timeLabel = `${minUntil} Min`;
          timeClass = delayMin > 0 ? 'bvg-time-delay' : 'bvg-time-later';
        }

        const delayTag = delayMin > 0
          ? `<small>+${delayMin} Min Verspätung</small>`
          : (delayMin < 0 ? `<small>${delayMin} Min früher</small>` : '');

        html += `
          <li class="bvg-departure-item" style="animation-delay:${i * 0.04}s">
            <span class="bvg-line-badge" style="background:${color};color:${textColor}">${lineName}</span>
            <span class="bvg-departure-dest">${this._escape(direction)}${delayTag}</span>
            <span class="bvg-departure-time ${timeClass}">${timeLabel}</span>
          </li>`;
      });

      html += `</ul>`;
    });

    body.innerHTML = html;
  }

  // ─── Hilfsfunktionen ─────────────────────────────────────────────────────

  _lineColor(name) {
    if (this.LINE_COLORS[name]) return this.LINE_COLORS[name];
    // Fallback für unbekannte Linien
    if (name.startsWith('U')) return '#224f86';
    if (name.startsWith('S')) return '#007734';
    if (name.startsWith('M') || name.startsWith('T')) return '#cc0000';
    return '#555';
  }

  // Kontrast: gibt Schwarz oder Weiß zurück
  _contrastColor(hex) {
    const c = hex.replace('#', '');
    if (c.length < 6) return '#000';
    const r = parseInt(c.slice(0,2),16);
    const g = parseInt(c.slice(2,4),16);
    const b = parseInt(c.slice(4,6),16);
    const lum = (0.299*r + 0.587*g + 0.114*b) / 255;
    return lum > 0.55 ? '#000' : '#fff';
  }

  _escape(str) {
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }
}

// ─── SVG-Station-Klick-Bindung ────────────────────────────────────────────────
// Wird aufgerufen NACHDEM die SVG-Karte geladen wurde (aus index.html heraus).
// Erwartet: window.stationPanel = new BVGStationPanel();

window.stationPanel = new BVGStationPanel();

/**
 * Bindet Klick-Events an SVG-Stationselemente.
 * 
 * Aufruf in index.html nach dem SVG-Load:
 *   bindStationClicks(svg);
 * 
 * Die Funktion sucht nach SVG-Elementen mit:
 *   - data-station-id Attribut  → wird direkt verwendet
 *   - data-stop-id Attribut     → wird direkt verwendet  
 *   - <title> Tag mit Stationsname → sucht per API
 */
window.bindStationClicks = async function(svgElement) {
  const svgNode = svgElement.node ? svgElement.node() : svgElement;

  // Alle klickbaren Elemente (Kreise, Rechtecke, Gruppen mit data-station-id)
  const clickTargets = svgNode.querySelectorAll('[data-station-id], [data-stop-id], circle[id], g[id]');

  // Stations-Lookup-Cache
  const stationCache = {};

  async function resolveStationId(element) {
    // 1. Direkte Attribut-ID
    const directId = element.getAttribute('data-station-id') || element.getAttribute('data-stop-id');
    if (directId) return { id: directId, name: directId };

    // 2. ID-Attribut (z.B. id="alexanderplatz")
    const elemId = element.id;

    // 3. Titel innerhalb des Elements
    const titleEl = element.querySelector('title');
    const rawName = titleEl?.textContent?.trim() || elemId || '';

    if (!rawName) return null;
    if (stationCache[rawName]) return stationCache[rawName];

    // 4. API-Suche nach Stationsname
    try {
      const res = await fetch(`https://v6.vbb.transport.rest/locations?query=${encodeURIComponent(rawName)}&results=1&stops=true&addresses=false&poi=false`);
      const data = await res.json();
      if (data && data[0] && data[0].type === 'stop') {
        const result = { id: data[0].id, name: data[0].name };
        stationCache[rawName] = result;
        return result;
      }
    } catch(e) { /* ignore */ }

    return null;
  }

  clickTargets.forEach(el => {
    el.classList.add('bvg-station-hover');
    el.style.cursor = 'pointer';

    el.addEventListener('click', async (e) => {
      e.stopPropagation();
      const station = await resolveStationId(el);
      if (station) {
        window.stationPanel.show(station.id, station.name);
      }
    });
  });

  console.log(`[BVGPanel] ${clickTargets.length} Stations-Elemente gebunden.`);
};
