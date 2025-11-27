<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/headerfooterstyle.css">
    <link rel="stylesheet" href="../css/addtable.css">
    <link rel="stylesheet" href="../css/sweetAlert.css">
    <link rel="stylesheet" href="../css/submit_review.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <script src="../js/sweetalert.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <title>Load Details</title>
</head>
<body>
    <?php
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    require 'databaseconnection.php'; // Database connection
    if (!isset($_SESSION['email'])) {
        header("Location: ../login.php");
        exit;
    }
    include('display_rating.php');
    function showAlert($message, $type = 'error'){
        $title = ($type == "success") ? "Success" : (($type == "error") ? "Error" : "");
        echo "<script>
        Swal.fire({
            icon: '$type',
            title: '$title',
            html: '$message',
        }).then((result) => {
            window.location.href = '../home.php';
        });
    </script>";
    }    
    ?>

    <header>
        <nav>
            <a href="../home.php">
                <img class="logo" src="../img/defaultImg/mainLogo2.png" alt="logo">
            </a>
            <div class="nav-actions">
                <?php if($_SESSION['usertype'] == 'carrier'){ ?>
                <button type="button" id="global_location_status" class="location-indicator" data-status="loading" aria-label="Refreshing location" title="Refreshing location">
                    <span class="location-indicator__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" role="presentation">
                            <path d="M12 2c-3.314 0-6 2.686-6 6c0 4.5 6 12 6 12s6-7.5 6-12c0-3.314-2.686-6-6-6zm0 8a2 2 0 1 1 0-4a2 2 0 0 1 0 4z"></path>
                        </svg>
                    </span>
                    <span class="location-indicator__dot" aria-hidden="true"></span>
                </button>
                <?php } ?>
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
                    <?php if($_SESSION['usertype'] == 'carrier'){?>
                    <a href="../history.php" class="sub-menu-link">
                        <img src="../img/defaultImg/setting.png">
                        <p>History</p>
                    </a>
                    <?php } ?>
                    <a href="../backend/logoutmodule.php" class="sub-menu-link">
                        <img src="../img/defaultImg/logout.png">
                        <p>Logout</p>
                    </a>
                </div>
            </div>
        </nav>
    </header>
    <script src="../js/dropdownmenu.js"></script>
    <?php if($_SESSION['usertype'] == 'carrier'){ ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof startLocationHeartbeat === 'function') {
                startLocationHeartbeat(null, null, 'global_location_status', '../backend/update_location.php', 10 * 60 * 1000);
            }
        });
    </script>
    <?php } ?>

    <?php
    if (isset($_POST['action']) && isset($_POST['id'])) {
        $id = $_POST['id'];
        $_SESSION['load_id'] = $id;
        $shipment_id = isset($_POST['shipment_id']) ? $_POST['shipment_id'] : ''; // Set shipment_id to an empty string if it is not set
        $action = $_POST['action']; 
        if ($action == 'delete') {
            // Delete the row
            $sql = "DELETE FROM loaddetails WHERE id = '$id'";
            $sql2 = "DELETE FROM shipment WHERE load_id = '$id'";
            $conn->query($sql2);
            $img_srcs = $_POST['img_srcs'];
            if (file_exists("../" . $img_srcs) && strpos($img_srcs, 'defaultImg') == false) {
                unlink("../" . $img_srcs);
            }
            $conn->query($sql);
            showAlert("Load Deleted Successfully.", "success");
        } elseif ($action == 'edit') {
            header("Location: updateload.php");
        } elseif ($action == 'cancel') { 
            try {
                $conn->begin_transaction();
                $sql = "UPDATE loaddetails SET status = 'notBooked' WHERE id = '$id'";
                $sql2 = "DELETE FROM shipment WHERE id = '$shipment_id'";
                $conn->query($sql);
                $conn->query($sql2);
                $conn->commit();
                showAlert("Load Canceled Successfully.", "success");
                exit;
            } catch (\Throwable $th) {
                $conn->rollback();
                showAlert("ERROR! ' . $th . '", "error");
                exit();
            }
        } elseif ($action == 'deliver') {
            try {
                $sql = "UPDATE loaddetails SET status = 'delivered' WHERE id = '$id'";
                $conn->query($sql);
                $sql2 = "UPDATE shipment SET delivered_time = NOW() WHERE load_id = '$id'";
                $conn->query($sql2);
                showAlert("Load Delivered Marked.", "success");
                exit;
            } catch (\Throwable $th) {
                $conn->rollback();
                showAlert("ERROR! ' . $th . '", "error");
                exit();
            }
        } elseif ($action == 'more') {
            $sql = "SELECT * FROM loaddetails WHERE id = '$id'";
            $result = $conn->query($sql);
            $more = mysqli_fetch_assoc($result);
            $stat = $more['status'];
            $sql2 = "SELECT * FROM shipment WHERE load_id = '$id'";
            $result2 = $conn->query($sql2);
            $row = mysqli_fetch_array($result2);
            ?>

            <?php
            $statusLabel = ucfirst($stat);
            $deliveredTime = null;
            $deliveryNote = null;
            if ($stat === 'delivered') {
                $sql2 = "SELECT delivered_time FROM shipment WHERE load_id = '$id'";
                $result2 = $conn->query($sql2);
                if ($result2 && $result2->num_rows > 0) {
                    $row2 = $result2->fetch_assoc();
                    $deliveredTime = $row2['delivered_time'];
                    $scheduled_time_dt = new DateTime($more['scheduled_time']);
                    $delivered_time_dt = new DateTime($deliveredTime);
                    $interval = $scheduled_time_dt->diff($delivered_time_dt);
                    $days = $interval->days;
                    $hours = $interval->h;
                    if ($days === 0 && $hours === 0) {
                        $deliveryNote = "Delivery was on time.";
                    } else {
                        $aheadOrLate = ($interval->invert == 1) ? "ahead of" : "late by";
                        $deliveryNote = "Delivery was $aheadOrLate {$days}d {$hours}h.";
                    }
                }
            }
            ?>

            <div class="add-main">
                <div class="form-header">
                    <p class="eyebrow">Load Details</p>
                    <h2><?php echo htmlspecialchars($more['name']); ?></h2>
                    <div class="status-chip status-<?php echo htmlspecialchars($stat); ?>"><?php echo htmlspecialchars($statusLabel); ?></div>
                    <p class="subtitle">Review shipment info, participants, and take action.</p>
                    <div class="cta-row">
                        <a class="ghost-btn" href="../home.php">Back</a>
                    </div>
                </div>

                <div class="detail-card">
                    <div class="detail-media">
                        <img src="../<?php echo $more['img_srcs']; ?>" alt="Load image">
                    </div>
                    <ul class="kv-list">
                        <li><span>Origin</span><span><?php echo htmlspecialchars($more['origin']); ?></span></li>
                        <li><span>Destination</span><span><?php echo htmlspecialchars($more['destination']); ?></span></li>
                        <li><span>Distance</span><span><?php echo htmlspecialchars($more['distance']); ?> km</span></li>
                        <li><span>Weight</span><span><?php echo htmlspecialchars($more['weight']); ?> ton</span></li>
                        <li><span>Description</span><span><?php echo htmlspecialchars($more['description']); ?></span></li>
                        <li><span>Scheduled</span><span><?php echo htmlspecialchars($more['scheduled_time']); ?></span></li>
                        <?php if ($deliveredTime) { ?>
                            <li><span>Delivered</span><span><?php echo htmlspecialchars($deliveredTime); ?></span></li>
                            <?php if ($deliveryNote) { ?>
                                <li><span>Note</span><span><?php echo htmlspecialchars($deliveryNote); ?></span></li>
                            <?php } ?>
                        <?php } ?>
                    </ul>

                    <div class="detail-section">
                        <h3 class="card-title">Map Details</h3>
                        <div id="load_map" class="map-canvas"></div>
                        <small class="map-status" id="map_status">Using free OpenStreetMap and OSRM; results may be approximate.</small>
                    </div>

                    <?php
                    if ($_SESSION['usertype'] == "carrier") {
                        $sql3 = "SELECT consignordetails.id as consignorID, consignordetails.name, consignordetails.email, consignordetails.address, consignordetails.contact, consignordetails.img_srcs
                            FROM consignordetails
                            INNER JOIN shipment ON consignordetails.id = shipment.consignor_id    
                            WHERE shipment.load_id = '$id'";
                        $result3 = $conn->query($sql3);
                        ?>
                        <div class="detail-section">
                            <h3 class="card-title">Load By</h3>
                            <?php
                            if ($result3 === false) {
                                echo "<p class='note'>Error: " . htmlspecialchars($conn->error) . "</p>";
                            } else {
                                $rowShip = mysqli_fetch_assoc($result3);
                                if ($rowShip === null) {
                                    echo "<p class='note'>No booking information available.</p>";
                                } else {
                                    ?>
                                    <div class="person">
                                        <img src="../<?php echo $rowShip["img_srcs"]; ?>" alt="Consignor" class="avatar">
                                        <div>
                                            <p class="person-name"><?php echo htmlspecialchars($rowShip["name"]); ?></p>
                                            <p class="person-meta"><?php echo htmlspecialchars($rowShip["email"]); ?></p>
                                            <p class="person-meta"><?php echo htmlspecialchars($rowShip["contact"]); ?></p>
                                            <p class="person-meta"><?php echo htmlspecialchars($rowShip["address"]); ?></p>
                                        </div>
                                    </div>
                                    <?php
                                }
                            }
                            ?>
                        </div>
                        <div class="detail-section detail-actions">
                            <h3 class="card-title">Actions</h3>
                            <?php if ($stat !== 'delivered') { ?>
                            <div class="action-row">
                                <form action="" method="post" onsubmit="confirmCancel(event)">
                                    <input type="hidden" name="action" value="cancel">
                                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                                    <input type="hidden" name="shipment_id" value="<?php echo $row['id']; ?>">
                                    <button type="submit" class="ghost-btn">Cancel</button>
                                </form>
                                <form action="" method="post" onsubmit="confirmDeliver(event)">
                                    <input type="hidden" name="action" value="deliver">
                                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                                    <button type="submit" class="primary-btn">Delivered</button>
                                </form>
                            </div>
                            <?php } else { ?>
                                <p class="note">Delivered</p>
                            <?php } ?>
                        </div>
                        <?php
                    } elseif ($_SESSION['usertype'] == "consignor") {
                        $sql3 = "SELECT shipment.id AS shipmentID, carrierdetails.id as carrierID, carrierdetails.name, carrierdetails.email, carrierdetails.address, carrierdetails.contact, carrierdetails.img_srcs
                            FROM carrierdetails
                            INNER JOIN shipment ON carrierdetails.id = shipment.carrier_id
                            WHERE shipment.load_id = '$id'";
                        $result3 = $conn->query($sql3);
                        ?>
                        <div class="detail-section">
                            <h3 class="card-title">Booked By</h3>
                            <?php
                            if ($result3 === false) {
                                echo "<p class='note'>Error: " . htmlspecialchars($conn->error) . "</p>";
                            } else {
                                $rowShip = mysqli_fetch_assoc($result3);
                                if ($rowShip === null) {
                                    echo "<p class='note'>No booking information available.</p>";
                                } else {
                                    ?>
                                    <div class="person">
                                        <img src="../<?php echo $rowShip["img_srcs"]; ?>" alt="Carrier" class="avatar">
                                        <div>
                                            <p class="person-name"><?php echo htmlspecialchars($rowShip["name"]); ?></p>
                                            <p class="person-meta"><?php echo htmlspecialchars($rowShip["email"]); ?></p>
                                            <p class="person-meta"><?php echo htmlspecialchars($rowShip["contact"]); ?></p>
                                            <p class="person-meta"><?php echo htmlspecialchars($rowShip["address"]); ?></p>
                                        </div>
                                    </div>
                                    <?php if ($stat !== 'delivered') { ?>
                                        <form action="" method="post" onsubmit="confirmCancel(event)" class="single-action">
                                            <input type="hidden" name="action" value="cancel">
                                            <input type="hidden" name="id" value="<?php echo $id; ?>">
                                            <input type="hidden" name="shipment_id" value="<?php echo $row['id']; ?>">
                                            <button type="submit" class="ghost-btn">Cancel</button>
                                        </form>
                                    <?php } else { ?>
                                        <p class="note">Delivered</p>
                                    <?php } ?>
                                    <?php
                                }
                            }
                            ?>
                        </div>
                        <div class="detail-section detail-actions">
                            <h3 class="card-title">Actions</h3>
                            <div class="action-row">
                                <?php if ($stat !== 'delivered') { ?>
                                <form action="" method="post" class="moreBtn">
                                    <input type="hidden" name="action" value="edit">
                                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                                    <button type="submit" class="primary-btn">Edit</button>
                                </form>
                                <?php } ?>
                                <form action="" method="post" class="deleteBtn" onsubmit="confirmDelete(event)">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                                    <input type="hidden" name="img_srcs" value="<?php echo htmlspecialchars($more['img_srcs'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <button type="submit" class="ghost-btn">Delete</button>
                                </form>
                            </div>
                        </div>
                        <?php
                    }
                    ?>
                </div>
            </div>
            <?php
        }
    }
    include '../layout/footer.php';
    ?>
    <script src="../js/confirmationSA.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var mapEl = document.getElementById('load_map');
            if (!mapEl || typeof L === 'undefined') {
                return;
            }

            var originLat = <?php echo json_encode($more['origin_latitude']); ?>;
            var originLng = <?php echo json_encode($more['origin_longitude']); ?>;
            var destLat = <?php echo json_encode($more['destination_latitude']); ?>;
            var destLng = <?php echo json_encode($more['destination_longitude']); ?>;

            var map = L.map('load_map').setView([27.700769, 85.300140], 6);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);

            var markers = [];

            if (originLat && originLng) {
                var originMarker = L.marker([originLat, originLng]).addTo(map);
                originMarker.bindPopup('Origin: <?php echo htmlspecialchars($more['origin']); ?>');
                markers.push(originMarker);
            }

            if (destLat && destLng) {
                var destMarker = L.marker([destLat, destLng]).addTo(map);
                destMarker.bindPopup('Destination: <?php echo htmlspecialchars($more['destination']); ?>');
                markers.push(destMarker);
            }

            if (markers.length > 0) {
                var group = new L.featureGroup(markers);
                map.fitBounds(group.getBounds());
            }

            // Optional: Draw route if both points are available
            if (originLat && originLng && destLat && destLng) {
                fetch('https://router.project-osrm.org/route/v1/driving/' + originLng + ',' + originLat + ';' + destLng + ',' + destLat + '?overview=full&geometries=geojson')
                    .then(function(res) { return res.json(); })
                    .then(function(data) {
                        if (data.routes && data.routes.length > 0) {
                            var route = data.routes[0].geometry;
                            L.geoJSON(route).addTo(map);
                        }
                    })
                    .catch(function() {});
            }
        });
    </script>
</body>
</html>
