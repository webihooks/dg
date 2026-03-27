<?php
// borzo/classes/WebhookHandler.php

class WebhookHandler {
    private $config;
    private $logger;
    private $db;
    
    public function __construct($config, $logger) {
        $this->config = $config;
        $this->logger = $logger;
        $this->db = Database::getInstance();
    }
    
    public function handle() {
        // Get signature header
        $signature = $_SERVER['HTTP_X_DV_SIGNATURE'] ?? '';
        $data = file_get_contents('php://input');
        
        // Log incoming webhook
        $this->logger->log('Webhook received', ['signature' => $signature]);
        
        // Verify signature
        if (!$this->verifySignature($data, $signature)) {
            $this->logger->log('Invalid signature', ['signature' => $signature]);
            http_response_code(401);
            echo 'Invalid signature';
            return;
        }
        
        // Process webhook data
        $callbackData = json_decode($data, true);
        
        if (!$callbackData) {
            $this->logger->log('Invalid JSON', ['data' => $data]);
            http_response_code(400);
            echo 'Invalid JSON';
            return;
        }
        
        try {
            $this->processCallback($callbackData);
            http_response_code(200);
            echo 'OK';
        } catch (Exception $e) {
            $this->logger->log('Error processing webhook', [
                'error' => $e->getMessage(),
                'data' => $callbackData
            ]);
            http_response_code(500);
            echo 'Error processing webhook';
        }
    }
    
    private function verifySignature($data, $signature) {
        $calculated = hash_hmac('sha256', $data, $this->config['webhook']['secret']);
        return hash_equals($calculated, $signature);
    }
    
    private function processCallback($data) {
        $eventType = $data['event_type'] ?? '';
        $eventDatetime = $data['event_datetime'] ?? '';
        
        $this->logger->log('Processing event', [
            'type' => $eventType,
            'datetime' => $eventDatetime
        ]);
        
        switch ($eventType) {
            case 'order_created':
            case 'order_changed':
                $this->processOrderEvent($data['order'], $eventType);
                break;
                
            case 'delivery_created':
            case 'delivery_changed':
                $this->processDeliveryEvent($data['delivery'], $eventType);
                break;
                
            default:
                $this->logger->log('Unknown event type', ['type' => $eventType]);
        }
    }
    
    private function processOrderEvent($order, $eventType) {
        // Update order in database
        $this->updateOrderStatus($order['order_id'], $order['status']);
        
        // Send notifications based on status
        switch ($order['status']) {
            case 'active':
                $this->sendCourierAssignedNotification($order);
                break;
            case 'completed':
                $this->sendDeliveryCompletedNotification($order);
                break;
            case 'canceled':
                $this->sendOrderCanceledNotification($order);
                break;
        }
        
        $this->logger->log('Order event processed', [
            'order_id' => $order['order_id'],
            'status' => $order['status']
        ]);
    }
    
    private function processDeliveryEvent($delivery, $eventType) {
        // Update delivery in database
        $this->updateDeliveryStatus($delivery['delivery_id'], $delivery['status']);
        
        $this->logger->log('Delivery event processed', [
            'delivery_id' => $delivery['delivery_id'],
            'status' => $delivery['status']
        ]);
    }
    
    private function updateOrderStatus($borzoOrderId, $status) {
        $sql = "UPDATE orders SET borzo_status = ?, updated_at = NOW() WHERE borzo_order_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$status, $borzoOrderId]);
    }
    
    private function updateDeliveryStatus($deliveryId, $status) {
        // Update delivery status if you track deliveries separately
        $sql = "UPDATE deliveries SET status = ?, updated_at = NOW() WHERE delivery_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$status, $deliveryId]);
    }
    
    private function sendCourierAssignedNotification($order) {
        // Get order details from database
        $orderDetails = $this->getOrderDetails($order['order_id']);
        
        if ($orderDetails) {
            // Send email/SMS to customer
            $this->sendEmail(
                $orderDetails['customer_email'],
                'Courier Assigned to Your Order',
                $this->getCourierAssignedEmailContent($order)
            );
        }
    }
    
    private function sendDeliveryCompletedNotification($order) {
        $orderDetails = $this->getOrderDetails($order['order_id']);
        
        if ($orderDetails) {
            $this->sendEmail(
                $orderDetails['customer_email'],
                'Your Order Has Been Delivered',
                $this->getDeliveryCompletedEmailContent($order)
            );
        }
    }
    
    private function sendOrderCanceledNotification($order) {
        $orderDetails = $this->getOrderDetails($order['order_id']);
        
        if ($orderDetails) {
            $this->sendEmail(
                $orderDetails['customer_email'],
                'Your Order Has Been Canceled',
                $this->getOrderCanceledEmailContent($order)
            );
            
            // Process refund if applicable
            if ($orderDetails['payment_method'] === 'bank_card') {
                $this->processRefund($orderDetails);
            }
        }
    }
    
    private function getOrderDetails($borzoOrderId) {
        $sql = "SELECT * FROM orders WHERE borzo_order_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$borzoOrderId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function sendEmail($to, $subject, $content) {
        // Implement your email sending logic
        mail($to, $subject, $content, "From: noreply@deegeecard.com");
    }
}