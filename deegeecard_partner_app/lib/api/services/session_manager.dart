import 'package:shared_preferences/shared_preferences.dart';
import 'dart:convert'; 

class SessionManager {
  static final SessionManager _instance = SessionManager._internal();
  factory SessionManager() => _instance;
  SessionManager._internal();

  Map<String, String> _cookies = {};

  // Load cookies from persistent storage
  Future<void> loadPersistentCookies() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final cookiesJson = prefs.getString('persistent_cookies');
      if (cookiesJson != null) {
        final Map<String, dynamic> cookiesMap = Map<String, dynamic>.from(json.decode(cookiesJson));
        _cookies = cookiesMap.map((key, value) => MapEntry(key, value.toString()));
        print('🍪 Loaded persistent cookies: $_cookies');
      }
    } catch (e) {
      print('❌ Error loading persistent cookies: $e');
    }
  }

  // Save cookies to persistent storage
  Future<void> savePersistentCookies() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('persistent_cookies', json.encode(_cookies));
      print('💾 Saved persistent cookies');
    } catch (e) {
      print('❌ Error saving persistent cookies: $e');
    }
  }

  Map<String, String> get cookies => _cookies;

  void updateCookies(Map<String, String> newCookies) {
    _cookies.addAll(newCookies);
    savePersistentCookies(); // Save whenever cookies are updated
  }

  void clearCookies() {
    _cookies.clear();
    _clearPersistentCookies();
  }

  Future<void> _clearPersistentCookies() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove('persistent_cookies');
      print('🗑️ Cleared persistent cookies');
    } catch (e) {
      print('❌ Error clearing persistent cookies: $e');
    }
  }

  Map<String, String> getHeadersWithCookies(Map<String, String> baseHeaders) {
    final headers = Map<String, String>.from(baseHeaders);
    if (_cookies.isNotEmpty) {
      headers['Cookie'] = _cookies.entries
          .map((entry) => '${entry.key}=${entry.value}')
          .join('; ');
    }
    return headers;
  }

  void updateFromResponseHeaders(Map<String, String> responseHeaders) {
    final setCookieHeader = responseHeaders['set-cookie'];
    if (setCookieHeader != null) {
      final cookies = setCookieHeader.split(';');
      for (var cookie in cookies) {
        final parts = cookie.split('=');
        if (parts.length == 2) {
          _cookies[parts[0].trim()] = parts[1].trim();
        }
      }
      savePersistentCookies(); // Save after updating from response
    }
  }
}