<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$errors = [];

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit;
}

require 'backend/databaseconnection.php';

$userSelects = $_SESSION['usertype'];
$table = ($userSelects == "carrier") ? "carrierdetails" : "consignordetails";

// Fetch user data from the database
$result = $conn->query("SELECT * FROM $table WHERE id = " . $_SESSION['id']);
$row = ($result && $result->num_rows > 0) ? $result->fetch_assoc() : null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Collect form data
    $name = trim($_POST['name']);
    $contact = trim($_POST['contact']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);
    $newPassword = $_POST['password'];
    $lastLatitude = isset($_POST['last_latitude']) ? trim($_POST['last_latitude']) : '';
    $lastLongitude = isset($_POST['last_longitude']) ? trim($_POST['last_longitude']) : '';

    // Validation
    $reNameRegEx = '/^[A-Z][a-zA-Z]*(?: [A-Z][a-zA-Z]*)*$/';
    if (!preg_match($reNameRegEx, $name)) {
        $errors[] = "Name must be only alphabetical and like Rojan Dumaru.";
    }
    if (empty($name) || empty($email) || empty($contact) || empty($address)) {
        $errors[] = "All fields are required.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    if (!empty($newPassword) && (strlen($newPassword) < 8 || strlen($newPassword) > 24)) {
        $errors[] = "Password must be between 8 and 24 characters.";
    }
    if (!is_numeric($contact) || strlen($contact) !== 10) {
        $errors[] = "Contact must be a 10-digit numeric value.";
    }
    $shouldUpdateLocation = false;
    if ($userSelects === 'carrier') {
        $hasLat = $lastLatitude !== '';
        $hasLong = $lastLongitude !== '';
        if ($hasLat || $hasLong) {
            if (!$hasLat || !$hasLong) {
                $errors[] = "Provide both latitude and longitude for your last known location.";
            } else {
                if (!is_numeric($lastLatitude) || $lastLatitude < -90 || $lastLatitude > 90) {
                    $errors[] = "Latitude must be between -90 and 90.";
                }
                if (!is_numeric($lastLongitude) || $lastLongitude < -180 || $lastLongitude > 180) {
                    $errors[] = "Longitude must be between -180 and 180.";
                }
                $shouldUpdateLocation = empty($errors);
            }
        }
    }

    // If no errors, process the form
    if (empty($errors)) {
        $uploadedFilePath = "";
        
        if (!empty($_FILES['profile_pic']['name'])) {
            // Handling image upload
            $allowedExtensions = ['jpg', 'jpeg', 'png'];
            $uploadDirectory = 'img/profileUploads/';
            $imgName = $_FILES['profile_pic']['name'];
            $imgExtension = pathinfo($imgName, PATHINFO_EXTENSION);
            
            if (!in_array($imgExtension, $allowedExtensions)) {
                $errors[] = "Invalid image format. Allowed formats: JPG, JPEG, PNG.";
            } else {
                $uploadedFilePath = $uploadDirectory . basename($imgName);
                if (!move_uploaded_file($_FILES['profile_pic']['tmp_name'], $uploadedFilePath)) {
                    $errors[] = "Failed to upload the new image.";
                }
            }
        }

        // Prepare the SQL update statement
        $updateSql = "UPDATE $table SET name = ?, contact = ?, email = ?, address = ?";
        $params = [$name, $contact, $email, $address];
        $types = "ssss";

        if ($shouldUpdateLocation) {
            $updateSql .= ", last_latitude = ?, last_longitude = ?, last_location_updated_at = ?";
            $params[] = floatval($lastLatitude);
            $params[] = floatval($lastLongitude);
            $params[] = date('Y-m-d H:i:s');
            $types .= "dds";
        }
        
        if (!empty($newPassword)) {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateSql .= ", password = ?";
            $params[] = $hashedPassword;
            $types .= "s";
        }
        
        if (!empty($uploadedFilePath)) {
            $updateSql .= ", img_srcs = ?";
            $params[] = $uploadedFilePath;
            $types .= "s";
            $_SESSION['profilePic'] = $uploadedFilePath;
        }
        
        $updateSql .= " WHERE id = ?";
        $params[] = $_SESSION['id'];
        $types .= "i";

        // Execute the prepared statement
        $stmt = $conn->prepare($updateSql);
        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
            // Update session variables
            $_SESSION['name'] = $name;
            $_SESSION['email'] = $email;
            $_SESSION['contact'] = $contact;
            $_SESSION['address'] = $address;

            header("Location: profile.php?success=1");
            exit;
        } else {
            $errors[] = "Database update failed.";
        }
        
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="js/sweetalert.js"></script>
    <script src="js/geolocation.js"></script>
    <link rel="stylesheet" href="css/headerfooterstyle.css">
    <link rel="stylesheet" href="css/addtable.css">
    <link rel="stylesheet" href="css/profile.css">
    <link rel="stylesheet" href="css/sweetAlert.css">
    <title>User Profile</title>
