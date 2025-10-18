import 'package:flutter/services.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:workmanager/workmanager.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:audioplayers/audioplayers.dart';
import '../api/services/api_service.dart';

class Phase2Service {
  static const MethodChannel _channel = 
      MethodChannel('com.deegeecard/foreground_service');
  
  static final FlutterLocalNotificationsPlugin _notifications = 
      FlutterLocalNotificationsPlugin();
  static final AudioPlayer _audioPlayer = AudioPlayer();
  static final ApiService _apiService = ApiService();

  static bool _isMonitoring = false;
  static int _lastOrderCheck = 0;

  // Background task unique name
  static const String backgroundTaskName = "orderBackgroundCheck";

  static Future<void> initialize() async {
    // Initialize notifications
    const AndroidInitializationSettings androidSettings =
        AndroidInitializationSettings('@mipmap/ic_launcher');
    
    const InitializationSettings settings = 
        InitializationSettings(android: androidSettings);
    
    await _notifications.initialize(settings);

    // Initialize WorkManager for background tasks
    await Workmanager().initialize(
      callbackDispatcher,
      isInDebugMode: true,
    );

    // Create notification channel
    await _createNotificationChannel();

    print('Phase 2 Service initialized with background order checking');
  }

  static Future<void> startOrderMonitoring() async {
    try {
      // Start native foreground service
      await _channel.invokeMethod('startForegroundService');
      
      // Start background periodic task
      await Workmanager().registerPeriodicTask(
        backgroundTaskName,
        "orderBackgroundCheck",
        frequency: const Duration(minutes: 5), // Check every 5 minutes
        initialDelay: const Duration(seconds: 10),
        constraints: Constraints(
          networkType: NetworkType.connected,
        ),
      );

      // Save service state
      final prefs = await SharedPreferences.getInstance();
      await prefs.setBool('phase2_monitoring_active', true);
      await prefs.setInt('last_order_check', DateTime.now().millisecondsSinceEpoch);

      _isMonitoring = true;
      
      print('Phase 2 order monitoring started with background checks');
    } on PlatformException catch (e) {
      print("Failed to start Phase 2 monitoring: '${e.message}'.");
      rethrow;
    }
  }

  static Future<void> stopOrderMonitoring() async {
    try {
      // Stop native foreground service
      await _channel.invokeMethod('stopForegroundService');
      
      // Cancel background task
      await Workmanager().cancelByUniqueName(backgroundTaskName);

      // Save service state
      final prefs = await SharedPreferences.getInstance();
      await prefs.setBool('phase2_monitoring_active', false);

      _isMonitoring = false;
      
      print('Phase 2 order monitoring stopped');
    } on PlatformException catch (e) {
      print("Failed to stop Phase 2 monitoring: '${e.message}'.");
      rethrow;
    }
  }

  static Future<bool> isOrderMonitoringActive() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getBool('phase2_monitoring_active') ?? false;
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
      // Use the new getNewOrders method from ApiService
      final newOrders = await _apiService.getNewOrders(sinceTimestamp);
      
      // If no specific new orders endpoint, use recent orders as fallback
      if (newOrders.isEmpty) {
        final recentOrders = await _apiService.getRecentOrders();
        
        // Filter orders that are newer than our last check
        return recentOrders.where((order) {
          final orderTime = _getOrderTimestamp(order);
          return orderTime > sinceTimestamp;
        }).toList();
      }
      
      return newOrders;
    } catch (e) {
      print('Error fetching orders from API: $e');
      return [];
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
      
      if (order['timestamp'] != null) {
        final timestamp = order['timestamp'];
        if (timestamp is int) return timestamp;
        if (timestamp is String) {
          return DateTime.parse(timestamp).millisecondsSinceEpoch;
        }
      }
      
      // If no timestamp found, use current time
      return DateTime.now().millisecondsSinceEpoch;
    } catch (e) {
      print('Error parsing order timestamp: $e');
      return DateTime.now().millisecondsSinceEpoch;
    }
  }

  static Future<void> _notifyNewOrder(String orderId, String customerName, double totalAmount) async {
    try {
      // Play sound
      await _audioPlayer.play(AssetSource('sounds/new_order.wav'));
      
      // Show notification
      const AndroidNotificationDetails androidDetails = AndroidNotificationDetails(
        'new_orders_channel',
        'New Orders',
        'Notifications for new restaurant orders',
        importance: Importance.high,
        priority: Priority.high,
        playSound: true,
      );
      
      const NotificationDetails details = NotificationDetails(android: androidDetails);
      
      await _notifications.show(
        DateTime.now().millisecondsSinceEpoch.remainder(100000),
        'New Order Received! 🎉',
        'Order #$orderId from $customerName - ₹$totalAmount',
        details,
      );

      print('📢 Notification sent for order #$orderId');
    } catch (e) {
      print('Error showing notification: $e');
    }
  }

  static Future<void> _createNotificationChannel() async {
    final AndroidNotificationChannel channel = AndroidNotificationChannel(
      'new_orders_channel',
      'New Orders',
      'Notifications for new restaurant orders',
      importance: Importance.high,
    );

    await _notifications
        .resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(channel);
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

// Background task callback
@pragma('vm:entry-point')
void callbackDispatcher() {
  Workmanager().executeTask((task, inputData) async {
    print("🔄 Background task running: $task");
    
    if (task == "orderBackgroundCheck") {
      await Phase2Service.checkForNewOrders();
      return Future.value(true);
    }
    
    return Future.value(false);
  });
}