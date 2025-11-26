<?php
// expects $loadrow and $carrier_id to be defined by caller
$proximity = isset($loadrow['carrier_distance_km']) && $loadrow['carrier_distance_km'] !== null
    ? number_format($loadrow['carrier_distance_km'], 2) . ' km away'
    : null;
?>
<div class="post-container">
    <div class="user-info">
        <div class="detail">
            <img src="<?php echo $loadrow['consignor_img']; ?>" alt="">
            <div>
                <p><?php echo $loadrow['consignor_name']; ?></p>
                <small><?php echo $loadrow['dateofpost']; ?></small>
            </div>
        </div>
    </div>
    <hr>
    
    <div class="content-detail">
        <div class="content-image">
            <img src="<?php echo $loadrow['img_srcs']; ?>" alt="Image" class="post-img">
        </div>
        <div class="content-description">
            <h3><?php echo $loadrow['name']; ?></h3>
            <ul>
                <li>Origin: <?php echo $loadrow['origin']; ?></li>
                <li>Destination: <?php echo $loadrow['destination']; ?></li>
                <?php if ($proximity) { ?><li>Distance from you: <?php echo $proximity; ?></li><?php } ?>
                <li>Distance: <?php echo $loadrow['distance']; ?> Km</li>
                <li>Weight: <?php echo $loadrow['weight']; ?> Ton</li>
                <?php
                $pricePerTon = (isset($loadrow['price']) && isset($loadrow['weight']) && floatval($loadrow['weight']) > 0)
                    ? number_format(floatval($loadrow['price']) / floatval($loadrow['weight']), 2)
                    : null;
                ?>
                <li>Description: <?php echo $loadrow['description']; ?></li>
                <li>Price: <?php echo $loadrow['price']; ?><?php echo $pricePerTon ? ' ('.$pricePerTon.' per ton)' : ''; ?></li>
            </ul>
        </div>
    </div>
    <hr>
    <div class="activity-icon booked">
        <form action="backend/booking.php" method="post">
            <input type="hidden" name="action" value="book">
            <input type="hidden" name="load_id" value="<?php echo $loadrow['id']; ?>">
            <input type="hidden" name="carrier_id" value="<?php echo $carrier_id; ?>">
            <input type="hidden" name="consignor_id" value="<?php echo $loadrow['consignor_id']; ?>">
            <button type="submit" class="primary-btn" style="width: 200px;">
                <i class="fa-solid fa-handshake-simple" aria-hidden="true"></i> Book
            </button>
        </form>
        <a class="primary-btn" style="width: 200px; text-align:center; display:inline-flex; align-items:center; justify-content:center; gap:6px; font-size:0.95rem;" href="layout/load_details.php?id=<?php echo $loadrow['id']; ?>">
            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
            <span>Details</span>
        </a>
    </div>                  
</div>
