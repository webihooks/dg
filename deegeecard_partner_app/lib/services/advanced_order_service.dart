import 'package:flutter/services.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:audioplayers/audioplayers.dart';

class AdvancedOrderService {
  static const MethodChannel _channel = 
      MethodChannel('com.deegeecard/foreground_service');
  
  static final FlutterLocalNotificationsPlugin _notifications = 
      FlutterLocalNotificationsPlugin();
  static final AudioPlayer _audioPlayer = AudioPlayer();

  static Future<void> initialize() async {
    // Initialize notifications
    const AndroidInitializationSettings androidSettings =
        AndroidInitializationSettings('@mipmap/ic_launcher');
    
    const InitializationSettings settings = 
        InitializationSettings(android: androidSettings);
    
    await _notifications.initialize(settings);
    
    // Create notification channel for Android 8.0+
    await _createNotificationChannel();
  }

  static Future<void> startOrderMonitoring() async {
    try {
      await _channel.invokeMethod('startForegroundService');
      
      // Save service state
      final prefs = await SharedPreferences.getInstance();
      await prefs.setBool('advanced_order_monitoring_active', true);
      
      print('Advanced order monitoring service started');
    } on PlatformException catch (e) {
      print("Failed to start advanced order monitoring: '${e.message}'.");
      rethrow;
    }
  }

  static Future<void> stopOrderMonitoring() async {
    try {
      await _channel.invokeMethod('stopForegroundService');
      
      // Save service state
      final prefs = await SharedPreferences.getInstance();
      await prefs.setBool('advanced_order_monitoring_active', false);
      
      print('Advanced order monitoring service stopped');
    } on PlatformException catch (e) {
      print("Failed to stop advanced order monitoring: '${e.message}'.");
      rethrow;
    }
  }

  static Future<bool> isOrderMonitoringActive() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getBool('advanced_order_monitoring_active') ?? false;
  }

  static Future<void> showNewOrderNotification(String orderId) async {
    try {
      // Play sound
      await _audioPlayer.play(AssetSource('sounds/new_order.wav'));
      
      // Show notification
      const AndroidNotificationDetails androidDetails = AndroidNotificationDetails(
        'new_orders_channel', // Same channel ID as in native code
        'New Orders',
        channelDescription: 'Notifications for new restaurant orders',
        importance: Importance.high,
        priority: Priority.high,
        playSound: true,
        sound: RawResourceAndroidNotificationSound('new_order'),
      );
      
      const NotificationDetails details = NotificationDetails(android: androidDetails);
      
      await _notifications.show(
        DateTime.now().millisecondsSinceEpoch.remainder(100000),
        'New Order Received! 🎉',
        'Order #$orderId is ready for processing',
        details,
      );
    } catch (e) {
      print('Error showing notification: $e');
    }
  }

  static Future<void> _createNotificationChannel() async {
    // Create a Notification Channel for Android 8.0+
    const AndroidNotificationChannel channel = AndroidNotificationChannel(
      'new_orders_channel', // Same channel ID as above
      'New Orders',
      description: 'Notifications for new restaurant orders',
      importance: Importance.high,
      playSound: true,
    );

    await _notifications
        .resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(channel);
  }

  // Test method to simulate new order
  static Future<void> testNewOrder() async {
    await showNewOrderNotification('TEST-${DateTime.now().millisecondsSinceEpoch}');
  }
}