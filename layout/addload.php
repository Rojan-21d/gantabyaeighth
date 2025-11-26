<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../js/sweetalert.js"></script>
    <script src="../js/datevalidation.js"></script>
    <script src="../js/dynamicprice.js"></script>
    <script src="../js/dateselectionaddload.js"></script>
    <script src="../js/geolocation.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <link rel="stylesheet" href="../css/addtable.css">
    <link rel="stylesheet" href="../css/sweetAlert.css">
    <title>Add Load</title>
</head>
<body>

<?php
// Check if the session has not started, then start the session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include '../backend/databaseconnection.php';
require '../backend/calculate_price.php'; // Adjust the path as necessary

// Check if the user is not logged in
if (!isset($_SESSION['email'])) {
    // Redirect the user to the login page
    header("Location: ../login.php");
    exit;
}

if (isset($_POST['signupBtn'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Retrieve and validate form data
        $name = $_POST['name'];
        $origin = $_POST['origin'];
        $destination = $_POST['destination'];
        $distance = $_POST['distance'];
        $description = $_POST['description'];
        $weight = $_POST['weight'];
        $scheduled_time = $_POST['scheduled_time'];
        $origin_latitude = $_POST['origin_latitude'];
        $origin_longitude = $_POST['origin_longitude'];

        // Validate form fields
        $errors = [];

        if (empty($name)) {
            $errors[] = "Name is required.";
        }
        if (empty($origin)) {
            $errors[] = "Origin is required.";
        }
        if (empty($destination)) {
            $errors[] = "Destination is required.";
        }
        if ($origin_latitude === '' || $origin_longitude === '') {
            $errors[] = "Origin latitude and longitude are required.";
        }
        if (empty($distance) || !is_numeric($distance)) {
            $errors[] = "Distance must be a numeric value.";
        }
        if (empty($weight) || !is_numeric($weight)) {
            $errors[] = "Weight must be a numeric value.";
        }
        if ($origin_latitude !== '' && (!is_numeric($origin_latitude) || $origin_latitude < -90 || $origin_latitude > 90)) {
            $errors[] = "Origin latitude must be a numeric value between -90 and 90.";
        }
        if ($origin_longitude !== '' && (!is_numeric($origin_longitude) || $origin_longitude < -180 || $origin_longitude > 180)) {
            $errors[] = "Origin longitude must be a numeric value between -180 and 180.";
        }
        // Validate the scheduled_time field
        if (empty($scheduled_time)) {
            $errors[] = "Scheduled time is required.";
        } else {
            $current_time = date('Y-m-d\TH:i'); // Current date and time in the same format as datetime-local input
            if ($scheduled_time < $current_time) {
                $errors[] = "Scheduled time cannot be in the past.";
            }
        }

        // Correct the file input name from 'image' to 'load_pic'
        if (!empty($_FILES['load_pic']['name'])) {
            $allowed_formats = array('jpg', 'jpeg', 'png');
            $upload_directory = 'img/loadUploads/';
            $img_name = $_FILES['load_pic']['name'];
            $img_extension = pathinfo($img_name, PATHINFO_EXTENSION);

            // Validate the file extension
            if (!in_array(strtolower($img_extension), $allowed_formats)) {
                $errors[] = "Only JPG, JPEG, and PNG images are allowed.";
            } else {
                $uploaded_file_path = $upload_directory . $img_name;
                if (!move_uploaded_file($_FILES['load_pic']['tmp_name'], '../' . $uploaded_file_path)) {
                    $errors[] = "Error uploading the image.";
                }
            }
        } else {
            $uploaded_file_path = 'img/defaultImg/loadimage.jpg';
        }

        // Display errors using SweetAlert
        if (!empty($errors)) {
            $errorMessages = join("<br>", $errors);
            echo '<script>
                Swal.fire({
                    icon: "error",
                    title: "Errors",
                    html: `' . $errorMessages . '`,
                });
            </script>';
        } else {
            // Calculate the dynamic price
            $calculatedPrice = calculateDynamicPrice($conn, $distance, $weight, $scheduled_time);

            $insertSql = "INSERT INTO loaddetails (name, origin, destination, distance, description, weight, status, consignor_id, img_srcs, scheduled_time, price, origin_latitude, origin_longitude)
                    VALUES (?, ?, ?, ?, ?, ?, 'notBooked', ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insertSql);
            $distanceVal = floatval($distance);
            $weightVal = floatval($weight);
            $priceVal = floatval($calculatedPrice);
            $originLatVal = ($origin_latitude === '') ? null : floatval($origin_latitude);
            $originLongVal = ($origin_longitude === '') ? null : floatval($origin_longitude);
            $stmt->bind_param(
                "sssdsdissddd",
                $name,
                $origin,
                $destination,
                $distanceVal,
                $description,
                $weightVal,
                $_SESSION['id'],
                $uploaded_file_path,
                $scheduled_time,
                $priceVal,
                $originLatVal,
                $originLongVal
            );

            if ($stmt->execute()) {
                header("Location: addload.php?success=1");
                exit;
            } else {
                echo '<script>
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: "Database Error: ' . $conn->error . '",
                    });
                </script>';    
            }
        }
    }
}
?>

    <div class="add-main">
        <div class="form-header">
            <p class="eyebrow">New shipment</p>
            <h2>Add Load</h2>
            <p class="subtitle">Share pickup, drop-off, timing, and weight to get an instant price.</p>
        </div>

        <?php if (isset($_GET['success'])) { ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        title: 'Load Added Successfully!',
                        icon: 'success',
                        confirmButtonText: 'OK'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = 'addload.php';
                        }
                    });
                });
            </script>
        <?php } ?>

        <form action="" method="POST" enctype="multipart/form-data" class="addForm">
            <div class="form-section">
                <div class="section-heading">
                    <span class="pill">Basics & details</span>
                    <p class="section-copy">Keep it clear; set weight, time, and image here.</p>
                </div>
                <div class="data-input">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" placeholder="e.g., Pallet of textiles" required>
                </div>
                <div class="data-input">
                    <label for="description">Description</label>
                    <input type="text" id="description" name="description" placeholder="Fragile, stackable, special notes">
                </div>
                <div class="data-input">
                    <label for="weight">Weight (Tons)</label>
                    <input type="number" id="weight" name="weight" min="0" max="50" required autofill="false">
                </div>
                <div class="data-input">
                    <label for="scheduled_time">Scheduled Time</label>
                    <input type="datetime-local" id="scheduled_time" name="scheduled_time" required autofill="false" placeholder="mm/dd/yyyy --:-- --">
                    <small class="helper-text">Format: mm/dd/yyyy hh:mm</small>
                </div>
                <div class="data-input">
                    <label for="load_pic">Image</label>
                    <input class="inpImg" type="file" id="load_pic" name="load_pic" accept="image/*" placeholder="Image">
                </div>
            </div>

            <div class="form-section">
                <div class="section-heading">
                    <span class="pill">Locations & distance</span>
                    <p class="section-copy">Pickup + drop-off details with quick GPS assist.</p>
                </div>
                <div class="data-input">
                    <label for="origin">Origin</label>
                    <input type="text" id="origin" name="origin" placeholder="Pickup city or address" required>
                </div>
                <div class="data-input">
                    <label for="destination">Destination</label>
                    <input type="text" id="destination" name="destination" placeholder="Delivery city or address" required>
                </div>
                <div class="geo-grid">
                    <div class="data-input">
                        <label for="origin_latitude">Origin Latitude</label>
                        <input type="number" step="0.00000001" id="origin_latitude" name="origin_latitude" placeholder="Use GPS" readonly>
                    </div>
                    <div class="data-input">
                        <label for="origin_longitude">Origin Longitude</label>
                        <input type="number" step="0.00000001" id="origin_longitude" name="origin_longitude" placeholder="Use GPS" readonly>
                    </div>
                    <div class="data-input">
                        <label for="destination_latitude">Destination Latitude</label>
                        <input type="number" step="0.00000001" id="destination_latitude" name="destination_latitude" placeholder="Auto-filled" readonly>
                    </div>
                    <div class="data-input">
                        <label for="destination_longitude">Destination Longitude</label>
                        <input type="number" step="0.00000001" id="destination_longitude" name="destination_longitude" placeholder="Auto-filled" readonly>
                    </div>
                </div>
                <div class="data-input">
                    <label for="distance">Distance (KM)</label>
                    <input type="number" id="distance" name="distance" min="0" required readonly>
                </div>
            </div>

            <div class="form-section full-span">
                <div class="map-panel">
                    <div class="map-toolbar">
                        <div class="map-toggle" role="group" aria-label="Choose which pin to set">
                            <button type="button" class="toggle-btn active" data-role="origin">Set origin</button>
                            <button type="button" class="toggle-btn" data-role="destination">Set destination</button>
                        </div>
                        <div class="map-tools">
                            <button type="button" class="ghost-btn" id="plot_selection">Plot selection</button>
                            <button type="button" class="ghost-btn" id="use_gps_origin">Use current location</button>
                            <button type="button" class="map-reset ghost-btn" id="reset_map">Reset map</button>
                            <div class="map-help">Type an address or click the map to drop a pin. Drag markers to fine tune.</div>
                        </div>
                    </div>
                    <div id="load_map" class="map-canvas"></div>
                    <small class="map-status" id="map_status">Using free OpenStreetMap and OSRM; results may be approximate.</small>
                </div>
            </div>

            <div class="form-section full-span">
                <div class="price-row">
                    <div class="note">
                        <small>Price updates automatically based on distance, weight, and scheduled time.</small>
                    </div>
                    <div class="price-chip">
                        <span class="price-label">Calculated Price</span>
                        <span class="price-value" id="calculated_price">0</span>
                    </div>
                </div>
            </div>

            <div class="cta-row full-span">
                <input type="hidden" name="id" value="">
                <button type="submit" name="signupBtn" class="primary-btn">Add Load</button>
                <a class="ghost-btn" href="../">Back Home</a>
            </div>
        </form>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        (function() {
            var mapEl = document.getElementById('load_map');
            if (!mapEl || typeof L === 'undefined') {
                return;
            }

            var originInput = document.getElementById('origin');
            var destinationInput = document.getElementById('destination');
            var originLatInput = document.getElementById('origin_latitude');
            var originLngInput = document.getElementById('origin_longitude');
            var destLatInput = document.getElementById('destination_latitude');
            var destLngInput = document.getElementById('destination_longitude');
            var distanceInput = document.getElementById('distance');
            var statusEl = document.getElementById('map_status');
            var activeRole = 'origin';
            var markers = { origin: null, destination: null };

            var map = L.map('load_map', { zoomControl: true }).setView([27.700769, 85.300140], 6);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);

            function setStatus(msg, isError) {
                if (!statusEl) return;
                statusEl.textContent = msg;
                statusEl.style.color = isError ? '#fca5a5' : '#a7f3d0';
            }

            function debounce(fn, delay) {
                var timer;
                return function() {
                    var args = arguments;
                    clearTimeout(timer);
                    timer = setTimeout(function() {
                        fn.apply(null, args);
                    }, delay);
                };
            }

            function buildLabel(props) {
                var parts = [];
                if (props.name) parts.push(props.name);
                if (props.city && parts.indexOf(props.city) === -1) parts.push(props.city);
                if (props.state && parts.indexOf(props.state) === -1) parts.push(props.state);
                if (props.country && parts.indexOf(props.country) === -1) parts.push(props.country);
                return parts.filter(Boolean).join(', ');
            }

            function ensureMarker(role) {
                if (markers[role]) return markers[role];
                var marker = L.marker([27.700769, 85.300140], { draggable: true }).addTo(map);
                marker.on('dragend', function(e) {
                    var pos = e.target.getLatLng();
                    applyLocation(role, pos.lat, pos.lng, null, true);
                });
                markers[role] = marker;
                return marker;
            }

            function applyLocation(role, lat, lng, label, fromMarker) {
                var latVal = parseFloat(lat);
                var lngVal = parseFloat(lng);
                if (isNaN(latVal) || isNaN(lngVal)) return;
                var latStr = latVal.toFixed(6);
                var lngStr = lngVal.toFixed(6);

                if (role === 'origin') {
                    originLatInput.value = latStr;
                    originLngInput.value = lngStr;
                    if (label) originInput.value = label;
                } else {
                    destLatInput.value = latStr;
                    destLngInput.value = lngStr;
                    if (label) destinationInput.value = label;
                }

                var marker = ensureMarker(role);
                marker.setLatLng([latVal, lngVal]);
                map.setView([latVal, lngVal], Math.max(map.getZoom(), 11));

                if (!label) {
                    reverseGeocode(role, latVal, lngVal);
                }

                if (!fromMarker) {
                    marker.dragging.enable();
                }

                updateRouteDistance();
            }

            function reverseGeocode(role, lat, lng) {
                fetch('https://photon.komoot.io/reverse?lat=' + lat + '&lon=' + lng + '&limit=1')
                    .then(function(res) { return res.json(); })
                    .then(function(data) {
                        var feature = data && data.features && data.features[0];
                        if (!feature) return;
                        var props = feature.properties || {};
                        var label = buildLabel(props);
                        if (role === 'origin' && label) originInput.value = label;
                        if (role === 'destination' && label) destinationInput.value = label;
                    })
                    .catch(function() {});
            }

            function geocodeAndSet(role, text) {
                if (!text || text.length < 3) return;
                setStatus('Finding ' + role + '...', false);
                fetch('https://photon.komoot.io/api/?q=' + encodeURIComponent(text) + '&limit=1')
                    .then(function(res) { return res.json(); })
                    .then(function(data) {
                        var feature = data && data.features && data.features[0];
                        if (!feature) {
                            setStatus('No match for ' + role + '.', true);
                            return;
                        }
                        var coords = feature.geometry && feature.geometry.coordinates;
                        if (!coords || coords.length < 2) {
                            setStatus('No match for ' + role + '.', true);
                            return;
                        }
                        var props = feature.properties || {};
                        var label = buildLabel(props) || text;
                        applyLocation(role, coords[1], coords[0], label);
                        setStatus('Pinned ' + role + '.', false);
                    })
                    .catch(function() {
                        setStatus('Could not search right now.', true);
                    });
            }

            function updateRouteDistance() {
                var oLat = parseFloat(originLatInput.value);
                var oLng = parseFloat(originLngInput.value);
                var dLat = parseFloat(destLatInput.value);
                var dLng = parseFloat(destLngInput.value);
                if (isNaN(oLat) || isNaN(oLng) || isNaN(dLat) || isNaN(dLng)) {
                    return;
                }
                setStatus('Calculating route distance...', false);
                fetch('https://router.project-osrm.org/route/v1/driving/' + oLng + ',' + oLat + ';' + dLng + ',' + dLat + '?overview=false')
                    .then(function(res) { return res.json(); })
                    .then(function(data) {
                        if (!data || data.code !== 'Ok' || !data.routes || !data.routes.length) {
                            throw new Error('no route');
                        }
                        var km = (data.routes[0].distance || 0) / 1000;
                        if (!isNaN(km)) {
                            distanceInput.value = km.toFixed(1);
                            if (typeof Event === 'function') {
                                var ev = new Event('input', { bubbles: true });
                                distanceInput.dispatchEvent(ev);
                            }
                            setStatus('Distance auto-filled (' + km.toFixed(1) + ' km).', false);
                        }
                    })
                    .catch(function() {
                        setStatus('Could not calculate route distance right now.', true);
                    });
            }

            map.on('click', function(e) {
                applyLocation(activeRole, e.latlng.lat, e.latlng.lng, null);
                setStatus('Pinned ' + activeRole + ' from map.', false);
            });

            var toggleButtons = document.querySelectorAll('.toggle-btn[data-role]');
            function setActiveRole(role) {
                activeRole = role;
                for (var i = 0; i < toggleButtons.length; i++) {
                    toggleButtons[i].classList.toggle('active', toggleButtons[i].dataset.role === role);
                }
            }
            for (var i = 0; i < toggleButtons.length; i++) {
                toggleButtons[i].addEventListener('click', function() {
                    setActiveRole(this.dataset.role);
                });
            }
            setActiveRole('origin');

            var debounceOrigin = debounce(function() {
                geocodeAndSet('origin', originInput.value);
            }, 600);

            var debounceDestination = debounce(function() {
                geocodeAndSet('destination', destinationInput.value);
            }, 600);

            originInput.addEventListener('input', debounceOrigin);
            destinationInput.addEventListener('input', debounceDestination);

            var gpsButton = document.getElementById('use_gps_origin');
            if (gpsButton) {
                gpsButton.addEventListener('click', function() {
                    if (typeof requestLocation === 'function') {
                        requestLocation('origin_latitude', 'origin_longitude', null);
                        var attempts = 0;
                        var watch = setInterval(function() {
                            attempts++;
                            var oLat = parseFloat(originLatInput.value);
                            var oLng = parseFloat(originLngInput.value);
                            if (!isNaN(oLat) && !isNaN(oLng)) {
                                applyLocation('origin', oLat, oLng, originInput.value || null, true);
                                clearInterval(watch);
                            }
                            if (attempts > 10) {
                                clearInterval(watch);
                            }
                        }, 800);
                    }
                });
            }

            var plotBtn = document.getElementById('plot_selection');
            if (plotBtn) {
                plotBtn.addEventListener('click', function() {
                    var role = activeRole;
                    var latInput = role === 'origin' ? originLatInput : destLatInput;
                    var lngInput = role === 'origin' ? originLngInput : destLngInput;
                    var textInput = role === 'origin' ? originInput : destinationInput;

                    var latVal = parseFloat(latInput.value);
                    var lngVal = parseFloat(lngInput.value);
                    if (!isNaN(latVal) && !isNaN(lngVal)) {
                        applyLocation(role, latVal, lngVal, textInput.value || null, true);
                        setStatus('Plotted ' + role + ' from saved coordinates.', false);
                        return;
                    }
                    if (textInput.value) {
                        geocodeAndSet(role, textInput.value);
                        return;
                    }
                    setStatus('No ' + role + ' data to plot yet.', true);
                });
            }

            function seedFromFields() {
                var oLat = parseFloat(originLatInput.value);
                var oLng = parseFloat(originLngInput.value);
                var dLat = parseFloat(destLatInput.value);
                var dLng = parseFloat(destLngInput.value);
                if (!isNaN(oLat) && !isNaN(oLng)) {
                    applyLocation('origin', oLat, oLng, originInput.value || null, true);
                }
                if (!isNaN(dLat) && !isNaN(dLng)) {
                    applyLocation('destination', dLat, dLng, destinationInput.value || null, true);
                }
            }

            seedFromFields();

            var resetBtn = document.getElementById('reset_map');
            if (resetBtn) {
                resetBtn.addEventListener('click', function() {
                    if (markers.origin) {
                        map.removeLayer(markers.origin);
                        markers.origin = null;
                    }
                    if (markers.destination) {
                        map.removeLayer(markers.destination);
                        markers.destination = null;
                    }
                    originLatInput.value = '';
                    originLngInput.value = '';
                    destLatInput.value = '';
                    destLngInput.value = '';
                    distanceInput.value = '';
                    setStatus('Map cleared. Set origin/destination again.', false);
                    map.setView([27.700769, 85.300140], 6);
                    if (typeof Event === 'function') {
                        var ev = new Event('input', { bubbles: true });
                        distanceInput.dispatchEvent(ev);
                    }
                });
            }
        })();
    </script>
</body>
</html>
