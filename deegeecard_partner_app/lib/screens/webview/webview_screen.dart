import 'dart:io';
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';
import 'package:webview_flutter_android/webview_flutter_android.dart';
import 'package:webview_flutter_wkwebview/webview_flutter_wkwebview.dart';
import 'package:shared_preferences/shared_preferences.dart';
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
  /// ================================
  /// STATE VARIABLES
  /// ================================
  late final WebViewController _controller;
  bool _isLoading = true;
  double _progress = 0;
  bool _canGoBack = false;
  bool _canGoForward = false;

  final ImagePicker _imagePicker = ImagePicker();
  List<XFile> _selectedFiles = [];

  /// ================================
  /// CONSTANTS & CONFIGURATION
  /// ================================
  static const _primaryColor = Color(0xffff6c2f);
  static const _secondaryColor = Color(0xffff8c42);

  // Enhanced external domains list
  final List<String> _externalDomains = [
    'whatsapp.com',
    'api.whatsapp.com',
    'wa.me',
    'facebook.com',
    'fb.com',
    'instagram.com',
    'twitter.com',
    't.me',
    'telegram.me',
    'linkedin.com',
    'youtube.com',
    'maps.google.com',
    'goo.gl/maps',
    'mailto:',
    'tel:',
    'sms:',
    'market://',
    'play.google.com',
    'thedhamaalcafe',
  ];

  final List<String> _downloadExtensions = [
    '.pdf',
    '.doc',
    '.docx',
    '.xls',
    '.xlsx',
    '.zip',
    '.rar',
    '.mp4',
    '.mp3',
  ];

  /// ================================
  /// LIFECYCLE METHODS
  /// ================================
  @override
  void initState() {
    super.initState();
    _initializeWebView();
    _requestPermissions();
  }

  @override
  void dispose() {
    _controller.clearCache();
    super.dispose();
  }

  /// ================================
  /// WEBVIEW INITIALIZATION
  /// ================================
  void _initializeWebView() {
    late final PlatformWebViewControllerCreationParams params;

    if (WebViewPlatform.instance is WebKitWebViewPlatform) {
      params = WebKitWebViewControllerCreationParams(
        allowsInlineMediaPlayback: true,
        mediaTypesRequiringUserAction: const <PlaybackMediaTypes>{},
      );
    } else {
      params = const PlatformWebViewControllerCreationParams();
    }

    final WebViewController controller =
        WebViewController.fromPlatformCreationParams(params);

    // Core configuration
    controller
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setBackgroundColor(const Color(0x00000000))
      ..setNavigationDelegate(_createNavigationDelegate())
      ..addJavaScriptChannel(
        'FlutterBridge',
        onMessageReceived: _handleJavaScriptMessage,
      )
      ..loadRequest(Uri.parse(widget.url));

    // Platform-specific configuration
    _configurePlatformSpecifics(controller);

    _controller = controller;
  }

  NavigationDelegate _createNavigationDelegate() {
    return NavigationDelegate(
      onProgress: (int progress) {
        setState(() => _progress = progress / 100);
      },
      onPageStarted: (String url) {
        setState(() {
          _isLoading = true;
          _progress = 0;
        });
      },
      onPageFinished: (String url) {
        setState(() {
          _isLoading = false;
          _progress = 1.0;
        });
        _updateNavigationState();
        _injectEnhancementScript();
      },
      onWebResourceError: (WebResourceError error) {
        _logError('WebView Error', '''
        Code: ${error.errorCode}
        Description: ${error.description}
        Type: ${error.errorType}
        URL: ${error.url}
        ''');
      },
      onNavigationRequest: (NavigationRequest request) {
        return _handleNavigationRequest(request);
      },
      onUrlChange: (UrlChange change) {
        _logInfo('URL changed to: ${change.url}');
      },
    );
  }

  void _configurePlatformSpecifics(WebViewController controller) {
    // Android configuration
    if (controller.platform is AndroidWebViewController) {
      AndroidWebViewController.enableDebugging(true);
      final androidController = controller.platform as AndroidWebViewController;
      androidController.setMediaPlaybackRequiresUserGesture(false);
      androidController.setOnPlatformPermissionRequest((request) {
        _logInfo('Permission requested: ${request.types}');
        request.grant();
      });
    }

    // iOS configuration
    if (controller.platform is WebKitWebViewController) {
      final webKitController = controller.platform as WebKitWebViewController;
      webKitController.setAllowsBackForwardNavigationGestures(true);
      webKitController.setAllowsLinkPreview(true);
    }
  }

  /// ================================
  /// NAVIGATION HANDLING - ENHANCED
  /// ================================
  NavigationDecision _handleNavigationRequest(NavigationRequest request) {
    final url = request.url.toLowerCase();

    _logInfo('Navigation request: $url');

    // Enhanced external URL detection
    if (_shouldOpenExternally(url)) {
      _launchExternalUrl(request.url);
      return NavigationDecision.prevent;
    }

    // Allow navigation for our main domain (except specific external paths)
    if (_isInternalUrl(url)) {
      return NavigationDecision.navigate;
    }

    // Prevent other external domains from loading in WebView
    _launchExternalUrl(request.url);
    return NavigationDecision.prevent;
  }

  bool _shouldOpenExternally(String url) {
    // Check for specific paths that should always open externally
    if (url.contains('thedhamaalcafe')) {
      _logInfo('Opening thedhamaalcafe externally');
      return true;
    }

    // Check WhatsApp API links
    if (url.contains('api.whatsapp.com')) {
      _logInfo('Opening WhatsApp API link externally');
      return true;
    }

    // Check external domains
    for (final domain in _externalDomains) {
      if (url.contains(domain)) {
        _logInfo('Opening externally - domain match: $domain');
        return true;
      }
    }

    // Check download extensions
    for (final extension in _downloadExtensions) {
      if (url.endsWith(extension)) {
        _logInfo('Opening externally - download: $extension');
        return true;
      }
    }

    return false;
  }

  bool _isInternalUrl(String url) {
    // Allow dgcard.online domain except specific external paths
    if (url.contains('dgcard.online')) {
      // Block specific paths that should open externally
      if (url.contains('thedhamaalcafe')) {
        return false;
      }
      return true;
    }

    // Allow relative URLs and data URLs
    if (url.startsWith('javascript:') ||
        url.startsWith('about:') ||
        url.startsWith('data:') ||
        Uri.parse(url).host.isEmpty) {
      return true;
    }

    return false;
  }

  Future<void> _launchExternalUrl(String url) async {
    try {
      final Uri uri = Uri.parse(url);

      _logInfo('Launching external URL: $url');

      // Special handling for WhatsApp
      if (url.contains('api.whatsapp.com') || url.contains('wa.me')) {
        // Try to launch with a more direct approach
        final whatsappUri = Uri.parse(
          url.replaceAll('https://', 'whatsapp://'),
        );
        try {
          if (await canLaunchUrl(whatsappUri)) {
            await launchUrl(whatsappUri);
            _logInfo('Opened in WhatsApp directly');
            return;
          }
        } catch (e) {
          _logError('WhatsApp direct launch failed', e.toString());
        }
      }

      // Enhanced URL launching with multiple fallbacks
      bool launched = false;

      // Try with external application mode first
      if (await canLaunchUrl(uri)) {
        await launchUrl(uri, mode: LaunchMode.externalApplication);
        launched = true;
        _logInfo('Opened with externalApplication mode: $url');
      }
      // If that fails, try with platform default mode
      else if (await canLaunchUrl(uri)) {
        await launchUrl(uri, mode: LaunchMode.platformDefault);
        launched = true;
        _logInfo('Opened with platformDefault mode: $url');
      }
      // If that fails, try inAppWebView mode as last resort
      else if (await canLaunchUrl(uri)) {
        await launchUrl(uri, mode: LaunchMode.inAppWebView);
        launched = true;
        _logInfo('Opened with inAppWebView mode: $url');
      }

      if (!launched) {
        _logError('All launch methods failed for URL', url);
        _showSnackBar('Cannot open this link. Please try again.');

        // Final fallback
        try {
          if (await canLaunchUrl(uri)) {
            await launchUrl(uri);
            _logInfo('Opened with default mode: $url');
          }
        } catch (e) {
          _logError('Final fallback also failed', e.toString());
        }
      }
    } catch (e) {
      _logError('URL launch error', e.toString());
      _showSnackBar('Error opening link');
    }
  }

  Future<void> _updateNavigationState() async {
    final canGoBack = await _controller.canGoBack();
    final canGoForward = await _controller.canGoForward();

    if (mounted) {
      setState(() {
        _canGoBack = canGoBack;
        _canGoForward = canGoForward;
      });
    }
  }

  /// ================================
  /// BACK BUTTON HANDLING
  /// ================================
  Future<bool> _onWillPop() async {
    if (await _controller.canGoBack()) {
      _controller.goBack();
      return false;
    }
    return true;
  }

  /// ================================
  /// FILE UPLOAD HANDLING
  /// ================================
  Future<void> _handleFileUpload() async {
    final result = await showModalBottomSheet<FileUploadOption>(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (context) => _buildFileUploadOptions(),
    );

    if (result != null) {
      switch (result) {
        case FileUploadOption.gallery:
          await _pickImageFromGallery();
          break;
        case FileUploadOption.camera:
          await _pickImageFromCamera();
          break;
      }
    }
  }

  Widget _buildFileUploadOptions() {
    return Container(
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.only(
          topLeft: Radius.circular(16),
          topRight: Radius.circular(16),
        ),
      ),
      child: SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            _buildOptionTile(
              icon: Icons.photo_library,
              title: 'Choose from Gallery',
              option: FileUploadOption.gallery,
            ),
            _buildOptionTile(
              icon: Icons.photo_camera,
              title: 'Take Photo',
              option: FileUploadOption.camera,
            ),
            const SizedBox(height: 8),
            Container(
              margin: const EdgeInsets.symmetric(horizontal: 16),
              child: ElevatedButton(
                onPressed: () => Navigator.pop(context),
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.grey.shade200,
                  foregroundColor: Colors.grey.shade800,
                  minimumSize: const Size(double.infinity, 50),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
                child: const Text('Cancel'),
              ),
            ),
            const SizedBox(height: 16),
          ],
        ),
      ),
    );
  }

  Widget _buildOptionTile({
    required IconData icon,
    required String title,
    required FileUploadOption option,
  }) {
    return ListTile(
      leading: Icon(icon, color: _primaryColor),
      title: Text(title, style: const TextStyle(fontWeight: FontWeight.w500)),
      onTap: () => Navigator.pop(context, option),
    );
  }

  Future<void> _pickImageFromGallery() async {
    try {
      final XFile? image = await _imagePicker.pickImage(
        source: ImageSource.gallery,
        maxWidth: 1024,
        maxHeight: 1024,
        imageQuality: 80,
      );

      if (image != null && mounted) {
        _selectedFiles = [image];
        _showSnackBar('Image selected');
        await _handleFileSelection(image);
      }
    } catch (e) {
      _showSnackBar('Failed to pick image');
      _logError('Gallery pick error', e.toString());
    }
  }

  Future<void> _pickImageFromCamera() async {
    try {
      final XFile? image = await _imagePicker.pickImage(
        source: ImageSource.camera,
        maxWidth: 1024,
        maxHeight: 1024,
        imageQuality: 80,
      );

      if (image != null && mounted) {
        _selectedFiles = [image];
        _showSnackBar('Photo taken');
        await _handleFileSelection(image);
      }
    } catch (e) {
      _showSnackBar('Failed to take photo');
      _logError('Camera pick error', e.toString());
    }
  }

  Future<void> _handleFileSelection(XFile image) async {
    try {
      final file = File(image.path);
      final bytes = await file.readAsBytes();
      final base64Image = base64Encode(bytes);
      final mimeType = _getMimeType(image.name);

      await _controller.runJavaScript('''
        (function() {
          let fileInput = document.querySelector('input[type="file"]');
          if (!fileInput) {
            console.log('No file input found, creating one');
            fileInput = document.createElement('input');
            fileInput.type = 'file';
            fileInput.style.display = 'none';
            document.body.appendChild(fileInput);
          }

          const byteCharacters = atob('$base64Image');
          const byteNumbers = new Array(byteCharacters.length);
          for (let i = 0; i < byteCharacters.length; i++) {
            byteNumbers[i] = byteCharacters.charCodeAt(i);
          }
          const byteArray = new Uint8Array(byteNumbers);
          const blob = new Blob([byteArray], { type: '$mimeType' });
          
          const file = new File([blob], '${image.name}', { type: '$mimeType' });
          
          const dataTransfer = new DataTransfer();
          dataTransfer.items.add(file);
          
          fileInput.files = dataTransfer.files;
          
          const event = new Event('change', { bubbles: true });
          fileInput.dispatchEvent(event);
          
          console.log('File input triggered with actual image data');
        })();
      ''');

      _logInfo('File selection handled for: ${image.name}');
    } catch (e) {
      _logError('File selection error', e.toString());
      _showSnackBar('Failed to upload image');

      await _controller.runJavaScript('''
        const fileInput = document.querySelector('input[type="file"]');
        if (fileInput) {
          fileInput.click();
        }
      ''');
    }
  }

  String _getMimeType(String fileName) {
    final extension = fileName.toLowerCase().split('.').last;
    switch (extension) {
      case 'jpg':
      case 'jpeg':
        return 'image/jpeg';
      case 'png':
        return 'image/png';
      case 'gif':
        return 'image/gif';
      case 'webp':
        return 'image/webp';
      default:
        return 'image/jpeg';
    }
  }

  /// ================================
  /// JAVASCRIPT COMMUNICATION
  /// ================================
  void _handleJavaScriptMessage(JavaScriptMessage message) {
    final String messageData = message.message;
    _logInfo('JS Message: $messageData');

    try {
      final data = jsonDecode(messageData);
      final action = data['action'];

      switch (action) {
        case 'requestFileUpload':
          _handleFileUpload();
          break;
        case 'requestCameraAccess':
          _handleCameraAccess();
          break;
        case 'requestMicrophoneAccess':
          _handleMicrophoneAccess();
          break;
        case 'fileUploadSuccess':
          _showSnackBar('File uploaded successfully');
          break;
        case 'fileUploadError':
          final error = data['error'] ?? 'Unknown error';
          _showSnackBar('Upload failed: $error');
          break;
        default:
          _logInfo('Unknown JS action: $action');
      }
    } catch (e) {
      switch (messageData) {
        case 'requestFileUpload':
          _handleFileUpload();
          break;
        case 'requestCameraAccess':
          _handleCameraAccess();
          break;
        case 'requestMicrophoneAccess':
          _handleMicrophoneAccess();
          break;
        default:
          _logInfo('Unknown JS message: $messageData');
      }
    }
  }

  Future<void> _injectEnhancementScript() async {
    const enhancementScript = '''
      // Enhanced WebView functionality
      (function() {
        // Enhanced file upload handling
        document.addEventListener('click', function(e) {
          const target = e.target;
          if (target.type === 'file' || 
              (target.tagName === 'INPUT' && target.getAttribute('type') === 'file')) {
            e.preventDefault();
            e.stopPropagation();
            
            FlutterBridge.postMessage(JSON.stringify({
              action: 'requestFileUpload'
            }));
          }
        });

        // Monitor file input changes and report status
        document.addEventListener('change', function(e) {
          if (e.target.type === 'file' && e.target.files.length > 0) {
            const file = e.target.files[0];
            console.log('File selected:', file.name);
            
            setTimeout(() => {
              FlutterBridge.postMessage(JSON.stringify({
                action: 'fileUploadSuccess',
                fileName: file.name,
                fileSize: file.size
              }));
            }, 1000);
          }
        });

        // Enhanced external link handling
        document.addEventListener('click', function(e) {
          let target = e.target;
          while (target && target.tagName !== 'A') {
            target = target.parentElement;
          }
          if (target && target.href) {
            const href = target.href.toLowerCase();
            const externalPatterns = [
              'whatsapp.com', 'api.whatsapp.com', 'facebook.com', 'instagram.com', 
              'twitter.com', 'youtube.com', 'maps.google.com', 'thedhamaalcafe',
              'mailto:', 'tel:', 'sms:'
            ];
            
            for (const pattern of externalPatterns) {
              if (href.includes(pattern)) {
                e.preventDefault();
                e.stopPropagation();
                FlutterBridge.postMessage('Opening external link: ' + target.href);
                window.open(target.href, '_system');
                return;
              }
            }
          }
        });

        // Media autoplay enhancement
        const mediaElements = document.querySelectorAll('video, audio');
        mediaElements.forEach(media => {
          media.setAttribute('playsinline', '');
          media.setAttribute('webkit-playsinline', '');
          media.autoplay = true;
        });

        // Form enhancement
        document.querySelectorAll('form, input').forEach(element => {
          element.setAttribute('autocomplete', 'on');
        });

        console.log('🚀 Enhanced WebView functionality activated');
      })();
    ''';

    try {
      await _controller.runJavaScript(enhancementScript);
      _logInfo('Enhanced script injected successfully');
    } catch (e) {
      _logError('Enhanced script injection failed', e.toString());
    }
  }

  /// ================================
  /// PERMISSION HANDLING
  /// ================================
  Future<void> _requestPermissions() async {
    try {
      final permissions = await [
        Permission.camera,
        Permission.microphone,
        Permission.location,
        Permission.notification,
        Permission.storage,
      ].request();

      _logPermissionsStatus(permissions);
      _logInfo('All permissions requested successfully');
    } catch (e) {
      _logError('Permission request failed', e.toString());
    }
  }

  void _logPermissionsStatus(Map<Permission, PermissionStatus> permissions) {
    permissions.forEach((permission, status) {
      _logInfo('Permission $permission: $status');
    });
  }

  Future<void> _handleCameraAccess() async {
    final status = await Permission.camera.status;
    _controller.runJavaScript('''
      if (window.onCameraAccessResult) {
        window.onCameraAccessResult(${status.isGranted});
      }
    ''');
  }

  Future<void> _handleMicrophoneAccess() async {
    final status = await Permission.microphone.status;
    _controller.runJavaScript('''
      if (window.onMicrophoneAccessResult) {
        window.onMicrophoneAccessResult(${status.isGranted});
      }
    ''');
  }

  /// ================================
  /// UI COMPONENTS - BOTTOM NAVIGATION REMOVED
  /// ================================
  @override
  Widget build(BuildContext context) {
    return WillPopScope(
      onWillPop: _onWillPop,
      child: Scaffold(
        backgroundColor: Colors.white,
        body: SafeArea(
          child: Column(
            children: [
              _buildCustomAppBar(),
              if (_isLoading && _progress < 1.0) _buildProgressIndicator(),
              Expanded(child: _buildWebViewContent()),
            ],
          ),
        ),
        // Bottom navigation bar removed completely
      ),
    );
  }

  Widget _buildCustomAppBar() {
    return Container(
      height: kToolbarHeight + MediaQuery.of(context).padding.top,
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [_primaryColor, _secondaryColor],
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
          SizedBox(height: MediaQuery.of(context).padding.top),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 12),
              child: Row(
                children: [
                  _buildAppBarButton(
                    icon: Icons.arrow_back_ios_new_rounded,
                    onPressed: () async {
                      if (await _controller.canGoBack()) {
                        _controller.goBack();
                      } else {
                        if (mounted) {
                          Navigator.pop(context);
                        }
                      }
                    },
                    tooltip: 'Back',
                  ),
                  const SizedBox(width: 8),
                  _buildAppBarButton(
                    icon: Icons.arrow_forward_ios_rounded,
                    onPressed: _canGoForward
                        ? () => _controller.goForward()
                        : null,
                    tooltip: 'Forward',
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          widget.title,
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 16,
                            fontWeight: FontWeight.w600,
                          ),
                          overflow: TextOverflow.ellipsis,
                          maxLines: 1,
                        ),
                        Text(
                          'DeeGeeCard Partner',
                          style: TextStyle(
                            color: Colors.white.withOpacity(0.8),
                            fontSize: 10,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 8),
                  _buildAppBarButton(
                    icon: Icons.refresh_rounded,
                    onPressed: () {
                      _controller.reload();
                      _showSnackBar('Refreshing...');
                    },
                    tooltip: 'Reload',
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildAppBarButton({
    required IconData icon,
    required VoidCallback? onPressed,
    required String tooltip,
  }) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white.withOpacity(onPressed != null ? 0.15 : 0.08),
        borderRadius: BorderRadius.circular(8),
      ),
      child: IconButton(
        onPressed: onPressed,
        icon: Icon(icon, size: 18, color: Colors.white),
        tooltip: tooltip,
        padding: const EdgeInsets.all(6),
        constraints: const BoxConstraints(minWidth: 36, minHeight: 36),
      ),
    );
  }

  Widget _buildProgressIndicator() {
    return LinearProgressIndicator(
      value: _progress,
      backgroundColor: Colors.grey.shade200,
      valueColor: AlwaysStoppedAnimation<Color>(_primaryColor),
      minHeight: 2,
    );
  }

  Widget _buildWebViewContent() {
    return Stack(
      children: [
        WebViewWidget(controller: _controller),
        if (_isLoading) _buildLoadingOverlay(),
      ],
    );
  }

  Widget _buildLoadingOverlay() {
    return Container(
      color: Colors.white.withOpacity(0.9),
      child: Center(
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
                valueColor: AlwaysStoppedAnimation<Color>(_primaryColor),
              ),
              const SizedBox(height: 12),
              const Text(
                'Loading...',
                style: TextStyle(fontSize: 14, fontWeight: FontWeight.w500),
              ),
              const SizedBox(height: 4),
              Text(
                '${(_progress * 100).toInt()}%',
                style: TextStyle(color: Colors.grey.shade600, fontSize: 12),
              ),
            ],
          ),
        ),
      ),
    );
  }

  /// ================================
  /// LOGOUT FUNCTIONALITY
  /// ================================
  // Note: Since bottom navigation is removed, you might want to add a logout button
  // in the app bar or handle logout through the webview itself

  void _showSnackBar(String message) {
    if (!mounted) return;

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: _primaryColor,
        duration: const Duration(seconds: 2),
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
      ),
    );
  }

  /// ================================
  /// LOGGING UTILITIES
  /// ================================
  void _logInfo(String message) {
    debugPrint('ℹ️ $message');
  }

  void _logError(String title, String details) {
    debugPrint('❌ $title: $details');
  }
}

enum FileUploadOption { gallery, camera }
