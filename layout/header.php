<?php
// Check if the session has not started, then start the session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Check if the user is not logged in
if(!isset($_SESSION['email'])) {
    // Redirect the user to the login page or any other authentication page
    header("Location: ../login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/headerfooterstyle.css">
    <link rel="stylesheet" href="css/addtable.css">
    <link rel="stylesheet" href="css/maincontentstyle.css">
    <script src="https://kit.fontawesome.com/7b1b8b2fa3.js" crossorigin="anonymous"></script>
</head>
<body>
    <header>  
        <nav>
            <a href="home.php">
                <img class="logo" src="img/defaultImg/mainLogo2.png" alt="logo">
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
                    <img src="<?php echo $_SESSION['profilePic'] ?>" onclick='toggleMenu()'>
                </div>
            </div>
            <!-- dropdown -->
            <div class="sub-menu-wrap" id="subMenu">
                <div class="sub-menu">
                    <div class="user-info">
                        <img src="<?php echo $_SESSION['profilePic'] ?>">
                        <h2><?php echo $_SESSION['name'];?></h2>
                    </div>
                    <hr>
                    <a href="profile.php" class="sub-menu-link">
                        <img src="<?php echo $_SESSION['profilePic'] ?>">
                        <p>Profile</p>                      
                    </a>
                    <a href="home.php" class="sub-menu-link">
                        <img src="img/defaultImg/home.png">
                        <p>Home</p>                      
                    </a>
                    <?php if($_SESSION['usertype'] == 'carrier'){?>
                    <a href="history.php" class="sub-menu-link">
                        <img src="img/defaultImg/setting.png">
                        <p>History</p>                      
                    </a>
                    <?php } ?>
                    <a href="backend/logoutmodule.php" class="sub-menu-link">
                        <img src="img/defaultImg/logout.png">
                        <p>Logout</p>                      
                    </a>
                </div>
            </div>
        </nav>
    </header>
    <script src="js/dropdownmenu.js"></script>
    <?php if($_SESSION['usertype'] == 'carrier'){ ?>
        <script src="js/geolocation.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                if (typeof startLocationHeartbeat === 'function') {
                    startLocationHeartbeat(null, null, 'global_location_status', 'backend/update_location.php', 10 * 60 * 1000);
                }
            });
        </script>
    <?php } ?>
</body>
</html>
