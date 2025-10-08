import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'screens/native/login_screen.dart';
import 'screens/native/dashboard_screen.dart';
import 'api/services/api_service.dart';
import 'services/app_initializer.dart'; // Add this import

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  _initializeApp();
}

Future<void> _initializeApp() async {
  // Initialize app-wide services
  await AppInitializer.initialize();
  
  // Initialize API service and load persistent cookies
  final apiService = ApiService();
  await apiService.initialize();
  
  runApp(const MyApp());
}

class MyApp extends StatelessWidget {
  const MyApp({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'DeeGeeCard Partner',
      theme: ThemeData(
        primarySwatch: Colors.blue,
        visualDensity: VisualDensity.adaptivePlatformDensity,
      ),
      home: FutureBuilder<bool>(
        future: _checkLoginStatus(),
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Scaffold(
              body: Center(
                child: CircularProgressIndicator(),
              ),
            );
          }
          
          final isLoggedIn = snapshot.data ?? false;
          return isLoggedIn ? const DashboardScreen() : const LoginScreen();
        },
      ),
      routes: {
        '/login': (context) => const LoginScreen(),
        '/dashboard': (context) => const DashboardScreen(),
      },
    );
  }

  Future<bool> _checkLoginStatus() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getBool('isLoggedIn') ?? false;
  }
}