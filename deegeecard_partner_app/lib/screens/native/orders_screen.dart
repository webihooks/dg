import 'package:flutter/material.dart';
import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../../api/services/orders_service.dart';
import '../../api/services/api_service.dart';
import '../../api/models/order_model.dart';
import 'package:flutter/material.dart';

// Add this helper method to your _OrdersScreenState class
Color _getStatusColor(String status) {
  switch (status.toLowerCase()) {
    case 'pending':
      return Colors.amber;
    case 'confirmed':
      return Colors.cyan;
    case 'preparing':
      return Colors.orange;
    case 'ready':
      return Colors.green;
    case 'completed':
      return Colors.orange;
    case 'cancelled':
      return Colors.red;
    default:
      return Colors.grey;
  }
}

class OrdersScreen extends StatefulWidget {
  const OrdersScreen({Key? key}) : super(key: key);

  @override
  _OrdersScreenState createState() => _OrdersScreenState();
}

class _OrdersScreenState extends State<OrdersScreen> {
  final OrdersService _ordersService = OrdersService();
  final ApiService _apiService = ApiService();
  List<Order> _orders = [];
  bool _isLoading = true;
  String _errorMessage = '';
  DateTime _fromDate = DateTime.now();
  DateTime _toDate = DateTime.now();

  @override
  void initState() {
    super.initState();
    _checkSession();
    _loadOrders();
  }

  Future<void> _checkSession() async {
    try {
      debugPrint('🔐 Checking session status...');
      
      final prefs = await SharedPreferences.getInstance();
      final userId = prefs.getInt('userId');
      final email = prefs.getString('email');
      
      debugPrint('📱 Stored User ID: $userId');
      debugPrint('📱 Stored Email: $email');
      
      // Test the dashboard API to see if session is valid
      final dashboardData = await _apiService.getDashboardData();
      
      debugPrint('🎯 Dashboard API response: ${dashboardData['success']}');
      if (dashboardData['success'] == true) {
        debugPrint('👤 User data: ${dashboardData['user']?['name']}');
      } else {
        debugPrint('🚫 Dashboard API failed: ${dashboardData['message']}');
      }
      
    } catch (e) {
      debugPrint('🚫 Session check failed: $e');
    }
  }

  Future<void> _loadOrders() async {
    try {
      debugPrint('🔄 Starting to load orders...');
      debugPrint('📅 Date range: ${_formatDate(_fromDate)} to ${_formatDate(_toDate)}');
      
      setState(() {
        _isLoading = true;
      });

      final orders = await _ordersService.getOrders(
        fromDate: _formatDate(_fromDate),
        toDate: _formatDate(_toDate),
      );

      debugPrint('✅ Successfully loaded ${orders.length} orders');
      
      setState(() {
        _orders = orders;
        _isLoading = false;
        _errorMessage = '';
      });
    } catch (e) {
      debugPrint('❌ Error loading orders: $e');
      setState(() {
        _isLoading = false;
        _errorMessage = 'Failed to load orders: $e';
      });
    }
  }

  String _formatDate(DateTime date) {
    return '${date.year}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';
  }

  String _formatDisplayDate(DateTime date) {
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    return '${months[date.month - 1]} ${date.day}, ${date.year}';
  }

