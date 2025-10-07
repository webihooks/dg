import 'user_model.dart'; // Make sure this import is correct

class LoginResponse {
  final User? user;
  final bool success;
  final String message;
  final String? redirectUrl;

  LoginResponse({
    required this.success,
    required this.message,
    this.user,
    this.redirectUrl,
  });

  factory LoginResponse.fromJson(Map<String, dynamic> json) {
    return LoginResponse(
      success: json['success'] ?? false,
      message: json['message'] ?? '',
      user: json['user'] != null ? User.fromJson(json['user']) : null,
      redirectUrl: json['redirect_url'],
    );
  }
}