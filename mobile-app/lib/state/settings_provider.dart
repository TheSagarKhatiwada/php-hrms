import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';

import '../models/app_settings.dart';
import '../services/settings_service.dart';
import '../services/storage_service.dart';

class SettingsProvider extends ChangeNotifier {
  SettingsProvider(this._service, this._storageService);

  final SettingsService _service;
  final StorageService _storageService;

  AppSettings? _settings;
  bool _loading = false;
  ThemeMode _themeMode = ThemeMode.system;

  AppSettings? get settings => _settings;
  bool get loading => _loading;
  ThemeMode get themeMode => _themeMode;

  Future<void> init() async {
    final saved = await _storageService.getThemeMode();
    if (saved == 'light') {
      _themeMode = ThemeMode.light;
    } else if (saved == 'dark') {
      _themeMode = ThemeMode.dark;
    } else {
      _themeMode = ThemeMode.system;
    }
    notifyListeners();
  }

  Future<void> load() async {
    if (_loading) return;
    _loading = true;
    notifyListeners();
    try {
      final data = await _service.fetchSettings();
      final settings = data['settings'] as Map<String, dynamic>?;
      if (settings != null) {
        _settings = AppSettings.fromJson(settings);
      }
    } finally {
      _loading = false;
      notifyListeners();
    }
  }

  Future<void> setThemeMode(ThemeMode mode) async {
    _themeMode = mode;
    final value = mode == ThemeMode.light
        ? 'light'
        : mode == ThemeMode.dark
            ? 'dark'
            : 'system';
    await _storageService.setThemeMode(value);
    notifyListeners();
  }

  Color? get primaryColor => _parseColor(_settings?.primaryColor);
  Color? get secondaryColor => _parseColor(_settings?.secondaryColor);

  Color? _parseColor(String? value) {
    if (value == null || value.isEmpty) return null;
    final hex = value.replaceAll('#', '').trim();
    if (hex.length != 6) return null;
    final parsed = int.tryParse(hex, radix: 16);
    if (parsed == null) return null;
    return Color(0xFF000000 | parsed);
  }
}
