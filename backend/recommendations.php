<?php
require_once __DIR__ . '/recommendation_algorithms.php';

function getNearbyLoads($conn, $carrierId, $distanceLimitKm = 10) {
    if (!$conn) {
        return ['recommended' => [], 'all' => [], 'notice' => 'Unable to load recommendations right now.'];
    }

    $notice = '';
    $allSql = "SELECT ld.*, cd.name AS consignor_name, cd.img_srcs AS consignor_img, cd.email AS consignor_email
        FROM loaddetails ld
        JOIN consignordetails cd ON ld.consignor_id = cd.id
        LEFT JOIN shipment s ON ld.id = s.load_id
        WHERE ld.status = 'notBooked'
        GROUP BY ld.id, cd.name, cd.img_srcs, cd.email
        ORDER BY ld.dateofpost DESC";

    $allLoadsResult = $conn->query($allSql);
    $allLoads = [];
    if ($allLoadsResult) {
        while ($row = $allLoadsResult->fetch_assoc()) {
            $allLoads[] = $row;
        }
    }

    // Fetch carrier full row for scoring (vehicle/capacity/etc.)
    $carrierRow = null;
    $carrierQuery = $conn->prepare("SELECT * FROM carrierdetails WHERE id = ?");
    if ($carrierQuery) {
        $carrierQuery->bind_param("i", $carrierId);
        $carrierQuery->execute();
        $carrierRow = $carrierQuery->get_result()->fetch_assoc();
        $carrierQuery->close();
    }

    $medianPricePerKm = getMedianPricePerKm($conn);
    $recommendedResult = [];

    $carrierLocationStmt = $conn->prepare("SELECT last_latitude, last_longitude FROM carrierdetails WHERE id = ?");
    $carrierLocation = null;
    if ($carrierLocationStmt) {
        $carrierLocationStmt->bind_param("i", $carrierId);
        $carrierLocationStmt->execute();
        $carrierLocation = $carrierLocationStmt->get_result()->fetch_assoc();
        $carrierLocationStmt->close();
    }

    $hasCarrierLocation = $carrierLocation
        && $carrierLocation['last_latitude'] !== null
        && $carrierLocation['last_longitude'] !== null
        && $carrierLocation['last_latitude'] !== ''
        && $carrierLocation['last_longitude'] !== '';

    if ($hasCarrierLocation) {
        $recommendedSql = "SELECT ld.*, cd.name AS consignor_name, cd.img_srcs AS consignor_img, cd.email AS consignor_email,
            (6371 * acos(
                cos(radians(?)) * cos(radians(ld.origin_latitude)) *
                cos(radians(ld.origin_longitude) - radians(?)) +
                sin(radians(?)) * sin(radians(ld.origin_latitude))
            )) AS carrier_distance_km
            FROM loaddetails ld
            JOIN consignordetails cd ON ld.consignor_id = cd.id
            LEFT JOIN shipment s ON ld.id = s.load_id
            WHERE ld.status = 'notBooked'
                AND ld.origin_latitude IS NOT NULL
                AND ld.origin_longitude IS NOT NULL
            GROUP BY ld.id, cd.name, cd.img_srcs, cd.email
            HAVING carrier_distance_km <= ?
            ORDER BY carrier_distance_km ASC, ld.dateofpost DESC";
        $stmt = $conn->prepare($recommendedSql);
        if ($stmt) {
            $stmt->bind_param(
                "dddi",
                $carrierLocation['last_latitude'],
                $carrierLocation['last_longitude'],
                $carrierLocation['last_latitude'],
                $distanceLimitKm
            );
            $stmt->execute();
            $res = $stmt->get_result();
            while ($res && ($row = $res->fetch_assoc())) {
                $recommendedResult[] = $row;
            }
            // $notice = ($recommendedResult && count($recommendedResult) > 0)
            //     ? "Showing loads within {$distanceLimitKm} km of your last saved location."
            //     : "No loads found within {$distanceLimitKm} km of your last saved location. Showing all available loads.";
        }
    } else {
        $notice = "Add your current location in your profile to see nearby loads first.";
    }

    // Score and sort recommended
    if (!empty($recommendedResult) && $carrierRow) {
        foreach ($recommendedResult as &$load) {
            $distanceKm = isset($load['carrier_distance_km']) ? floatval($load['carrier_distance_km']) : null;
            $load['score'] = computeCompositeScore($load, $carrierRow, $medianPricePerKm, $distanceKm);
        }
        unset($load);
        usort($recommendedResult, function ($a, $b) {
            return ($b['score'] ?? 0) <=> ($a['score'] ?? 0);
        });
    }

    // Score and sort all loads by composite score (distance not included)
    if (!empty($allLoads) && $carrierRow) {
        foreach ($allLoads as &$load) {
            $load['score'] = computeCompositeScore($load, $carrierRow, $medianPricePerKm, null);
        }
        unset($load);
        usort($allLoads, function ($a, $b) {
            return ($b['score'] ?? 0) <=> ($a['score'] ?? 0);
        });
    }

    return ['recommended' => $recommendedResult, 'all' => $allLoads, 'notice' => $notice];
}
