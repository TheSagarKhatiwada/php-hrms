import 'dart:convert';

import 'package:http/http.dart' as http;

import '../core/constants.dart';

class ApiClient {
  ApiClient({this.baseUrl = apiBaseUrl});

  final String baseUrl;

  Future<Map<String, dynamic>> getJson(
    String path, {
    String? token,
    Map<String, String>? query,
  }) async {
    final uri = Uri.parse('$baseUrl$path').replace(queryParameters: query);
    final response = await http.get(uri, headers: _headers(token));
    return _handleResponse(response);
  }

  Future<Map<String, dynamic>> postJson(
    String path, {
    String? token,
    Map<String, dynamic>? body,
  }) async {
    final uri = Uri.parse('$baseUrl$path');
    final response = await http.post(
      uri,
      headers: _headers(token),
      body: jsonEncode(body ?? {}),
    );
    return _handleResponse(response);
  }

  Map<String, String> _headers(String? token) {
    final headers = <String, String>{
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    };
    if (token != null && token.isNotEmpty) {
      headers['Authorization'] = 'Bearer $token';
    }
    return headers;
  }

  Map<String, dynamic> _handleResponse(http.Response response) {
    final body = response.body;
    if (body.trim().isEmpty) {
      throw ApiException(
        'Empty response (${response.statusCode}). Check API URL and server logs.',
      );
    }
    try {
      final decoded = jsonDecode(body);
      if (decoded is Map<String, dynamic>) {
        if (response.statusCode >= 400) {
          throw ApiException(
            decoded['message']?.toString() ?? 'Request failed',
          );
        }
        return decoded;
      }
      throw ApiException('Unexpected response format');
    } on FormatException {
      final snippet = body.length > 240 ? body.substring(0, 240) : body;
      throw ApiException(
        'Unexpected response (${response.statusCode}): $snippet',
      );
    }
  }
}

class ApiException implements Exception {
  ApiException(this.message);

  final String message;

  @override
  String toString() => message;
}
