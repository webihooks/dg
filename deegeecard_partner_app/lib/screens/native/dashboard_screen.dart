import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';
import '../webview/webview_screen.dart';
import '../../api/services/api_service.dart';
import 'orders_screen.dart';
import '../../constants/colors.dart';
import '../../services/enhanced_service.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({Key? key}) : super(key: key);

  @override
  _DashboardScreenState createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  int _currentIndex = 0;
  String _userName = 'Loading...';
  Map<String, dynamic> _salesSummary = {};
  bool _isLoading = true;
  String _errorMessage = '';
  DateTime _lastUpdate = DateTime.now();
  
  // Service monitoring variables
  bool _isMonitoringActive = false;
  bool _isServiceLoading = false;

  // Primary color
  final Color primaryColor = const Color(0xffff6c2f);

  // WebView URLs for More menu
  final Map<String, Map<String, String>> _moreMenuItems = {
    'Store Timing': {
      'url': 'https://dgcard.online/store_timing.php',
      'title': 'Store Timing'
    },
    'Store ON/OFF': {
      'url': 'https://dgcard.online/store_on_off.php',
      'title': 'Store ON/OFF'
    },
    'Delivery Charges': {
      'url': 'https://dgcard.online/delivery_charges.php',
      'title': 'Delivery Charges'
    },
    'GST': {
      'url': 'https://dgcard.online/gst_charge.php',
      'title': 'GST'
    },
    'Discount': {
      'url': 'https://dgcard.online/discount.php',
      'title': 'Discount'
    },
    'Coupon Code': {
      'url': 'https://dgcard.online/coupon.php',
      'title': 'Coupon Code'
    },
    'Products': {
      'url': 'https://dgcard.online/products.php',
      'title': 'Products'
    },
    'Tags': {
      'url': 'https://dgcard.online/tags.php',
      'title': 'Tags'
    },
    'Photo Gallery': {
      'url': 'https://dgcard.online/photo-gallery.php',
      'title': 'Photo Gallery'
    },
  };

  @override
  void initState() {
    super.initState();
    _loadDashboardData();
    _loadServiceStatus();
  }

  Future<void> _loadDashboardData({bool forceRefresh = false}) async {
    try {
      setState(() {
        _isLoading = true;
        if (forceRefresh) {
          _salesSummary = {};
        }
      });

      // Load user data from SharedPreferences
      final prefs = await SharedPreferences.getInstance();
      final storedName = prefs.getString('name');
      
      if (storedName != null && !forceRefresh) {
        setState(() {
          _userName = storedName;
        });
      }

      // Fetch dashboard data from API
      await _fetchDashboardData();

      setState(() {
        _isLoading = false;
      });
    } catch (e) {
      setState(() {
        _isLoading = false;
        _errorMessage = 'Failed to load dashboard data: $e';
      });
    }
  }

  Future<void> _fetchDashboardData() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final userId = prefs.getInt('userId');
      
      if (userId == null) {
        throw Exception('User not logged in');
      }

      // Use the imported ApiService from api/services/api_service.dart
      final apiService = ApiService();
      final response = await apiService.getDashboardData();

      setState(() {
        _salesSummary = response['summary'] ?? {};
        // Update user name from API response if available
        if (response['user'] != null && response['user']['name'] != null) {
          _userName = response['user']['name'];
          // Update stored name
          prefs.setString('name', _userName);
        }
        _lastUpdate = DateTime.now(); // Track last update time
      });
    } catch (e) {
      throw Exception('Failed to fetch dashboard data: $e');
    }
  }

  Future<void> _loadServiceStatus() async {
    setState(() {
      _isServiceLoading = true;
    });
    
    final isActive = await EnhancedService.isOrderMonitoringActive();
    
    setState(() {
      _isMonitoringActive = isActive;
      _isServiceLoading = false;
    });
  }

  Future<void> _toggleOrderMonitoring() async {
    setState(() {
      _isServiceLoading = true;
    });

    try {
      if (_isMonitoringActive) {
        await EnhancedService.stopOrderMonitoring();
      } else {
        await EnhancedService.startOrderMonitoring();
      }
      
      setState(() {
        _isMonitoringActive = !_isMonitoringActive;
      });
      
      // Show success message
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            _isMonitoringActive 
                ? 'Enhanced monitoring started ✅\n• Foreground service active\n• Manual order checking available\n• Sound notifications' 
                : 'Enhanced monitoring stopped ⏸️',
          ),
          backgroundColor: _isMonitoringActive ? Colors.green : Colors.orange,
          duration: Duration(seconds: 4),
        ),
      );
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Failed to ${_isMonitoringActive ? 'stop' : 'start'} monitoring: $e'),
          backgroundColor: Colors.red,
          duration: Duration(seconds: 3),
        ),
      );
    } finally {
      setState(() {
        _isServiceLoading = false;
      });
    }
  }

  Future<void> _testNotification() async {
    try {
      await EnhancedService.testNewOrder();
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Test notification sent! Check your notifications.'),
          backgroundColor: Colors.blue,
          duration: Duration(seconds: 2),
        ),
      );
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Failed to send test notification: $e'),
          backgroundColor: Colors.red,
          duration: Duration(seconds: 3),
        ),
      );
    }
  }

  Future<void> _manualOrderCheck() async {
    try {
      setState(() {
        _isServiceLoading = true;
      });
      
      final result = await EnhancedService.manualOrderCheck();
      
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(result['message'] ?? 'Order check completed'),
          backgroundColor: result['success'] == true ? Colors.green : Colors.red,
          duration: Duration(seconds: 3),
        ),
      );
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Manual check failed: $e'),
          backgroundColor: Colors.red,
          duration: Duration(seconds: 3),
        ),
      );
    } finally {
      setState(() {
        _isServiceLoading = false;
      });
    }
  }

  Future<Map<String, String>> _getSessionData() async {
    final prefs = await SharedPreferences.getInstance();
    final userId = prefs.getInt('userId');
    final userEmail = prefs.getString('email');
    final userName = prefs.getString('name');
    
    if (userId != null) {
      return {
        'source': 'flutter_app',
        'flutter_user_id': userId.toString(),
        'flutter_email': userEmail ?? '',
        'flutter_name': userName ?? '',
      };
    }
    return {};
  }

  Future<void> _logout() async {
    // Stop monitoring service on logout
    await EnhancedService.stopOrderMonitoring();
    
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool('isLoggedIn', false);
    await prefs.remove('userId');
    await prefs.remove('email');
    await prefs.remove('name');
    await prefs.remove('userRole');
    
    // Navigate back to login screen
    Navigator.pushReplacementNamed(context, '/login');
  }

  Widget _buildDashboardContent() {
    switch (_currentIndex) {
      case 0: // Dashboard
        return _buildDashboardTab();
      case 1: // Orders
        return OrdersScreen();
      case 2: // Menu (More)
        return _buildMenuTab();
      case 3: // Logout
        _logout();
        return Container();
      default:
        return _buildDashboardTab();
    }
  }

  Widget _buildDashboardTab() {
    if (_isLoading) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            CircularProgressIndicator(
              valueColor: AlwaysStoppedAnimation<Color>(AppColors.primary),
            ),
            const SizedBox(height: 16),
            Text(
              'Loading dashboard data...',
              style: TextStyle(
                color: AppColors.primary,
              ),
            ),
          ],
        ),
      );
    }

    if (_errorMessage.isNotEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.error, size: 64, color: AppColors.primary),
            const SizedBox(height: 16),
            Text(
              _errorMessage,
              style: TextStyle(fontSize: 16, color: AppColors.primary),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 20),
            ElevatedButton(
              onPressed: _loadDashboardData,
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.primary,
                foregroundColor: Colors.white,
              ),
              child: const Text('Retry'),
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _loadDashboardData,
      child: SingleChildScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Welcome Section - Full Width
            Container(
              width: double.infinity, // Full width
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
                padding: const EdgeInsets.all(24),
                child: Column(
                  children: [
                    Icon(Icons.dashboard, size: 72, color: AppColors.primary),
                    const SizedBox(height: 20),
                    Text(
                      'Welcome, $_userName!',
                      style: const TextStyle(
                        fontSize: 26,
                        fontWeight: FontWeight.bold,
                        color: Colors.black,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'DeeGeeCard Partner Dashboard',
                      style: TextStyle(
                        fontSize: 18,
                        color: Colors.grey[600],
                      ),
                    ),
                    const SizedBox(height: 16),
                    Text(
                      'Today - ${DateTime.now().toString().split(' ')[0]}',
                      style: const TextStyle(
                        fontSize: 16,
                        color: Colors.grey,
                      ),
                    ),
                  ],
                ),
              ),
            ),

            const SizedBox(height: 24),

            // Enhanced Monitoring Control Card
            Container(
              width: double.infinity,
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
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Status Header
                    Row(
                      children: [
                        Container(
                          padding: const EdgeInsets.all(8),
                          decoration: BoxDecoration(
                            color: _isMonitoringActive ? Colors.green.withOpacity(0.1) : Colors.grey.withOpacity(0.1),
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Icon(
                            _isMonitoringActive ? 
                              Icons.notifications_active : 
                              Icons.notifications_off,
                            size: 32,
                            color: _isMonitoringActive ? Colors.green : Colors.grey,
                          ),
                        ),
                        const SizedBox(width: 16),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                _isMonitoringActive ? 
                                  'Enhanced Monitoring Active' : 
                                  'Enhanced Monitoring Inactive',
                                style: const TextStyle(
                                  fontSize: 18,
                                  fontWeight: FontWeight.bold,
                                  color: Colors.black,
                                ),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                _isMonitoringActive ?
                                  '✓ Foreground service active\n✓ Manual order checking\n✓ Sound & native notifications' :
                                  'Start monitoring for order notifications',
                                style: TextStyle(
                                  fontSize: 14,
                                  color: Colors.grey[600],
                                  height: 1.4,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                    
                    const SizedBox(height: 20),
                    
                    // Main Control Button
                    SizedBox(
                      width: double.infinity,
                      child: ElevatedButton.icon(
                        onPressed: _isServiceLoading ? null : _toggleOrderMonitoring,
                        icon: Icon(
                          _isMonitoringActive ? Icons.stop : Icons.play_arrow,
                          color: Colors.white,
                          size: 24,
                        ),
                        label: Text(
                          _isMonitoringActive ? 
                            'STOP ENHANCED MONITORING' : 
                            'START ENHANCED MONITORING',
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 16,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: _isMonitoringActive ? 
                            Colors.red : Colors.green,
                          foregroundColor: Colors.white,
                          padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 20),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(12),
                          ),
                          elevation: 2,
                        ),
                      ),
                    ),
                    
                    // Loading Indicator
                    if (_isServiceLoading)
                      const Padding(
                        padding: EdgeInsets.only(top: 16),
                        child: Center(
                          child: Column(
                            children: [
                              SizedBox(
                                height: 24,
                                width: 24,
                                child: CircularProgressIndicator(
                                  strokeWidth: 3,
                                  valueColor: AlwaysStoppedAnimation<Color>(Colors.blue),
                                ),
                              ),
                              SizedBox(height: 8),
                              Text(
                                'Updating service...',
                                style: TextStyle(
                                  color: Colors.grey,
                                  fontSize: 12,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    
                    const SizedBox(height: 16),
                    
                    // Action Buttons Row
                    Row(
                      children: [
                        Expanded(
                          child: OutlinedButton.icon(
                            onPressed: _testNotification,
                            icon: Icon(Icons.notification_important, color: Colors.blue, size: 18),
                            label: const Text(
                              'TEST NOTIFY',
                              style: TextStyle(
                                color: Colors.blue,
                                fontWeight: FontWeight.w600,
                                fontSize: 12,
                              ),
                            ),
                            style: OutlinedButton.styleFrom(
                              padding: const EdgeInsets.symmetric(vertical: 10),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(8),
                              ),
                              side: const BorderSide(color: Colors.blue),
                            ),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: OutlinedButton.icon(
                            onPressed: _isServiceLoading ? null : _manualOrderCheck,
                            icon: Icon(Icons.refresh, color: Colors.orange, size: 18),
                            label: const Text(
                              'CHECK ORDERS',
                              style: TextStyle(
                                color: Colors.orange,
                                fontWeight: FontWeight.w600,
                                fontSize: 12,
                              ),
                            ),
                            style: OutlinedButton.styleFrom(
                              padding: const EdgeInsets.symmetric(vertical: 10),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(8),
                              ),
                              side: const BorderSide(color: Colors.orange),
                            ),
                          ),
                        ),
                      ],
                    ),
                    
                    // Info Text
                    const SizedBox(height: 12),
                    Text(
                      _isMonitoringActive ?
                        'ℹ️  Use "Check Orders" to manually search for new orders' :
                        'ℹ️  Start monitoring to enable order checking and notifications',
                      style: TextStyle(
                        fontSize: 12,
                        color: Colors.grey[600],
                        fontStyle: FontStyle.italic,
                      ),
                      textAlign: TextAlign.center,
                    ),
                  ],
                ),
              ),
            ),

            const SizedBox(height: 24),

            // Sales Summary Section
            const Text(
              "Today's Sales Summary",
              style: TextStyle(
                fontSize: 22,
                fontWeight: FontWeight.bold,
                color: Colors.black,
              ),
            ),
            const SizedBox(height: 16),

            // Sales Summary Cards
            GridView.count(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              crossAxisCount: 2,
              crossAxisSpacing: 16,
              mainAxisSpacing: 16,
              childAspectRatio: 1.1,
              children: [
                _buildSummaryCard(
                  'Total Sales',
                  '₹${_formatNumber(_salesSummary['total_sales'])}',
                  AppColors.primary,
                  Icons.shopping_cart,
                  '${_salesSummary['total_orders'] ?? 0} orders',
                ),
                _buildSummaryCard(
                  'Subtotal',
                  '₹${_formatNumber(_salesSummary['subtotal'])}',
                  AppColors.primary,
                  Icons.receipt,
                  'Before discounts & taxes',
                ),
                _buildSummaryCard(
                  'Taxes & Charges',
                  '₹${_formatNumber(_toDouble(_salesSummary['total_tax']) + _toDouble(_salesSummary['total_delivery']))}',
                  AppColors.primary,
                  Icons.account_balance,
                  'GST: ₹${_formatNumber(_salesSummary['total_tax'])}',
                ),
                _buildSummaryCard(
                  'Discounts',
                  '₹${_formatNumber(_salesSummary['total_discounts'])}',
                  AppColors.primary,
                  Icons.discount,
                  'Applied to orders',
                ),
              ],
            ),

            const SizedBox(height: 24),

            // Average Order Value
            if (_toDouble(_salesSummary['avg_order_value']) > 0)
              Container(
                width: double.infinity, // Full width
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
                  padding: const EdgeInsets.all(20),
                  child: Row(
                    children: [
                      Icon(Icons.trending_up, size: 32, color: AppColors.primary),
                      const SizedBox(width: 16),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'Average Order Value',
                              style: const TextStyle(
                                fontSize: 18,
                                fontWeight: FontWeight.bold,
                                color: Colors.black,
                              ),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              '₹${_formatNumber(_salesSummary['avg_order_value'])}',
                              style: TextStyle(
                                fontSize: 22,
                                fontWeight: FontWeight.bold,
                                color: AppColors.primary,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              
            const SizedBox(height: 24),
            
            // Last Update Info
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.grey[50],
                borderRadius: BorderRadius.circular(8),
              ),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.update, size: 16, color: Colors.grey[600]),
                  const SizedBox(width: 8),
                  Text(
                    'Last updated: ${_lastUpdate.toString().split('.')[0]}',
                    style: TextStyle(
                      fontSize: 12,
                      color: Colors.grey[600],
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

  Widget _buildSummaryCard(
    String title,
    String amount,
    Color color,
    IconData icon,
    String subtitle,
  ) {
    return Container(
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
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: color.withOpacity(0.1),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Icon(icon, size: 24, color: color),
                ),
                Text(
                  title,
                  style: TextStyle(
                    fontSize: 14,
                    color: Colors.grey[600],
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ],
            ),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  amount,
                  style: TextStyle(
                    fontSize: 22,
                    fontWeight: FontWeight.bold,
                    color: color,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  subtitle,
                  style: TextStyle(
                    fontSize: 12,
                    color: Colors.grey[600],
                  ),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildMenuTab() {
    return FutureBuilder<Map<String, String>>(
      future: _getSessionData(),
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return const Center(child: CircularProgressIndicator());
        }
        
        final sessionData = snapshot.data ?? {};
        
        return ListView.builder(
          itemCount: _moreMenuItems.length,
          itemBuilder: (context, index) {
            final itemKey = _moreMenuItems.keys.elementAt(index);
            final item = _moreMenuItems[itemKey]!;
            
            // Add session parameters to URL
            String urlWithSession = item['url']!;
            if (sessionData.isNotEmpty) {
              final uri = Uri.parse(urlWithSession);
              final params = Map<String, String>.from(uri.queryParameters);
              params.addAll(sessionData);
              urlWithSession = uri.replace(queryParameters: params).toString();
            }
            
            return Container(
              margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(8),
                boxShadow: [
                  BoxShadow(
                    color: Colors.grey.withOpacity(0.1),
                    blurRadius: 4,
                    offset: const Offset(0, 2),
                  ),
                ],
              ),
              child: ListTile(
                leading: Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: primaryColor.withOpacity(0.1),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Icon(Icons.web, size: 20, color: primaryColor),
                ),
                title: Text(
                  item['title']!,
                  style: const TextStyle(
                    fontWeight: FontWeight.w500,
                  ),
                ),
                trailing: Icon(Icons.arrow_forward_ios, size: 16, color: primaryColor),
                onTap: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (context) => WebViewScreen(
                        url: urlWithSession,
                        title: item['title']!,
                      ),
                    ),
                  );
                },
              ),
            );
          },
        );
      },
    );
  }

  // Helper method to safely convert any value to double
  double _toDouble(dynamic value) {
    if (value == null) return 0.0;
    if (value is double) return value;
    if (value is int) return value.toDouble();
    if (value is String) return double.tryParse(value) ?? 0.0;
    return 0.0;
  }

  String _formatNumber(dynamic number) {
    if (number == null) return '0';
    
    // Convert to double first
    final numValue = _toDouble(number);
    
    // Format the number
    if (numValue % 1 == 0) {
      return numValue.toInt().toString();
    } else {
      return numValue.toStringAsFixed(2);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        backgroundColor: primaryColor,
        foregroundColor: Colors.white,
        title: const Text('Dashboard'),
        elevation: 0,
        actions: [
          // Service status indicator in app bar
          Container(
            margin: const EdgeInsets.only(right: 16),
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
            decoration: BoxDecoration(
              color: _isMonitoringActive ? Colors.green.withOpacity(0.2) : Colors.grey.withOpacity(0.2),
              borderRadius: BorderRadius.circular(20),
              border: Border.all(
                color: _isMonitoringActive ? Colors.green : Colors.grey,
                width: 1,
              ),
            ),
            child: Row(
              children: [
                Icon(
                  _isMonitoringActive ? Icons.notifications_active : Icons.notifications_off,
                  size: 16,
                  color: _isMonitoringActive ? Colors.green : Colors.grey,
                ),
                const SizedBox(width: 6),
                Text(
                  _isMonitoringActive ? 'ACTIVE' : 'INACTIVE',
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.bold,
                    color: _isMonitoringActive ? Colors.green : Colors.grey,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
      body: _buildDashboardContent(),
      bottomNavigationBar: BottomNavigationBar(
        currentIndex: _currentIndex,
        onTap: (index) {
          setState(() {
            _currentIndex = index;
          });
        },
        type: BottomNavigationBarType.fixed,
        selectedItemColor: primaryColor,
        unselectedItemColor: Colors.grey,
        backgroundColor: Colors.white,
        elevation: 8,
        items: const [
          BottomNavigationBarItem(
            icon: Icon(Icons.dashboard),
            label: 'Dashboard',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.shopping_cart),
            label: 'Orders',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.menu),
            label: 'Menu',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.logout),
            label: 'Logout',
          ),
        ],
      ),
    );
  }
}