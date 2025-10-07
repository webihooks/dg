import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import '../constants/api_constants.dart';
import '../models/order_model.dart';
import 'session_manager.dart';

class OrdersService {
  static final OrdersService _instance = OrdersService._internal();
  factory OrdersService() => _instance;
  OrdersService._internal();

  final SessionManager _sessionManager = SessionManager();

  Map<String, String> get _headersWithCookies {
    return _sessionManager.getHeadersWithCookies(ApiConstants.headers);
  }

  void _updateCookies(http.Response response) {
    _sessionManager.updateFromResponseHeaders(response.headers);
  }

  // Get orders with date range
  Future<List<Order>> getOrders({
    String fromDate = '',
    String toDate = '',
    int page = 1,
    int perPage = 50,
  }) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final userId = prefs.getInt('userId');
      final userEmail = prefs.getString('email');

      debugPrint('👤 Current User ID: $userId');
      debugPrint('👤 Current User Email: $userEmail');
      debugPrint('🍪 Session cookies: ${_sessionManager.cookies}');

      final params = {
        // Remove pagination parameters for now
        'source': 'flutter_app',
        'flutter_user_id': userId?.toString() ?? '',
        'flutter_email': userEmail ?? '',
      };

      if (fromDate.isNotEmpty) params['from_date'] = fromDate;
      if (toDate.isNotEmpty) params['to_date'] = toDate;

      final url = Uri.parse('${ApiConstants.baseUrl}/flutter_api/orders.php')
          .replace(queryParameters: params);

      debugPrint('🔗 Fetching orders from: $url');

      final response = await http.get(
        url,
        headers: _headersWithCookies,
      );

      _updateCookies(response);

      debugPrint('📡 Orders API Response: ${response.statusCode}');
      debugPrint('📦 Response body: ${response.body}');

      if (response.statusCode == 200) {
        final Map<String, dynamic> data = json.decode(response.body);
        if (data['success'] == true) {
          final List<dynamic> ordersJson = data['orders'] ?? [];
          debugPrint('✅ Loaded ${ordersJson.length} orders');
          return ordersJson.map((json) => Order.fromJson(json)).toList();
        } else {
          throw Exception(data['message'] ?? 'Failed to load orders');
        }
      } else {
        throw Exception('Server error: ${response.statusCode}');
      }
    } catch (e) {
      debugPrint('❌ Orders API Error: $e');
      throw Exception('Network error: $e');
    }
  }

  // Update order status
  Future<bool> updateOrderStatus(int orderId, String newStatus) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final userId = prefs.getInt('userId');

      final url = Uri.parse('${ApiConstants.baseUrl}/flutter_api/update_order_status.php');
      
      debugPrint('🔄 Updating order $orderId to status: $newStatus');

      final response = await http.post(
        url,
        headers: _headersWithCookies,
        body: {
          'order_id': orderId.toString(),
          'new_status': newStatus,
          'source': 'flutter_app',
          'flutter_user_id': userId?.toString() ?? '',
        },
      );

      _updateCookies(response);

      if (response.statusCode == 200) {
        final Map<String, dynamic> data = json.decode(response.body);
        return data['success'] == true;
      } else {
        return false;
      }
    } catch (e) {
      debugPrint('❌ Update order status error: $e');
      return false;
    }
  }

  // Cancel order
  Future<bool> cancelOrder(int orderId) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final userId = prefs.getInt('userId');

      final url = Uri.parse('${ApiConstants.baseUrl}/flutter_api/cancel_order.php');
      
      debugPrint('❌ Cancelling order: $orderId');

      final response = await http.post(
        url,
        headers: _headersWithCookies,
        body: {
          'order_id': orderId.toString(),
          'source': 'flutter_app',
          'flutter_user_id': userId?.toString() ?? '',
        },
      );

      _updateCookies(response);

      if (response.statusCode == 200) {
        final Map<String, dynamic> data = json.decode(response.body);
        return data['success'] == true;
      } else {
        return false;
      }
    } catch (e) {
      debugPrint('❌ Cancel order error: $e');
      return false;
    }
  }
}