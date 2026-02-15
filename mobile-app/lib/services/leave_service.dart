import '../models/leave_item.dart';
import 'api_client.dart';

class LeaveService {
  LeaveService(this._client);

  final ApiClient _client;

  Future<Map<String, dynamic>> fetchOptions({required String token}) {
    return _client.getJson('/leave/options.php', token: token);
  }

  Future<List<LeaveItem>> fetchRequests({required String token, String? status}) async {
    final query = <String, String>{};
    if (status != null && status.isNotEmpty) {
      query['status'] = status;
    }
    final data = await _client.getJson('/leave/list.php', token: token, query: query);
    final rows = data['items'] as List<dynamic>? ?? [];
    return rows.whereType<Map<String, dynamic>>().map(LeaveItem.fromJson).toList();
  }

  Future<Map<String, dynamic>> submitRequest({
    required String token,
    required int leaveTypeId,
    required String startDate,
    required String endDate,
    required String reason,
  }) {
    return _client.postJson('/leave/request.php', token: token, body: {
      'leave_type_id': leaveTypeId,
      'start_date': startDate,
      'end_date': endDate,
      'reason': reason,
    });
  }
}
