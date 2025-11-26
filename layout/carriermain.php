<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['email'])) {
    header("Location: ../login.php");
    exit;
}

$carrier_id = $_SESSION['id'];
require_once __DIR__ . '/../backend/recommendations.php';

$distanceLimitKm = 10;
$recommendation = getNearbyLoads($conn, $carrier_id, $distanceLimitKm);
$recommendedResult = $recommendation['recommended'];
$allLoads = $recommendation['all'];
$locationNotice = $recommendation['notice'];
?>

<div class="main-content">
    <h2>Loads for You</h2>
    <?php if (!empty($locationNotice)) { ?>
        <p style="color: #b7b7b7; font-size: 14px; margin-top: -5px;"><?php echo htmlspecialchars($locationNotice); ?></p>
    <?php } ?>
    <?php
    if (!empty($recommendedResult)) {
        echo '<h3 style="margin-top:15px;">Recommended near you</h3>';
        foreach ($recommendedResult as $loadrow) {
            include __DIR__ . '/render_load_card.php';
        }
    }

    if (!empty($allLoads)) {
        echo '<h3 style="margin-top:20px;">All loads</h3>';
        foreach ($allLoads as $loadrow) {
            // hide proximity in all-loads section
            $loadrow['carrier_distance_km'] = null;
            include __DIR__ . '/render_load_card.php';
        }
    }
    ?>
</div>
