import 'dart:io';
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:image_picker/image_picker.dart';

class WebViewScreen extends StatefulWidget {
  final String url;
  final String title;

  const WebViewScreen({Key? key, required this.url, required this.title})
    : super(key: key);

  @override
  State<WebViewScreen> createState() => _WebViewScreenState();
}

class _WebViewScreenState extends State<WebViewScreen> {
  late final WebViewController _controller;
  bool _isLoading = true;
  String _currentTitle = '';
  double _progress = 0;

  final ImagePicker _imagePicker = ImagePicker();

  @override
  void initState() {
    super.initState();
    _currentTitle = widget.title;
    _initializeWebView();
    _requestPermissions();
  }

  void _initializeWebView() {
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setBackgroundColor(const Color(0x00000000))
      ..setNavigationDelegate(
        NavigationDelegate(
          onProgress: (int progress) {
            setState(() {
              _progress = progress / 100;
            });
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
            _injectJavaScriptBridge();
          },
          onWebResourceError: (WebResourceError error) {
            debugPrint('WebView Error: ${error.description}');
          },
          onNavigationRequest: (NavigationRequest request) {
            return _handleNavigation(request);
          },
          onUrlChange: (UrlChange change) {
            if (change.url != null) {
              _updatePageTitle();
            }
          },
        ),
      )
      ..addJavaScriptChannel(
        'FlutterBridge',
        onMessageReceived: _handleJavaScriptMessage,
      )
      ..loadRequest(Uri.parse(widget.url));
  }

  NavigationDecision _handleNavigation(NavigationRequest request) {
    final url = request.url;
    debugPrint('Navigation request: $url');

    // Handle ALL external URLs - this is the key fix
    if (_shouldLaunchInExternalApp(url)) {
      debugPrint('Opening externally: $url');
      _launchExternalUrl(url);
      return NavigationDecision.prevent;
    }

    // Allow internal navigation for your domain
    debugPrint('Internal navigation: $url');
    return NavigationDecision.navigate;
  }

  bool _shouldLaunchInExternalApp(String url) {
    final lowerUrl = url.toLowerCase();

    // Open these in external apps :cite[1]
    return lowerUrl.startsWith('https://wa.me/') ||
        lowerUrl.startsWith('whatsapp://') ||
        lowerUrl.startsWith('tel:') ||
        lowerUrl.startsWith('mailto:') ||
        lowerUrl.startsWith('sms:') ||
        lowerUrl.startsWith('market://') ||
        // File downloads
        lowerUrl.endsWith('.pdf') ||
        lowerUrl.endsWith('.csv') ||
        lowerUrl.endsWith('.xlsx') ||
        // External domains (not your main domain)
        (Uri.parse(url).host.isNotEmpty &&
            !Uri.parse(url).host.contains('dgcard.online')) ||
        // Specific external domains
        lowerUrl.contains('thedhamaalcafe') ||
        lowerUrl.contains('facebook.com') ||
        lowerUrl.contains('instagram.com') ||
        lowerUrl.contains('twitter.com') ||
        lowerUrl.contains('youtube.com') ||
        lowerUrl.contains('maps.google.com');
  }

  Future<void> _launchExternalUrl(String url) async {
    try {
      debugPrint('Attempting to launch: $url');

      // Ensure URL has proper protocol :cite[7]
      if (!url.startsWith('http') &&
          !url.startsWith('tel:') &&
          !url.startsWith('mailto:') &&
          !url.startsWith('whatsapp:')) {
        url = 'https://$url';
      }

      final uri = Uri.parse(url);

      // Special handling for WhatsApp
      if (url.toLowerCase().contains('wa.me') ||
          url.toLowerCase().contains('whatsapp://')) {
        final whatsappUri = Uri.parse(
          url.replaceAll('https://', 'whatsapp://'),
        );
        if (await canLaunchUrl(whatsappUri)) {
          await launchUrl(whatsappUri, mode: LaunchMode.externalApplication);
          return;
        }
      }

      // Force external browser for everything else
      if (await canLaunchUrl(uri)) {
        await launchUrl(
          uri,
          mode: LaunchMode.externalApplication, // This forces external browser
        );
      } else {
        _showSnackBar('Cannot open this link');
      }
    } catch (e) {
      debugPrint('Error launching URL: $e');
      _showSnackBar('Error opening link');
    }
  }

  Future<void> _requestPermissions() async {
    await [
      Permission.camera,
      Permission.storage,
      Permission.microphone,
    ].request();
  }

