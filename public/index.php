<?php
$mapsKey = $_ENV['GOOGLE_MAPS_KEY'] ?? getenv('GOOGLE_MAPS_KEY') ?: 'AIzaSyB27M0Sl9zDxwED92C1s3XxAZK0seAJSF4';
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YOID – Station Map</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: #fff;
            height: 100dvh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* ── Header ── */
        header {
            background: #fff;
            border-bottom: 1px solid #e8e8e8;
            padding: 0 20px;
            height: 52px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }
        .header-left { display: flex; align-items: center; gap: 10px; }
        header img.logo { height: 24px; }
        header h1 { font-size: 15px; font-weight: 600; color: #111; }
        #station-count {
            font-size: 12px; font-weight: 600; color: #7c3aed;
            background: #f0ebfa; border-radius: 20px; padding: 3px 10px;
        }

        /* ── Search ── */
        .search-bar {
            background: #fff;
            border-bottom: 1px solid #ebebeb;
            padding: 9px 14px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            gap: 9px;
        }
        #search-input {
            flex: 1; border: none; outline: none;
            font-size: 15px; font-family: inherit;
            color: #111; background: transparent;
        }
        #search-input::placeholder { color: #bbb; }
        #clear-btn {
            display: none; background: none; border: none;
            cursor: pointer; color: #bbb; font-size: 19px; line-height: 1; padding: 0 2px;
        }
        #clear-btn:hover { color: #777; }

        /* ── Map ── */
        #map { flex: 1; width: 100%; }

        /* ── Info window ── */
        .gm-style-iw-c  { border-radius: 10px !important; padding: 0 !important; box-shadow: 0 4px 16px rgba(0,0,0,0.12) !important; }
        .gm-style-iw-d  { overflow: hidden !important; padding: 0 !important; }
        .gm-style-iw-chr{ display: none !important; }
        .iw             { padding: 13px 15px; min-width: 200px; max-width: 290px; }
        .iw-title       { font-size: 14px; font-weight: 700; color: #111; margin-bottom: 3px; }
        .iw-serial      { font-size: 11px; color: #aaa; margin-bottom: 5px; font-family: monospace; }
        .iw-address     { font-size: 12px; color: #666; line-height: 1.5; margin-bottom: 8px; }
        .iw-photos      { display: flex; gap: 6px; margin-bottom: 10px; }
        .iw-photo-link  { display: block; width: 80px; height: 80px; border-radius: 6px; overflow: hidden; border: 1px solid #e5e7eb; flex-shrink: 0; text-decoration: none; }
        .iw-photo-link:hover { opacity: 0.85; }
        .iw-photo       { width: 100%; height: 100%; object-fit: cover; display: block; }
        .iw-photo-label { font-size: 10px; color: #aaa; text-align: center; margin-top: 2px; }
        .iw-badge       { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 600; padding: 3px 9px; border-radius: 20px; background: #f0ebfa; color: #5b21b6; }
        .iw-badge .dot  { width: 6px; height: 6px; border-radius: 50%; background: #7c3aed; }

        /* ── Lightbox ── */
        #lightbox {
            display: none; position: fixed; inset: 0; z-index: 9999;
            background: rgba(0,0,0,0.93);
            align-items: center; justify-content: center;
            cursor: zoom-out;
            animation: lbFade .18s ease;
        }
        #lightbox.open { display: flex; }
        @keyframes lbFade { from { opacity: 0 } to { opacity: 1 } }
        #lightbox-img {
            max-width: 90vw; max-height: 85vh;
            object-fit: contain; border-radius: 8px;
            box-shadow: 0 8px 48px rgba(0,0,0,0.6);
            cursor: default;
        }
        #lightbox-close {
            position: absolute; top: 16px; right: 20px;
            background: none; border: none; color: #fff;
            font-size: 34px; line-height: 1; cursor: pointer; opacity: .7;
        }
        #lightbox-close:hover { opacity: 1; }
        #lightbox-label {
            position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%);
            color: rgba(255,255,255,.55); font-size: 13px; white-space: nowrap;
        }
        .lb-nav {
            position: absolute; top: 50%; transform: translateY(-50%);
            background: rgba(255,255,255,.15); border: none; color: #fff;
            font-size: 26px; width: 46px; height: 46px; border-radius: 50%;
            cursor: pointer; display: flex; align-items: center; justify-content: center;
        }
        .lb-nav:hover { background: rgba(255,255,255,.28); }
        #lightbox-prev { left: 16px; }
        #lightbox-next { right: 16px; }

        /* ── Station search dropdown ── */
        .search-wrap    { position: relative; flex-shrink: 0; }
        #search-results {
            display: none;
            position: absolute; top: 100%; left: 0; right: 0; z-index: 200;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-top: none;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.10);
            max-height: 320px; overflow-y: auto;
        }
        .sr-item {
            padding: 10px 14px; cursor: pointer;
            border-bottom: 1px solid #f3f4f6;
        }
        .sr-item:last-child { border-bottom: none; }
        .sr-item:hover, .sr-item.active { background: #f5f2ff; }
        .sr-title   { font-size: 14px; font-weight: 600; color: #111; }
        .sr-address { font-size: 12px; color: #888; margin-top: 2px; }
        .sr-empty   { padding: 12px 14px; font-size: 13px; color: #aaa; cursor: default; }
    </style>
</head>
<body>

<header>
    <div class="header-left">
        <img class="logo" src="https://partners.yoidpower.com/media/yoid-logo.svg" alt="YOID"
             onerror="this.style.display='none'">
        <h1>Station Map</h1>
    </div>
    <span id="station-count">Loading…</span>
</header>

<div class="search-wrap">
<div class="search-bar">
    <svg width="16" height="16" viewBox="0 0 20 20" fill="none" style="flex-shrink:0">
        <circle cx="8.5" cy="8.5" r="6.5" stroke="#bbb" stroke-width="2"/>
        <path d="M13.5 13.5L18 18" stroke="#bbb" stroke-width="2" stroke-linecap="round"/>
    </svg>
    <input id="search-input" type="text" placeholder="Search station name or city…" autocomplete="off">
    <button id="clear-btn" onclick="clearSearch()" aria-label="Clear">×</button>
</div>
<div id="search-results"></div>
</div>

<div id="map"></div>

<div id="lightbox" onclick="closeLightbox()">
    <button id="lightbox-close" onclick="closeLightbox()" aria-label="Close">×</button>
    <img id="lightbox-img" src="" alt="" onclick="event.stopPropagation()">
    <button class="lb-nav" id="lightbox-prev" onclick="lbNav(-1,event)" aria-label="Previous">&#8249;</button>
    <button class="lb-nav" id="lightbox-next" onclick="lbNav(1,event)"  aria-label="Next">&#8250;</button>
    <div id="lightbox-label"></div>
</div>

<script src="https://unpkg.com/@googlemaps/markerclusterer@2/dist/index.min.js"></script>
<script>
var MAP_STYLES = [
    // Strip all colour — full greyscale
    { elementType: 'all',                  stylers: [{ saturation: -100 }] },

    // Canvas — medium grey so white roads contrast clearly
    { featureType: 'landscape',            elementType: 'geometry',        stylers: [{ lightness: 72 }] },
    { featureType: 'administrative',       elementType: 'geometry',        stylers: [{ lightness: 60 }] },
    { featureType: 'poi',                  elementType: 'geometry',        stylers: [{ lightness: 78 }] },
    { featureType: 'poi.park',             elementType: 'geometry',        stylers: [{ lightness: 75 }] },

    // Local roads — white fill, visible grey stroke
    { featureType: 'road',                 elementType: 'geometry.fill',   stylers: [{ lightness: 100 }] },
    { featureType: 'road',                 elementType: 'geometry.stroke', stylers: [{ lightness: 40 }] },

    // Arterial roads — slightly off-white, darker stroke
    { featureType: 'road.arterial',        elementType: 'geometry.fill',   stylers: [{ lightness: 96 }] },
    { featureType: 'road.arterial',        elementType: 'geometry.stroke', stylers: [{ lightness: 30 }] },

    // Highways — most prominent
    { featureType: 'road.highway',         elementType: 'geometry.fill',   stylers: [{ lightness: 92 }] },
    { featureType: 'road.highway',         elementType: 'geometry.stroke', stylers: [{ lightness: 10 }] },

    // Transit
    { featureType: 'transit.line',         elementType: 'geometry',        stylers: [{ lightness: 55 }] },

    // Water — darker so it reads clearly
    { featureType: 'water',                elementType: 'geometry',        stylers: [{ lightness: 45 }] },

    // Labels — hide icons & POI clutter, keep road/place text
    { elementType: 'labels.icon',          stylers: [{ visibility: 'off' }] },
    { featureType: 'poi',                  elementType: 'labels',          stylers: [{ visibility: 'off' }] },
    { elementType: 'labels.text.fill',     stylers: [{ saturation: -100 }, { lightness: -40 }] },
    { elementType: 'labels.text.stroke',   stylers: [{ lightness: 90 }] },
];

var PIN_SVG = 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(
    '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="38" viewBox="0 0 28 38">' +
    '<path d="M14 0C6.268 0 0 6.268 0 14c0 10.5 14 24 14 24S28 24.5 28 14C28 6.268 21.732 0 14 0z" fill="#7C3AED"/>' +
    '<circle cx="14" cy="14" r="5.5" fill="rgba(255,255,255,0.92)"/>' +
    '</svg>'
);
var SEARCH_SVG = 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(
    '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="38" viewBox="0 0 28 38">' +
    '<path d="M14 0C6.268 0 0 6.268 0 14c0 10.5 14 24 14 24S28 24.5 28 14C28 6.268 21.732 0 14 0z" fill="#222"/>' +
    '<circle cx="14" cy="14" r="5.5" fill="rgba(255,255,255,0.92)"/>' +
    '</svg>'
);

var clusterRenderer = {
    render: function(cluster) {
        var n = cluster.count;
        var outer = n > 20 ? 46 : n > 10 ? 38 : 30;
        var inner = outer - 10, fs = n > 99 ? 11 : n > 9 ? 13 : 14;
        var svg =
            '<svg xmlns="http://www.w3.org/2000/svg" width="'+(outer*2)+'" height="'+(outer*2)+'">' +
            '<circle cx="'+outer+'" cy="'+outer+'" r="'+outer+'" fill="#7C3AED" fill-opacity="0.18"/>' +
            '<circle cx="'+outer+'" cy="'+outer+'" r="'+inner+'" fill="#7C3AED"/>' +
            '<text x="'+outer+'" y="'+outer+'" text-anchor="middle" dominant-baseline="central"' +
            ' fill="white" font-size="'+fs+'" font-weight="700"' +
            ' font-family="-apple-system,BlinkMacSystemFont,sans-serif">'+n+'</text></svg>';
        var size = outer * 2;
        return new google.maps.Marker({
            position: cluster.position,
            icon: { url: 'data:image/svg+xml;charset=UTF-8,'+encodeURIComponent(svg), size: new google.maps.Size(size,size), anchor: new google.maps.Point(outer,outer) },
            zIndex: 1000 + n
        });
    }
};

var map, infoWin, clusterer, markerList = [], stationIndex = [];
var PIN_SIZE, PIN_ANCHOR;

function initMap() {
    PIN_SIZE   = new google.maps.Size(28, 38);
    PIN_ANCHOR = new google.maps.Point(14, 38);

    map = new google.maps.Map(document.getElementById('map'), {
        center: { lat: 52.37, lng: 4.9 }, zoom: 7,
        styles: MAP_STYLES,
        zoomControl: true, scaleControl: false, rotateControl: false,
        fullscreenControl: false, streetViewControl: false,
        mapTypeControl: false, clickableIcons: false
    });

    infoWin   = new google.maps.InfoWindow({ disableAutoPan: false });
    clusterer = new markerClusterer.MarkerClusterer({
        map: map, markers: [], renderer: clusterRenderer,
        algorithm: new markerClusterer.SuperClusterAlgorithm({ radius: 80 })
    });

    // Close info window when clicking empty map area
    map.addListener('click', function() { infoWin.close(); });

    setupSearch();
    loadStations();
}

function loadStations() {
    fetch('api.php?online=1')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var list = data.stations || [];
            document.getElementById('station-count').textContent =
                list.length + ' station' + (list.length !== 1 ? 's' : '') + ' online';
            renderMarkers(list);
            fitBounds(list);
        })
        .catch(function() {
            document.getElementById('station-count').textContent = 'Could not load';
        });
}

/* ── Lightbox ── */
var lbPics = [], lbIdx = 0;

function openLightbox(idx) {
    lbIdx = idx;
    lbShow();
    document.getElementById('lightbox').classList.add('open');
    document.addEventListener('keydown', lbKey);
}
function lbShow() {
    var p = lbPics[lbIdx];
    document.getElementById('lightbox-img').src = p.full;
    document.getElementById('lightbox-label').textContent =
        p.label + (lbPics.length > 1 ? '  ' + (lbIdx + 1) + ' / ' + lbPics.length : '');
    var showNav = lbPics.length > 1;
    document.getElementById('lightbox-prev').style.display = showNav ? 'flex' : 'none';
    document.getElementById('lightbox-next').style.display = showNav ? 'flex' : 'none';
}
function lbNav(dir, e) {
    e.stopPropagation();
    lbIdx = (lbIdx + dir + lbPics.length) % lbPics.length;
    lbShow();
}
function closeLightbox() {
    document.getElementById('lightbox').classList.remove('open');
    document.getElementById('lightbox-img').src = '';
    document.removeEventListener('keydown', lbKey);
}
function lbKey(e) {
    if (e.key === 'Escape')      closeLightbox();
    if (e.key === 'ArrowLeft')   { lbIdx = (lbIdx - 1 + lbPics.length) % lbPics.length; lbShow(); }
    if (e.key === 'ArrowRight')  { lbIdx = (lbIdx + 1) % lbPics.length; lbShow(); }
}

function openStationInfo(st, mk) {
    lbPics = st.pictures || [];
    var photosHtml = '';
    if (lbPics.length > 0) {
        photosHtml = '<div class="iw-photos">';
        lbPics.forEach(function(p, idx) {
            photosHtml +=
                '<div style="text-align:center">' +
                '<div class="iw-photo-link" style="cursor:zoom-in" onclick="openLightbox(' + idx + ')">' +
                '<img src="' + esc(p.thumb) + '" alt="' + esc(p.label) + '" class="iw-photo">' +
                '</div>' +
                '<div class="iw-photo-label">' + esc(p.label) + '</div>' +
                '</div>';
        });
        photosHtml += '</div>';
    }
    infoWin.setContent(
        '<div class="iw">' +
        '<div class="iw-title">' + esc(st.title) + '</div>' +
        (st.serial ? '<div class="iw-serial">' + esc(st.serial) + '</div>' : '') +
        '<div class="iw-address">' + esc(st.address) + '</div>' +
        photosHtml +
        '<span class="iw-badge"><span class="dot"></span>Online</span>' +
        '</div>'
    );
    infoWin.open({ anchor: mk, map: map });
}

function renderMarkers(list) {
    markerList.forEach(function(m) { m.setMap(null); });
    markerList = [];
    stationIndex = [];
    clusterer.clearMarkers();
    list.forEach(function(s) {
        var m = new google.maps.Marker({
            position: { lat: s.lat, lng: s.lng },
            icon: { url: PIN_SVG, size: PIN_SIZE, scaledSize: PIN_SIZE, anchor: PIN_ANCHOR },
            title: s.title
        });
        (function(st, mk) {
            mk.addListener('click', function() { openStationInfo(st, mk); });
            stationIndex.push({ station: st, marker: mk });
        })(s, m);
        markerList.push(m);
    });
    clusterer.addMarkers(markerList);
}

function fitBounds(list) {
    if (!list.length) return;
    var b = new google.maps.LatLngBounds();
    list.forEach(function(s) { b.extend({ lat: s.lat, lng: s.lng }); });
    map.fitBounds(b);
    if (list.length === 1) map.setZoom(14);
}

function esc(str) {
    var d = document.createElement('div'); d.textContent = str || ''; return d.innerHTML;
}

function setupSearch() {
    var input   = document.getElementById('search-input');
    var results = document.getElementById('search-results');
    var clearBtn = document.getElementById('clear-btn');

    input.addEventListener('input', function() {
        var q = this.value.trim().toLowerCase();
        clearBtn.style.display = this.value ? 'block' : 'none';
        if (!q) { results.style.display = 'none'; return; }

        var matches = stationIndex.filter(function(entry) {
            return entry.station.title.toLowerCase().indexOf(q) !== -1 ||
                   entry.station.address.toLowerCase().indexOf(q) !== -1;
        }).slice(0, 10);

        if (!matches.length) {
            results.innerHTML = '<div class="sr-empty">No stations found</div>';
        } else {
            results.innerHTML = matches.map(function(entry, i) {
                return '<div class="sr-item" data-idx="' + i + '">' +
                    '<div class="sr-title">' + esc(entry.station.title) + '</div>' +
                    '<div class="sr-address">' + esc(entry.station.address) + '</div>' +
                    '</div>';
            }).join('');
            results.querySelectorAll('.sr-item').forEach(function(el, i) {
                el.addEventListener('click', function() {
                    var entry = matches[i];
                    map.panTo(entry.marker.getPosition());
                    map.setZoom(15);
                    openStationInfo(entry.station, entry.marker);
                    results.style.display = 'none';
                    input.value = entry.station.title;
                    clearBtn.style.display = 'block';
                });
            });
        }
        results.style.display = 'block';
    });

    input.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') clearSearch();
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.search-wrap')) {
            results.style.display = 'none';
        }
    });
}

function clearSearch() {
    var input = document.getElementById('search-input');
    input.value = '';
    document.getElementById('clear-btn').style.display = 'none';
    document.getElementById('search-results').style.display = 'none';
    infoWin.close();
}
</script>

<script src="https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars($mapsKey) ?>&loading=async&callback=initMap" async defer></script>

</body>
</html>
