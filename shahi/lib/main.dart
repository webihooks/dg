import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:webview_flutter/webview_flutter.dart';
import 'package:url_launcher/url_launcher.dart';
import 'dart:convert';
import 'package:http/http.dart' as http;

void main() {
  runApp(const MyApp());
}

class MyApp extends StatelessWidget {
  const MyApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Biryani By Bulk',
      theme: ThemeData(primarySwatch: Colors.blue),
      home: const WebViewScreen(),
    );
  }
}

class WebViewScreen extends StatefulWidget {
  const WebViewScreen({super.key});

  @override
  State<WebViewScreen> createState() => _WebViewScreenState();
}

class _WebViewScreenState extends State<WebViewScreen> {
  late final WebViewController _controller;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setNavigationDelegate(
        NavigationDelegate(
          onProgress: (int progress) {
            debugPrint('Loading: $progress%');
          },
          onPageStarted: (String url) {
            setState(() => _isLoading = true);
            debugPrint('Page started loading: $url');
          },
          onPageFinished: (String url) {
            setState(() => _isLoading = false);
            debugPrint('Page finished loading: $url');
          },
          onNavigationRequest: (NavigationRequest request) {
            final url = request.url.toLowerCase();

            // Handle external apps
            if (_shouldLaunchInExternalApp(url)) {
              _launchExternalApp(
                request.url,
              ); // Use original URL (not lowercased)
              return NavigationDecision.prevent;
            }

            // Allow all other navigation within WebView
            return NavigationDecision.navigate;
          },
        ),
      )
      ..loadRequest(Uri.parse('https://biryanibybulk.com'));
  }

  bool _shouldLaunchInExternalApp(String url) {
    return url.startsWith('https://wa.me/') ||
        url.startsWith('whatsapp://') ||
        url.startsWith('tel:') ||
        url.startsWith('mailto:') ||
        url.startsWith('https://maps.google.com') ||
        url.startsWith('https://goo.gl/maps/') ||
        url.startsWith('https://maps.app.goo.gl/') ||
        url.startsWith('comgooglemaps://') ||
        url.startsWith('https://www.facebook.com/') ||
        url.startsWith('fb://') ||
        url.startsWith('https://www.instagram.com/') ||
        url.startsWith('instagram://') ||
        url.startsWith('https://twitter.com/') ||
        url.startsWith('twitter://') ||
        url.startsWith('https://www.youtube.com/') ||
        url.startsWith('youtube://') ||
        url.startsWith('vnd.youtube://') ||
        url.startsWith('https://x.com/') ||
        url.startsWith('x://');
  }

  Future<String?> _resolveGoogleMapsUrl(String shortUrl) async {
    try {
      final response = await http.get(
        Uri.parse(shortUrl),
        headers: {
          'User-Agent':
              'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0 Mobile/15E148 Safari/604.1',
        },
      );

      if (response.statusCode == 200) {
        // Extract the final URL from the response
        final finalUrl = response.request?.url.toString();
        debugPrint('Resolved Google Maps URL: $finalUrl');
        return finalUrl;
      }
    } catch (e) {
      debugPrint('Error resolving Google Maps URL: $e');
    }
    return null;
  }

  Future<void> _launchExternalApp(String url) async {
    try {
      // Special handling for Google Maps short links
      if (url.toLowerCase().startsWith('https://maps.app.goo.gl/') ||
          url.toLowerCase().startsWith('https://goo.gl/maps/')) {
        // First try to resolve the short URL to get the final destination
        final resolvedUrl = await _resolveGoogleMapsUrl(url);

        if (resolvedUrl != null) {
          // Try to open in Google Maps app using the resolved URL
          final mapsUri = Uri.parse(
            'comgooglemaps://?url=${Uri.encodeComponent(resolvedUrl)}',
          );
          if (await canLaunchUrl(mapsUri)) {
            await launchUrl(mapsUri, mode: LaunchMode.externalApplication);
            return;
          }

          // If Google Maps app is not available, try to open the resolved URL in browser
          if (await canLaunchUrl(Uri.parse(resolvedUrl))) {
            await launchUrl(
              Uri.parse(resolvedUrl),
              mode: LaunchMode.externalApplication,
            );
            return;
          }
        }

        // Fallback: open original short URL in browser
        if (await canLaunchUrl(Uri.parse(url))) {
          await launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
          return;
        }
      }

      // Handle other URLs normally
      final uri = Uri.parse(url);
      if (await canLaunchUrl(uri)) {
        await launchUrl(uri, mode: LaunchMode.externalApplication);
      } else {
        // Fallback: open in WebView if app not available
        _controller.loadRequest(uri);
      }
    } catch (e) {
      debugPrint('Error launching URL: $e');
      // Fallback to WebView
      _controller.loadRequest(Uri.parse(url));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      extendBodyBehindAppBar: true,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        toolbarHeight: 0,
        systemOverlayStyle: SystemUiOverlayStyle(
          statusBarColor: Colors.black,
          statusBarIconBrightness: Brightness.light,
          statusBarBrightness: Brightness.light,
          systemStatusBarContrastEnforced: true,
        ),
      ),
      body: Stack(
        children: [
          Container(
            margin: EdgeInsets.only(top: MediaQuery.of(context).padding.top),
            child: WebViewWidget(controller: _controller),
          ),
          if (_isLoading)
            const Center(
              child: CircularProgressIndicator(
                valueColor: AlwaysStoppedAnimation<Color>(
                  Colors.black,
                ), // Black loader
              ),
            ),
        ],
      ),
    );
  }
}
