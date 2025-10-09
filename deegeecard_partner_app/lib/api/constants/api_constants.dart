class ApiConstants {
  static const String baseUrl = 'https://dgcard.online'; // Replace with your actual domain
  static const String loginEndpoint = '/flutter_api/login.php';
  static const String dashboardEndpoint = '/flutter_api/dashboard.php';
  static const String ordersEndpoint = '/flutter_api/orders.php'; // Add orders endpoint
  static const String newOrdersEndpoint = '/flutter_api/new_orders.php'; // For new orders specifically
  static const String logoutEndpoint = '/flutter_api/logout.php';
  
  // Headers
  static const Map<String, String> headers = {
    'Content-Type': 'application/x-www-form-urlencoded',
    'Accept': 'application/json',
  };
}