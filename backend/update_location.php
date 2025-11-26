<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id']) || !isset($_SESSION['usertype']) || $_SESSION['usertype'] !== 'carrier') {
    http_response_code(401);
    exit('Unauthorized');
}

require 'databaseconnection.php';

$lat = isset($_POST['lat']) ? $_POST['lat'] : null;
$lng = isset($_POST['lng']) ? $_POST['lng'] : null;

if (!is_numeric($lat) || !is_numeric($lng)) {
    http_response_code(400);
    exit('Invalid coordinates');
}

$latVal = floatval($lat);
$lngVal = floatval($lng);

// basic sanity bounds
if ($latVal < -90 || $latVal > 90 || $lngVal < -180 || $lngVal > 180) {
    http_response_code(400);
    exit('Out of range');
}

$stmt = $conn->prepare("UPDATE carrierdetails SET last_latitude = ?, last_longitude = ?, last_location_updated_at = NOW() WHERE id = ?");
if (!$stmt) {
    http_response_code(500);
    exit('DB error');
}
$stmt->bind_param('ddi', $latVal, $lngVal, $_SESSION['id']);
$stmt->execute();
$stmt->close();

http_response_code(204);
exit;