  Future<void> _updatePageTitle() async {
    try {
      final title = await _controller.getTitle();
      if (title != null && title.isNotEmpty && mounted) {
        setState(() {
          _currentTitle = title;
        });
      }
    } catch (e) {
      // Keep current title if update fails
    }
  }

  void _handleJavaScriptMessage(JavaScriptMessage message) {
    debugPrint('JS Message: ${message.message}');

    try {
      final data = jsonDecode(message.message);
      final action = data['action'];
      final url = data['url'];

      switch (action) {
        case 'fileUploadRequested':
          _handleFileUpload();
          break;
        case 'externalLinkClicked':
          _launchExternalUrl(url);
          break;
      }
    } catch (e) {
      if (message.message == 'fileUploadRequested') {
        _handleFileUpload();
      }
    }
  }

  Future<void> _injectJavaScriptBridge() async {
    const script = '''
      // Enhanced external link handling
      document.addEventListener('click', function(e) {
        try {
          let target = e.target;
          
          // Find the closest <a> tag
          while (target && target.tagName !== 'A') {
            target = target.parentElement;
            if (!target) break;
          }
          
          if (target && target.href) {
            const href = target.href;
            const lowerHref = href.toLowerCase();
            
            // Check if this should open externally
            const shouldOpenExternally = 
              lowerHref.startsWith('https://wa.me/') ||
              lowerHref.startsWith('whatsapp://') ||
              lowerHref.startsWith('tel:') ||
              lowerHref.startsWith('mailto:') ||
              lowerHref.startsWith('sms:') ||
              lowerHref.startsWith('market://') ||
              lowerHref.endsWith('.pdf') ||
              lowerHref.endsWith('.csv') ||
              lowerHref.includes('thedhamaalcafe') ||
              (href.includes('://') && !href.includes('dgcard.online'));
            
            if (shouldOpenExternally) {
              e.preventDefault();
              e.stopImmediatePropagation();
              
              FlutterBridge.postMessage(JSON.stringify({
                action: 'externalLinkClicked',
                url: href
              }));
              return false;
            }
          }
        } catch (error) {
          console.log('JavaScript error: ' + error);
        }
      });

      // File upload handling
      document.addEventListener('click', function(e) {
        const target = e.target;
        if (target.type === 'file' || 
            (target.tagName === 'INPUT' && target.getAttribute('type') === 'file')) {
          e.preventDefault();
          e.stopPropagation();
          FlutterBridge.postMessage('fileUploadRequested');
        }
      });

      console.log('WebView enhanced functionality activated');
    ''';

    try {
      await _controller.runJavaScript(script);
    } catch (e) {
      debugPrint('JavaScript injection error: $e');
    }
  }

  // File upload methods (keep your existing implementation)
  Future<void> _handleFileUpload() async {
    // Your existing file upload implementation
  }

  Future<void> _pickImage(ImageSource source) async {
    // Your existing image picker implementation
  }

  Future<void> _processSelectedImage(XFile image) async {
    // Your existing image processing implementation
  }

  @override
  Widget build(BuildContext context) {
    return WillPopScope(
      onWillPop: () async {
        // Always go back to dashboard
        Navigator.of(context).pop();
        return false;
      },
      child: Scaffold(
        appBar: AppBar(
          title: Text(
            _currentTitle,
            style: const TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w600,
              color: Colors.white,
            ),
          ),
          backgroundColor: Colors.orange,
          leading: IconButton(
            icon: const Icon(Icons.arrow_back, color: Colors.white),
            onPressed: () {
              // Always navigate to dashboard
              Navigator.of(context).pop();
            },
          ),
          actions: [
            if (_isLoading)
              const CircularProgressIndicator(
                valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
              ),
            IconButton(
              icon: const Icon(Icons.refresh, color: Colors.white),
              onPressed: () => _controller.reload(),
            ),
          ],
        ),
        body: Column(
          children: [
            if (_isLoading && _progress < 1.0)
              LinearProgressIndicator(
                value: _progress,
                backgroundColor: Colors.grey.shade200,
                valueColor: const AlwaysStoppedAnimation<Color>(Colors.orange),
              ),
            Expanded(child: WebViewWidget(controller: _controller)),
          ],
        ),
      ),
    );
  }

  void _showSnackBar(String message) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: Colors.orange,
        duration: const Duration(seconds: 3),
      ),
    );
  }
}
