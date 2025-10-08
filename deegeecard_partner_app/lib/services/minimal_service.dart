import 'package:flutter/services.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:audioplayers/audioplayers.dart';

class MinimalService {
  static const MethodChannel _channel = 
      MethodChannel('com.deegeecard/foreground_service');
  static final AudioPlayer _audioPlayer = AudioPlayer();

  static Future<void> initialize() async {
    print('Minimal Service initialized');
  }

  static Future<void> startOrderMonitoring() async {
    try {
      // For Phase 1, we'll simulate the service
      // In Phase 2, we'll implement the actual native service
      await _channel.invokeMethod('startForegroundService');
      
      final prefs = await SharedPreferences.getInstance();
      await prefs.setBool('minimal_monitoring_active', true);
      
      print('Minimal order monitoring started (simulated)');
    } on PlatformException catch (e) {
      print("Simulated service start: '${e.message}'.");
      final prefs = await SharedPreferences.getInstance();
      await prefs.setBool('minimal_monitoring_active', true);
    }
  }

  static Future<void> stopOrderMonitoring() async {
    try {
      await _channel.invokeMethod('stopForegroundService');
    } on PlatformException catch (e) {
      print("Simulated service stop: '${e.message}'.");
    } finally {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setBool('minimal_monitoring_active', false);
      print('Minimal order monitoring stopped');
    }
  }

  static Future<bool> isOrderMonitoringActive() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getBool('minimal_monitoring_active') ?? false;
  }

  static Future<void> playTestSound() async {
    try {
      await _audioPlayer.play(AssetSource('sounds/new_order.wav'));
      print('Test sound played');
    } catch (e) {
      print('Error playing test sound: $e');
    }
  }
}