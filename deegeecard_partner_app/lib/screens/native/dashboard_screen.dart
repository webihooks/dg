import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';
import '../webview/webview_screen.dart';
import '../../api/services/api_service.dart';
import 'orders_screen.dart';

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
  DateTime _lastUpdate = DateTime.now(); // ADD THIS LINE

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
  }

  Future<void> _loadDashboardData({bool forceRefresh = false}) async {
    try {
      setState(() {
        _isLoading = true;
        if (forceRefresh) {
          _salesSummary = {}; // Clear existing data
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
      return const Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            CircularProgressIndicator(),
            SizedBox(height: 16),
            Text('Loading dashboard data...'),
          ],
        ),
      );
    }

    if (_errorMessage.isNotEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.error, size: 64, color: Colors.red),
            const SizedBox(height: 16),
            Text(
              _errorMessage,
              style: const TextStyle(fontSize: 16, color: Colors.red),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 20),
            ElevatedButton(
              onPressed: () => _loadDashboardData(forceRefresh: true),
              child: const Text('Retry'),
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: () async {
        // Force refresh and clear cache
        await _loadDashboardData(forceRefresh: true);
      },
      child: SingleChildScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Welcome Section
            Card(
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: Column(
                  children: [
                    const Icon(Icons.dashboard, size: 64, color: Colors.blue),
                    const SizedBox(height: 16),
                    Text(
                      'Welcome, $_userName!',
                      style: const TextStyle(
                        fontSize: 24,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'DeeGeeCard Partner Dashboard',
                      style: TextStyle(
                        fontSize: 16,
                        color: Colors.grey[600],
                      ),
                    ),
                    const SizedBox(height: 16),
                    Text(
                      'Today - ${DateTime.now().toString().split(' ')[0]}',
                      style: const TextStyle(
                        fontSize: 14,
                        color: Colors.grey,
                      ),
                    ),
                    const SizedBox(height: 8),
                    // Last update time
                    Text(
                      'Last updated: ${_lastUpdate.hour}:${_lastUpdate.minute.toString().padLeft(2, '0')}:${_lastUpdate.second.toString().padLeft(2, '0')}',
                      style: const TextStyle(
                        fontSize: 12,
                        color: Colors.grey,
                      ),
                    ),
                  ],
                ),
              ),
            ),

            const SizedBox(height: 20),

            // Sales Summary Section
            const Text(
              "Today's Sales Summary",
              style: TextStyle(
                fontSize: 20,
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 16),

            // Sales Summary Cards
            GridView.count(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              crossAxisCount: 2,
              crossAxisSpacing: 12,
              mainAxisSpacing: 12,
              childAspectRatio: 1.2,
              children: [
                _buildSummaryCard(
                  'Total Sales',
                  '₹${_formatNumber(_salesSummary['total_sales'])}',
                  Colors.blue,
                  Icons.shopping_cart,
                  '${_salesSummary['total_orders'] ?? 0} orders',
                ),
                _buildSummaryCard(
                  'Subtotal',
                  '₹${_formatNumber(_salesSummary['subtotal'])}',
                  Colors.green,
                  Icons.receipt,
                  'Before discounts & taxes',
                ),
                _buildSummaryCard(
                  'Taxes & Charges',
                  '₹${_formatNumber(_toDouble(_salesSummary['total_tax']) + _toDouble(_salesSummary['total_delivery']))}',
                  Colors.orange,
                  Icons.account_balance,
                  'GST: ₹${_formatNumber(_salesSummary['total_tax'])}',
                ),
                _buildSummaryCard(
                  'Discounts',
                  '₹${_formatNumber(_salesSummary['total_discounts'])}',
                  Colors.purple,
                  Icons.discount,
                  'Applied to orders',
                ),
              ],
            ),

            const SizedBox(height: 20),

            // Average Order Value
            if (_toDouble(_salesSummary['avg_order_value']) > 0)
              Card(
                color: Colors.indigo[50],
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Row(
                    children: [
                      const Icon(Icons.trending_up, color: Colors.indigo),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text(
                              'Average Order Value',
                              style: TextStyle(
                                fontWeight: FontWeight.bold,
                                color: Colors.indigo,
                              ),
                            ),
                            Text(
                              '₹${_formatNumber(_salesSummary['avg_order_value'])}',
                              style: const TextStyle(
                                fontSize: 18,
                                fontWeight: FontWeight.bold,
                                color: Colors.indigo,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
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
    return Card(
      elevation: 4,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Icon(icon, color: color),
                Text(
                  title,
                  style: TextStyle(
                    fontSize: 12,
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
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                    color: color,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  subtitle,
                  style: TextStyle(
                    fontSize: 10,
                    color: Colors.grey[600],
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildOrdersTab() {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const Icon(Icons.shopping_cart, size: 64, color: Colors.green),
          const SizedBox(height: 16),
          const Text(
            'Orders Management',
            style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 8),
          const Text(
            'View and manage your orders here',
            style: TextStyle(fontSize: 16, color: Colors.grey),
          ),
          const SizedBox(height: 20),
          ElevatedButton(
            onPressed: () {
              // Add orders functionality
            },
            child: const Text('View Orders'),
          ),
        ],
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
            
            return Card(
              margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
              child: ListTile(
                leading: const Icon(Icons.web, color: Colors.blue),
                title: Text(item['title']!),
                trailing: const Icon(Icons.arrow_forward_ios, size: 16),
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
        title: const Text('DeeGeeCard Partner'),
        backgroundColor: Colors.blue,
        foregroundColor: Colors.white,
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () => _loadDashboardData(forceRefresh: true),
            tooltip: 'Refresh Data',
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