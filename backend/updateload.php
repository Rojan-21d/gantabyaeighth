<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/headerfooterstyle.css">
    <link rel="stylesheet" href="../css/addtable.css">
    <link rel="stylesheet" href="../css/sweetAlert.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../js/sweetalert.js"></script>
    <script src="../js/imgPreview.js"></script>
    <script src="../js/dynamicprice.js"></script>
    <script src="../js/geolocation.js"></script>
    <title>Update Load</title>
</head>
<body>
    <?php
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    require 'databaseconnection.php';
    require '../backend/calculate_price.php';

    if (!isset($_SESSION['email'])) {
        header("Location: ../login.php");
        exit;
    }

    function showAlert($message, $type = 'error', $redirect = false) {
        $title = ($type == "success") ? "Success" : "Error";
        $redirectScript = $redirect ? "window.location.href = '../home.php';" : '';
        echo "<script>
            Swal.fire({
                icon: '$type',
                title: '$title',
                html: '$message',
            }).then((result) => {
                $redirectScript
            });
        </script>";
    }

    function validateInput($data) {
        return htmlspecialchars(trim($data));
    }

    function fetchLoadDetails($conn, $loadId) {
        $sql = "SELECT * FROM loaddetails WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('i', $loadId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();
            return $row;
        } else {
            showAlert("Load details fetching failed: " . $conn->error);
            return false;
        }
    }

    $row = fetchLoadDetails($conn, $_SESSION['load_id']);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
        $id = $_POST['id'];
        $name = validateInput($_POST["name"]);
        $origin = validateInput($_POST["origin"]);
        $destination = validateInput($_POST['destination']);
        $distance = validateInput($_POST['distance']);
        $description = validateInput($_POST['description']);
        $weight = validateInput($_POST['weight']);
        $scheduled_time = validateInput($_POST['scheduled_time']);
        $origin_latitude = validateInput($_POST['origin_latitude']);
        $origin_longitude = validateInput($_POST['origin_longitude']);
        $destination_latitude = validateInput($_POST['destination_latitude']);
        $destination_longitude = validateInput($_POST['destination_longitude']);

        $errors = [];
        if (empty($name) || empty($origin) || empty($destination) || empty($distance) || empty($weight) || empty($scheduled_time)) {
            $errors[] = "All fields are required.";
        }
        if (!is_numeric($distance)) $errors[] = "Distance must be a numeric value.";
        if (!is_numeric($weight)) $errors[] = "Weight must be a numeric value.";
        if ($origin_latitude === '' || $origin_longitude === '') $errors[] = "Origin latitude and longitude are required.";
        if ($origin_latitude !== '' && (!is_numeric($origin_latitude) || $origin_latitude < -90 || $origin_latitude > 90)) $errors[] = "Origin latitude must be a numeric value between -90 and 90.";
        if ($origin_longitude !== '' && (!is_numeric($origin_longitude) || $origin_longitude < -180 || $origin_longitude > 180)) $errors[] = "Origin longitude must be a numeric value between -180 and 180.";

        if (!empty($errors)) {
            showAlert(implode("<br>", $errors));
        } else {
            $imageDestination = '';
            if (!empty($_FILES['image']['tmp_name'])) {
                if (file_exists("../".$row['img_srcs']) && strpos($row['img_srcs'], 'defaultImg') === false){
                    unlink("../".$row['img_srcs']);
                }
                $image = $_FILES['image'];
                $imageFileName = $image['name'];
                $imageTempName = $image['tmp_name'];
                $imageDestination = 'img/loadUploads/' . $imageFileName;
                if (!move_uploaded_file($imageTempName, "../". $imageDestination)) {
                    showAlert('Failed to upload image.');
                    $imageDestination = '';
                }
            }

            $calculatedPrice = calculateDynamicPrice($conn, $distance, $weight, $scheduled_time);

            $sql = "UPDATE loaddetails SET name=?, origin=?, destination=?, distance=?, description=?, weight=?, scheduled_time=?, price=?, origin_latitude=?, origin_longitude=?, destination_latitude=?, destination_longitude=?";
            $params = [$name, $origin, $destination, floatval($distance), $description, floatval($weight), $scheduled_time, floatval($calculatedPrice), floatval($origin_latitude), floatval($origin_longitude), floatval($destination_latitude), floatval($destination_longitude)];
            $types = "sssdsdsddddd";

            if (!empty($imageDestination)) {
                $sql .= ", img_srcs = ?";
                $params[] = $imageDestination;
                $types .= "s";
            }
            $sql .= " WHERE id = ?";
            $params[] = $id;
            $types .= "i";

            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param($types, ...$params);
                if ($stmt->execute()) {
                    showAlert('Updated Successfully', 'success', true);
                } else {
                    showAlert('Update Failed: ' . $stmt->error);
                }
                $stmt->close();
            } else {
                showAlert('Update Query Preparation Failed: ' . $conn->error);
            }
        }
        if (empty($errors)) {
            $row = fetchLoadDetails($conn, $_SESSION['load_id']);
        }
    }
    ?>

    <header>
        <nav>
            <a href="../home.php">
                <img class="logo" src="../img/defaultImg/mainLogo2.png" alt="logo">
            </a>
            <div class="nav-actions">
                <div class="nav__links">
                    <img src="../<?php echo $_SESSION['profilePic'] ?>" onclick='toggleMenu()'>
                </div>
            </div>
            <div class="sub-menu-wrap" id="subMenu">
                <div class="sub-menu">
                    <div class="user-info">
                        <img src="../<?php echo $_SESSION['profilePic'] ?>">
                        <h2><?php echo $_SESSION['name'];?></h2>
                    </div>
                    <hr>
                    <a href="../profile.php" class="sub-menu-link">
                        <img src="../<?php echo $_SESSION['profilePic'] ?>">
                        <p>Profile</p>
                    </a>
                    <a href="../home.php" class="sub-menu-link">
                        <img src="../img/defaultImg/home.png">
                        <p>Home</p>
                    </a>
                    <a href="../backend/logoutmodule.php" class="sub-menu-link">
                        <img src="../img/defaultImg/logout.png">
                        <p>Logout</p>
                    </a>
                </div>
            </div>
        </nav>
    </header>
    <script src="../js/dropdownmenu.js"></script>

    <div class="add-main">
        <div class="form-header">
            <p class="eyebrow">Update Load Details</p>
            <h2><?php echo htmlspecialchars($row['name']); ?></h2>
            <p class="subtitle">Edit the shipment information below.</p>
        </div>
        <form action="" method="POST" enctype="multipart/form-data" class="addForm">
            <div class="form-section">
                <div class="section-heading">
                    <span class="pill">Basics & details</span>
                </div>
                <div class="data-input">
                    <label for="name">Name:</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($row['name'] ?? ''); ?>">
                </div>
                <div class="data-input">
                    <label for="description">Description</label>
                    <input type="text" id="description" name="description" value="<?php echo htmlspecialchars($row['description'] ?? ''); ?>">
                </div>
                <div class="data-input">
                    <label for="weight">Weight (Tons)</label>
                    <input type="number" id="weight" name="weight" min="0" max="50" required value="<?php echo htmlspecialchars($row['weight'] ?? ''); ?>">
                </div>
                <div class="data-input">
                    <label for="scheduled_time">Scheduled Time</label>
                    <input type="datetime-local" id="scheduled_time" name="scheduled_time" required value="<?php echo htmlspecialchars($row['scheduled_time'] ?? ''); ?>">
                </div>
                <div class="data-input">
                    <label for="pic">Image</label>
                    <img src="../<?php echo $row['img_srcs']; ?>" alt="Load Picture" id="PicPreview" style="max-width: 100%; height: auto; border-radius: 12px; margin-bottom: 10px;">
                    <input type="file" name="image" id="pic" accept="image/*" style="display: none;" onchange="previewImage(event)">
                    <button type="button" class="primary-btn" onclick="document.getElementById('pic').click();">Change Image</button>
                </div>
            </div>

            <div class="form-section">
                <div class="section-heading">
                    <span class="pill">Locations & distance</span>
                </div>
                <div class="data-input">
                    <label for="origin">Origin</label>
                    <input type="text" id="origin" name="origin" value="<?php echo htmlspecialchars($row['origin'] ?? ''); ?>" required>
                </div>
                <div class="data-input">
                    <label for="destination">Destination</label>
                    <input type="text" id="destination" name="destination" value="<?php echo htmlspecialchars($row['destination'] ?? ''); ?>" required>
                </div>
                <div class="geo-grid">
                    <div class="data-input">
                        <label for="origin_latitude">Origin Latitude</label>
                        <input type="number" step="0.00000001" id="origin_latitude" name="origin_latitude" value="<?php echo htmlspecialchars($row['origin_latitude'] ?? ''); ?>" readonly>
                    </div>
                    <div class="data-input">
                        <label for="origin_longitude">Origin Longitude</label>
                        <input type="number" step="0.00000001" id="origin_longitude" name="origin_longitude" value="<?php echo htmlspecialchars($row['origin_longitude'] ?? ''); ?>" readonly>
                    </div>
                    <div class="data-input">
                        <label for="destination_latitude">Destination Latitude</label>
                        <input type="number" step="0.00000001" id="destination_latitude" name="destination_latitude" value="<?php echo htmlspecialchars($row['destination_latitude'] ?? ''); ?>" readonly>
                    </div>
                    <div class="data-input">
                        <label for="destination_longitude">Destination Longitude</label>
                        <input type="number" step="0.00000001" id="destination_longitude" name="destination_longitude" value="<?php echo htmlspecialchars($row['destination_longitude'] ?? ''); ?>" readonly>
                    </div>
                </div>
                <div class="data-input">
                    <label for="distance">Distance (KM)</label>
                    <input type="number" id="distance" name="distance" min="0" required readonly value="<?php echo htmlspecialchars($row['distance'] ?? ''); ?>">
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
                        <span class="price-value" id="calculated_price"><?php echo htmlspecialchars($row['price'] ?? '0'); ?></span>
                    </div>
                </div>
            </div>

            <div class="cta-row full-span">
                <input type="hidden" name="id" value="<?php echo $_SESSION['load_id']; ?>">
                <button type="submit" class="primary-btn">Update Load</button>
                <a href="../home.php" class="ghost-btn">Back</a>
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
    <?php include '../layout/footer.php'; ?>
</body>
</html>
