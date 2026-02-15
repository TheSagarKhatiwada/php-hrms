import '../models/user_profile.dart';

import 'api_client.dart';

class AuthService {
  AuthService(this._client);

  final ApiClient _client;

  Future<Map<String, dynamic>> login({
    required String loginId,
    required String password,
    double? lat,
    double? lon,
    double? accuracy,
    String? deviceId,
    String? deviceName,
  }) async {
    return _client.postJson('/login.php', body: {
      'login_id': loginId,
      'password': password,
      'lat': lat,
      'lon': lon,
      'accuracy': accuracy,
      'device_id': deviceId,
      'device_name': deviceName,
    });
  }

  Future<void> logout(String token) async {
    await _client.postJson('/logout.php', token: token);
  }

  Future<UserProfile> fetchProfile(String token) async {
    final data = await _client.getJson('/profile.php', token: token);
    final profile = data['employee'] as Map<String, dynamic>;
    return UserProfile.fromJson(profile);
  }
}
