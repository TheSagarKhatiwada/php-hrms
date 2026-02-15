import '../models/attendance_record.dart';

import 'api_client.dart';

class AttendanceService {
  AttendanceService(this._client);

  final ApiClient _client;

  Future<List<AttendanceRecord>> fetchHistory({
    required String token,
    int days = 7,
    String? start,
    String? end,
    int? branchId,
    String? employeeId,
  }) async {
    final query = <String, String>{'days': days.toString()};
    if (start != null && start.isNotEmpty) {
      query['start'] = start;
    }
    if (end != null && end.isNotEmpty) {
      query['end'] = end;
    }
    if (branchId != null) {
      query['branch_id'] = branchId.toString();
    }
    if (employeeId != null && employeeId.isNotEmpty) {
      query['emp_id'] = employeeId;
    }
    final data = await _client.getJson('/attendance/history.php',
        token: token, query: query);
    final rows = data['records'] as List<dynamic>? ?? [];
    return rows
        .whereType<Map<String, dynamic>>()
        .map(AttendanceRecord.fromJson)
        .toList();
  }

  Future<Map<String, dynamic>> fetchFilters({
    required String token,
    int? branchId,
  }) async {
    final query = <String, String>{};
    if (branchId != null) {
      query['branch_id'] = branchId.toString();
    }
    return _client.getJson('/attendance/filters.php',
        token: token, query: query);
  }

  Future<Map<String, dynamic>> clockInOut({
    required String token,
    required double lat,
    required double lon,
    double? accuracy,
    String? wifiSsid,
    String? wifiBssid,
  }) async {
    return _client.postJson('/attendance/clock.php', token: token, body: {
      'lat': lat,
      'lon': lon,
      'accuracy': accuracy,
      'wifi_ssid': wifiSsid,
      'wifi_bssid': wifiBssid,
    });
  }

  Future<Map<String, dynamic>> fetchGeofence({
    required String token,
  }) async {
    return _client.getJson('/geofence.php', token: token);
  }

  Future<Map<String, dynamic>> fetchRequestOptions({
    required String token,
    int? branchId,
  }) async {
    final query = <String, String>{};
    if (branchId != null) {
      query['branch_id'] = branchId.toString();
    }
    return _client.getJson('/attendance/request-options.php',
        token: token, query: query);
  }

  Future<Map<String, dynamic>> submitRequest({
    required String token,
    required String employeeId,
    required String requestDate,
    required String requestTime,
    required String reasonCode,
    String? remarks,
  }) async {
    return _client.postJson('/attendance/request.php', token: token, body: {
      'emp_id': employeeId,
      'request_date': requestDate,
      'request_time': requestTime,
      'reason_code': reasonCode,
      'remarks': remarks,
    });
  }

  Future<Map<String, dynamic>> fetchRequests({
    required String token,
    String? status,
    int limit = 50,
  }) async {
    final query = <String, String>{'limit': limit.toString()};
    if (status != null && status.isNotEmpty) {
      query['status'] = status;
    }
    return _client.getJson('/attendance/requests.php',
        token: token, query: query);
  }

  Future<Map<String, dynamic>> reviewRequest({
    required String token,
    required int requestId,
    required String action,
    String? reviewNotes,
  }) async {
    return _client.postJson('/attendance/review.php', token: token, body: {
      'request_id': requestId,
      'action': action,
      'review_notes': reviewNotes,
    });
  }

  Future<Map<String, dynamic>> syncOffline({
    required String token,
    required List<Map<String, dynamic>> entries,
  }) async {
    return _client.postJson('/attendance/sync.php',
        token: token, body: {'entries': entries});
  }
}
