import 'dart:convert';
import 'package:http/http.dart' as http;
import '../constants/api_constants.dart';
import '../models/login_response.dart';
import '../models/user_model.dart';
import 'session_manager.dart';

class ApiService {
  static final ApiService _instance = ApiService._internal();
  factory ApiService() => _instance;
  ApiService._internal();

  final SessionManager _sessionManager = SessionManager();

  // Initialize session manager - call this when app starts
  Future<void> initialize() async {
    await _sessionManager.loadPersistentCookies();
  }

  Map<String, String> get _headersWithCookies {
    return _sessionManager.getHeadersWithCookies(ApiConstants.headers);
  }

  void _updateCookies(http.Response response) {
    _sessionManager.updateFromResponseHeaders(response.headers);
  }

  // Login API
  Future<LoginResponse> login(String email, String password) async {
    try {
      final url = Uri.parse('${ApiConstants.baseUrl}${ApiConstants.loginEndpoint}');
      
      final response = await http.post(
        url,
        headers: _headersWithCookies,
        body: {
          'email': email,
          'password': password,
          'remember_me': '1',
        },
      );

      _updateCookies(response);

      if (response.statusCode == 200) {
        final Map<String, dynamic> data = json.decode(response.body);
        return LoginResponse.fromJson(data);
      } else {
        return LoginResponse(
          success: false,
          message: 'Server error: ${response.statusCode}',
        );
      }
    } catch (e) {
      return LoginResponse(
        success: false,
        message: 'Network error: $e',
      );
    }
  }

  // Get Dashboard Data
  Future<Map<String, dynamic>> getDashboardData() async {
    try {
      final url = Uri.parse('${ApiConstants.baseUrl}${ApiConstants.dashboardEndpoint}');
      
      // Add cache-busting parameter
      final cacheBuster = DateTime.now().millisecondsSinceEpoch;
      final finalUrl = url.replace(queryParameters: {
        ...url.queryParameters,
        '_t': cacheBuster.toString(),
      });
      
      final response = await http.get(
        finalUrl,
        headers: _headersWithCookies,
      );

      _updateCookies(response);

      if (response.statusCode == 200) {
        return json.decode(response.body);
      } else {
        throw Exception('Failed to load dashboard data: ${response.statusCode}');
      }
    } catch (e) {
      throw Exception('Network error: $e');
    }
  }

  // Logout API
  Future<bool> logout() async {
    try {
      final url = Uri.parse('${ApiConstants.baseUrl}${ApiConstants.logoutEndpoint}');
      
      final response = await http.post(
        url,
        headers: _headersWithCookies,
      );

      if (response.statusCode == 200) {
        _sessionManager.clearCookies();
        return true;
      } else {
        return false;
      }
    } catch (e) {
      return false;
    }
  }

  // Check if user is logged in (verify session)
  Future<bool> checkSession() async {
    try {
      final response = await getDashboardData();
      return response['success'] == true;
    } catch (e) {
      return false;
    }
  }
}