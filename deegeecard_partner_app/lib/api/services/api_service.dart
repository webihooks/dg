import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
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

  // Get New Orders - FOR PHASE 2 BACKGROUND CHECKING
  Future<List<dynamic>> getNewOrders(int sinceTimestamp) async {
    try {
      // Use the new orders endpoint
      final url = Uri.parse('${ApiConstants.baseUrl}${ApiConstants.newOrdersEndpoint}');
      
      // Add timestamp filter to get only new orders
      final finalUrl = url.replace(queryParameters: {
        ...url.queryParameters,
        'since_timestamp': sinceTimestamp.toString(),
        'user_id': await _getUserId(),
      });
      
      final response = await http.get(
        finalUrl,
        headers: _headersWithCookies,
      );

      _updateCookies(response);

      if (response.statusCode == 200) {
        final Map<String, dynamic> data = json.decode(response.body);
        
        // Handle response format for your API
        if (data['success'] == true) {
          if (data.containsKey('orders')) {
            return data['orders'] ?? [];
          } else if (data.containsKey('data')) {
            return data['data'] ?? [];
          } else {
            // If response is directly the orders array
            return _safeConvertToList(data);
          }
        } else {
          print('API returned error: ${data['message']}');
          return [];
        }
      } else {
        print('Failed to fetch new orders: ${response.statusCode}');
        // Fallback to general orders endpoint
        return await _getOrdersFallback(sinceTimestamp);
      }
    } catch (e) {
      print('Error fetching new orders: $e');
      // Fallback to general orders endpoint
      return await _getOrdersFallback(sinceTimestamp);
    }
  }

  // Safe conversion method to handle different data types
  List<dynamic> _safeConvertToList(dynamic data) {
    if (data is List) {
      return List<dynamic>.from(data);
    } else if (data is Map) {
      // If it's a map, try to extract orders from it
      if (data.containsKey('orders')) {
        return data['orders'] is List ? List<dynamic>.from(data['orders']) : [];
      }
      return [];
    } else {
      return [];
    }
  }

  // Fallback method using general orders endpoint
  Future<List<dynamic>> _getOrdersFallback(int sinceTimestamp) async {
    try {
      final url = Uri.parse('${ApiConstants.baseUrl}${ApiConstants.ordersEndpoint}');
      
      final finalUrl = url.replace(queryParameters: {
        ...url.queryParameters,
        'user_id': await _getUserId(),
        'status': 'pending', // Get pending orders
        'sort': 'newest',
      });
      
      final response = await http.get(
        finalUrl,
        headers: _headersWithCookies,
      );

      _updateCookies(response);

      if (response.statusCode == 200) {
        final Map<String, dynamic> data = json.decode(response.body);
        
        if (data['success'] == true) {
          List<dynamic> allOrders = [];
          
          if (data.containsKey('orders')) {
            allOrders = data['orders'] is List ? List<dynamic>.from(data['orders']) : [];
          } else if (data.containsKey('data')) {
            allOrders = data['data'] is List ? List<dynamic>.from(data['data']) : [];
          }
          
          // Filter orders that are newer than our last check
          return allOrders.where((order) {
            final orderTime = _getOrderTimestamp(order);
            return orderTime > sinceTimestamp;
          }).toList();
        }
      }
      return [];
    } catch (e) {
      print('Error in orders fallback: $e');
      return [];
    }
  }

  // Get recent orders for enhanced service
  Future<List<dynamic>> getRecentOrders() async {
    try {
      final url = Uri.parse('${ApiConstants.baseUrl}${ApiConstants.ordersEndpoint}');
      
      final finalUrl = url.replace(queryParameters: {
        ...url.queryParameters,
        'user_id': await _getUserId(),
        'status': 'pending',
        'sort': 'newest',
        'limit': '20',
      });
      
      final response = await http.get(
        finalUrl,
        headers: _headersWithCookies,
      );

      _updateCookies(response);

      if (response.statusCode == 200) {
        final Map<String, dynamic> data = json.decode(response.body);
        
        if (data['success'] == true) {
          if (data.containsKey('orders')) {
            return data['orders'] is List ? List<dynamic>.from(data['orders']) : [];
          } else if (data.containsKey('data')) {
            return data['data'] is List ? List<dynamic>.from(data['data']) : [];
          }
        }
      }
      return [];
    } catch (e) {
      print('Error fetching recent orders: $e');
      return [];
    }
  }

  // Get all orders (for manual checking)
  Future<List<dynamic>> getAllOrders() async {
    try {
      final url = Uri.parse('${ApiConstants.baseUrl}${ApiConstants.ordersEndpoint}');
      
      final finalUrl = url.replace(queryParameters: {
        ...url.queryParameters,
        'user_id': await _getUserId(),
        'status': 'all',
        'limit': '50',
      });
      
      final response = await http.get(
        finalUrl,
        headers: _headersWithCookies,
      );

      _updateCookies(response);

      if (response.statusCode == 200) {
        final Map<String, dynamic> data = json.decode(response.body);
        
        if (data['success'] == true) {
          if (data.containsKey('orders')) {
            return data['orders'] is List ? List<dynamic>.from(data['orders']) : [];
          } else if (data.containsKey('data')) {
            return data['data'] is List ? List<dynamic>.from(data['data']) : [];
          }
        }
      }
      return [];
    } catch (e) {
      print('Error fetching all orders: $e');
      return [];
    }
  }

  // Get user ID from shared preferences
  Future<String> _getUserId() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      return prefs.getInt('userId')?.toString() ?? '0';
    } catch (e) {
      return '0';
    }
  }

  // Helper method to extract timestamp from order
  int _getOrderTimestamp(Map<String, dynamic> order) {
    try {
      // Try different possible timestamp fields
      if (order['created_at'] != null) {
        final createdAt = order['created_at'];
        if (createdAt is int) return createdAt;
        if (createdAt is String) {
          return DateTime.parse(createdAt).millisecondsSinceEpoch;
        }
      }
      
      if (order['order_date'] != null) {
        final orderDate = order['order_date'];
        if (orderDate is int) return orderDate;
        if (orderDate is String) {
          return DateTime.parse(orderDate).millisecondsSinceEpoch;
        }
      }
      
      if (order['timestamp'] != null) {
        final timestamp = order['timestamp'];
        if (timestamp is int) return timestamp;
        if (timestamp is String) {
          return DateTime.parse(timestamp).millisecondsSinceEpoch;
        }
      }
      
      if (order['date_added'] != null) {
        final dateAdded = order['date_added'];
        if (dateAdded is int) return dateAdded;
        if (dateAdded is String) {
          return DateTime.parse(dateAdded).millisecondsSinceEpoch;
        }
      }
      
      // If no timestamp found, use current time (so it will be included)
      return DateTime.now().millisecondsSinceEpoch;
    } catch (e) {
      print('Error parsing order timestamp: $e');
      return DateTime.now().millisecondsSinceEpoch;
    }
  }

  // Get order count for dashboard
  Future<int> getPendingOrderCount() async {
    try {
      final url = Uri.parse('${ApiConstants.baseUrl}${ApiConstants.ordersEndpoint}');
      
      final finalUrl = url.replace(queryParameters: {
        ...url.queryParameters,
        'user_id': await _getUserId(),
        'status': 'pending',
        'count_only': '1',
      });
      
      final response = await http.get(
        finalUrl,
        headers: _headersWithCookies,
      );

      _updateCookies(response);

      if (response.statusCode == 200) {
        final Map<String, dynamic> data = json.decode(response.body);
        if (data['success'] == true) {
          return data['count'] ?? data['total_orders'] ?? 0;
        }
      }
      return 0;
    } catch (e) {
      print('Error fetching order count: $e');
      return 0;
    }
  }

  // Get today's orders summary
  Future<Map<String, dynamic>> getTodayOrdersSummary() async {
    try {
      final today = DateTime.now();
      final startOfDay = DateTime(today.year, today.month, today.day);
      final startTimestamp = startOfDay.millisecondsSinceEpoch;
      
      final url = Uri.parse('${ApiConstants.baseUrl}${ApiConstants.ordersEndpoint}');
      
      final finalUrl = url.replace(queryParameters: {
        ...url.queryParameters,
        'user_id': await _getUserId(),
        'status': 'all',
        'from_date': startTimestamp.toString(),
        'summary_only': '1',
      });
      
      final response = await http.get(
        finalUrl,
        headers: _headersWithCookies,
      );

      _updateCookies(response);

      if (response.statusCode == 200) {
        final Map<String, dynamic> data = json.decode(response.body);
        if (data['success'] == true) {
          return {
            'total_orders': data['total_orders'] ?? 0,
            'total_sales': data['total_sales'] ?? 0.0,
            'pending_orders': data['pending_orders'] ?? 0,
          };
        }
      }
      return {
        'total_orders': 0,
        'total_sales': 0.0,
        'pending_orders': 0,
      };
    } catch (e) {
      print('Error fetching today orders summary: $e');
      return {
        'total_orders': 0,
        'total_sales': 0.0,
        'pending_orders': 0,
      };
    }
  }

  // Get order details by ID
  Future<Map<String, dynamic>?> getOrderDetails(String orderId) async {
    try {
      final url = Uri.parse('${ApiConstants.baseUrl}${ApiConstants.ordersEndpoint}');
      
      final finalUrl = url.replace(queryParameters: {
        ...url.queryParameters,
        'order_id': orderId,
        'user_id': await _getUserId(),
      });
      
      final response = await http.get(
        finalUrl,
        headers: _headersWithCookies,
      );

      _updateCookies(response);

      if (response.statusCode == 200) {
        final Map<String, dynamic> data = json.decode(response.body);
        if (data['success'] == true) {
          return data['order'] ?? data['data'];
        }
      }
      return null;
    } catch (e) {
      print('Error fetching order details: $e');
      return null;
    }
  }

  // Update order status
  Future<bool> updateOrderStatus(String orderId, String status) async {
    try {
      final url = Uri.parse('${ApiConstants.baseUrl}${ApiConstants.ordersEndpoint}');
      
      final response = await http.post(
        url,
        headers: _headersWithCookies,
        body: {
          'order_id': orderId,
          'status': status,
          'user_id': await _getUserId(),
          'action': 'update_status',
        },
      );

      _updateCookies(response);

      if (response.statusCode == 200) {
        final Map<String, dynamic> data = json.decode(response.body);
        return data['success'] == true;
      }
      return false;
    } catch (e) {
      print('Error updating order status: $e');
      return false;
    }
  }

  // Check for any new orders (simplified version for background service)
  Future<List<dynamic>> checkForNewOrders() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final lastCheck = prefs.getInt('last_order_check') ?? 0;
      
      // Use the new orders endpoint
      final newOrders = await getNewOrders(lastCheck);
      
      if (newOrders.isNotEmpty) {
        // Update last check time
        await prefs.setInt('last_order_check', DateTime.now().millisecondsSinceEpoch);
      }
      
      return newOrders;
    } catch (e) {
      print('Error in checkForNewOrders: $e');
      return [];
    }
  }

  // Get order statistics for dashboard
  Future<Map<String, dynamic>> getOrderStatistics() async {
    try {
      // Use dashboard endpoint for statistics
      final response = await getDashboardData();
      
      if (response['success'] == true) {
        return {
          'today_orders': response['today_orders'] ?? 0,
          'total_orders': response['total_orders'] ?? 0,
          'total_sales': response['total_sales'] ?? 0.0,
          'pending_orders': response['pending_orders'] ?? 0,
          'average_order_value': response['avg_order_value'] ?? 0.0,
        };
      }
      return {
        'today_orders': 0,
        'total_orders': 0,
        'total_sales': 0.0,
        'pending_orders': 0,
        'average_order_value': 0.0,
      };
    } catch (e) {
      print('Error fetching order statistics: $e');
      return {
        'today_orders': 0,
        'total_orders': 0,
        'total_sales': 0.0,
        'pending_orders': 0,
        'average_order_value': 0.0,
      };
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

  // Test API connectivity
  Future<bool> testConnection() async {
    try {
      final url = Uri.parse('${ApiConstants.baseUrl}${ApiConstants.dashboardEndpoint}');
      final response = await http.get(
        url, // Fixed: use 'url' instead of 'finalUrl'
        headers: _headersWithCookies,
      );
      return response.statusCode == 200;
    } catch (e) {
      print('Connection test failed: $e');
      return false;
    }
  }

  // Get store status (ON/OFF)
  Future<bool> getStoreStatus() async {
    try {
      final url = Uri.parse('${ApiConstants.baseUrl}/flutter_api/store_status.php');
      
      final finalUrl = url.replace(queryParameters: {
        'user_id': await _getUserId(),
      });
      
      final response = await http.get(
        finalUrl,
        headers: _headersWithCookies,
      );

      _updateCookies(response);

      if (response.statusCode == 200) {
        final Map<String, dynamic> data = json.decode(response.body);
        return data['success'] == true && data['store_status'] == 'on';
      }
      return false;
    } catch (e) {
      print('Error fetching store status: $e');
      return false;
    }
  }

  // Update store status
  Future<bool> updateStoreStatus(bool isOpen) async {
    try {
      final url = Uri.parse('${ApiConstants.baseUrl}/flutter_api/store_status.php');
      
      final response = await http.post(
        url,
        headers: _headersWithCookies,
        body: {
          'user_id': await _getUserId(),
          'store_status': isOpen ? 'on' : 'off',
          'action': 'update',
        },
      );

      _updateCookies(response);

      if (response.statusCode == 200) {
        final Map<String, dynamic> data = json.decode(response.body);
        return data['success'] == true;
      }
      return false;
    } catch (e) {
      print('Error updating store status: $e');
      return false;
    }
  }
}