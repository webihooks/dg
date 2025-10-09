import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'screens/native/login_screen.dart';
import 'screens/native/dashboard_screen.dart';
import 'api/services/api_service.dart';
import 'services/enhanced_service.dart'; // Updated import

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  _initializeApp();
}

Future<void> _initializeApp() async {
  // Initialize Enhanced service
  await EnhancedService.initialize();
  
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
        fontFamily: 'Roboto',
      ),
      home: FutureBuilder<bool>(
        future: _checkLoginStatus(),
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Scaffold(
              backgroundColor: Colors.white,
              body: Center(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    CircularProgressIndicator(
                      valueColor: AlwaysStoppedAnimation<Color>(Color(0xffff6c2f)),
                    ),
                    SizedBox(height: 20),
                    Text(
                      'Loading...',
                      style: TextStyle(
                        color: Color(0xffff6c2f),
                        fontSize: 16,
                      ),
                    ),
                  ],
                ),
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
      debugShowCheckedModeBanner: false,
    );
  }

  Future<bool> _checkLoginStatus() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      return prefs.getBool('isLoggedIn') ?? false;
    } catch (e) {
      return false;
    }
  }
}