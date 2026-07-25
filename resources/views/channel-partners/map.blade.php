@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Channel Partner Map (Test)</h5>
                </div>
                <div class="card-body p-0">
                    <div id="map" style="height: 70vh; width: 100%;"></div>
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-md-4">
                            <label>Lat: <span id="lat">-</span></label>
                        </div>
                        <div class="col-md-4">
                            <label>Lng: <span id="lng">-</span></label>
                        </div>
                        <div class="col-md-4">
                            <label>Zoom: <span id="zoomLevel">-</span></label>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-12">
                            <label>Found: <span id="count">0</span> partners</label>
                            <button id="refreshBtn" class="btn btn-sm btn-primary float-end">Refresh</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const map = L.map('map').setView([19.0760, 72.8777], 10);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap'
    }).addTo(map);

    let markers = [];
    let userMarker = null;

    function clearMarkers() {
        markers.forEach(m => map.removeLayer(m));
        markers = [];
    }

    function showError(msg) {
        alert(msg);
    }

    async function loadPartners(lat, lng, zoom) {
        document.getElementById('lat').textContent = lat.toFixed(4);
        document.getElementById('lng').textContent = lng.toFixed(4);
        document.getElementById('zoomLevel').textContent = zoom;

        try {
            const response = await fetch(
                `/api/v1/webhook/channel-partner/nearby-map?latitude=${lat}&longitude=${lng}&zoom=${zoom}`,
                {
                    headers: {
                        'X-Signature': '{{ config("webhook.secret") }}',
                        'Accept': 'application/json'
                    }
                }
            );

            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }

            const json = await response.json();
            if (!json.status) {
                throw new Error(json.message || 'API error');
            }

            clearMarkers();

            const partners = json.data || [];
            document.getElementById('count').textContent = partners.length;

            partners.forEach(p => {
                const lat = parseFloat(p.latitude);
                const lng = parseFloat(p.longitude);
                if (!lat || !lng) return;

                const marker = L.marker([lat, lng]).addTo(map);
                marker.bindPopup(`
                    <strong>${p.name || 'Unknown'}</strong><br>
                    ${p.company_name || ''}<br>
                    ${p.city_name || ''}, ${p.state_name || ''}<br>
                    ${p.distance ? Math.round(p.distance * 100) / 100 + ' km' : ''}
                `);
                markers.push(marker);
            });

            console.log('Meta:', json.meta);
        } catch (e) {
            console.error('Load error:', e);
            showError('Failed to load partners: ' + e.message);
        }
    }

    function refresh() {
        const center = map.getCenter();
        const zoom = map.getZoom();
        loadPartners(center.lat, center.lng, zoom);
    }

    // Get user location
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            pos => {
                const lat = pos.coords.latitude;
                const lng = pos.coords.longitude;
                map.setView([lat, lng], 12);
                if (userMarker) map.removeLayer(userMarker);
                userMarker = L.marker([lat, lng], {icon: L.divIcon({className: 'bg-primary rounded-circle', iconSize: [12,12]})}).addTo(map).bindPopup('You are here');
                loadPartners(lat, lng, 12);
            },
            err => {
                console.warn('Geolocation denied:', err);
                refresh();
            }
        );
    } else {
        refresh();
    }

    // Update on zoom/move
    let debounceTimer;
    map.on('zoomend moveend', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(refresh, 500);
    });

    document.getElementById('refreshBtn').addEventListener('click', refresh);
});
</script>
@endsection
