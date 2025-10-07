class User {
  final int id;
  final String email;
  final String name;
  final String role;
  final bool isTrial;
  final DateTime? trialEnd;
  final bool hasActiveSubscription;

  User({
    required this.id,
    required this.email,
    required this.name,
    required this.role,
    required this.isTrial,
    this.trialEnd,
    required this.hasActiveSubscription,
  });

  factory User.fromJson(Map<String, dynamic> json) {
    return User(
      id: int.parse(json['id'].toString()),
      email: json['email'] ?? '',
      name: json['name'] ?? '',
      role: json['role'] ?? '',
      isTrial: json['is_trial'] == '1' || json['is_trial'] == true,
      trialEnd: json['trial_end'] != null ? DateTime.tryParse(json['trial_end']) : null,
      hasActiveSubscription: json['has_active_subscription'] == '1' || json['has_active_subscription'] == true,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'email': email,
      'name': name,
      'role': role,
      'is_trial': isTrial,
      'trial_end': trialEnd?.toIso8601String(),
      'has_active_subscription': hasActiveSubscription,
    };
  }
}