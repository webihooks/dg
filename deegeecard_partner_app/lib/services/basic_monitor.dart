import 'package:shared_preferences/shared_preferences.dart';
import 'package:audioplayers/audioplayers.dart';

class BasicOrderMonitor {
  static final AudioPlayer _audioPlayer = AudioPlayer();

  static Future<void> startMonitoring() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool('order_monitoring_active', true);
    print('Order monitoring started (basic mode)');
  }

  static Future<void> stopMonitoring() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool('order_monitoring_active', false);
    print('Order monitoring stopped (basic mode)');
  }

  static Future<bool> isMonitoringActive() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getBool('order_monitoring_active') ?? false;
  }

  static Future<void> playOrderSound() async {
    try {
      await _audioPlayer.play(AssetSource('sounds/new_order.wav'));
    } catch (e) {
      print('Error playing order sound: $e');
    }
  }
}