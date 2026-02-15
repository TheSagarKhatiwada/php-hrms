import 'api_client.dart';

class AuditService {
  AuditService(this._client);

  final ApiClient _client;

  Future<Map<String, dynamic>> fetchAudit({required String token}) {
    return _client.getJson('/attendance/audit.php', token: token);
  }
}
