<?php
function getMedianPricePerKm($conn) {
    $prices = [];
    $sql = "SELECT price, distance FROM loaddetails WHERE status = 'notBooked' AND price IS NOT NULL AND distance > 0";
    if ($result = $conn->query($sql)) {
        while ($row = $result->fetch_assoc()) {
            $pricePerKm = floatval($row['price']) / floatval($row['distance']);
            if ($pricePerKm > 0) {
                $prices[] = $pricePerKm;
            }
        }
        $result->free();
    }
    if (empty($prices)) {
        return 0;
    }
    sort($prices);
    $count = count($prices);
    $middle = (int) floor(($count - 1) / 2);
    if ($count % 2) {
        return $prices[$middle];
    }
    return ($prices[$middle] + $prices[$middle + 1]) / 2;
}

function computeCapacityVehicleScore(array $load, array $carrier) {
    $score = 0.5; // neutral
    $signals = 0;

    // Weight vs carrier capacity if present
    if (isset($carrier['max_capacity']) && is_numeric($carrier['max_capacity']) && is_numeric($load['weight'])) {
        $signals++;
        $capacity = floatval($carrier['max_capacity']);
        $weight = floatval($load['weight']);
        if ($capacity <= 0) {
            $score += 0;
        } elseif ($weight <= $capacity) {
            // closer to 1 if good headroom
            $score += min(0.5, ($capacity - $weight) / max($capacity, 1) * 0.5);
        } else {
            // overweight penalty
            $score -= 0.3;
        }
    }

    // Vehicle type match if both provided
    if (!empty($carrier['vehicle_type']) && !empty($load['required_vehicle_type'])) {
        $signals++;
        $score += (strcasecmp($carrier['vehicle_type'], $load['required_vehicle_type']) === 0) ? 0.3 : -0.2;
    }

    // Special requirements (reefer/hazmat/oversize) if present as boolean flags
    $requirements = ['requires_reefer', 'requires_hazmat', 'requires_oversize'];
    foreach ($requirements as $req) {
        if (isset($load[$req])) {
            $signals++;
            $carrierKey = 'can_' . substr($req, 9); // expects carrier can_reefer/can_hazmat/can_oversize
            $can = !empty($carrier[$carrierKey]);
            $needs = (bool) $load[$req];
            if ($needs) {
                $score += $can ? 0.2 : -0.3;
            } else {
                $score += 0.05; // tiny boost for easy loads
            }
        }
    }

    if ($signals === 0) {
        return 0.5;
    }
    // normalize into 0..1 bounds
    return max(0, min(1, $score));
}

function computeFreshnessUrgencyScore(array $load) {
    $now = new DateTime();
    $posted = !empty($load['dateofpost']) ? new DateTime($load['dateofpost']) : null;
    $scheduled = !empty($load['scheduled_time']) ? new DateTime($load['scheduled_time']) : null;

    $recencyScore = 0.5;
    if ($posted) {
        $ageHours = max(0, ($now->getTimestamp() - $posted->getTimestamp()) / 3600);
        // 0 hours -> 1.0, 7 days -> ~0.25, older decays further
        $recencyScore = max(0, 1 - ($ageHours / (24 * 7)));
    }

    $urgencyScore = 0.5;
    if ($scheduled) {
        $hoursUntil = ($scheduled->getTimestamp() - $now->getTimestamp()) / 3600;
        if ($hoursUntil <= 0) {
            $urgencyScore = 1; // already due/past due -> urgent
        } elseif ($hoursUntil <= 24) {
            $urgencyScore = 0.9;
        } elseif ($hoursUntil <= 72) {
            $urgencyScore = 0.7;
        } else {
            // decay for far future dates
            $urgencyScore = max(0.2, 1 - ($hoursUntil / (24 * 14)));
        }
    }

    // Blend: give recency and urgency equal weight
    return max(0, min(1, 0.5 * $recencyScore + 0.5 * $urgencyScore));
}

function computePriceScore(array $load, $medianPricePerKm) {
    $price = isset($load['price']) ? floatval($load['price']) : 0;
    $distance = isset($load['distance']) ? floatval($load['distance']) : 0;
    $weight = isset($load['weight']) ? floatval($load['weight']) : 0;
    if ($price <= 0 || $distance <= 0) {
        return 0.5;
    }

    $pricePerKm = $price / $distance;
    $pricePerKmTon = ($weight > 0) ? $price / ($distance * $weight) : $pricePerKm;
    $baseline = ($medianPricePerKm && $medianPricePerKm > 0) ? $medianPricePerKm : $pricePerKm;

    // Score based on how much better than median this load is
    $ratio = $pricePerKm / $baseline;
    if ($ratio >= 1.5) {
        $score = 1.0;
    } elseif ($ratio >= 1.1) {
        $score = 0.85;
    } elseif ($ratio >= 0.9) {
        $score = 0.65;
    } elseif ($ratio >= 0.7) {
        $score = 0.45;
    } else {
        $score = 0.3;
    }

    // Slightly adjust with per-ton info if available
    if ($weight > 0) {
        $score = min(1, $score + min(0.1, $pricePerKmTon / 10));
    }
    return $score;
}

function computeCompositeScore(array $load, array $carrier, $medianPricePerKm, $distanceKm = null) {
    $priceScore = computePriceScore($load, $medianPricePerKm);
    $freshScore = computeFreshnessUrgencyScore($load);
    $capacityScore = computeCapacityVehicleScore($load, $carrier);

    // Optional proximity bonus if distance provided
    $proximityScore = 0.5;
    if ($distanceKm !== null && is_numeric($distanceKm)) {
        $d = max(0.01, floatval($distanceKm));
        $proximityScore = max(0, min(1, 1 - ($d / 50))); // within 50km fades to 0
    }

    // Weighted blend
    return max(0, min(1, 0.35 * $priceScore + 0.25 * $freshScore + 0.25 * $capacityScore + 0.15 * $proximityScore));
}
