import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../../constants/colors.dart';

class WebViewScreen extends StatefulWidget {
  final String url;
  final String title;

  const WebViewScreen({Key? key, required this.url, required this.title})
    : super(key: key);

  @override
  _WebViewScreenState createState() => _WebViewScreenState();
}

class _WebViewScreenState extends State<WebViewScreen> {
  late final WebViewController _controller;
  bool _isLoading = true;
  int _currentIndex = 2; // Default to Menu tab

  @override
  void initState() {
    super.initState();
    _initializeWebView();
  }

  // START: Initialize WebView
  /// Initializes WebViewController with proper settings and session data
  void _initializeWebView() {
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setBackgroundColor(const Color(0x00000000))
      ..setNavigationDelegate(
        NavigationDelegate(
          onProgress: (int progress) {
            debugPrint('WebView loading: $progress%');
          },
          onPageStarted: (String url) {
            setState(() {
              _isLoading = true;
            });
          },
          onPageFinished: (String url) {
            setState(() {
              _isLoading = false;
            });
          },
          onWebResourceError: (WebResourceError error) {
            debugPrint('''
Page resource error:
  code: ${error.errorCode}
  description: ${error.description}
  errorType: ${error.errorType}
  url: ${error.url}
            ''');
          },
          onNavigationRequest: (NavigationRequest request) {
            // Allow all navigation
            return NavigationDecision.navigate;
          },
        ),
      )
      ..loadRequest(Uri.parse(widget.url));
  }
  // END: Initialize WebView

  // START: Get Screen Icon
  /// Returns appropriate icon based on screen title
  IconData _getScreenIcon() {
    switch (widget.title.toLowerCase()) {
      case 'store timing':
        return Icons.access_time;
      case 'store on/off':
        return Icons.power_settings_new;
      case 'delivery charges':
        return Icons.local_shipping;
      case 'gst':
        return Icons.account_balance;
      case 'discount':
        return Icons.discount;
      case 'coupon code':
        return Icons.local_offer;
      case 'products':
        return Icons.shopping_bag;
      case 'tags':
        return Icons.tag;
      case 'photo gallery':
        return Icons.photo_library;
      default:
        return Icons.web;
    }
  }
  // END: Get Screen Icon

  // START: User Logout
  /// Handles user logout by clearing session data and navigating to login screen
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
  // END: User Logout

  // START: Handle Navigation
  /// Handles bottom navigation bar item taps
  void _handleNavigation(int index) {
    switch (index) {
      case 0: // Dashboard
        Navigator.pushReplacementNamed(context, '/dashboard');
        break;
      case 1: // Orders
        Navigator.pushReplacementNamed(context, '/orders');
        break;
      case 2: // Menu - Stay on current WebView
        setState(() {
          _currentIndex = index;
        });
        break;
      case 3: // Logout
        _logout();
        break;
    }
  }
  // END: Handle Navigation

