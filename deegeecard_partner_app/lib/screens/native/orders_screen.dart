import 'package:flutter/material.dart';
import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'dart:async';
import 'package:audioplayers/audioplayers.dart';
import '../../api/services/orders_service.dart';
import '../../api/services/api_service.dart';
import '../../api/models/order_model.dart';
import '../../constants/colors.dart';

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
  final AudioPlayer _audioPlayer = AudioPlayer();
  List<Order> _orders = [];
  bool _isLoading = true;
  String _errorMessage = '';
  DateTime _fromDate = DateTime.now();
  DateTime _toDate = DateTime.now();
  Order? _selectedOrder;
  
  // Auto-refresh variables
  Timer? _refreshTimer;
  bool _isRefreshing = false;
  final int _refreshInterval = 10;

  // Timer for countdown updates
  Timer? _countdownTimer;

  // Sound notification variables
  bool _isSoundEnabled = true;
  int _lastOrderCount = 0;
  bool _isPlayingSound = false;
  
  // Track pending orders that should ring
  Set<int> _pendingOrderIds = {};
  
  // Timer for continuous sound looping
  Timer? _soundLoopTimer;

  // Primary color
  final Color primaryColor = AppColors.primary;

  @override
  void initState() {
    super.initState();
    _checkSession();
    _loadOrders();
    _startAutoRefresh();
    _startCountdownTimer();
    
    // Audio setup
    _setupAudioPlayer();
  }

  @override
  void dispose() {
    _refreshTimer?.cancel();
    _countdownTimer?.cancel();
    _soundLoopTimer?.cancel();
    _audioPlayer.dispose();
    super.dispose();
  }

  // Setup audio player listeners
  void _setupAudioPlayer() {
    _audioPlayer.onPlayerStateChanged.listen((state) {
      debugPrint('🔊 Player state: $state');
    });
    
    _audioPlayer.onPlayerComplete.listen((event) {
      debugPrint('🔊 Sound completed');
    });

    _audioPlayer.onLog.listen((message) {
      debugPrint('🔊 Audio log: $message');
    });
  }

  // Play continuous sound in loop - FIXED VERSION
  Future<void> _playContinuousSound() async {
    if (!_isSoundEnabled || _isPlayingSound) {
      debugPrint('🔇 Sound not played - enabled: $_isSoundEnabled, playing: $_isPlayingSound');
      return;
    }

    try {
      debugPrint('🔊 STARTING CONTINUOUS SOUND LOOP');
      
      // Stop any current playback and timers
      await _audioPlayer.stop();
      _soundLoopTimer?.cancel();
      
      setState(() {
        _isPlayingSound = true;
      });

      // Function to play sound
      Future<void> playSound() async {
        try {
          await _audioPlayer.play(
            AssetSource('sounds/new_order.wav'),
            volume: 1.0,
          );
          debugPrint('✅ Sound played successfully');
        } catch (e) {
          debugPrint('❌ Error playing sound: $e');
        }
      }

      // Play the sound first time
      await playSound();
      
      debugPrint('✅ SOUND STARTED, SETTING UP LOOP');

      // Set up a timer to restart the sound every 2 seconds for continuous effect
      _soundLoopTimer = Timer.periodic(Duration(seconds: 2), (timer) {
        if (!_isSoundEnabled || _pendingOrderIds.isEmpty || !mounted) {
          debugPrint('🛑 Stopping sound loop - disabled: $_isSoundEnabled, pending: ${_pendingOrderIds.isEmpty}');
          _stopAllSounds();
          timer.cancel();
          return;
        }
        
        debugPrint('🔄 Restarting sound in loop - pending orders: ${_pendingOrderIds.length}');
        playSound();
      });

    } catch (e) {
      debugPrint('❌ Sound initialization error: $e');
      setState(() {
        _isPlayingSound = false;
      });
    }
  }

  // Stop all sounds and timers
  Future<void> _stopAllSounds() async {
    try {
      _soundLoopTimer?.cancel();
      _soundLoopTimer = null;
      await _audioPlayer.stop();
      if (mounted) {
        setState(() {
          _isPlayingSound = false;
        });
      }
      debugPrint('🔇 All sounds and timers stopped');
    } catch (e) {
      debugPrint('❌ Error stopping sounds: $e');
    }
  }

  // Check for new orders and manage sound
  void _checkForNewOrders(List<Order> currentOrders) {
    if (_lastOrderCount == 0) {
      debugPrint('📊 First load: ${currentOrders.length} orders');
      _lastOrderCount = currentOrders.length;
      
      // Initialize pending orders list
      _updatePendingOrders(currentOrders);
      return;
    }

    if (currentOrders.length > _lastOrderCount) {
      final newOrderCount = currentOrders.length - _lastOrderCount;
      debugPrint('🎉 NEW ORDERS DETECTED: $newOrderCount');
      
      // Get previous pending count
      final previousPendingCount = _pendingOrderIds.length;
      
      // Update pending orders list
      _updatePendingOrders(currentOrders);
      
      // If there are NEW pending orders, start the sound
      if (_pendingOrderIds.isNotEmpty && _pendingOrderIds.length > previousPendingCount) {
        debugPrint('🚀 NEW PENDING ORDERS DETECTED: ${_pendingOrderIds.length} total');
        
        if (_isSoundEnabled) {
          debugPrint('🔊 STARTING SOUND FOR NEW PENDING ORDERS');
          _playContinuousSound();
        }
        
        // Show notification
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('🔔 ${_pendingOrderIds.length} pending order(s) need attention!'),
              backgroundColor: Colors.orange,
              duration: const Duration(seconds: 3),
            ),
          );
        }
      } else if (_pendingOrderIds.isNotEmpty) {
        debugPrint('📋 Existing pending orders: ${_pendingOrderIds.length}');
      }
    }

    _lastOrderCount = currentOrders.length;
  }

  // Update the list of pending orders
  void _updatePendingOrders(List<Order> orders) {
    final newPendingOrders = orders
        .where((order) => order.status.toLowerCase() == 'pending')
        .map((order) => order.orderId)
        .toSet();
    
    _pendingOrderIds = newPendingOrders;
    debugPrint('📋 Pending orders updated: ${_pendingOrderIds.length} orders');
    
    // If no pending orders and sound is playing, stop it
    if (_pendingOrderIds.isEmpty && _isPlayingSound) {
      debugPrint('🛑 No pending orders left - stopping sound');
      _stopAllSounds();
    }
  }

  // Stop sound for specific order (when accepted/rejected)
  void _stopSoundForOrder(int orderId) {
    _pendingOrderIds.remove(orderId);
    debugPrint('✅ Order #$orderId processed - removed from pending');
    
    // If no more pending orders, stop the sound
    if (_pendingOrderIds.isEmpty && _isPlayingSound) {
      _stopAllSounds();
      debugPrint('🔇 No more pending orders - sound stopped');
    } else {
      debugPrint('📋 Remaining pending orders: ${_pendingOrderIds.length}');
    }
  }

  // Start countdown timer for real-time updates
  void _startCountdownTimer() {
    _countdownTimer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (mounted) {
        setState(() {});
      }
    });
  }

  // Calculate real-time remaining seconds for an order
  int _calculateRemainingSeconds(Order order) {
    final now = DateTime.now();
    final orderTime = order.createdAt;
    final elapsedSeconds = now.difference(orderTime).inSeconds;
    final remainingSeconds = order.timerRemaining - elapsedSeconds;
    return remainingSeconds > 0 ? remainingSeconds : 0;
  }

  Future<void> _checkSession() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final userId = prefs.getInt('userId');
      final email = prefs.getString('email');
      
      debugPrint('📱 User ID: $userId, Email: $email');
      
      final dashboardData = await _apiService.getDashboardData();
      debugPrint('🎯 Dashboard: ${dashboardData['success']}');
      
    } catch (e) {
      debugPrint('🚫 Session check failed: $e');
    }
  }

  // Start automatic refresh timer
  void _startAutoRefresh() {
    _refreshTimer = Timer.periodic(Duration(seconds: _refreshInterval), (timer) {
      if (mounted && !_isRefreshing) {
        debugPrint('🔄 Auto-refresh (10s) triggered');
        _refreshOrdersSilently();
      }
    });
  }

  // Silent refresh without loading indicator
  Future<void> _refreshOrdersSilently() async {
    try {
      if (_isRefreshing) return;
      
      setState(() {
        _isRefreshing = true;
      });

      final orders = await _ordersService.getOrders(
        fromDate: _formatDate(_fromDate),
        toDate: _formatDate(_toDate),
      );

      if (mounted) {
        setState(() {
          _checkForNewOrders(orders);
          _orders = orders;
          _isRefreshing = false;
        });
      }
    } catch (e) {
      debugPrint('❌ Silent refresh error: $e');
      if (mounted) {
        setState(() {
          _isRefreshing = false;
        });
      }
    }
  }

  // Manual refresh with loading indicator
  Future<void> _loadOrders() async {
    try {
      setState(() {
        _isLoading = true;
      });

      final orders = await _ordersService.getOrders(
        fromDate: _formatDate(_fromDate),
        toDate: _formatDate(_toDate),
      );

      if (mounted) {
        setState(() {
          _checkForNewOrders(orders);
          _orders = orders;
          _isLoading = false;
          _errorMessage = '';
        });
      }
    } catch (e) {
      debugPrint('❌ Error loading orders: $e');
      if (mounted) {
        setState(() {
          _isLoading = false;
          _errorMessage = 'Failed to load orders: $e';
        });
      }
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
        
        // Stop sound for this order when status is changed
        _stopSoundForOrder(orderId);
        
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
          
          // Stop sound for this order when cancelled
          _stopSoundForOrder(orderId);
          
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

  // Toggle sound notifications - FIXED VERSION
  void _toggleSoundNotifications() {
    setState(() {
      _isSoundEnabled = !_isSoundEnabled;
    });
    
    if (!_isSoundEnabled) {
      // Stop sound when disabled
      _stopAllSounds();
      debugPrint('🔇 Sound disabled manually');
    } else if (_pendingOrderIds.isNotEmpty) {
      // Start sound when enabled and there are pending orders
      debugPrint('🔊 Sound enabled manually - starting for pending orders');
      _playContinuousSound();
    }
    
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(_isSoundEnabled ? '🔊 Sound notifications enabled' : '🔇 Sound notifications disabled'),
        duration: const Duration(seconds: 2),
      ),
    );
  }

  // Test sound function - FIXED VERSION
  void _testSound() {
    debugPrint('🔊 Testing sound manually');
    
    // Temporarily add a pending order to test sound
    final tempOrderId = -999; // Temporary ID for testing
    _pendingOrderIds.add(tempOrderId);
    
    _playContinuousSound();
    
    // Remove temporary order after 3 seconds
    Future.delayed(Duration(seconds: 3), () {
      _pendingOrderIds.remove(tempOrderId);
      _stopAllSounds();
    });
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
    final remainingSeconds = _calculateRemainingSeconds(order);
    final hasActiveTimer = remainingSeconds > 0 && order.canUpdateStatus;
    final isPending = order.status.toLowerCase() == 'pending';

    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
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
        border: isPending ? Border.all(color: Colors.orange, width: 2) : null,
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
                  child: Row(
                    children: [
                      if (isPending)
                        Icon(Icons.notifications_active, color: Colors.orange, size: 20),
                      SizedBox(width: isPending ? 8 : 0),
                      Expanded(
                        child: Text(
                          'Order #${order.orderId}',
                          style: const TextStyle(
                            fontSize: 18,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ),
                    ],
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
                  'Total: ₹${order.totalAmount.toStringAsFixed(0)}',
                  style: const TextStyle(
                    fontWeight: FontWeight.bold,
                    fontSize: 16,
                  ),
                ),
                Text(
                  _formatDateTime12Hour(order.createdAt),
                  style: TextStyle(
                    color: Colors.grey[600],
                    fontSize: 12,
                  ),
                ),
              ],
            ),

            const SizedBox(height: 12),

            if (hasActiveTimer)
              _buildRealTimeTimer(remainingSeconds),

            const SizedBox(height: 8),

            Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: () => _showOrderDetails(order),
                    icon: const Icon(Icons.remove_red_eye, size: 16),
                    label: const Text('View Details'),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: primaryColor,
                      side: BorderSide(color: primaryColor),
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
                    SizedBox(
                      width: 48,
                      height: 48,
                      child: IconButton(
                        onPressed: () => _cancelOrder(order.orderId),
                        icon: const Icon(Icons.cancel, color: Colors.red, size: 24),
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

  Widget _buildRealTimeTimer(int remainingSeconds) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      decoration: BoxDecoration(
        color: remainingSeconds <= 0 ? Colors.red : 
               remainingSeconds <= 30 ? Colors.orange : Colors.green,
        borderRadius: BorderRadius.circular(6),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            Icons.timer,
            color: Colors.white,
            size: 16,
          ),
          const SizedBox(width: 4),
          Text(
            _formatTimer(remainingSeconds),
            style: const TextStyle(
              color: Colors.white,
              fontWeight: FontWeight.bold,
            ),
          ),
          const SizedBox(width: 4),
          Text(
            _getTimerLabel(remainingSeconds),
            style: const TextStyle(
              color: Colors.white,
              fontSize: 12,
            ),
          ),
        ],
      ),
    );
  }

  String _formatTimer(int seconds) {
    if (seconds <= 0) return '00:00';
    final minutes = seconds ~/ 60;
    final remainingSeconds = seconds % 60;
    return '${minutes.toString().padLeft(2, '0')}:${remainingSeconds.toString().padLeft(2, '0')}';
  }

  String _getTimerLabel(int seconds) {
    if (seconds <= 0) return 'Time Up!';
    if (seconds <= 30) return 'Hurry!';
    if (seconds <= 60) return 'Almost Up';
    return 'Remaining';
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
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text(
                  'Date Range',
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                Row(
                  children: [
                    // Test sound button only
                    IconButton(
                      onPressed: _testSound,
                      icon: const Icon(Icons.volume_up),
                      tooltip: 'Test Sound',
                      color: primaryColor,
                    ),
                    // Sound toggle button
                    IconButton(
                      onPressed: _toggleSoundNotifications,
                      icon: Icon(
                        _isSoundEnabled ? Icons.volume_up : Icons.volume_off,
                        color: _isSoundEnabled ? primaryColor : Colors.grey,
                      ),
                      tooltip: _isSoundEnabled ? 'Disable sound' : 'Enable sound',
                    ),
                  ],
                ),
              ],
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
                  icon: Icon(Icons.calendar_today, color: primaryColor),
                  tooltip: 'Select Date Range',
                ),
              ],
            ),
            // Status indicators
            if (_isPlayingSound)
              Padding(
                padding: const EdgeInsets.only(top: 8),
                child: Row(
                  children: [
                    Icon(Icons.notifications_active, color: Colors.orange, size: 16),
                    const SizedBox(width: 4),
                    Text(
                      'Ringing - ${_pendingOrderIds.length} pending order(s)',
                      style: TextStyle(
                        color: Colors.orange,
                        fontSize: 12,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ],
                ),
              ),
            if (_pendingOrderIds.isNotEmpty && !_isPlayingSound)
              Padding(
                padding: const EdgeInsets.only(top: 8),
                child: Row(
                  children: [
                    Icon(Icons.warning, color: Colors.amber, size: 16),
                    const SizedBox(width: 4),
                    Text(
                      '${_pendingOrderIds.length} pending order(s) - sound off',
                      style: TextStyle(
                        color: Colors.amber,
                        fontSize: 12,
                      ),
                    ),
                  ],
                ),
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
          // Auto-refresh indicator with 10s info
          if (_isRefreshing)
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(vertical: 8),
              color: Colors.blue.withOpacity(0.1),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  SizedBox(
                    width: 16,
                    height: 16,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      valueColor: AlwaysStoppedAnimation<Color>(primaryColor),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Text(
                    'Checking for new orders (every 10s)...',
                    style: TextStyle(
                      fontSize: 12,
                      color: primaryColor,
                    ),
                  ),
                ],
              ),
            ),
          Expanded(
            child: _isLoading
                ? Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        CircularProgressIndicator(
                          valueColor: AlwaysStoppedAnimation<Color>(primaryColor),
                        ),
                        const SizedBox(height: 16),
                        Text(
                          'Loading orders...',
                          style: TextStyle(
                            color: primaryColor,
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
                            Icon(Icons.error, size: 64, color: primaryColor),
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
                                backgroundColor: primaryColor,
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
      floatingActionButton: FloatingActionButton(
        onPressed: _loadOrders,
        backgroundColor: primaryColor,
        foregroundColor: Colors.white,
        child: const Icon(Icons.refresh),
        tooltip: 'Refresh Orders',
      ),
    );
  }
}

// Order Details Modal (keep your existing one)
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
              color: primaryColor,
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
            color: primaryColor,
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
              color: isTotal ? primaryColor : Colors.grey[700],
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
                color: isTotal ? primaryColor : Colors.black,
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
                color: primaryColor,
              ),
            ),
          ],
        ),
      ),
    );
  }

  String _formatDateTime12Hour(DateTime date) {
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    final hour = date.hour;
    final minute = date.minute;
    final period = hour >= 12 ? 'PM' : 'AM';
    final hour12 = hour % 12 == 0 ? 12 : hour % 12;
    return '${date.day} ${months[date.month - 1]}, ${hour12.toString().padLeft(2, '0')}:${minute.toString().padLeft(2, '0')} $period';
  }
}