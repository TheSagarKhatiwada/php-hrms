import 'package:flutter/material.dart';
import 'package:local_auth/local_auth.dart';
import 'package:provider/provider.dart';

import 'core/constants.dart';
import 'core/theme.dart';
import 'screens/home_screen.dart';
import 'screens/login_screen.dart';
import 'services/api_client.dart';
import 'services/attendance_service.dart';
import 'services/auth_service.dart';
import 'services/notification_service.dart';
import 'services/report_service.dart';
import 'services/settings_service.dart';
import 'services/storage_service.dart';
import 'services/leave_service.dart';
import 'services/audit_service.dart';
import 'services/push_service.dart';
import 'state/attendance_provider.dart';
import 'state/auth_provider.dart';
import 'state/notification_provider.dart';
import 'state/settings_provider.dart';

class HrmsApp extends StatelessWidget {
  const HrmsApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        Provider(create: (_) => ApiClient()),
        Provider(create: (_) => StorageService()),
        ProxyProvider<ApiClient, AuthService>(
          update: (context, client, previous) => AuthService(client),
        ),
        ProxyProvider<ApiClient, AttendanceService>(
          update: (context, client, previous) => AttendanceService(client),
        ),
        ProxyProvider<ApiClient, NotificationService>(
          update: (context, client, previous) => NotificationService(client),
        ),
        ProxyProvider<ApiClient, ReportService>(
          update: (context, client, previous) => ReportService(client),
        ),
        ProxyProvider<ApiClient, SettingsService>(
          update: (context, client, previous) => SettingsService(client),
        ),
        ProxyProvider<ApiClient, LeaveService>(
          update: (context, client, previous) => LeaveService(client),
        ),
        ProxyProvider<ApiClient, AuditService>(
          update: (context, client, previous) => AuditService(client),
        ),
        ChangeNotifierProxyProvider2<AuthService, StorageService, AuthProvider>(
          create: (context) => AuthProvider(
            context.read<AuthService>(),
            context.read<StorageService>(),
          ),
          update: (_, authService, storageService, previous) =>
              previous ?? AuthProvider(authService, storageService),
        ),
        ChangeNotifierProxyProvider2<AttendanceService, StorageService, AttendanceProvider>(
          create: (context) => AttendanceProvider(
            context.read<AttendanceService>(),
            context.read<StorageService>(),
          ),
          update: (_, service, storage, previous) =>
              previous ?? AttendanceProvider(service, storage),
        ),
        ChangeNotifierProxyProvider<NotificationService, NotificationProvider>(
          create: (context) =>
              NotificationProvider(context.read<NotificationService>()),
          update: (_, service, previous) => previous ?? NotificationProvider(service),
        ),
        ChangeNotifierProxyProvider2<SettingsService, StorageService, SettingsProvider>(
          create: (context) => SettingsProvider(
            context.read<SettingsService>(),
            context.read<StorageService>(),
          ),
          update: (_, service, storage, previous) =>
              previous ?? SettingsProvider(service, storage),
        ),
      ],
      child: Consumer<SettingsProvider>(
        builder: (context, settings, _) {
          final theme = buildAppTheme(
            primary: settings.primaryColor,
            secondary: settings.secondaryColor,
            brightness: Brightness.light,
          );
          final darkTheme = buildAppTheme(
            primary: settings.primaryColor,
            secondary: settings.secondaryColor,
            brightness: Brightness.dark,
          );
          final title = settings.settings?.appName ?? appName;
          return MaterialApp(
            title: title,
            theme: theme,
            darkTheme: darkTheme,
            themeMode: settings.themeMode,
            home: const AuthGate(),
          );
        },
      ),
    );
  }
}

class AuthGate extends StatefulWidget {
  const AuthGate({super.key});

  @override
  State<AuthGate> createState() => _AuthGateState();
}

class _AuthGateState extends State<AuthGate> with WidgetsBindingObserver {
  final LocalAuthentication _localAuth = LocalAuthentication();
  bool _shouldRelock = false;
  bool _authInProgress = false;
  String? _lastPushRegisteredToken;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final auth = context.read<AuthProvider>();
      final settings = context.read<SettingsProvider>();
      settings.init();
      settings.load();
      if (!auth.initialized) {
        auth.init();
      }
    });
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    final hasSession = context.read<AuthProvider>().token != null;
    if (!hasSession) return;
    if (state == AppLifecycleState.paused || state == AppLifecycleState.inactive) {
      _shouldRelock = true;
    }
    if (state == AppLifecycleState.resumed && _shouldRelock) {
      _promptBiometricUnlock();
    }
  }

  Future<void> _promptBiometricUnlock() async {
    if (_authInProgress || !mounted) return;
    final authProvider = context.read<AuthProvider>();
    _authInProgress = true;
    try {
      final supported = await _localAuth.isDeviceSupported();
      final canCheck = await _localAuth.canCheckBiometrics;
      if (!supported && !canCheck) {
        _shouldRelock = false;
        return;
      }
      final ok = await _localAuth.authenticate(
        localizedReason: 'Authenticate to continue using HRMS',
        options: const AuthenticationOptions(
          biometricOnly: false,
          stickyAuth: true,
        ),
      );
      if (ok) {
        _shouldRelock = false;
      } else {
        await authProvider.logout();
      }
    } catch (_) {
      await authProvider.logout();
    } finally {
      _authInProgress = false;
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    if (auth.token != null && auth.token != _lastPushRegisteredToken) {
      _lastPushRegisteredToken = auth.token;
      WidgetsBinding.instance.addPostFrameCallback((_) async {
        if (!mounted || auth.token == null) return;
        final notificationService = context.read<NotificationService>();
        await PushService.instance.registerDeviceToken(
          authToken: auth.token!,
          notificationService: notificationService,
        );
      });
    }
    if (auth.token == null) {
      _lastPushRegisteredToken = null;
    }
    if (!auth.initialized) {
      return const Scaffold(
        body: Center(child: CircularProgressIndicator()),
      );
    }
    if (auth.token != null) {
      return const HomeScreen();
    }
    return const LoginScreen();
  }
}
