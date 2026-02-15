import 'dart:async';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';

import 'notification_service.dart';

class PushService {
  static final PushService instance = PushService._();
  PushService._();

  StreamSubscription<String>? _refreshSubscription;

  Future<void> registerDeviceToken({
    required String authToken,
    required NotificationService notificationService,
  }) async {
    try {
      if (Firebase.apps.isEmpty) {
        await Firebase.initializeApp();
      }
    } catch (_) {
      return;
    }

    final messaging = FirebaseMessaging.instance;
    await messaging.requestPermission(alert: true, badge: true, sound: true);

    final token = await messaging.getToken();
    if (token != null && token.isNotEmpty) {
      await notificationService.registerDeviceToken(
        token: authToken,
        deviceToken: token,
        platform: _platformName(),
      );
    }

    _refreshSubscription ??=
        messaging.onTokenRefresh.listen((newToken) async {
      if (newToken.isEmpty) return;
      try {
        await notificationService.registerDeviceToken(
          token: authToken,
          deviceToken: newToken,
          platform: _platformName(),
        );
      } catch (_) {
        // Ignore token refresh sync failures
      }
    });
  }

  String _platformName() {
    switch (defaultTargetPlatform) {
      case TargetPlatform.iOS:
        return 'ios';
      default:
        return 'android';
    }
  }
}
