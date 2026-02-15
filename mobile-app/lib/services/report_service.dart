import '../models/report_item.dart';

import 'api_client.dart';

class ReportService {
  ReportService(this._client);

  final ApiClient _client;

  Future<Map<String, dynamic>> generateReport({
    required String token,
    required String reportType,
    String? date,
    String? range,
    String? branch,
    String? employees,
  }) async {
    return _client.postJson('/reports/generate.php', token: token, body: {
      'report_type': reportType,
      'date': date,
      'range': range,
      'branch': branch,
      'employees': employees ?? '*',
    });
  }

  Future<List<ReportItem>> listReports({
    required String token,
    String? type,
    String? dateFrom,
    String? dateTo,
    int page = 1,
    int perPage = 10,
  }) async {
    final query = <String, String>{
      'page': page.toString(),
      'per_page': perPage.toString(),
    };
    if (type != null && type.isNotEmpty) {
      query['type'] = type;
    }
    if (dateFrom != null && dateFrom.isNotEmpty) {
      query['date_from'] = dateFrom;
    }
    if (dateTo != null && dateTo.isNotEmpty) {
      query['date_to'] = dateTo;
    }

    final data = await _client.getJson('/reports/list.php',
        token: token, query: query);
    final rows = data['data'] as List<dynamic>? ?? [];
    return rows
        .whereType<Map<String, dynamic>>()
        .map(ReportItem.fromJson)
        .toList();
  }

  Future<void> deleteReport({required String token, required int id}) async {
    await _client.postJson('/reports/delete.php', token: token, body: {
      'id': id,
    });
  }
}
