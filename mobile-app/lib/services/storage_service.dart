import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

import '../models/user_profile.dart';

class StorageService {
  static const _tokenKey = 'auth_token';
  static const _profileKey = 'profile';
  static const _themeModeKey = 'theme_mode';
  static const _attendanceHistoryKey = 'attendance_history';
  static const _geofenceKey = 'geofence_data';
  static const _profileImageCacheKey = 'profile_image_cache';
  static const _clockSyncQueueKey = 'clock_sync_queue';
  static const _lastSyncedAtKey = 'last_synced_at';

  Future<String?> getToken() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(_tokenKey);
  }

  Future<void> setToken(String? token) async {
    final prefs = await SharedPreferences.getInstance();
    if (token == null) {
      await prefs.remove(_tokenKey);
    } else {
      await prefs.setString(_tokenKey, token);
    }
  }

  Future<UserProfile?> getProfile() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_profileKey);
    if (raw == null) return null;
    final decoded = jsonDecode(raw);
    if (decoded is Map<String, dynamic>) {
      return UserProfile.fromJson(decoded);
    }
    return null;
  }

  Future<void> setProfile(UserProfile? profile) async {
    final prefs = await SharedPreferences.getInstance();
    if (profile == null) {
      await prefs.remove(_profileKey);
    } else {
      final raw = jsonEncode({
        'emp_id': profile.empId,
        'name': profile.name,
        'email': profile.email,
        'office_email': profile.officeEmail,
        'phone': profile.phone,
        'office_phone': profile.officePhone,
        'address': profile.address,
        'join_date': profile.joinDate,
        'status': profile.status,
        'user_image': profile.userImage,
        'designation': profile.designation,
        'branch_label': profile.branchLabel,
        'role_id': profile.roleId,
        'can_process_attendance_requests': profile.canProcessAttendanceRequests,
        'can_view_reports': profile.canViewReports,
        'can_manage_employees': profile.canManageEmployees,
      });
      await prefs.setString(_profileKey, raw);
    }
  }

  Future<String?> getProfileImageCache() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(_profileImageCacheKey);
  }

  Future<void> setProfileImageCache(String? base64Image) async {
    final prefs = await SharedPreferences.getInstance();
    if (base64Image == null || base64Image.isEmpty) {
      await prefs.remove(_profileImageCacheKey);
    } else {
      await prefs.setString(_profileImageCacheKey, base64Image);
    }
  }

  Future<String?> getThemeMode() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(_themeModeKey);
  }

  Future<void> setThemeMode(String? mode) async {
    final prefs = await SharedPreferences.getInstance();
    if (mode == null) {
      await prefs.remove(_themeModeKey);
    } else {
      await prefs.setString(_themeModeKey, mode);
    }
  }

  Future<List<Map<String, dynamic>>> getAttendanceHistory() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_attendanceHistoryKey);
    if (raw == null || raw.isEmpty) return [];
    final decoded = jsonDecode(raw);
    if (decoded is! List) return [];
    return decoded.whereType<Map<String, dynamic>>().toList();
  }

  Future<void> setAttendanceHistory(List<Map<String, dynamic>> items) async {
    final prefs = await SharedPreferences.getInstance();
    if (items.isEmpty) {
      await prefs.remove(_attendanceHistoryKey);
      return;
    }
    await prefs.setString(_attendanceHistoryKey, jsonEncode(items));
  }

  Future<Map<String, dynamic>?> getGeofence() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_geofenceKey);
    if (raw == null || raw.isEmpty) return null;
    final decoded = jsonDecode(raw);
    if (decoded is Map<String, dynamic>) {
      return decoded;
    }
    return null;
  }

  Future<void> setGeofence(Map<String, dynamic>? geofence) async {
    final prefs = await SharedPreferences.getInstance();
    if (geofence == null) {
      await prefs.remove(_geofenceKey);
      return;
    }
    await prefs.setString(_geofenceKey, jsonEncode(geofence));
  }

  Future<List<Map<String, dynamic>>> getClockSyncQueue() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_clockSyncQueueKey);
    if (raw == null || raw.isEmpty) return [];
    final decoded = jsonDecode(raw);
    if (decoded is! List) return [];
    return decoded.whereType<Map<String, dynamic>>().toList();
  }

  Future<void> setClockSyncQueue(List<Map<String, dynamic>> queue) async {
    final prefs = await SharedPreferences.getInstance();
    if (queue.isEmpty) {
      await prefs.remove(_clockSyncQueueKey);
      return;
    }
    await prefs.setString(_clockSyncQueueKey, jsonEncode(queue));
  }

  Future<String?> getLastSyncedAt() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(_lastSyncedAtKey);
  }

  Future<void> setLastSyncedAt(String? iso) async {
    final prefs = await SharedPreferences.getInstance();
    if (iso == null || iso.isEmpty) {
      await prefs.remove(_lastSyncedAtKey);
    } else {
      await prefs.setString(_lastSyncedAtKey, iso);
    }
  }
}
