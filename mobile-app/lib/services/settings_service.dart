import 'api_client.dart';

class SettingsService {
  SettingsService(this._client);

  final ApiClient _client;

  Future<Map<String, dynamic>> fetchSettings() async {
    return _client.getJson('/settings.php');
  }
}
