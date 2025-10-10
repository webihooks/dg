import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../webview/webview_screen.dart';
import '../../api/services/api_service.dart';
import 'orders_screen.dart';
import '../../constants/colors.dart';

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

  // Primary color
  final Color primaryColor = const Color(0xffff6c2f);

  // WebView URLs for More menu with icons
  final List<Map<String, dynamic>> _moreMenuItems = [
    {
      'title': 'Store Timing',
      'url': 'https://dgcard.online/store_timing.php',
      'icon': Icons.access_time,
    },
    {
      'title': 'Store ON/OFF',
      'url': 'https://dgcard.online/store_on_off.php',
      'icon': Icons.power_settings_new,
    },
    {
      'title': 'Delivery Charges',
      'url': 'https://dgcard.online/delivery_charges.php',
      'icon': Icons.local_shipping,
    },
    {
      'title': 'GST',
      'url': 'https://dgcard.online/gst_charge.php',
      'icon': Icons.account_balance,
    },
    {
      'title': 'Discount',
      'url': 'https://dgcard.online/discount.php',
      'icon': Icons.discount,
    },
    {
      'title': 'Coupon Code',
      'url': 'https://dgcard.online/coupon.php',
      'icon': Icons.local_offer,
    },
    {
      'title': 'Products',
      'url': 'https://dgcard.online/products.php',
      'icon': Icons.shopping_bag,
    },
    {
      'title': 'Tags',
      'url': 'https://dgcard.online/tags.php',
      'icon': Icons.tag,
    },
    {
      'title': 'Photo Gallery',
      'url': 'https://dgcard.online/photo-gallery.php',
      'icon': Icons.photo_library,
    },
  ];

  // App bar titles for each tab
  final List<String> _appBarTitles = ['Dashboard', 'Orders', 'Menu', 'Logout'];

  @override
  void initState() {
    super.initState();
    _loadDashboardData();
  }

  // START: Load Dashboard Data
  Future<void> _loadDashboardData({bool forceRefresh = false}) async {
    try {
      setState(() {
        _isLoading = true;
        if (forceRefresh) {
          _salesSummary = {};
        }
      });

      final prefs = await SharedPreferences.getInstance();
      final storedName = prefs.getString('name');

      if (storedName != null && !forceRefresh) {
        setState(() {
          _userName = storedName;
        });
      }

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

      final apiService = ApiService();
      final response = await apiService.getDashboardData();

      setState(() {
        _salesSummary = response['summary'] ?? {};
        if (response['user'] != null && response['user']['name'] != null) {
          _userName = response['user']['name'];
          prefs.setString('name', _userName);
        }
        _lastUpdate = DateTime.now();
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

    Navigator.pushReplacementNamed(context, '/login');
  }

  Future<void> _navigateToWebView(Map<String, dynamic> menuItem) async {
    final sessionData = await _getSessionData();

    String urlWithSession = menuItem['url'];
    if (sessionData.isNotEmpty) {
      final uri = Uri.parse(urlWithSession);
      final params = Map<String, String>.from(uri.queryParameters);
      params.addAll(sessionData);
      urlWithSession = uri.replace(queryParameters: params).toString();
    }

    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (context) =>
            WebViewScreen(url: urlWithSession, title: menuItem['title']),
      ),
    );
  }

  // START: Build Dashboard Content
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
              style: TextStyle(color: AppColors.primary),
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
            _buildWelcomeSection(),
            const SizedBox(height: 24),
            _buildSalesSummarySection(),
            const SizedBox(height: 24),
            if (_toDouble(_salesSummary['avg_order_value']) > 0)
              _buildAverageOrderCard(),
            const SizedBox(height: 24),
            _buildLastUpdateInfo(),
          ],
        ),
      ),
    );
  }

  Widget _buildWelcomeSection() {
    return Container(
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
        padding: const EdgeInsets.all(24),
        child: Column(
          children: [
            Icon(Icons.dashboard, size: 50, color: AppColors.primary),
            const SizedBox(height: 5),
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
              style: TextStyle(fontSize: 18, color: Colors.grey[600]),
            ),
            const SizedBox(height: 16),
            Text(
              'Today - ${DateTime.now().toString().split(' ')[0]}',
              style: const TextStyle(fontSize: 16, color: Colors.grey),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSalesSummarySection() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          "Today's Sales Summary",
          style: TextStyle(
            fontSize: 22,
            fontWeight: FontWeight.bold,
            color: Colors.black,
          ),
        ),
        const SizedBox(height: 16),
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
      ],
    );
  }

  Widget _buildAverageOrderCard() {
    return Container(
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
    );
  }

  Widget _buildLastUpdateInfo() {
    return Container(
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
            style: TextStyle(fontSize: 12, color: Colors.grey[600]),
          ),
        ],
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
                  style: TextStyle(fontSize: 12, color: Colors.grey[600]),
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
    return ListView.builder(
      itemCount: _moreMenuItems.length,
      itemBuilder: (context, index) {
        final menuItem = _moreMenuItems[index];

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
              child: Icon(menuItem['icon'], size: 20, color: primaryColor),
            ),
            title: Text(
              menuItem['title'],
              style: const TextStyle(fontWeight: FontWeight.w500),
            ),
            trailing: Icon(
              Icons.arrow_forward_ios,
              size: 16,
              color: primaryColor,
            ),
            onTap: () => _navigateToWebView(menuItem),
          ),
        );
      },
    );
  }

  double _toDouble(dynamic value) {
    if (value == null) return 0.0;
    if (value is double) return value;
    if (value is int) return value.toDouble();
    if (value is String) return double.tryParse(value) ?? 0.0;
    return 0.0;
  }

  String _formatNumber(dynamic number) {
    if (number == null) return '0';
    final numValue = _toDouble(number);
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
        title: Text(_appBarTitles[_currentIndex]),
        elevation: 0,
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
          BottomNavigationBarItem(icon: Icon(Icons.menu), label: 'Menu'),
          BottomNavigationBarItem(icon: Icon(Icons.logout), label: 'Logout'),
        ],
      ),
    );
  }
}
