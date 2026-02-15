# HRMS-App

Flutter mobile app for HRMS attendance, profile, and notifications.

## Setup

1) Ensure Flutter SDK is installed.
2) Update the API base URL in [lib/core/constants.dart](lib/core/constants.dart).
3) Install dependencies:

```bash
flutter pub get
```

## Run

```bash
flutter run
```

## Permissions

Android permissions are declared in [android/app/src/main/AndroidManifest.xml](android/app/src/main/AndroidManifest.xml):
- Location (foreground and background)
- Wi-Fi and network state

iOS permissions are declared in [ios/Runner/Info.plist](ios/Runner/Info.plist):
- Location usage descriptions

Note: Wi-Fi SSID/BSSID requires location permission on Android 10+.

## API Endpoints

The app expects the mobile endpoints:
- POST /login.php
- POST /logout.php
- GET /profile.php
- GET /geofence.php
- POST /attendance/clock.php
- GET /attendance/history.php
- POST /attendance/sync.php
- GET /notifications.php
- POST /notifications.php
- POST /device-token.php
- POST /location.php
