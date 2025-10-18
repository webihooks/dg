import 'package:flutter/services.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:audioplayers/audioplayers.dart';

class SimpleForegroundService {
  static const MethodChannel _channel = 
      MethodChannel('com.deegeecard/foreground_service');
  static final AudioPlayer _audioPlayer = AudioPlayer();

  static Future<void> startOrderMonitoring() async {
    try {
      await _channel.invokeMethod('startForegroundService');
      
      // Save service state
      final prefs = await SharedPreferences.getInstance();
      await prefs.setBool('order_monitoring_active', true);
      
      print('Order monitoring service started');
    } on PlatformException catch (e) {
      print("Failed to start order monitoring: '${e.message}'.");
      // Fallback: just save the state
      final prefs = await SharedPreferences.getInstance();
      await prefs.setBool('order_monitoring_active', true);
    }
  }

  static Future<void> stopOrderMonitoring() async {
    try {
      await _channel.invokeMethod('stopForegroundService');
    } on PlatformException catch (e) {
      print("Failed to stop order monitoring: '${e.message}'.");
    } finally {
      // Always save the state
      final prefs = await SharedPreferences.getInstance();
      await prefs.setBool('order_monitoring_active', false);
      print('Order monitoring service stopped');
    }
  }

  static Future<bool> isOrderMonitoringActive() async {
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