  // START: Build WebView Content
  /// Constructs the WebView content with loading indicator
  Widget _buildWebViewContent() {
    return Stack(
      children: [
        WebViewWidget(controller: _controller),
        if (_isLoading)
          Center(
            child: Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(12),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withOpacity(0.1),
                    blurRadius: 8,
                    offset: const Offset(0, 2),
                  ),
                ],
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  CircularProgressIndicator(
                    valueColor: AlwaysStoppedAnimation<Color>(
                      const Color(0xffff6c2f),
                    ),
                  ),
                  const SizedBox(height: 12),
                  Text(
                    'Loading ${widget.title}...',
                    style: const TextStyle(
                      color: Color(0xff333333),
                      fontSize: 14,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ],
              ),
            ),
          ),
      ],
    );
  }
  // END: Build WebView Content

  // START: Build Enhanced Custom App Bar
  /// Creates a completely custom app bar with gradient and more styling options
  Widget _buildEnhancedCustomAppBar() {
    return Container(
      height: kToolbarHeight + MediaQuery.of(context).padding.top,
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            const Color(0xffff6c2f), // Primary orange
            const Color(0xffff8c42), // Lighter orange
          ],
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.15),
            blurRadius: 6,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Column(
        children: [
          // Status bar spacer
          SizedBox(height: MediaQuery.of(context).padding.top),
          // App bar content
          Expanded(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 12.0),
              child: Row(
                children: [
                  // Back button with nice styling
                  Container(
                    decoration: BoxDecoration(
                      color: Colors.white.withOpacity(0.15),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: IconButton(
                      onPressed: () {
                        Navigator.pop(context);
                      },
                      icon: const Icon(
                        Icons.arrow_back_ios_new_rounded,
                        color: Colors.white,
                        size: 20,
                      ),
                      tooltip: 'Back',
                      padding: const EdgeInsets.all(8),
                    ),
                  ),

                  const SizedBox(width: 12),

                  // Icon with background
                  Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: Colors.white.withOpacity(0.2),
                      shape: BoxShape.circle,
                    ),
                    child: Icon(
                      _getScreenIcon(),
                      color: Colors.white,
                      size: 20,
                    ),
                  ),

                  const SizedBox(width: 12),

                  // Title with better typography
                  Expanded(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          widget.title,
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 18,
                            fontWeight: FontWeight.w700,
                            letterSpacing: 0.5,
                          ),
                          overflow: TextOverflow.ellipsis,
                          maxLines: 1,
                        ),
                        const SizedBox(height: 2),
                        Text(
                          'DeeGeeCard Partner',
                          style: TextStyle(
                            color: Colors.white.withOpacity(0.8),
                            fontSize: 10,
                            fontWeight: FontWeight.w400,
                          ),
                          overflow: TextOverflow.ellipsis,
                        ),
                      ],
                    ),
                  ),

                  const SizedBox(width: 8),

                  // Reload button with styling
                  Container(
                    decoration: BoxDecoration(
                      color: Colors.white.withOpacity(0.15),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: IconButton(
                      onPressed: () {
                        _controller.reload();
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(
                            content: Text('Refreshing ${widget.title}...'),
                            backgroundColor: const Color(0xffff6c2f),
                            duration: const Duration(seconds: 1),
                          ),
                        );
                      },
                      icon: const Icon(
                        Icons.refresh_rounded,
                        color: Colors.white,
                        size: 20,
                      ),
                      tooltip: 'Reload Page',
                      padding: const EdgeInsets.all(8),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
  // END: Build Enhanced Custom App Bar

  // START: Build Custom Bottom Navigation Bar
  /// Creates a custom bottom navigation bar with enhanced styling
  Widget _buildCustomBottomNavigationBar() {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.1),
            blurRadius: 8,
            offset: const Offset(0, -2),
          ),
        ],
        border: Border(
          top: BorderSide(color: Colors.grey.shade300, width: 0.5),
        ),
      ),
      child: SafeArea(
        child: Container(
          height: kBottomNavigationBarHeight,
          padding: const EdgeInsets.symmetric(horizontal: 8),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceAround,
            children: [
              // Dashboard Tab
              _buildBottomNavItem(
                icon: Icons.dashboard_rounded,
                label: 'Dashboard',
                index: 0,
                isActive: _currentIndex == 0,
              ),

              // Orders Tab
              _buildBottomNavItem(
                icon: Icons.shopping_cart_rounded,
                label: 'Orders',
                index: 1,
                isActive: _currentIndex == 1,
              ),

              // Menu Tab
              _buildBottomNavItem(
                icon: Icons.menu_rounded,
                label: 'Menu',
                index: 2,
                isActive: _currentIndex == 2,
              ),

              // Logout Tab
              _buildBottomNavItem(
                icon: Icons.logout_rounded,
                label: 'Logout',
                index: 3,
                isActive: _currentIndex == 3,
              ),
            ],
          ),
        ),
      ),
    );
  }
  // END: Build Custom Bottom Navigation Bar

  // START: Build Bottom Navigation Item
  /// Creates individual bottom navigation item with enhanced styling
  Widget _buildBottomNavItem({
    required IconData icon,
    required String label,
    required int index,
    required bool isActive,
  }) {
    return Expanded(
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: () => _handleNavigation(index),
          borderRadius: BorderRadius.circular(12),
          child: Container(
            padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 4),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                // Icon with active state styling
                Container(
                  padding: const EdgeInsets.all(6),
                  decoration: BoxDecoration(
                    color: isActive
                        ? const Color(0xffff6c2f).withOpacity(0.15)
                        : Colors.transparent,
                    shape: BoxShape.circle,
                  ),
                  child: Icon(
                    icon,
                    size: 22,
                    color: isActive
                        ? const Color(0xffff6c2f)
                        : Colors.grey.shade600,
                  ),
                ),

                const SizedBox(height: 4),

                // Label with active state styling
                Text(
                  label,
                  style: TextStyle(
                    fontSize: 10,
                    fontWeight: isActive ? FontWeight.w600 : FontWeight.w500,
                    color: isActive
                        ? const Color(0xffff6c2f)
                        : Colors.grey.shade600,
                    letterSpacing: isActive ? 0.2 : 0.1,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
  // END: Build Bottom Navigation Item

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      // Remove the default AppBar and use our custom one
      appBar: null,
      body: Column(
        children: [
          // Enhanced Custom App Bar
          _buildEnhancedCustomAppBar(),

          // WebView Content
          Expanded(child: _buildWebViewContent()),
        ],
      ),
      // Custom Bottom Navigation Bar
      bottomNavigationBar: _buildCustomBottomNavigationBar(),
    );
  }
}
