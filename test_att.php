<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$conn = new mysqli("localhost", "newblogs_aditya", "fpGK6N024NCb", "newblogs_aditya");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get user IP and device info
function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

function getDeviceInfo() {
    return $_SERVER['HTTP_USER_AGENT'];
}

// Add new columns to the database
$conn->query("ALTER TABLE attendance_records 
ADD COLUMN accuracy FLOAT AFTER longitude,
ADD COLUMN address TEXT AFTER accuracy;");

// Handle punch in/out
if (isset($_POST['action'])) {
    $userIP = getUserIP();
    $deviceInfo = getDeviceInfo();
    $currentTime = date('Y-m-d H:i:s');
    $latitude = $_POST['latitude'] ?? null;
    $longitude = $_POST['longitude'] ?? null;
    $accuracy = $_POST['accuracy'] ?? null;
    $address = $_POST['address'] ?? null;

    if ($_POST['action'] === 'punch_in') {
        // Handle image data
        $imageData = $_POST['image_data'] ?? '';
        $imageData = str_replace('data:image/png;base64,', '', $imageData);
        $imageData = base64_decode($imageData);

        $sql = "INSERT INTO attendance_records (user_ip, device_info, image_data, latitude, longitude, accuracy, address, punch_in, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'punched_in')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssddiss", $userIP, $deviceInfo, $imageData, $latitude, $longitude, $accuracy, $address, $currentTime);
        $stmt->execute();
        echo "success_in";
    } elseif ($_POST['action'] === 'punch_out') {
        $sql = "UPDATE attendance_records 
                SET punch_out = ?, latitude = ?, longitude = ?, accuracy = ?, address = ?, status = 'punched_out' 
                WHERE user_ip = ? AND status = 'punched_in' 
                ORDER BY id DESC LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sdddss", $currentTime, $latitude, $longitude, $accuracy, $address, $userIP);
        $stmt->execute();
        echo "success_out";
    }
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Attendance System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background-color: #f0f2f5;
        }
        .container {
            text-align: center;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        button {
            padding: 15px 30px;
            font-size: 18px;
            cursor: pointer;
            border: none;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        #punchButton.punch-in {
            background-color: #4CAF50;
            color: white;
        }
        #punchButton.punch-out {
            background-color: #f44336;
            color: white;
        }
        
        .camera-container {
            display: none;
            margin: 20px 0;
        }
        
        #videoElement {
            width: 100%;
            max-width: 400px;
            margin-bottom: 10px;
        }
        
        #capturedImage {
            display: none;
            width: 100%;
            max-width: 400px;
            margin-bottom: 10px;
        }
        
        .camera-buttons {
            margin: 10px 0;
        }
        
        .camera-buttons button {
            margin: 0 5px;
        }
        
        .location-info {
            margin: 10px 0;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
            font-size: 14px;
            line-height: 1.5;
        }
        .accuracy-high {
            color: #28a745;
        }
        .accuracy-medium {
            color: #ffc107;
        }
        .accuracy-low {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Attendance System</h1>
        <div class="location-info" id="locationInfo">
            Fetching location...
        </div>
        <button id="punchButton" class="punch-in">Punch In</button>
        
        <div class="camera-container" id="cameraContainer">
            <video id="videoElement" autoplay playsinline></video>
            <canvas id="canvas" style="display:none;"></canvas>
            <img id="capturedImage" alt="Captured photo">
            <div class="camera-buttons">
                <button id="captureButton">Capture Photo</button>
                <button id="retakeButton" style="display:none;">Retake</button>
                <button id="submitButton" style="display:none;">Submit</button>
            </div>
        </div>
    </div>

    <script>
        const button = document.getElementById('punchButton');
        const cameraContainer = document.getElementById('cameraContainer');
        const video = document.getElementById('videoElement');
        const canvas = document.getElementById('canvas');
        const capturedImage = document.getElementById('capturedImage');
        const captureButton = document.getElementById('captureButton');
        const retakeButton = document.getElementById('retakeButton');
        const submitButton = document.getElementById('submitButton');
        let isPunchedIn = false;
        let stream = null;
        let imageData = null;
        let currentPosition = null;
        const locationInfo = document.getElementById('locationInfo');

        async function startCamera() {
            try {
                stream = await navigator.mediaDevices.getUserMedia({ video: true });
                video.srcObject = stream;
                cameraContainer.style.display = 'block';
            } catch (err) {
                console.error('Error accessing camera:', err);
                alert('Could not access camera. Please ensure camera permissions are granted.');
            }
        }

        function stopCamera() {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                video.srcObject = null;
                cameraContainer.style.display = 'none';
            }
        }

        captureButton.addEventListener('click', () => {
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);
            imageData = canvas.toDataURL('image/png');
            capturedImage.src = imageData;
            
            video.style.display = 'none';
            capturedImage.style.display = 'block';
            captureButton.style.display = 'none';
            retakeButton.style.display = 'inline-block';
            submitButton.style.display = 'inline-block';
        });

        retakeButton.addEventListener('click', () => {
            video.style.display = 'block';
            capturedImage.style.display = 'none';
            captureButton.style.display = 'inline-block';
            retakeButton.style.display = 'none';
            submitButton.style.display = 'none';
            imageData = null;
        });

        button.addEventListener('click', async () => {
            if (!isPunchedIn) {
                await startCamera();
                return;
            }
            
            const action = 'punch_out';
            submitPunch(action);
        });

        submitButton.addEventListener('click', async () => {
            if (!imageData) {
                alert('Please capture a photo first');
                return;
            }

            const action = 'punch_in';
            await submitPunch(action);
            stopCamera();
        });

        // Enhanced location function
        async function getLocation() {
            try {
                const position = await new Promise((resolve, reject) => {
                    navigator.geolocation.getCurrentPosition(resolve, reject, {
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 0
                    });
                });
                
                currentPosition = {
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude,
                    accuracy: position.coords.accuracy // accuracy in meters
                };

                // Get address using reverse geocoding
                try {
                    const response = await fetch(`https://nominatim.openstreetmap.org/reverse?lat=${currentPosition.latitude}&lon=${currentPosition.longitude}&format=json`);
                    const data = await response.json();
                    currentPosition.address = data.display_name;
                } catch (error) {
                    console.error('Error getting address:', error);
                    currentPosition.address = 'Address not available';
                }

                // Update location display with accuracy information
                const accuracyClass = currentPosition.accuracy <= 20 ? 'accuracy-high' : 
                                    currentPosition.accuracy <= 100 ? 'accuracy-medium' : 
                                    'accuracy-low';
                
                locationInfo.innerHTML = `
                    <strong>Current Location:</strong><br>
                    Latitude: ${currentPosition.latitude.toFixed(6)}<br>
                    Longitude: ${currentPosition.longitude.toFixed(6)}<br>
                    <span class="${accuracyClass}">
                        Accuracy: ${currentPosition.accuracy.toFixed(1)} meters
                    </span><br>
                    <small>Address: ${currentPosition.address}</small>
                `;

                return currentPosition;
            } catch (error) {
                console.error('Error getting location:', error);
                locationInfo.innerHTML = `
                    <span class="accuracy-low">
                        Unable to fetch location. Please ensure:
                        <ul>
                            <li>Location services are enabled</li>
                            <li>You've granted location permission</li>
                            <li>You have a GPS signal or network connection</li>
                        </ul>
                    </span>
                `;
                return null;
            }
        }

        // Modify the submitPunch function
        async function submitPunch(action) {
            try {
                // Get current location before submitting
                await getLocation();
                
                if (!currentPosition && action === 'punch_in') {
                    alert('Accurate location is required for punch in. Please enable location services and try again.');
                    return;
                }

                const formData = new FormData();
                formData.append('action', action);
                if (imageData) {
                    formData.append('image_data', imageData);
                }
                if (currentPosition) {
                    formData.append('latitude', currentPosition.latitude);
                    formData.append('longitude', currentPosition.longitude);
                    formData.append('accuracy', currentPosition.accuracy);
                    formData.append('address', currentPosition.address);
                }

                const response = await fetch('test_att.php', {
                    method: 'POST',
                    body: new URLSearchParams(formData)
                });

                const result = await response.text();
                console.log('Server response:', result);
                
                if (result === 'success_in') {
                    button.textContent = 'Punch Out';
                    button.classList.remove('punch-in');
                    button.classList.add('punch-out');
                    isPunchedIn = true;
                    stopCamera();
                } else if (result === 'success_out') {
                    button.textContent = 'Punch In';
                    button.classList.remove('punch-out');
                    button.classList.add('punch-in');
                    isPunchedIn = false;
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            }
        }

        // Update location every minute while the page is open
        setInterval(getLocation, 60000);

        // Initial location fetch
        getLocation();
    </script>
</body>
</html>