</head>
<body>

<?php include 'layout/header.php'; ?>

<div class="add-main">
    <div class="form-header">
        <p class="eyebrow">Your Profile</p>
        <h2>Account Settings</h2>
        <p class="subtitle">Update your personal details, password, and location settings.</p>
    </div>

    <?php if (isset($_GET['success'])) { ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    title: 'Profile Updated Successfully!',
                    icon: 'success',
                    confirmButtonText: 'OK'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'profile.php';
                    }
                });
            });
        </script>
    <?php } ?>

    <?php if (!empty($errors)) { ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Errors',
                    html: '<?php echo implode("<br>", $errors); ?>',
                });
            });
        </script>
    <?php } ?>

    <form action="" method="POST" enctype="multipart/form-data" class="addForm" id="profileForm">
        <div class="form-section full-span">
            <!-- Profile Picture -->
            <div class="section-heading">
                <span class="pill">Photo</span>
                <p class="section-copy">Update your profile picture.</p>
            </div>
            <div class="profile-picture">
                <img src="<?php echo htmlspecialchars($row['img_srcs']); ?>" alt="Profile Picture" id="PicPreview">
                <input type="file" name="profile_pic" id="pic" accept="image/*" style="display: none;" onchange="previewImage(event)">
                <button type="button" class="ghost-btn" onclick="document.getElementById('pic').click()">Change Picture</button>
            </div>

            <!-- Personal Details -->
            <div class="section-heading">
                <span class="pill">Personal Details</span>
                <p class="section-copy">Keep your name, contact, and address up-to-date.</p>
            </div>
            <div class="data-input">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($row['name']); ?>" required>
            </div>
            <div class="data-input">
                <label for="contact">Contact</label>
                <input type="text" id="contact" name="contact" value="<?php echo htmlspecialchars($row['contact']); ?>" required>
            </div>
            <div class="data-input">
                <label for="address">Address</label>
                <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($row['address']); ?>" required>
            </div>

            <!-- Account Security -->
            <div class="section-heading">
                <span class="pill">Account Security</span>
                <p class="section-copy">Update your email and password.</p>
            </div>
            <div class="data-input">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($row['email']); ?>" required>
            </div>
            <div class="data-input">
                <label for="password">New Password</label>
                <input type="password" id="password" name="password" placeholder="Enter new password to change">
            </div>

            <!-- Location Settings for Carrier -->
            <?php if ($userSelects === 'carrier'): ?>
            <div class="section-heading">
                <span class="pill">Location</span>
                <p class="section-copy">Help consignors find you by sharing your location.</p>
            </div>
            <div class="geo-grid">
                <div class="data-input">
                    <label for="last_latitude">Last Known Latitude</label>
                    <input type="number" step="0.00000001" id="last_latitude" name="last_latitude" value="<?php echo htmlspecialchars($row['last_latitude'] ?? ''); ?>">
                </div>
                <div class="data-input">
                    <label for="last_longitude">Last Known Longitude</label>
                    <input type="number" step="0.00000001" id="last_longitude" name="last_longitude" value="<?php echo htmlspecialchars($row['last_longitude'] ?? ''); ?>">
                </div>
            </div>
            <div class="data-input">
                <button type="button" class="ghost-btn" onclick="requestLocation('last_latitude','last_longitude','location_status')">Use Current Location</button>
                <small id="location_status"></small>
            </div>
            <?php endif; ?>
            <div class="cta-row">
            <button type="submit" name="updateBtn" class="primary-btn">Save Changes</button>
            <a class="ghost-btn" href="home.php">Back Home</a>
            </div>
        </div>

        
        
    </form>
</div>

<script src="js/imgPreview.js"></script>
<script src="js/dropdownmenu.js"></script>

</body>
</html>
