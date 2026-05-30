<?php
// includes/loyalty_helper.php - Universal (works with PDO and MySQLi)
if (!function_exists('getLoyaltySettings')) {
    function getLoyaltySettings($conn, $user_id) {
        $default = [
            'redemption_points' => 1000,
            'redemption_currency_amount' => 10.00,
            'earn_points_per_currency' => 1,
            'welcome_points' => 1000
        ];
        
        // Detect if connection is PDO or MySQLi
        $isPdo = ($conn instanceof PDO);
        
        if ($isPdo) {
            // PDO version (used by products.php, place_order.php, etc.)
            $sql = "SELECT redemption_points, redemption_currency_amount, earn_points_per_currency, welcome_points 
                    FROM loyalty_settings WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                return $default;
            }
            $stmt->execute([$user_id]);
            $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            // MySQLi version (used by orders.php)
            $sql = "SELECT redemption_points, redemption_currency_amount, earn_points_per_currency, welcome_points 
                    FROM loyalty_settings WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                return $default;
            }
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $settings = $result->fetch_assoc();
            $stmt->close();
        }
        
        if ($settings && is_array($settings)) {
            return array_merge($default, $settings);
        }
        return $default;
    }
}
?>