import 'package:flutter/material.dart';
import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'dart:async';
import 'package:audioplayers/audioplayers.dart';
import '../../api/services/orders_service.dart';
import '../../api/services/api_service.dart';
import '../../api/models/order_model.dart';
import '../../constants/colors.dart';

// START: Status Color Helper
/// Returns appropriate color based on order status
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
// END: Status Color Helper

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

  // START: Setup Audio Player
  /// Initializes audio player with state change listeners
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
  // END: Setup Audio Player

  // START: Play Continuous Sound
  /// Plays sound in continuous loop for pending orders
  Future<void> _playContinuousSound() async {
    if (!_isSoundEnabled || _isPlayingSound) {
      debugPrint(
        '🔇 Sound not played - enabled: $_isSoundEnabled, playing: $_isPlayingSound',
      );
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
          debugPrint(
            '🛑 Stopping sound loop - disabled: $_isSoundEnabled, pending: ${_pendingOrderIds.isEmpty}',
          );
          _stopAllSounds();
          timer.cancel();
          return;
        }

        debugPrint(
          '🔄 Restarting sound in loop - pending orders: ${_pendingOrderIds.length}',
        );
        playSound();
      });
    } catch (e) {
      debugPrint('❌ Sound initialization error: $e');
      setState(() {
        _isPlayingSound = false;
      });
    }
  }
  // END: Play Continuous Sound

  // START: Stop All Sounds
  /// Stops all audio playback and clears related timers
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
  // END: Stop All Sounds

  // START: Check for New Orders
  /// Compares current orders with previous count to detect new orders
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
      if (_pendingOrderIds.isNotEmpty &&
          _pendingOrderIds.length > previousPendingCount) {
        debugPrint(
          '🚀 NEW PENDING ORDERS DETECTED: ${_pendingOrderIds.length} total',
        );

        if (_isSoundEnabled) {
          debugPrint('🔊 STARTING SOUND FOR NEW PENDING ORDERS');
          _playContinuousSound();
        }

        // Show notification
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(
                '🔔 ${_pendingOrderIds.length} pending order(s) need attention!',
              ),
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
  // END: Check for New Orders

  // START: Update Pending Orders
  /// Updates the set of pending order IDs based on current orders
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
  // END: Update Pending Orders

  // START: Stop Sound for Order
  /// Removes order from pending list and stops sound if no pending orders remain
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
  // END: Stop Sound for Order

  // START: Start Countdown Timer
  /// Starts timer for real-time countdown updates
  void _startCountdownTimer() {
    _countdownTimer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (mounted) {
        setState(() {});
      }
    });
  }
  // END: Start Countdown Timer

  // START: Calculate Remaining Seconds
  /// Calculates remaining time for order timer
  int _calculateRemainingSeconds(Order order) {
    final now = DateTime.now();
    final orderTime = order.createdAt;
    final elapsedSeconds = now.difference(orderTime).inSeconds;
    final remainingSeconds = order.timerRemaining - elapsedSeconds;
    return remainingSeconds > 0 ? remainingSeconds : 0;
  }
  // END: Calculate Remaining Seconds

  // START: Check Session
  /// Validates user session and fetches dashboard data
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
  // END: Check Session

  // START: Start Auto Refresh
  /// Initializes automatic order refresh timer
  void _startAutoRefresh() {
    _refreshTimer = Timer.periodic(Duration(seconds: _refreshInterval), (
      timer,
    ) {
      if (mounted && !_isRefreshing) {
        debugPrint('🔄 Auto-refresh (10s) triggered');
        _refreshOrdersSilently();
      }
    });
  }
  // END: Start Auto Refresh

  // START: Refresh Orders Silently
  /// Fetches orders without showing loading indicator
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
  // END: Refresh Orders Silently

  // START: Load Orders
  /// Fetches orders with loading indicator and error handling
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
  // END: Load Orders

  // START: Format Date for API
  /// Converts DateTime to API-compatible string format
  String _formatDate(DateTime date) {
    return '${date.year}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';
  }
  // END: Format Date for API

  // START: Format Display Date
  /// Formats date for user-friendly display
  String _formatDisplayDate(DateTime date) {
    const months = [
      'Jan',
      'Feb',
      'Mar',
      'Apr',
      'May',
      'Jun',
      'Jul',
      'Aug',
      'Sep',
      'Oct',
      'Nov',
      'Dec',
    ];
    return '${months[date.month - 1]} ${date.day}, ${date.year}';
  }
  // END: Format Display Date

  // START: Format Time 12 Hour
  /// Converts time to 12-hour format with AM/PM
  String _formatTime12Hour(DateTime date) {
    final hour = date.hour;
    final minute = date.minute;
    final period = hour >= 12 ? 'PM' : 'AM';
    final hour12 = hour % 12 == 0 ? 12 : hour % 12;
    return '${hour12.toString().padLeft(2, '0')}:${minute.toString().padLeft(2, '0')} $period';
  }
  // END: Format Time 12 Hour

  // START: Format DateTime 12 Hour
  /// Formats complete date and time in 12-hour format
  String _formatDateTime12Hour(DateTime date) {
    const months = [
      'Jan',
      'Feb',
      'Mar',
      'Apr',
      'May',
      'Jun',
      'Jul',
      'Aug',
      'Sep',
      'Oct',
      'Nov',
      'Dec',
    ];
    return '${date.day} ${months[date.month - 1]}, ${_formatTime12Hour(date)}';
  }
  // END: Format DateTime 12 Hour

  // START: Update Order Status
  /// Updates order status and handles sound notification
  Future<void> _updateOrderStatus(int orderId, String newStatus) async {
    try {
      final success = await _ordersService.updateOrderStatus(
        orderId,
        newStatus,
      );
      if (success) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text('Order marked as $newStatus!')));

        // Stop sound for this order when status is changed
        _stopSoundForOrder(orderId);

        _loadOrders();
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Failed to update order status')),
        );
      }
    } catch (e) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('Error: $e')));
    }
  }
  // END: Update Order Status

  // START: Cancel Order
  /// Cancels order with confirmation dialog
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
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text('Error: $e')));
      }
    }
  }
  // END: Cancel Order

  // START: Toggle Sound Notifications
  /// Enables/disables sound notifications with proper state management
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
        content: Text(
          _isSoundEnabled
              ? '🔊 Sound notifications enabled'
              : '🔇 Sound notifications disabled',
        ),
        duration: const Duration(seconds: 2),
      ),
    );
  }
  // END: Toggle Sound Notifications

  // START: Test Sound
  /// Plays test sound for verification
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
  // END: Test Sound

  // START: Select Date Range
  /// Shows date range picker and updates filter
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
  // END: Select Date Range

  // START: Show Order Details
  /// Displays order details in bottom modal sheet
  void _showOrderDetails(Order order) {
    setState(() {
      _selectedOrder = order;
    });

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) =>
          OrderDetailsModal(order: order, primaryColor: primaryColor),
    );
  }
  // END: Show Order Details

  // START: Build Order Card
  /// Creates individual order card widget
  Widget _buildOrderCard(Order order) {
    final remainingSeconds = _calculateRemainingSeconds(order);
    final hasActiveTimer = remainingSeconds > 0 && order.canUpdateStatus;
    final isPending = order.status.toLowerCase() == 'pending';
    final isConfirmed = order.status.toLowerCase() == 'confirmed';
    final isReady = order.status.toLowerCase() == 'ready';

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
            // Order header with ID and status
            _buildOrderHeader(order, isPending),
            const SizedBox(height: 8),

            // Customer information
            _buildCustomerInfo(order),
            const SizedBox(height: 8),

            // Order type and item count
            _buildOrderMeta(order),
            const SizedBox(height: 8),

            // Total amount and creation time
            _buildOrderFooter(order),
            const SizedBox(height: 12),

            // Real-time timer if active
            if (hasActiveTimer) _buildRealTimeTimer(remainingSeconds),
            const SizedBox(height: 8),

            // Action buttons
            _buildActionButtons(order, isPending, isConfirmed, isReady),
          ],
        ),
      ),
    );
  }
  // END: Build Order Card

  // START: Build Order Header
  /// Creates order header with ID, notification icon, and status badge
  Widget _buildOrderHeader(Order order, bool isPending) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Expanded(
          child: Row(
            children: [
              if (isPending)
                Icon(
                  Icons.notifications_active,
                  color: Colors.orange,
                  size: 20,
                ),
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
    );
  }
  // END: Build Order Header

  // START: Build Customer Info
  /// Displays customer name and phone number
  Widget _buildCustomerInfo(Order order) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Customer: ${order.customerName}',
          style: const TextStyle(fontWeight: FontWeight.w500),
        ),
        if (order.customerPhone.isNotEmpty)
          Text('Phone: ${order.customerPhone}'),
      ],
    );
  }
  // END: Build Customer Info

  // START: Build Order Meta
  /// Shows order type and item count
  Widget _buildOrderMeta(Order order) {
    return Row(
      children: [
        Expanded(
          child: Text(
            order.formattedOrderType,
            style: TextStyle(color: Colors.grey[600]),
          ),
        ),
        Text(
          '${order.itemCount} items',
          style: TextStyle(color: Colors.grey[600]),
        ),
      ],
    );
  }
  // END: Build Order Meta

  // START: Build Order Footer
  /// Displays total amount and order creation time
  Widget _buildOrderFooter(Order order) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(
          'Total: ₹${order.totalAmount.toStringAsFixed(0)}',
          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
        ),
        Text(
          _formatDateTime12Hour(order.createdAt),
          style: TextStyle(color: Colors.grey[600], fontSize: 12),
        ),
      ],
    );
  }
  // END: Build Order Footer

  // START: Build Action Buttons
  /// Creates action buttons based on order status and permissions
  Widget _buildActionButtons(
    Order order,
    bool isPending,
    bool isConfirmed,
    bool isReady,
  ) {
    return Row(
      children: [
        Expanded(
          child: OutlinedButton.icon(
            onPressed: () => _showOrderDetails(order),
            icon: const Icon(Icons.remove_red_eye, size: 16),
            label: const Text('View'),
            style: OutlinedButton.styleFrom(
              foregroundColor: primaryColor,
              side: BorderSide(color: primaryColor),
            ),
          ),
        ),
        const SizedBox(width: 8),

        if (order.canUpdateStatus) ...[
          // PENDING STATE: Show Accept and Reject buttons
          if (isPending) ...[
            Expanded(
              child: ElevatedButton(
                onPressed: () => _updateOrderStatus(order.orderId, 'Confirmed'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.green,
                  foregroundColor: Colors.white,
                ),
                child: const Text('Accept'),
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: ElevatedButton(
                onPressed: () => _cancelOrder(order.orderId),
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.red,
                  foregroundColor: Colors.white,
                ),
                child: const Text('Reject'),
              ),
            ),
          ],

          // CONFIRMED STATE: Show Ready button
          if (isConfirmed && order.canMarkReady)
            Expanded(
              child: ElevatedButton(
                onPressed: () => _updateOrderStatus(order.orderId, 'Ready'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.orange,
                  foregroundColor: Colors.white,
                ),
                child: const Text('Ready'),
              ),
            ),

          // READY STATE: Show Complete button
          if (isReady && order.canMarkComplete)
            Expanded(
              child: ElevatedButton(
                onPressed: () => _updateOrderStatus(order.orderId, 'Completed'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.green,
                  foregroundColor: Colors.white,
                ),
                child: const Text('Complete'),
              ),
            ),

          // Cancel button for other states
          if (order.canCancel && !isPending) const SizedBox(width: 8),
          if (order.canCancel && !isPending)
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
    );
  }
  // END: Build Action Buttons

  // START: Build Real Time Timer
  /// Creates countdown timer widget for time-sensitive orders
  Widget _buildRealTimeTimer(int remainingSeconds) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      decoration: BoxDecoration(
        color: remainingSeconds <= 0
            ? Colors.red
            : remainingSeconds <= 30
            ? Colors.orange
            : Colors.green,
        borderRadius: BorderRadius.circular(6),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.timer, color: Colors.white, size: 16),
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
            style: const TextStyle(color: Colors.white, fontSize: 12),
          ),
        ],
      ),
    );
  }
  // END: Build Real Time Timer

  // START: Format Timer
  /// Formats seconds into MM:SS format
  String _formatTimer(int seconds) {
    if (seconds <= 0) return '00:00';
    final minutes = seconds ~/ 60;
    final remainingSeconds = seconds % 60;
    return '${minutes.toString().padLeft(2, '0')}:${remainingSeconds.toString().padLeft(2, '0')}';
  }
  // END: Format Timer

  // START: Get Timer Label
  /// Returns appropriate label based on remaining time
  String _getTimerLabel(int seconds) {
    if (seconds <= 0) return 'Time Up!';
    if (seconds <= 30) return 'Hurry!';
    if (seconds <= 60) return 'Almost Up';
    return 'Remaining';
  }
  // END: Get Timer Label

  // START: Build Date Range Selector
  /// Creates date range selector with sound controls
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
                  style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
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
                      tooltip: _isSoundEnabled
                          ? 'Disable sound'
                          : 'Enable sound',
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
                    style: TextStyle(color: Colors.grey[600]),
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
            _buildSoundStatusIndicators(),
          ],
        ),
      ),
    );
  }
  // END: Build Date Range Selector

  // START: Build Sound Status Indicators
  /// Shows sound and pending order status indicators
  Widget _buildSoundStatusIndicators() {
    return Column(
      children: [
        if (_isPlayingSound)
          Padding(
            padding: const EdgeInsets.only(top: 8),
            child: Row(
              children: [
                Icon(
                  Icons.notifications_active,
                  color: Colors.orange,
                  size: 16,
                ),
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
                  style: TextStyle(color: Colors.amber, fontSize: 12),
                ),
              ],
            ),
          ),
      ],
    );
  }
  // END: Build Sound Status Indicators

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        backgroundColor: AppColors.primary, // Use primary color
        foregroundColor: Colors.white,
        title: const Text('Orders'),
        elevation: 0,
        toolbarHeight: 0,
      ),
      body: Column(
        children: [
          _buildDateRangeSelector(),
          // Auto-refresh indicator with 10s info
          if (_isRefreshing) _buildAutoRefreshIndicator(),
          Expanded(child: _buildOrderListContent()),
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

  // START: Build Auto Refresh Indicator
  /// Shows auto-refresh progress indicator
  Widget _buildAutoRefreshIndicator() {
    return Container(
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
            style: TextStyle(fontSize: 12, color: primaryColor),
          ),
        ],
      ),
    );
  }
  // END: Build Auto Refresh Indicator

  // START: Build Order List Content
  /// Builds appropriate content based on loading state and order data
  Widget _buildOrderListContent() {
    if (_isLoading) {
      return _buildLoadingIndicator();
    } else if (_errorMessage.isNotEmpty) {
      return _buildErrorState();
    } else if (_orders.isEmpty) {
      return _buildEmptyState();
    } else {
      return _buildOrderList();
    }
  }
  // END: Build Order List Content

  // START: Build Loading Indicator
  /// Shows loading progress indicator
  Widget _buildLoadingIndicator() {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          CircularProgressIndicator(
            valueColor: AlwaysStoppedAnimation<Color>(primaryColor),
          ),
          const SizedBox(height: 16),
          Text('Loading orders...', style: TextStyle(color: primaryColor)),
        ],
      ),
    );
  }
  // END: Build Loading Indicator

  // START: Build Error State
  /// Shows error message with retry option
  Widget _buildErrorState() {
    return Center(
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
    );
  }
  // END: Build Error State

  // START: Build Empty State
  /// Shows empty orders message
  Widget _buildEmptyState() {
    return const Center(
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
    );
  }
  // END: Build Empty State

  // START: Build Order List
  /// Creates scrollable list of order cards with refresh indicator
  Widget _buildOrderList() {
    return RefreshIndicator(
      onRefresh: _loadOrders,
      child: ListView.builder(
        itemCount: _orders.length,
        itemBuilder: (context, index) {
          return _buildOrderCard(_orders[index]);
        },
      ),
    );
  }

  // END: Build Order List
}

