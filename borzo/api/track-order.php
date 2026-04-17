<?php
// track-order.php - Live Tracking with Google Maps
session_start();
require_once 'db_connection.php';

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$google_maps_key = 'AIzaSyCHhTLDYVu7dLYkohIKHiSEU9pi3_1TZl8'; // Replace with your actual key

if (!$order_id) {
    header("Location: orders.php");
    exit();
}

// Fetch order details with courier info
$sql = "SELECT o.*, 
        u.Name as user_name,
        u.phone as user_phone
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        WHERE o.order_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $order_id);
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();
$stmt->close();

if (!$order) {
    header("Location: orders.php");
    exit();
}

// Fetch tracking history
$sql = "SELECT * FROM order_delivery_tracking 
        WHERE order_id = ? 
        ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $order_id);
$stmt->execute();
$history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Order #<?php echo $order_id; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
        }
        
        .tracking-container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 20px;
        }
        
        .order-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .order-header h1 {
            margin: 0;
            font-size: 2.2rem;
        }
        
        .order-header .badge {
            font-size: 1rem;
            padding: 8px 15px;
            border-radius: 50px;
            margin-left: 15px;
        }
        
        .status-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border-left: 4px solid #667eea;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .status-new { background: #ffc107; color: #000; }
        .status-available { background: #17a2b8; color: #fff; }
        .status-active { background: #28a745; color: #fff; }
        .status-delayed { background: #fd7e14; color: #fff; }
        .status-completed { background: #6c757d; color: #fff; }
        .status-canceled { background: #dc3545; color: #fff; }
        
        #map {
            height: 400px;
            width: 100%;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .info-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            height: 100%;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .info-card h4 {
            color: #333;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 15px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .info-item {
            margin-bottom: 15px;
        }
        
        .info-item strong {
            color: #555;
            display: block;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        
        .info-item span {
            color: #333;
            font-size: 1.1rem;
        }
        
        .timeline {
            position: relative;
            padding: 20px 0;
        }
        
        .timeline-item {
            position: relative;
            padding-left: 40px;
            margin-bottom: 25px;
        }
        
        .timeline-item:before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: -25px;
            width: 2px;
            background: #e0e0e0;
        }
        
        .timeline-item:last-child:before {
            display: none;
        }
        
        .timeline-dot {
            position: absolute;
            left: 10px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #667eea;
            border: 2px solid white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .timeline-content {
            background: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .timeline-time {
            color: #999;
            font-size: 0.85rem;
            margin-bottom: 5px;
        }
        
        .timeline-status {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .timeline-desc {
            color: #666;
            font-size: 0.9rem;
        }
        
        .courier-info {
            background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
            border-radius: 15px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .courier-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #667eea;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 600;
            margin-right: 15px;
        }
        
        .refresh-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #667eea;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 5px 15px rgba(102,126,234,0.4);
            transition: all 0.3s ease;
            border: none;
            z-index: 1000;
        }
        
        .refresh-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 8px 20px rgba(102,126,234,0.6);
        }
        
        .refresh-btn i {
            font-size: 24px;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .courier-moving {
            animation: pulse 2s infinite;
        }
        
        @media (max-width: 768px) {
            .tracking-container {
                padding: 10px;
            }
            
            .order-header h1 {
                font-size: 1.5rem;
            }
            
            #map {
                height: 300px;
            }
            
            .refresh-btn {
                bottom: 20px;
                right: 20px;
                width: 45px;
                height: 45px;
            }
        }
    </style>
</head>
<body>
    <div class="tracking-container">
        <!-- Order Header -->
        <div class="order-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h1>Order #<?php echo $order_id; ?></h1>
                    <p class="mb-0">Track your delivery in real-time</p>
                </div>
                <div>
                    <span class="badge bg-light text-dark status-<?php echo $order['borzo_status'] ?? 'pending'; ?>">
                        <?php echo strtoupper($order['borzo_status'] ?? 'PENDING'); ?>
                    </span>
                    <?php if (!empty($order['borzo_order_id'])): ?>
                        <span class="badge bg-dark ms-2">Borzo ID: <?php echo $order['borzo_order_id']; ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-lg-8">
                <!-- Google Map -->
                <div id="map"></div>
                
                <!-- Courier Info Card (shown when courier is active) -->
                <?php if (!empty($order['courier_name']) && $order['borzo_status'] === 'active'): ?>
                <div class="courier-info">
                    <div class="d-flex align-items-center">
                        <div class="courier-avatar">
                            <?php echo strtoupper(substr($order['courier_name'], 0, 1)); ?>
                        </div>
                        <div>
                            <h5 class="mb-1"><?php echo htmlspecialchars($order['courier_name']); ?></h5>
                            <p class="mb-1"><i class="bi bi-telephone"></i> <?php echo htmlspecialchars($order['courier_phone']); ?></p>
                            <p class="mb-0 text-success courier-moving"><i class="bi bi-geo-alt"></i> Moving towards destination</p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="col-lg-4">
                <!-- Delivery Info Card -->
                <div class="info-card">
                    <h4><i class="bi bi-truck"></i> Delivery Information</h4>
                    
                    <div class="info-item">
                        <strong>Delivery Address</strong>
                        <span><?php echo htmlspecialchars($order['delivery_address']); ?></span>
                    </div>
                    
                    <div class="info-item">
                        <strong>Customer</strong>
                        <span><?php echo htmlspecialchars($order['customer_name']); ?></span>
                        <span class="d-block small text-muted"><?php echo htmlspecialchars($order['customer_phone']); ?></span>
                    </div>
                    
                    <?php if (!empty($order['estimated_delivery_time'])): ?>
                    <div class="info-item">
                        <strong>Estimated Delivery</strong>
                        <span><?php echo date('d M Y, h:i A', strtotime($order['estimated_delivery_time'])); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($order['actual_delivery_time'])): ?>
                    <div class="info-item">
                        <strong>Actual Delivery</strong>
                        <span><?php echo date('d M Y, h:i A', strtotime($order['actual_delivery_time'])); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="info-item">
                        <strong>Tracking URL</strong>
                        <?php if (!empty($order['delivery_tracking_url'])): ?>
                            <a href="<?php echo $order['delivery_tracking_url']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-box-arrow-up-right"></i> Open in Borzo
                            </a>
                        <?php else: ?>
                            <span class="text-muted">Not available yet</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Tracking Timeline -->
                <div class="info-card mt-3">
                    <h4><i class="bi bi-clock-history"></i> Tracking History</h4>
                    <div class="timeline">
                        <?php if (!empty($history)): ?>
                            <?php foreach ($history as $event): ?>
                                <div class="timeline-item">
                                    <div class="timeline-dot"></div>
                                    <div class="timeline-content">
                                        <div class="timeline-time">
                                            <i class="bi bi-clock"></i> <?php echo date('d M Y, h:i A', strtotime($event['created_at'])); ?>
                                        </div>
                                        <div class="timeline-status">
                                            <span class="status-badge status-<?php echo $event['status']; ?>">
                                                <?php echo ucfirst($event['status']); ?>
                                            </span>
                                        </div>
                                        <?php if (!empty($event['status_description'])): ?>
                                            <div class="timeline-desc"><?php echo htmlspecialchars($event['status_description']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted text-center">No tracking history available</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Refresh Button -->
    <button class="refresh-btn" onclick="refreshTracking()" title="Refresh tracking">
        <i class="bi bi-arrow-repeat"></i>
    </button>
    
    <!-- Hidden data for JavaScript -->
    <script>
        const orderId = <?php echo $order_id; ?>;
        const pickupLat = <?php echo $order['pickup_latitude'] ?? 'null'; ?>;
        const pickupLng = <?php echo $order['pickup_longitude'] ?? 'null'; ?>;
        const deliveryLat = <?php echo $order['delivery_latitude'] ?? 'null'; ?>;
        const deliveryLng = <?php echo $order['delivery_longitude'] ?? 'null'; ?>;
        const courierLat = <?php echo $order['courier_latitude'] ?? 'null'; ?>;
        const courierLng = <?php echo $order['courier_longitude'] ?? 'null'; ?>;
    </script>
    
    <!-- Google Maps JavaScript -->
    <script src="https://maps.googleapis.com/maps/api/js?key=<?php echo $google_maps_key; ?>&callback=initMap&v=weekly" async defer></script>
    
    <script>
        let map;
        let pickupMarker;
        let deliveryMarker;
        let courierMarker;
        let directionsService;
        let directionsRenderer;
        let updateInterval;
        
        function initMap() {
            // Default center (Mumbai)
            const defaultCenter = { lat: 19.0760, lng: 72.8777 };
            
            // Initialize map
            map = new google.maps.Map(document.getElementById('map'), {
                zoom: 12,
                center: defaultCenter,
                mapTypeControl: true,
                fullscreenControl: true,
                streetViewControl: true,
                zoomControl: true,
                styles: [
                    {
                        featureType: "poi",
                        elementType: "labels",
                        stylers: [{ visibility: "off" }]
                    }
                ]
            });
            
            // Initialize directions service
            directionsService = new google.maps.DirectionsService();
            directionsRenderer = new google.maps.DirectionsRenderer({
                map: map,
                suppressMarkers: true,
                polylineOptions: {
                    strokeColor: '#667eea',
                    strokeWeight: 5,
                    strokeOpacity: 0.8
                }
            });
            
            // Add pickup marker
            if (pickupLat && pickupLng) {
                pickupMarker = new google.maps.Marker({
                    position: { lat: parseFloat(pickupLat), lng: parseFloat(pickupLng) },
                    map: map,
                    title: 'Pickup Location',
                    icon: {
                        url: 'https://maps.google.com/mapfiles/ms/icons/green-dot.png',
                        scaledSize: new google.maps.Size(40, 40)
                    },
                    animation: google.maps.Animation.DROP
                });
                
                // Add info window for pickup
                const pickupInfo = new google.maps.InfoWindow({
                    content: '<div style="padding:10px;"><strong>Pickup Location</strong><br>Your store</div>'
                });
                
                pickupMarker.addListener('click', () => {
                    pickupInfo.open(map, pickupMarker);
                });
            }
            
            // Add delivery marker
            if (deliveryLat && deliveryLng) {
                deliveryMarker = new google.maps.Marker({
                    position: { lat: parseFloat(deliveryLat), lng: parseFloat(deliveryLng) },
                    map: map,
                    title: 'Delivery Location',
                    icon: {
                        url: 'https://maps.google.com/mapfiles/ms/icons/red-dot.png',
                        scaledSize: new google.maps.Size(40, 40)
                    },
                    animation: google.maps.Animation.DROP
                });
                
                const deliveryInfo = new google.maps.InfoWindow({
                    content: '<div style="padding:10px;"><strong>Delivery Location</strong><br>Customer address</div>'
                });
                
                deliveryMarker.addListener('click', () => {
                    deliveryInfo.open(map, deliveryMarker);
                });
            }
            
            // Add courier marker if active
            if (courierLat && courierLng) {
                addCourierMarker(courierLat, courierLng);
                
                // Draw route if both pickup and delivery exist
                if (pickupLat && pickupLng && deliveryLat && deliveryLng) {
                    drawRoute();
                }
                
                // Center map on courier
                map.setCenter({ lat: parseFloat(courierLat), lng: parseFloat(courierLng) });
                map.setZoom(14);
                
                // Start real-time updates
                startRealTimeUpdates();
            } else if (pickupLat && pickupLng) {
                // Center on pickup if no courier
                map.setCenter({ lat: parseFloat(pickupLat), lng: parseFloat(pickupLng) });
                map.setZoom(13);
            } else if (deliveryLat && deliveryLng) {
                // Center on delivery if no pickup
                map.setCenter({ lat: parseFloat(deliveryLat), lng: parseFloat(deliveryLng) });
                map.setZoom(13);
            }
        }
        
        function addCourierMarker(lat, lng) {
            // Remove existing courier marker if any
            if (courierMarker) {
                courierMarker.setMap(null);
            }
            
            // Create custom courier icon (animated truck)
            courierMarker = new google.maps.Marker({
                position: { lat: parseFloat(lat), lng: parseFloat(lng) },
                map: map,
                title: 'Courier',
                icon: {
                    path: google.maps.SymbolPath.FORWARD_CLOSED_ARROW,
                    scale: 6,
                    strokeColor: '#667eea',
                    strokeWeight: 3,
                    fillColor: '#667eea',
                    fillOpacity: 1,
                    rotation: 0
                },
                animation: google.maps.Animation.BOUNCE
            });
            
            const courierInfo = new google.maps.InfoWindow({
                content: '<div style="padding:10px;"><strong>Courier Location</strong><br>Moving towards destination</div>'
            });
            
            courierMarker.addListener('click', () => {
                courierInfo.open(map, courierMarker);
            });
            
            // Stop bouncing after 3 seconds
            setTimeout(() => {
                if (courierMarker) {
                    courierMarker.setAnimation(null);
                }
            }, 3000);
        }
        
        function drawRoute() {
            if (!pickupLat || !pickupLng || !deliveryLat || !deliveryLng) return;
            
            const request = {
                origin: { lat: parseFloat(pickupLat), lng: parseFloat(pickupLng) },
                destination: { lat: parseFloat(deliveryLat), lng: parseFloat(deliveryLng) },
                travelMode: google.maps.TravelMode.DRIVING
            };
            
            directionsService.route(request, (result, status) => {
                if (status === 'OK') {
                    directionsRenderer.setDirections(result);
                    
                    // Get route distance and time
                    const route = result.routes[0].legs[0];
                    console.log(`Distance: ${route.distance.text}, Duration: ${route.duration.text}`);
                } else {
                    console.error('Directions request failed:', status);
                }
            });
        }
        
        function startRealTimeUpdates() {
            // Update every 30 seconds
            updateInterval = setInterval(updateCourierLocation, 30000);
        }
        
        function updateCourierLocation() {
            fetch(`/borzo/api/track-order.php?order_id=${orderId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.courier) {
                        const courier = data.courier;
                        if (courier.latitude && courier.longitude) {
                            addCourierMarker(courier.latitude, courier.longitude);
                            
                            // Update route if needed
                            if (pickupLat && pickupLng && deliveryLat && deliveryLng) {
                                drawRoute();
                            }
                        }
                    }
                })
                .catch(error => console.error('Error updating courier location:', error));
        }
        
        function refreshTracking() {
            // Show loading on button
            const btn = document.querySelector('.refresh-btn i');
            btn.classList.add('spin');
            
            // Reload the page after a short delay
            setTimeout(() => {
                window.location.reload();
            }, 500);
        }
        
        // Clean up interval on page unload
        window.addEventListener('beforeunload', () => {
            if (updateInterval) {
                clearInterval(updateInterval);
            }
        });
    </script>
    
    <style>
        .spin {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>
</body>
</html>