import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;

import '../core/constants.dart';
import '../models/user_profile.dart';
import '../services/auth_service.dart';
import '../services/storage_service.dart';

class AuthProvider extends ChangeNotifier {
  AuthProvider(this._authService, this._storageService);

  final AuthService _authService;
  final StorageService _storageService;

  bool _initialized = false;
  bool _loading = false;
  String? _token;
  UserProfile? _profile;
  Uint8List? _profileImageBytes;
  String? _error;

  bool get initialized => _initialized;
  bool get loading => _loading;
  String? get token => _token;
  UserProfile? get profile => _profile;
  Uint8List? get profileImageBytes => _profileImageBytes;
  String? get error => _error;

  Future<void> init() async {
    _token = await _storageService.getToken();
    _profile = await _storageService.getProfile();
    final cachedImage = await _storageService.getProfileImageCache();
    if (cachedImage != null && cachedImage.isNotEmpty) {
      try {
        _profileImageBytes = base64Decode(cachedImage);
      } catch (_) {
        _profileImageBytes = null;
      }
    }
    _initialized = true;
    notifyListeners();
  }

  Future<bool> login({
    required String loginId,
    required String password,
    double? lat,
    double? lon,
    double? accuracy,
  }) async {
    _loading = true;
    _error = null;
    notifyListeners();

    try {
      final data = await _authService.login(
        loginId: loginId,
        password: password,
        lat: lat,
        lon: lon,
        accuracy: accuracy,
      );
      _token = data['token']?.toString();
      if (_token == null || _token!.isEmpty) {
        throw Exception('Missing token');
      }
      await _storageService.setToken(_token);
      _profile = await _authService.fetchProfile(_token!);
      await _storageService.setProfile(_profile);
      await _cacheProfileImage(_profile?.userImage);
      return true;
    } catch (e) {
      _error = e.toString();
      return false;
    } finally {
      _loading = false;
      notifyListeners();
    }
  }

  Future<void> logout() async {
    final token = _token;
    _token = null;
    _profile = null;
    _profileImageBytes = null;
    await _storageService.setToken(null);
    await _storageService.setProfile(null);
    await _storageService.setProfileImageCache(null);
    notifyListeners();

    if (token != null) {
      try {
        await _authService.logout(token);
      } catch (_) {
        // Ignore logout errors
      }
    }
  }

  Future<void> refreshProfile() async {
    if (_token == null) return;
    try {
      _profile = await _authService.fetchProfile(_token!);
      await _storageService.setProfile(_profile);
      await _cacheProfileImage(_profile?.userImage);
      notifyListeners();
    } catch (_) {
      // Ignore refresh errors
    }
  }

  Future<void> _cacheProfileImage(String? path) async {
    final url = _resolveUserImage(path);
    if (url == null) {
      _profileImageBytes = null;
      await _storageService.setProfileImageCache(null);
      return;
    }

    try {
      final response = await http.get(Uri.parse(url));
      if (response.statusCode >= 200 && response.statusCode < 300) {
        _profileImageBytes = response.bodyBytes;
        await _storageService.setProfileImageCache(base64Encode(response.bodyBytes));
      }
    } catch (_) {
      // Keep existing cached image if any
    }
  }

  String? _resolveUserImage(String? path) {
    if (path == null || path.trim().isEmpty) return null;
    final trimmed = path.trim();
    if (trimmed.startsWith('http://') || trimmed.startsWith('https://')) {
      return trimmed;
    }
    final root = apiRootUrl.endsWith('/')
      ? apiRootUrl.substring(0, apiRootUrl.length - 1)
        : apiRootUrl;
    final normalized = trimmed.startsWith('/') ? trimmed : '/$trimmed';
    return '$root$normalized';
  }
}
