import 'package:audioplayers/audioplayers.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';

class OrderNotificationHandler {
  static final FlutterLocalNotificationsPlugin _notifications = 
      FlutterLocalNotificationsPlugin();
  static final AudioPlayer _audioPlayer = AudioPlayer();

  static Future<void> initialize() async {
    const AndroidInitializationSettings androidSettings =
        AndroidInitializationSettings('@mipmap/ic_launcher');
    
    const InitializationSettings settings = 
        InitializationSettings(android: androidSettings);
    
    await _notifications.initialize(settings);
  }

  static Future<void> showNewOrderNotification({
    required String orderId,
    required String restaurantName,
    required String customerName,
    required double totalAmount,
  }) async {
    // Play sound notification
    await _playOrderSound();
    
    // Show notification
    const AndroidNotificationDetails androidDetails = AndroidNotificationDetails(
      'new_orders_channel',
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
      'New Order Received!',
      'Order #$orderId from $customerName - \$$totalAmount',
      details,
    );
  }

  static Future<void> _playOrderSound() async {
    try {
      await _audioPlayer.play(AssetSource('sounds/new_order.wav'));
    } catch (e) {
      print('Error playing order sound: $e');
    }
  }
}