// START: Order Details Modal
/// Bottom sheet modal for displaying detailed order information
class OrderDetailsModal extends StatelessWidget {
  final Order order;
  final Color primaryColor;

  const OrderDetailsModal({
    Key? key,
    required this.order,
    required this.primaryColor,
  }) : super(key: key);

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
          // Modal header
          _buildModalHeader(context),
          // Modal content
          Expanded(child: _buildModalContent()),
        ],
      ),
    );
  }
  // END: Order Details Modal

  // START: Build Modal Header
  /// Creates modal header with order ID and close button
  Widget _buildModalHeader(BuildContext context) {
    return Container(
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
    );
  }
  // END: Build Modal Header

  // START: Build Modal Content
  /// Creates scrollable modal content with order details
  Widget _buildModalContent() {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _buildDetailSection(
            title: 'Order Summary',
            children: [
              _buildDetailRow(
                'Order Date',
                _formatDateTime12Hour(order.createdAt),
              ),
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
              if (order.orderType == 'delivery' &&
                  order.deliveryAddress.isNotEmpty)
                _buildDetailRow('Delivery Address', order.deliveryAddress),
              if (order.orderType == 'dining' && order.tableNumber.isNotEmpty)
                _buildDetailRow('Table Number', order.tableNumber),
            ],
          ),

          const SizedBox(height: 20),

          _buildDetailSection(
            title: 'Order Items (${order.items.length})',
            children: [...order.items.map((item) => _buildOrderItem(item))],
          ),

          const SizedBox(height: 20),

          _buildDetailSection(
            title: 'Payment Summary',
            children: [
              _buildDetailRow(
                'Subtotal',
                '₹${order.subtotal.toStringAsFixed(0)}',
              ),
              if (order.discountAmount > 0)
                _buildDetailRow(
                  'Discount',
                  '-₹${order.discountAmount.toStringAsFixed(0)} ${order.discountType.isNotEmpty ? '(${order.discountType})' : ''}',
                ),
              if (order.gstAmount > 0)
                _buildDetailRow(
                  'GST',
                  '₹${order.gstAmount.toStringAsFixed(0)}',
                ),
              if (order.deliveryCharge > 0)
                _buildDetailRow(
                  'Delivery Charge',
                  '₹${order.deliveryCharge.toStringAsFixed(0)}',
                ),
              const Divider(),
              _buildDetailRow(
                'Total Amount',
                '₹${order.totalAmount.toStringAsFixed(0)}',
                isTotal: true,
              ),
            ],
          ),
        ],
      ),
    );
  }
  // END: Build Modal Content

  // START: Build Detail Section
  /// Creates a section with title and content container
  Widget _buildDetailSection({
    required String title,
    required List<Widget> children,
  }) {
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
            child: Column(children: children),
          ),
        ),
      ],
    );
  }
  // END: Build Detail Section

  // START: Build Detail Row
  /// Creates a row for displaying key-value pairs
  Widget _buildDetailRow(
    String label,
    String value, {
    bool isStatus = false,
    bool isTotal = false,
  }) {
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
  // END: Build Detail Row

  // START: Build Order Item
  /// Creates individual order item widget
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
                    style: const TextStyle(fontWeight: FontWeight.w500),
                  ),
                  Text(
                    '₹${item.price.toStringAsFixed(0)} each',
                    style: TextStyle(color: Colors.grey[600], fontSize: 12),
                  ),
                ],
              ),
            ),
            Text(
              'X ${item.quantity}',
              style: const TextStyle(fontWeight: FontWeight.w500),
            ),
            const SizedBox(width: 16),
            Text(
              '₹${item.total.toStringAsFixed(0)}',
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
  // END: Build Order Item

  // START: Format DateTime 12 Hour
  /// Formats date and time for display in modal
  String _formatDateTime12Hour(DateTime date) {
    const months = [
      'Jan',
      'Feb',
      'Mar',
      'Apr',
      'May',
      'Jun',
      'Jul',
      'Aug',
      'Sep',
      'Oct',
      'Nov',
      'Dec',
    ];
    final hour = date.hour;
    final minute = date.minute;
    final period = hour >= 12 ? 'PM' : 'AM';
    final hour12 = hour % 12 == 0 ? 12 : hour % 12;
    return '${date.day} ${months[date.month - 1]}, ${hour12.toString().padLeft(2, '0')}:${minute.toString().padLeft(2, '0')} $period';
  }

  // END: Format DateTime 12 Hour
}
