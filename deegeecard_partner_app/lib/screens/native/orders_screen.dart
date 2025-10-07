import 'package:flutter/material.dart';
import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../../api/services/orders_service.dart';
import '../../api/services/api_service.dart';
import '../../api/models/order_model.dart';
import '../../constants/colors.dart'; // Import colors file

// Helper method for status colors
Color getStatusColor(String status) {
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
  Order? _selectedOrder;

  // Primary color
  final Color primaryColor = AppColors.primary;

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

  // Format time in 12-hour format with AM/PM
  String _formatTime12Hour(DateTime date) {
    final hour = date.hour;
    final minute = date.minute;
    final period = hour >= 12 ? 'PM' : 'AM';
    final hour12 = hour % 12 == 0 ? 12 : hour % 12;
    return '${hour12.toString().padLeft(2, '0')}:${minute.toString().padLeft(2, '0')} $period';
  }

  // Format date and time in 12-hour format
  String _formatDateTime12Hour(DateTime date) {
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    return '${date.day} ${months[date.month - 1]}, ${_formatTime12Hour(date)}';
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

  void _showOrderDetails(Order order) {
    setState(() {
      _selectedOrder = order;
    });
    
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) => OrderDetailsModal(order: order, primaryColor: primaryColor),
    );
  }

  Widget _buildOrderCard(Order order) {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      decoration: BoxDecoration(
        color: Colors.white, // White background #ffffff
        borderRadius: BorderRadius.circular(12),
        boxShadow: [
          BoxShadow(
            color: Colors.grey.withOpacity(0.2),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Expanded(
                  child: Text(
                    'Order #${order.orderId}',
                    style: const TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                  decoration: BoxDecoration(
                    color: getStatusColor(order.status),
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

            Text(
              'Customer: ${order.customerName}',
              style: const TextStyle(fontWeight: FontWeight.w500),
            ),
            if (order.customerPhone.isNotEmpty)
              Text('Phone: ${order.customerPhone}'),

            const SizedBox(height: 8),

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
                  _formatDateTime12Hour(order.createdAt), // 12-hour format with AM/PM
                  style: TextStyle(
                    color: Colors.grey[600],
                    fontSize: 12,
                  ),
                ),
              ],
            ),

            const SizedBox(height: 12),

            // Real-time countdown timer
            if (order.timerRemaining > 0 && order.canUpdateStatus)
              _buildRealTimeTimer(order),

            const SizedBox(height: 8),

            Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: () => _showOrderDetails(order),
                    icon: const Icon(Icons.remove_red_eye, size: 16),
                    label: const Text('View Details'),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: primaryColor, // Primary color instead of blue
                      side: BorderSide(color: primaryColor), // Primary color instead of blue
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                
                if (order.canUpdateStatus) ...[
                  if (order.canMarkReady)
                    Expanded(
                      child: ElevatedButton(
                        onPressed: () => _updateOrderStatus(order.orderId, 'Ready'),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: Colors.green,
                          foregroundColor: Colors.white,
                        ),
                        child: const Text('Ready'),
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
                    SizedBox( // Bigger cancel button
                      width: 48, // Increased width
                      height: 48, // Increased height
                      child: IconButton(
                        onPressed: () => _cancelOrder(order.orderId),
                        icon: const Icon(Icons.cancel, color: Colors.red, size: 24), // Increased icon size
                        tooltip: 'Cancel Order',
                        style: IconButton.styleFrom(
                          backgroundColor: Colors.red.withOpacity(0.1),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(8),
                          ),
                        ),
                      ),
                    ),
                ],
              ],
            ),
          ],
        ),
      ),
    );
  }

  // Real-time countdown timer widget
  Widget _buildRealTimeTimer(Order order) {
    return StatefulBuilder(
      builder: (context, setState) {
        // Initialize with current remaining time
        int currentRemaining = order.timerRemaining;
        
        // Set up a timer to update every second
        if (currentRemaining > 0) {
          Future.delayed(const Duration(seconds: 1), () {
            if (currentRemaining > 0) {
              setState(() {
                currentRemaining--;
              });
            }
          });
        }

        return Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
          decoration: BoxDecoration(
            color: currentRemaining <= 0 ? Colors.red : Colors.orange,
            borderRadius: BorderRadius.circular(6),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.timer, color: Colors.white, size: 16),
              const SizedBox(width: 4),
              Text(
                _formatTimer(currentRemaining),
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  String _formatTimer(int seconds) {
    if (seconds <= 0) return '00:00';
    final minutes = seconds ~/ 60;
    final remainingSeconds = seconds % 60;
    return '${minutes.toString().padLeft(2, '0')}:${remainingSeconds.toString().padLeft(2, '0')}';
  }

  Widget _buildDateRangeSelector() {
    return Container(
      margin: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        boxShadow: [
          BoxShadow(
            color: Colors.grey.withOpacity(0.2),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
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
                  icon: Icon(Icons.calendar_today, color: primaryColor), // Primary color
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
        toolbarHeight: 0,
        backgroundColor: Colors.transparent,
        elevation: 0,
      ),
      body: Column(
        children: [
          _buildDateRangeSelector(),
          Expanded(
            child: _isLoading
                ? Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        CircularProgressIndicator(
                          valueColor: AlwaysStoppedAnimation<Color>(primaryColor), // Primary color
                        ),
                        const SizedBox(height: 16),
                        Text(
                          'Loading orders...',
                          style: TextStyle(
                            color: primaryColor, // Primary color
                          ),
                        ),
                      ],
                    ),
                  )
                : _errorMessage.isNotEmpty
                    ? Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(Icons.error, size: 64, color: primaryColor), // Primary color
                            const SizedBox(height: 16),
                            Text(
                              _errorMessage,
                              textAlign: TextAlign.center,
                              style: const TextStyle(fontSize: 16),
                            ),
                            const SizedBox(height: 20),
                            ElevatedButton(
                              onPressed: _loadOrders,
                              style: ElevatedButton.styleFrom(
                                backgroundColor: primaryColor, // Primary color
                                foregroundColor: Colors.white,
                              ),
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

// Order Details Modal
class OrderDetailsModal extends StatelessWidget {
  final Order order;
  final Color primaryColor;

  const OrderDetailsModal({Key? key, required this.order, required this.primaryColor}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Container(
      height: MediaQuery.of(context).size.height * 0.85,
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.only(
          topLeft: Radius.circular(20),
          topRight: Radius.circular(20),
        ),
      ),
      child: Column(
        children: [
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: primaryColor, // Primary color instead of blue
              borderRadius: const BorderRadius.only(
                topLeft: Radius.circular(20),
                topRight: Radius.circular(20),
              ),
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  'Order #${order.orderId}',
                  style: const TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.bold,
                    color: Colors.white,
                  ),
                ),
                IconButton(
                  onPressed: () => Navigator.of(context).pop(),
                  icon: const Icon(Icons.close, color: Colors.white),
                ),
              ],
            ),
          ),
          Expanded(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _buildDetailSection(
                    title: 'Order Summary',
                    children: [
                      _buildDetailRow('Order Date', _formatDateTime12Hour(order.createdAt)),
                      _buildDetailRow('Status', order.status, isStatus: true),
                      _buildDetailRow('Order Type', order.formattedOrderType),
                      if (order.orderNotes.isNotEmpty)
                        _buildDetailRow('Notes', order.orderNotes),
                    ],
                  ),

                  const SizedBox(height: 20),

                  _buildDetailSection(
                    title: 'Customer Information',
                    children: [
                      _buildDetailRow('Name', order.customerName),
                      if (order.customerPhone.isNotEmpty)
                        _buildDetailRow('Phone', order.customerPhone),
                      if (order.orderType == 'delivery' && order.deliveryAddress.isNotEmpty)
                        _buildDetailRow('Delivery Address', order.deliveryAddress),
                      if (order.orderType == 'dining' && order.tableNumber.isNotEmpty)
                        _buildDetailRow('Table Number', order.tableNumber),
                    ],
                  ),

                  const SizedBox(height: 20),

                  _buildDetailSection(
                    title: 'Order Items (${order.items.length})',
                    children: [
                      ...order.items.map((item) => _buildOrderItem(item)),
                    ],
                  ),

                  const SizedBox(height: 20),

                  _buildDetailSection(
                    title: 'Payment Summary',
                    children: [
                      _buildDetailRow('Subtotal', '₹${order.subtotal.toStringAsFixed(2)}'),
                      if (order.discountAmount > 0)
                        _buildDetailRow('Discount', '-₹${order.discountAmount.toStringAsFixed(2)} ${order.discountType.isNotEmpty ? '(${order.discountType})' : ''}'),
                      if (order.gstAmount > 0)
                        _buildDetailRow('GST', '₹${order.gstAmount.toStringAsFixed(2)}'),
                      if (order.deliveryCharge > 0)
                        _buildDetailRow('Delivery Charge', '₹${order.deliveryCharge.toStringAsFixed(2)}'),
                      const Divider(),
                      _buildDetailRow(
                        'Total Amount',
                        '₹${order.totalAmount.toStringAsFixed(2)}',
                        isTotal: true,
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildDetailSection({required String title, required List<Widget> children}) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: TextStyle(
            fontSize: 18,
            fontWeight: FontWeight.bold,
            color: primaryColor, // Primary color instead of blue
          ),
        ),
        const SizedBox(height: 8),
        Container(
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(12),
            boxShadow: [
              BoxShadow(
                color: Colors.grey.withOpacity(0.1),
                blurRadius: 4,
                offset: const Offset(0, 2),
              ),
            ],
          ),
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              children: children,
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildDetailRow(String label, String value, {bool isStatus = false, bool isTotal = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            label,
            style: TextStyle(
              fontWeight: isTotal ? FontWeight.bold : FontWeight.normal,
              color: isTotal ? primaryColor : Colors.grey[700], // Primary color for total
            ),
          ),
          if (isStatus)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
              decoration: BoxDecoration(
                color: getStatusColor(value),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Text(
                value,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 12,
                  fontWeight: FontWeight.bold,
                ),
              ),
            )
          else
            Text(
              value,
              style: TextStyle(
                fontWeight: isTotal ? FontWeight.bold : FontWeight.normal,
                fontSize: isTotal ? 16 : 14,
                color: isTotal ? primaryColor : Colors.black, // Primary color for total
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildOrderItem(OrderItem item) {
    return Container(
      margin: const EdgeInsets.symmetric(vertical: 4),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: Colors.grey.shade200),
      ),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    item.productName,
                    style: const TextStyle(
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                  Text(
                    '₹${item.price.toStringAsFixed(2)} each',
                    style: TextStyle(
                      color: Colors.grey[600],
                      fontSize: 12,
                    ),
                  ),
                ],
              ),
            ),
            Text(
              'Qty: ${item.quantity}',
              style: const TextStyle(
                fontWeight: FontWeight.w500,
              ),
            ),
            const SizedBox(width: 16),
            Text(
              '₹${item.total.toStringAsFixed(2)}',
              style: TextStyle(
                fontWeight: FontWeight.bold,
                color: primaryColor, // Primary color instead of blue
              ),
            ),
          ],
        ),
      ),
    );
  }

  // Format date and time in 12-hour format
  String _formatDateTime12Hour(DateTime date) {
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    final hour = date.hour;
    final minute = date.minute;
    final period = hour >= 12 ? 'PM' : 'AM';
    final hour12 = hour % 12 == 0 ? 12 : hour % 12;
    return '${date.day} ${months[date.month - 1]}, ${hour12.toString().padLeft(2, '0')}:${minute.toString().padLeft(2, '0')} $period';
  }
}