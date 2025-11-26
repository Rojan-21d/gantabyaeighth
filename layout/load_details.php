<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['email'])) {
    header("Location: ../login.php");
    exit;
}

require '../backend/databaseconnection.php';

$loadId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($loadId <= 0) {
    header("Location: ../home.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT ld.*, cd.name AS consignor_name, cd.email AS consignor_email, cd.contact AS consignor_contact,
           cd.address AS consignor_address, cd.img_srcs AS consignor_img
    FROM loaddetails ld
    JOIN consignordetails cd ON ld.consignor_id = cd.id
    WHERE ld.id = ?
");

if (!$stmt) {
    header("Location: ../home.php");
    exit;
}

$stmt->bind_param("i", $loadId);
$stmt->execute();
$load = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$load) {
    header("Location: ../home.php");
    exit;
}

$hasCoords = !empty($load['origin_latitude']) && !empty($load['origin_longitude']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/headerfooterstyle.css">
    <link rel="stylesheet" href="../css/addtable.css">
    <link rel="stylesheet" href="../css/maincontentstyle.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <title>Load Details</title>
</head>
<body>
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

    <div class="add-main">
        <div class="form-header">
            <p class="eyebrow">Load Detail</p>
            <h2><?php echo htmlspecialchars($load['name']); ?></h2>
            <p class="subtitle">Full view of this load with map and consignor contact.</p>
            <div class="cta-row">
                <a class="ghost-btn cta-btn" style="width:200px;" href="../home.php">Back</a>
                <form action="../backend/booking.php" method="post">
                    <input type="hidden" name="action" value="book">
                    <input type="hidden" name="load_id" value="<?php echo $load['id']; ?>">
                    <input type="hidden" name="carrier_id" value="<?php echo $_SESSION['id']; ?>">
                    <input type="hidden" name="consignor_id" value="<?php echo $load['consignor_id']; ?>">
                    <button type="submit" class="primary-btn cta-btn" style="width:200px;">Book this load</button>
                </form>
            </div>
        </div>

        <div class="detail-card">
            <div class="content-detail">
                <div class="content-image">
                    <img src="../<?php echo $load['img_srcs']; ?>" alt="Load image">
                </div>
                <div class="content-description">
                    <h3>Summary</h3>
                    <ul>
                        <li>Origin: <?php echo htmlspecialchars($load['origin']); ?></li>
                        <li>Destination: <?php echo htmlspecialchars($load['destination']); ?></li>
                        <li>Distance: <?php echo htmlspecialchars($load['distance']); ?> km</li>
                        <li>Weight: <?php echo htmlspecialchars($load['weight']); ?> ton</li>
                        <li>Price: <?php echo htmlspecialchars($load['price']); ?></li>
                        <li>Description: <?php echo htmlspecialchars($load['description']); ?></li>
                        <li>Scheduled: <?php echo htmlspecialchars($load['scheduled_time']); ?></li>
                    </ul>
                </div>
                <div class="content-description">
                    <h3>Consignor</h3>
                    <ul>
                        <li>Name: <?php echo htmlspecialchars($load['consignor_name']); ?></li>
                        <li>Email: <?php echo htmlspecialchars($load['consignor_email']); ?></li>
                        <li>Contact: <?php echo htmlspecialchars($load['consignor_contact']); ?></li>
                        <li>Address: <?php echo htmlspecialchars($load['consignor_address']); ?></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="detail-card">
            <div class="map-panel">
                <div class="map-toolbar">
                    <div class="map-help">Map of pickup point</div>
                </div>
                <div id="detail_map" class="map-canvas"></div>
                <?php if (!$hasCoords) { ?>
                    <small class="map-status">No coordinates saved for this load.</small>
                <?php } else { ?>
                    <small class="map-status">Showing origin coordinates.</small>
                <?php } ?>
            </div>
        </div>
    </div>

    <script src="../js/dropdownmenu.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        (function() {
            var hasCoords = <?php echo $hasCoords ? 'true' : 'false'; ?>;
            if (!hasCoords || typeof L === 'undefined') return;
            var lat = parseFloat('<?php echo $load['origin_latitude']; ?>');
            var lng = parseFloat('<?php echo $load['origin_longitude']; ?>');
            if (isNaN(lat) || isNaN(lng)) return;
            var map = L.map('detail_map', { zoomControl: true }).setView([lat, lng], 12);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);
            L.marker([lat, lng]).addTo(map);
        })();
    </script>
</body>
</html>
