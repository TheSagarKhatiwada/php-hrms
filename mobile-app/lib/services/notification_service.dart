import '../models/notification_item.dart';

import 'api_client.dart';

class NotificationService {
  NotificationService(this._client);

  final ApiClient _client;

  Future<List<NotificationItem>> fetchNotifications({
    required String token,
    int limit = 20,
  }) async {
    final data = await _client.getJson('/notifications.php',
        token: token, query: {'limit': limit.toString()});
    final rows = data['notifications'] as List<dynamic>? ?? [];
    return rows
        .whereType<Map<String, dynamic>>()
        .map(NotificationItem.fromJson)
        .toList();
  }

  Future<void> markRead({
    required String token,
    required int id,
  }) async {
    await _client.postJson('/notifications.php', token: token, body: {
      'action': 'mark_read',
      'notification_id': id,
    });
  }

  Future<void> markAllRead({
    required String token,
  }) async {
    await _client.postJson('/notifications.php', token: token, body: {
      'action': 'mark_all_read',
    });
  }

  Future<void> delete({
    required String token,
    required int id,
  }) async {
    await _client.postJson('/notifications.php', token: token, body: {
      'action': 'delete',
      'notification_id': id,
    });
  }

  Future<void> registerDeviceToken({
    required String token,
    required String deviceToken,
    required String platform,
    String? deviceId,
  }) async {
    await _client.postJson('/device-token.php', token: token, body: {
      'token': deviceToken,
      'platform': platform,
      'device_id': deviceId,
    });
  }
}
