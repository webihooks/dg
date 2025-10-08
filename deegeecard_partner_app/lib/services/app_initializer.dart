import 'package:shared_preferences/shared_preferences.dart';

class AppInitializer {
  static Future<void> initialize() async {
    // Any app-wide initialization can go here
    // For now, we'll just ensure SharedPreferences is ready
    await SharedPreferences.getInstance();
    print('App initialized successfully');
  }
}