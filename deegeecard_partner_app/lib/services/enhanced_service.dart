import 'package:flutter/services.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:audioplayers/audioplayers.dart';
import '../api/services/api_service.dart';

class EnhancedService {
  static const MethodChannel _channel = 
      MethodChannel('com.deegeecard/foreground_service');
  
  static final AudioPlayer _audioPlayer = AudioPlayer();
  static final ApiService _apiService = ApiService();

  static bool _isMonitoring = false;

  static Future<void> initialize() async {
    print('Enhanced Service initialized');
  }

  static Future<void> startOrderMonitoring() async {
    try {
      // Start native foreground service
      await _channel.invokeMethod('startForegroundService');
      
      // Save service state
      final prefs = await SharedPreferences.getInstance();
      await prefs.setBool('enhanced_monitoring_active', true);
      await prefs.setInt('last_order_check', DateTime.now().millisecondsSinceEpoch);

      _isMonitoring = true;
      
      print('Enhanced order monitoring started');
    } on PlatformException catch (e) {
      print("Failed to start enhanced monitoring: '${e.message}'.");
      // Fallback: just save the state
      final prefs = await SharedPreferences.getInstance();
      await prefs.setBool('enhanced_monitoring_active', true);
    }
  }

  static Future<void> stopOrderMonitoring() async {
    try {
      // Stop native foreground service
      await _channel.invokeMethod('stopForegroundService');
    } on PlatformException catch (e) {
      print("Failed to stop enhanced monitoring: '${e.message}'.");
    } finally {
      // Always save the state
      final prefs = await SharedPreferences.getInstance();
      await prefs.setBool('enhanced_monitoring_active', false);
      _isMonitoring = false;
      print('Enhanced order monitoring stopped');
    }
  }

  static Future<bool> isOrderMonitoringActive() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getBool('enhanced_monitoring_active') ?? false;
  }

  static Future<Map<String, dynamic>> checkForNewOrders() async {
    try {
      print('🔍 Checking for new orders...');
      
      final prefs = await SharedPreferences.getInstance();
      final lastCheck = prefs.getInt('last_order_check') ?? 0;
      
      // Get orders from API (you'll need to implement this in your ApiService)
      final orders = await _fetchRecentOrders();
      
      // Filter new orders (simplified logic)
      final newOrders = _filterNewOrders(orders, lastCheck);
      
      if (newOrders.isNotEmpty) {
        print('🎉 Found ${newOrders.length} new orders!');
        
        // Notify for each new order
        for (final order in newOrders) {
          await _notifyNewOrder(
            order['id']?.toString() ?? 'Unknown',
            order['customer_name']?.toString() ?? 'Customer',
            (order['total_amount'] is num ? order['total_amount'] : 0).toDouble()
          );
        }
        
        // Update last check time
        await prefs.setInt('last_order_check', DateTime.now().millisecondsSinceEpoch);
        
        return {
          'success': true,
          'newOrders': newOrders.length,
          'message': 'Found ${newOrders.length} new orders'
        };
      } else {
        print('📭 No new orders found');
        return {
          'success': true,
          'newOrders': 0,
          'message': 'No new orders found'
        };
      }
    } catch (e) {
      print('❌ Error checking orders: $e');
      return {
        'success': false,
        'error': e.toString(),
        'message': 'Failed to check orders: $e'
      };
    }
  }

  static Future<List<dynamic>> _fetchRecentOrders() async {
  try {
    // Use the new getRecentOrders method from ApiService
    return await _apiService.getRecentOrders();
  } catch (e) {
    print('Error fetching orders from API: $e');
    
    // Fallback: try getAllOrders if getRecentOrders fails
    try {
      final allOrders = await _apiService.getAllOrders();
      // Filter only recent orders (last 1 hour)
      final oneHourAgo = DateTime.now().subtract(Duration(hours: 1)).millisecondsSinceEpoch;
      return allOrders.where((order) {
        final orderTime = _getOrderTimestamp(order);
        return orderTime > oneHourAgo;
      }).toList();
    } catch (fallbackError) {
      print('Fallback also failed: $fallbackError');
      return [];
    }
  }
}

// Helper method to extract timestamp from order
static int _getOrderTimestamp(Map<String, dynamic> order) {
  try {
    // Try different possible timestamp fields
    if (order['created_at'] != null) {
      final createdAt = order['created_at'];
      if (createdAt is int) return createdAt;
      if (createdAt is String) {
        return DateTime.parse(createdAt).millisecondsSinceEpoch;
      }
    }
    
    if (order['order_date'] != null) {
      final orderDate = order['order_date'];
      if (orderDate is int) return orderDate;
      if (orderDate is String) {
        return DateTime.parse(orderDate).millisecondsSinceEpoch;
      }
    }
    
    // Default to current time if no timestamp found
    return DateTime.now().millisecondsSinceEpoch;
  } catch (e) {
    return DateTime.now().millisecondsSinceEpoch;
  }
}

  static List<dynamic> _filterNewOrders(List<dynamic> orders, int sinceTimestamp) {
    // Simple filtering - you might want to implement more sophisticated logic
    // based on your order data structure
    if (sinceTimestamp == 0) return orders; // First time checking
    
    return orders.where((order) {
      final orderTime = order['created_at'] ?? order['order_date'] ?? 0;
      return orderTime > sinceTimestamp;
    }).toList();
  }

  static Future<void> _notifyNewOrder(String orderId, String customerName, double totalAmount) async {
    try {
      // Play sound
      await _audioPlayer.play(AssetSource('sounds/new_order.wav'));
      
      // Show native notification via method channel
      await _channel.invokeMethod('showOrderNotification', {
        'orderId': orderId,
        'customerName': customerName,
        'totalAmount': totalAmount,
      });

      print('📢 Notification sent for order #$orderId');
    } catch (e) {
      print('Error showing notification: $e');
    }
  }

  // Test method to simulate new order
  static Future<void> testNewOrder() async {
    await _notifyNewOrder(
      'TEST-${DateTime.now().millisecondsSinceEpoch}',
      'Test Customer',
      999.99
    );
  }

  // Manual order check (for testing)
  static Future<Map<String, dynamic>> manualOrderCheck() async {
    return await checkForNewOrders();
  }
}