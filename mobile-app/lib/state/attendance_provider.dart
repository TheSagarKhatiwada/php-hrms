import 'package:flutter/foundation.dart';

import '../models/attendance_record.dart';
import '../services/attendance_service.dart';
import '../services/storage_service.dart';

class AttendanceProvider extends ChangeNotifier {
  AttendanceProvider(this._service, this._storageService);

  final AttendanceService _service;
  final StorageService _storageService;

  List<AttendanceRecord> _history = [];
  bool _loading = false;
  bool _hasPendingSync = false;
  bool _initialized = false;
  DateTime? _lastSyncedAt;
  List<Map<String, dynamic>> _clockSyncQueue = [];
  String? _message;

  List<AttendanceRecord> get history => _history;
  bool get loading => _loading;
  bool get hasPendingSync => _hasPendingSync;
  int get pendingSyncCount => _clockSyncQueue.length;
  DateTime? get lastSyncedAt => _lastSyncedAt;
  String? get message => _message;

  Future<void> initialize() async {
    if (_initialized) return;
    _clockSyncQueue = await _storageService.getClockSyncQueue();
    final last = await _storageService.getLastSyncedAt();
    _lastSyncedAt = last == null ? null : DateTime.tryParse(last);
    _hasPendingSync = _clockSyncQueue.isNotEmpty;
    _initialized = true;
    notifyListeners();
  }

  Future<void> loadHistory({
    required String token,
    int days = 7,
    String? start,
    String? end,
    int? branchId,
    String? employeeId,
  }) async {
    _loading = true;
    notifyListeners();
    try {
      final fresh = await _service.fetchHistory(
        token: token,
        days: days,
        start: start,
        end: end,
        branchId: branchId,
        employeeId: employeeId,
      );
      _history = fresh;
      await _storageService.setAttendanceHistory(
        fresh
            .map((record) => {
                  'date': record.date,
                  'in_time': record.inTime,
                  'out_time': record.outTime,
                  'punch_count': record.punchCount,
                })
            .toList(),
      );
      _hasPendingSync = false;
      _markSyncedNow();
    } catch (_) {
      final cached = await _storageService.getAttendanceHistory();
      _history = cached.map(AttendanceRecord.fromJson).toList();
      _hasPendingSync = true;
    } finally {
      _loading = false;
      notifyListeners();
    }
  }

  Future<bool> clock({
    required String token,
    required double lat,
    required double lon,
    double? accuracy,
    String? wifiSsid,
    String? wifiBssid,
  }) async {
    _loading = true;
    _message = null;
    notifyListeners();
    try {
      final data = await _service.clockInOut(
        token: token,
        lat: lat,
        lon: lon,
        accuracy: accuracy,
        wifiSsid: wifiSsid,
        wifiBssid: wifiBssid,
      );
      _message = data['message']?.toString();
      final ok = data['success'] == true;
      if (ok) {
        _markSyncedNow();
      } else {
        await _enqueueClockEntry(
          lat: lat,
          lon: lon,
          accuracy: accuracy,
          wifiSsid: wifiSsid,
          wifiBssid: wifiBssid,
        );
      }
      _hasPendingSync = !ok || _clockSyncQueue.isNotEmpty;
      return ok;
    } catch (e) {
      _message = e.toString();
      await _enqueueClockEntry(
        lat: lat,
        lon: lon,
        accuracy: accuracy,
        wifiSsid: wifiSsid,
        wifiBssid: wifiBssid,
      );
      _hasPendingSync = true;
      return false;
    } finally {
      _loading = false;
      notifyListeners();
    }
  }

  Future<Map<String, dynamic>> loadGeofence({required String token}) async {
    try {
      final fresh = await _service.fetchGeofence(token: token);
      final geofence = fresh['geofence'] as Map<String, dynamic>?;
      await _storageService.setGeofence(geofence);
      _hasPendingSync = false;
      _markSyncedNow();
      return fresh;
    } catch (_) {
      final cached = await _storageService.getGeofence();
      if (cached != null) {
        _hasPendingSync = true;
        return {
          'success': true,
          'geofence': cached,
        };
      }
      rethrow;
    }
  }

  Future<void> syncPending({required String token}) async {
    if (_clockSyncQueue.isEmpty) return;

    final now = DateTime.now();
    final remaining = <Map<String, dynamic>>[];

    for (final item in _clockSyncQueue) {
      final nextRetryAtRaw = item['next_retry_at']?.toString();
      final nextRetryAt = nextRetryAtRaw == null ? null : DateTime.tryParse(nextRetryAtRaw);
      if (nextRetryAt != null && now.isBefore(nextRetryAt)) {
        remaining.add(item);
        continue;
      }

      try {
        final result = await _service.clockInOut(
          token: token,
          lat: (item['lat'] as num?)?.toDouble() ?? 0,
          lon: (item['lon'] as num?)?.toDouble() ?? 0,
          accuracy: (item['accuracy'] as num?)?.toDouble(),
          wifiSsid: item['wifi_ssid']?.toString(),
          wifiBssid: item['wifi_bssid']?.toString(),
        );
        final ok = result['success'] == true;
        if (!ok) {
          remaining.add(_nextRetryItem(item, now));
        } else {
          _markSyncedNow();
        }
      } catch (_) {
        remaining.add(_nextRetryItem(item, now));
      }
    }

    _clockSyncQueue = remaining;
    _hasPendingSync = _clockSyncQueue.isNotEmpty;
    await _storageService.setClockSyncQueue(_clockSyncQueue);
    notifyListeners();
  }

  Future<void> _enqueueClockEntry({
    required double lat,
    required double lon,
    double? accuracy,
    String? wifiSsid,
    String? wifiBssid,
  }) async {
    _clockSyncQueue.add({
      'lat': lat,
      'lon': lon,
      'accuracy': accuracy,
      'wifi_ssid': wifiSsid,
      'wifi_bssid': wifiBssid,
      'attempt': 0,
      'next_retry_at': DateTime.now().toIso8601String(),
    });
    await _storageService.setClockSyncQueue(_clockSyncQueue);
  }

  Map<String, dynamic> _nextRetryItem(Map<String, dynamic> item, DateTime now) {
    final attempt = ((item['attempt'] as num?)?.toInt() ?? 0) + 1;
    final backoffMinutes = attempt <= 1 ? 1 : (attempt <= 3 ? 2 : 5);
    return {
      ...item,
      'attempt': attempt,
      'next_retry_at': now.add(Duration(minutes: backoffMinutes)).toIso8601String(),
    };
  }

  void _markSyncedNow() {
    _lastSyncedAt = DateTime.now();
    _storageService.setLastSyncedAt(_lastSyncedAt!.toIso8601String());
  }
}
