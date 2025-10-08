import 'package:flutter/services.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:audioplayers/audioplayers.dart';

class Phase1Service {
  static const MethodChannel _channel = 
      MethodChannel('com.deegeecard/foreground_service');
  static final AudioPlayer _audioPlayer = AudioPlayer();

  static Future<void> initialize() async {
    print('Phase 1 Service initialized');
  }

  static Future<void> startOrderMonitoring() async {
    try {
      await _channel.invokeMethod('startForegroundService');
      
      // Save service state
      final prefs = await SharedPreferences.getInstance();
      await prefs.setBool('phase1_monitoring_active', true);
      
      print('Phase 1 order monitoring service started');
    } on PlatformException catch (e) {
      print("Failed to start order monitoring: '${e.message}'.");
      // Fallback: just save the state
      final prefs = await SharedPreferences.getInstance();
      await prefs.setBool('phase1_monitoring_active', true);
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
      await prefs.setBool('phase1_monitoring_active', false);
      print('Phase 1 order monitoring service stopped');
    }
  }

  static Future<bool> isOrderMonitoringActive() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getBool('phase1_monitoring_active') ?? false;
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