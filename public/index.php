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
        .iw             { padding: 13px 15px; min-width: 185px; max-width: 260px; }
        .iw-title       { font-size: 14px; font-weight: 700; color: #111; margin-bottom: 3px; }
        .iw-serial      { font-size: 11px; color: #aaa; margin-bottom: 5px; font-family: monospace; }
        .iw-address     { font-size: 12px; color: #666; line-height: 1.5; margin-bottom: 8px; }
        .iw-badge       { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 600; padding: 3px 9px; border-radius: 20px; background: #f0ebfa; color: #5b21b6; }
        .iw-badge .dot  { width: 6px; height: 6px; border-radius: 50%; background: #7c3aed; }

        /* ── Google Places ── */
        .pac-container  { border-radius: 10px !important; box-shadow: 0 6px 18px rgba(0,0,0,0.10) !important; border: 1px solid rgba(0,0,0,0.07) !important; font-family: inherit !important; font-size: 14px !important; margin-top: 4px; }
        .pac-item       { padding: 7px 12px !important; border: none !important; cursor: pointer; }
        .pac-item:hover,.pac-item-selected { background: #f5f5f5 !important; }
        .pac-item .pac-item-query { font-weight: 500 !important; color: #000 !important; font-size: 14px !important; }
        .pac-item span  { font-size: 12px !important; color: #888 !important; }
        div.pac-container:after { display: none !important; }
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

<div class="search-bar">
    <svg width="16" height="16" viewBox="0 0 20 20" fill="none" style="flex-shrink:0">
        <circle cx="8.5" cy="8.5" r="6.5" stroke="#bbb" stroke-width="2"/>
        <path d="M13.5 13.5L18 18" stroke="#bbb" stroke-width="2" stroke-linecap="round"/>
    </svg>
    <input id="search-input" type="text" placeholder="Search address or location…" autocomplete="off">
    <button id="clear-btn" onclick="clearSearch()" aria-label="Clear">×</button>
</div>

<div id="map"></div>

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

var map, infoWin, clusterer, searchMarker, markerList = [];
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

function renderMarkers(list) {
    markerList.forEach(function(m) { m.setMap(null); });
    markerList = [];
    clusterer.clearMarkers();
    list.forEach(function(s) {
        var m = new google.maps.Marker({
            position: { lat: s.lat, lng: s.lng },
            icon: { url: PIN_SVG, size: PIN_SIZE, scaledSize: PIN_SIZE, anchor: PIN_ANCHOR },
            title: s.title
        });
        (function(st, mk) {
            mk.addListener('click', function() {
                infoWin.setContent(
                    '<div class="iw">' +
                    '<div class="iw-title">' + esc(st.title) + '</div>' +
                    (st.serial ? '<div class="iw-serial">' + esc(st.serial) + '</div>' : '') +
                    '<div class="iw-address">' + esc(st.address) + '</div>' +
                    '<span class="iw-badge"><span class="dot"></span>Online</span>' +
                    '</div>'
                );
                infoWin.open({ anchor: mk, map: map });
            });
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
    var input = document.getElementById('search-input');
    var ac = new google.maps.places.Autocomplete(input, {
        types: ['geocode', 'establishment'],
        componentRestrictions: { country: ['fr','de','nl','be','es','it','pl','se','dk','fi','pt','at','ie','gr','cz','hu','sk','si','hr','lt','lv','ee','bg','ro'] }
    });
    ac.addListener('place_changed', function() {
        var place = ac.getPlace();
        if (!place.geometry || !place.geometry.location) return;
        document.getElementById('clear-btn').style.display = 'block';
        if (searchMarker) searchMarker.setMap(null);
        searchMarker = new google.maps.Marker({
            position: place.geometry.location, map: map,
            icon: { url: SEARCH_SVG, size: PIN_SIZE, scaledSize: PIN_SIZE, anchor: PIN_ANCHOR }
        });
        infoWin.setContent(
            '<div class="iw">' +
            '<div class="iw-title">' + esc(place.name || place.formatted_address || '') + '</div>' +
            '<div class="iw-address">' + esc(place.formatted_address || '') + '</div>' +
            '</div>'
        );
        infoWin.open({ anchor: searchMarker, map: map });
        map.panTo(place.geometry.location);
        map.setZoom(14);
    });
    input.addEventListener('input', function() {
        document.getElementById('clear-btn').style.display = this.value ? 'block' : 'none';
    });
}

function clearSearch() {
    document.getElementById('search-input').value = '';
    document.getElementById('clear-btn').style.display = 'none';
    if (searchMarker) { searchMarker.setMap(null); searchMarker = null; }
    infoWin.close();
}
</script>

<script src="https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars($mapsKey) ?>&libraries=places&loading=async&callback=initMap" async defer></script>

</body>
</html>
