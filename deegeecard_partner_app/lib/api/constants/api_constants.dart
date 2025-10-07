class ApiConstants {
  static const String baseUrl = 'https://dgcard.online'; // Replace with your actual domain
  static const String loginEndpoint = '/flutter_api/login.php';
  static const String dashboardEndpoint = '/flutter_api/dashboard.php';
  static const String logoutEndpoint = '/flutter_api/logout.php';
  
  // Headers
  static const Map<String, String> headers = {
    'Content-Type': 'application/x-www-form-urlencoded',
    'Accept': 'application/json',
  };
}