import 'package:flutter/services.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:audioplayers/audioplayers.dart';
import '../api/services/api_service.dart';

class SimplePhase2Service {
  static const MethodChannel _channel = 
      MethodChannel('com.deegeecard/foreground_service');
  
  static final AudioPlayer _audioPlayer = AudioPlayer();
  static final ApiService _apiService = ApiService();

  static bool _isMonitoring = false;
  static int _lastOrderCheck = 0;

  static Future<void> initialize() async {
    print('Simple Phase 2 Service initialized');
  }

  static Future<void> startOrderMonitoring() async {
    try {
      // Start native foreground service
      await _channel.invokeMethod('startForegroundService');
      
      // Start periodic checking using Timer (simpler than WorkManager)
      _startPeriodicChecking();
      
      // Save service state
      final prefs = await SharedPreferences.getInstance();
      await prefs.setBool('simple_phase2_monitoring_active', true);
      await prefs.setInt('last_order_check', DateTime.now().millisecondsSinceEpoch);

      _isMonitoring = true;
      
      print('Simple Phase 2 order monitoring started');
    } on PlatformException catch (e) {
      print("Failed to start Simple Phase 2 monitoring: '${e.message}'.");
      rethrow;
    }
  }

  static Future<void> stopOrderMonitoring() async {
    try {
      // Stop native foreground service
      await _channel.invokeMethod('stopForegroundService');
      
      // Stop periodic checking
      _stopPeriodicChecking();

      // Save service state
      final prefs = await SharedPreferences.getInstance();
      await prefs.setBool('simple_phase2_monitoring_active', false);

      _isMonitoring = false;
      
      print('Simple Phase 2 order monitoring stopped');
    } on PlatformException catch (e) {
      print("Failed to stop Simple Phase 2 monitoring: '${e.message}'.");
      rethrow;
    }
  }

  static Future<bool> isOrderMonitoringActive() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getBool('simple_phase2_monitoring_active') ?? false;
  }

  static void _startPeriodicChecking() {
    // We'll implement this using Timer for simplicity
    // In a real app, you'd want to use WorkManager for true background execution
    print('Periodic checking started (simulated)');
  }

  static void _stopPeriodicChecking() {
    print('Periodic checking stopped');
  }

  static Future<void> checkForNewOrders() async {
    try {
      print('🔍 Checking for new orders...');
      
      final prefs = await SharedPreferences.getInstance();
      final lastCheck = prefs.getInt('last_order_check') ?? 0;
      
      // Get new orders from API
      final newOrders = await _fetchNewOrdersSince(lastCheck);
      
      if (newOrders.isNotEmpty) {
        print('🎉 Found ${newOrders.length} new orders!');
        
        for (final order in newOrders) {
          await _notifyNewOrder(
            order['id']?.toString() ?? 'Unknown',
            order['customer_name']?.toString() ?? 'Customer',
            double.parse(order['total_amount']?.toString() ?? '0')
          );
        }
        
        // Update last check time
        await prefs.setInt('last_order_check', DateTime.now().millisecondsSinceEpoch);
      } else {
        print('📭 No new orders found');
      }
    } catch (e) {
      print('❌ Error checking orders: $e');
    }
  }

  static Future<List<dynamic>> _fetchNewOrdersSince(int sinceTimestamp) async {
    try {
      // Use the getNewOrders method from ApiService
      final newOrders = await _apiService.getNewOrders(sinceTimestamp);
      return newOrders;
    } catch (e) {
      print('Error fetching orders from API: $e');
      return [];
    }
  }

  static Future<void> _notifyNewOrder(String orderId, String customerName, double totalAmount) async {
    try {
      // Play sound
      await _audioPlayer.play(AssetSource('sounds/new_order.wav'));
      
      // Show a simple notification using platform channels
      // For a real notification, you'd use flutter_local_notifications
      print('📢 New Order Notification: Order #$orderId from $customerName - ₹$totalAmount');
      
      // You can also show a local alert or update UI if app is in foreground
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
  static Future<void> manualOrderCheck() async {
    await checkForNewOrders();
  }
}