  Future<void> _updateOrderStatus(int orderId, String newStatus) async {
    try {
      final success = await _ordersService.updateOrderStatus(orderId, newStatus);
      if (success) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Order marked as $newStatus!')),
        );
        _loadOrders();
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Failed to update order status')),
        );
      }
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Error: $e')),
      );
    }
  }

  Future<void> _cancelOrder(int orderId) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Cancel Order'),
        content: const Text('Are you sure you want to cancel this order?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('No'),
          ),
          TextButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: const Text('Yes, Cancel'),
          ),
        ],
      ),
    );

    if (confirmed == true) {
      try {
        final success = await _ordersService.cancelOrder(orderId);
        if (success) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Order cancelled successfully!')),
          );
          _loadOrders();
        } else {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Failed to cancel order')),
          );
        }
      } catch (e) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error: $e')),
        );
      }
    }
  }

  Future<void> _selectDateRange() async {
    final DateTimeRange? picked = await showDateRangePicker(
      context: context,
      firstDate: DateTime(2020),
      lastDate: DateTime.now(),
      initialDateRange: DateTimeRange(start: _fromDate, end: _toDate),
    );

    if (picked != null) {
      setState(() {
        _fromDate = picked.start;
        _toDate = picked.end;
      });
      _loadOrders();
    }
  }

  Widget _buildOrderCard(Order order) {
  return Card(
    margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Header row - FIXED: Use the helper method
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'Order #${order.orderId}',
                style: const TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.bold,
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                decoration: BoxDecoration(
                  color: _getStatusColor(order.status), // Use helper method
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Text(
                  order.status,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 12,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
            ],
          ),

          const SizedBox(height: 8),

          // Customer info
          Text(
            'Customer: ${order.customerName}',
            style: const TextStyle(fontWeight: FontWeight.w500),
          ),
          if (order.customerPhone.isNotEmpty)
            Text('Phone: ${order.customerPhone}'),

          const SizedBox(height: 8),

          // Order details
          Row(
            children: [
              Expanded(
                child: Text(
                  order.formattedOrderType,
                  style: TextStyle(
                    color: Colors.grey[600],
                  ),
                ),
              ),
              Text(
                '${order.itemCount} items',
                style: TextStyle(
                  color: Colors.grey[600],
                ),
              ),
            ],
          ),

          const SizedBox(height: 8),

          // Financial info
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'Total: ₹${order.totalAmount.toStringAsFixed(2)}',
                style: const TextStyle(
                  fontWeight: FontWeight.bold,
                  fontSize: 16,
                ),
              ),
              Text(
                _formatDateTime(order.createdAt),
                style: TextStyle(
                  color: Colors.grey[600],
                  fontSize: 12,
                ),
              ),
            ],
          ),

          const SizedBox(height: 12),

          // Timer (if applicable)
          if (order.timerRemaining > 0 && order.canUpdateStatus)
            _buildTimer(order),

          const SizedBox(height: 8),

          // Action buttons
          if (order.canUpdateStatus)
            Row(
              children: [
                if (order.canMarkReady)
                  Expanded(
                    child: ElevatedButton(
                      onPressed: () => _updateOrderStatus(order.orderId, 'Ready'),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Colors.green,
                        foregroundColor: Colors.white,
                      ),
                      child: const Text('Mark Ready'),
                    ),
                  ),
                if (order.canMarkReady) const SizedBox(width: 8),
                if (order.canMarkComplete)
                  Expanded(
                    child: ElevatedButton(
                      onPressed: () => _updateOrderStatus(order.orderId, 'Completed'),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Colors.orange,
                        foregroundColor: Colors.white,
                      ),
                      child: const Text('Complete'),
                    ),
                  ),
                if (order.canCancel) const SizedBox(width: 8),
                if (order.canCancel)
                  IconButton(
                    onPressed: () => _cancelOrder(order.orderId),
                    icon: const Icon(Icons.cancel, color: Colors.red),
                    tooltip: 'Cancel Order',
                  ),
              ],
            ),
        ],
      ),
    ),
  );
}

  String _formatDateTime(DateTime date) {
    return '${date.day}/${date.month}/${date.year} ${date.hour}:${date.minute.toString().padLeft(2, '0')}';
  }

  Widget _buildTimer(Order order) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      decoration: BoxDecoration(
        color: order.isDelayed ? Colors.red : Colors.orange,
        borderRadius: BorderRadius.circular(6),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.timer, color: Colors.white, size: 16),
          const SizedBox(width: 4),
          Text(
            _formatTimer(order.timerRemaining),
            style: const TextStyle(
              color: Colors.white,
              fontWeight: FontWeight.bold,
            ),
          ),
        ],
      ),
    );
  }

  String _formatTimer(int seconds) {
    final minutes = seconds ~/ 60;
    final remainingSeconds = seconds % 60;
    return '${minutes.toString().padLeft(2, '0')}:${remainingSeconds.toString().padLeft(2, '0')}';
  }

  Widget _buildDateRangeSelector() {
    return Card(
      margin: const EdgeInsets.all(16),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Date Range',
              style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                Expanded(
                  child: Text(
                    '${_formatDisplayDate(_fromDate)} - ${_formatDisplayDate(_toDate)}',
                    style: TextStyle(
                      color: Colors.grey[600],
                    ),
                  ),
                ),
                IconButton(
                  onPressed: _selectDateRange,
                  icon: const Icon(Icons.calendar_today),
                  tooltip: 'Select Date Range',
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Order Management'),
        backgroundColor: Colors.orange,
        foregroundColor: Colors.white,
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: _loadOrders,
            tooltip: 'Refresh Orders',
          ),
        ],
      ),
      body: Column(
        children: [
          _buildDateRangeSelector(),
          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator())
                : _errorMessage.isNotEmpty
                    ? Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            const Icon(Icons.error, size: 64, color: Colors.red),
                            const SizedBox(height: 16),
                            Text(
                              _errorMessage,
                              textAlign: TextAlign.center,
                              style: const TextStyle(fontSize: 16),
                            ),
                            const SizedBox(height: 20),
                            ElevatedButton(
                              onPressed: _loadOrders,
                              child: const Text('Retry'),
                            ),
                          ],
                        ),
                      )
                    : _orders.isEmpty
                        ? const Center(
                            child: Column(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                Icon(Icons.receipt_long, size: 64, color: Colors.grey),
                                SizedBox(height: 16),
                                Text(
                                  'No orders found',
                                  style: TextStyle(fontSize: 16, color: Colors.grey),
                                ),
                              ],
                            ),
                          )
                        : RefreshIndicator(
                            onRefresh: _loadOrders,
                            child: ListView.builder(
                              itemCount: _orders.length,
                              itemBuilder: (context, index) {
                                return _buildOrderCard(_orders[index]);
                              },
                            ),
                          ),
          ),
        ],
      ),
    );
  }
}