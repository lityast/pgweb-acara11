<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <title>Web GIS Flores Timur - Geoserver</title>
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        html, body, #map {
            width: 100%;
            height: 100vh;
            margin: 0;
            padding: 0;
        }
        /* CSS untuk legenda agar tampil rapi, permanen, dan bisa digulir */
        .info.legend {
            background: white;
            padding: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.2);
            border-radius: 5px;
            line-height: 1.5em;
            max-height: 220px; /* Batas tinggi maksimal kotak legenda */
            overflow-y: auto;  /* Aktifkan scroll vertikal jika konten melebihi batas */
            overflow-x: hidden;
        }
        .info.legend img {
            max-width: 150px;
            height: auto;
            display: block;
            margin-bottom: 5px;
        }
        /* CSS untuk kotak popup informasi saat polygon diklik */
        .leaflet-popup-content {
            font-size: 14px;
            font-weight: bold;
        }
    </style>
</head>
<body>

    <!-- Wadah Peta -->
    <div id="map"></div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <script>
        // 1. Inisialisasi Peta dengan koordinat Flores Timur
        var map = L.map("map").setView([-8.35, 122.95], 10);

        // 2. Base Map OpenStreetMap
        var osm = L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        });
        osm.addTo(map);

        // 3. Layer WMS Batas Kecamatan
        var wmsLayer1 = L.tileLayer.wms("http://localhost:8080/geoserver/wms", {
            layers: "pgweb:batas_kecamatan",
            format: "image/png",
            transparent: true,
            version: "1.1.0"
        });
        wmsLayer1.addTo(map);

        // 4. Layer WMS Jalan
        var wmsLayer2 = L.tileLayer.wms("http://localhost:8080/geoserver/wms", {
            layers: "pgweb:jalan",
            format: "image/png",
            transparent: true,
            version: "1.1.0"
        });
        wmsLayer2.addTo(map);

        // 5. Pengaturan Kontrol Layer di Pojok Kanan Atas
        var baseMaps = {
            "OpenStreetMap": osm
        };

        var overlayMaps = {
            "Batas Kecamatan": wmsLayer1,
            "Jalan": wmsLayer2
        };

        L.control.layers(baseMaps, overlayMaps).addTo(map);

        // 6. Menambahkan Skala Peta di Pojok Kiri Bawah
        L.control.scale({
            position: 'bottomleft',
            metric: true,      // Menampilkan satuan meter/kilometer
            imperial: false    // Menyembunyikan satuan mil/kaki agar lebih bersih
        }).addTo(map);

        // 7. Menambahkan Legenda Gabungan (Batas Kecamatan & Jalan) di Pojok Kiri Bawah
        var legend = L.control({position: 'bottomleft'});

        legend.onAdd = function (map) {
            var div = L.DomUtil.create('div', 'info legend');
            
            // Mencegah scroll mouse pada legenda memicu zoom pada peta
            L.DomEvent.disableScrollPropagation(div);
            L.DomEvent.disableClickPropagation(div);
            
            // URL Legenda Batas Kecamatan
            var urlKecamatan = "http://localhost:8080/geoserver/wms?REQUEST=GetLegendGraphic&VERSION=1.0.0&FORMAT=image/png&LAYER=pgweb:batas_kecamatan";
            
            // URL Legenda Jalan
            var urlJalan = "http://localhost:8080/geoserver/wms?REQUEST=GetLegendGraphic&VERSION=1.0.0&FORMAT=image/png&LAYER=pgweb:jalan";
            
            div.innerHTML = '<b>Legenda Peta</b><br>' +
                            '<b>Batas Kecamatan</b><br><img src="' + urlKecamatan + '" alt="legend-kecamatan"><br>' +
                            '<b>Jalan</b><br><img src="' + urlJalan + '" alt="legend-jalan">';
            return div;
        };

        legend.addTo(map);

        // 8. Fitur Klik Polygon untuk Menampilkan Informasi Kecamatan (GetFeatureInfo)
        function getFeatureInfoUrl(map, layer, latlng, params) {
            var point = map.latLngToContainerPoint(latlng, map.getZoom()),
                size = map.getSize(),
                bounds = map.getBounds(),
                sw = bounds.getSouthWest(),
                ne = bounds.getNorthEast();

            var defaultParams = {
                request: 'GetFeatureInfo',
                service: 'WMS',
                srs: 'EPSG:4326',
                version: layer.options.version,
                format: layer.options.format,
                bbox: sw.lng + ',' + sw.lat + ',' + ne.lng + ',' + ne.lat,
                width: size.x,
                height: size.y,
                query_layers: layer.options.layers,
                info_format: 'text/html'
            };

            params = L.Util.extend(defaultParams, params || {});
            params[params.version === '1.3.0' ? 'i' : 'x'] = Math.round(point.x);
            params[params.version === '1.3.0' ? 'j' : 'y'] = Math.round(point.y);

            return layer._url + L.Util.getParamString(params, layer._url, true);
        }

        map.addEventListener('click', function (e) {
            var url = getFeatureInfoUrl(map, wmsLayer1, e.latlng, {
                'info_format': 'application/json'
            });

            // Mengambil data dari GeoServer menggunakan Fetch API
            fetch(url)
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    if (data.features && data.features.length > 0) {
                        var props = data.features[0].properties;
                        var namaKecamatan = props.NAMOBJ || props.nama_kecamatan || "Tidak ada data nama";
                        
                        L.popup()
                            .setLatLng(e.latlng)
                            .setContent("Kecamatan: " + namaKecamatan)
                            .openOn(map);
                    }
                })
                .catch(function (error) {
                    console.log("Error mengambil data feature info: ", error);
                });
        });
    </script>

</body>
</html>