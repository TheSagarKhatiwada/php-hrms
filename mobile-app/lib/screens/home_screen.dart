import 'dart:async';

import 'package:flutter/material.dart';
import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:latlong2/latlong.dart';
import 'package:provider/provider.dart';

import '../core/constants.dart';
import '../services/location_service.dart';
import '../services/wifi_service.dart';
import '../state/attendance_provider.dart';
import '../state/auth_provider.dart';
import '../state/settings_provider.dart';
import '../widgets/geofence_map.dart';
import '../widgets/primary_button.dart';
import 'attendance_history_screen.dart';
import 'profile_screen.dart';
import 'requests_screen.dart';
import 'notifications_screen.dart';
import 'reports_screen.dart';
import 'attendance_request_screen.dart';
import 'leave_screen.dart';
import 'audit_screen.dart';
import 'package:local_auth/local_auth.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  int _currentIndex = 0;
  StreamSubscription<List<ConnectivityResult>>? _connectivitySubscription;
  Timer? _offlineTick;
  bool _isOffline = false;
  DateTime? _offlineSince;

  List<Widget> get _pages => [
        _HomeOverview(onNavigate: _setIndex),
        const _ClockTab(),
        const AttendanceHistoryScreen(),
        const ProfileScreen(),
      ];

  void _setIndex(int index) {
    setState(() => _currentIndex = index);
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _bootstrap());
    _initConnectivityWatch();
  }

  @override
  void dispose() {
    _connectivitySubscription?.cancel();
    _offlineTick?.cancel();
    super.dispose();
  }

  Future<void> _bootstrap() async {
    final token = context.read<AuthProvider>().token;
    if (token == null) return;
    final attendance = context.read<AttendanceProvider>();
    await attendance.initialize();
    await attendance.loadHistory(token: token, days: 7);
    await attendance.syncPending(token: token);
  }

  Future<void> _initConnectivityWatch() async {
    final connectivity = Connectivity();
    final initial = await connectivity.checkConnectivity();
    _applyConnectivity(initial);
    _connectivitySubscription =
        connectivity.onConnectivityChanged.listen(_applyConnectivity);
  }

  void _applyConnectivity(List<ConnectivityResult> results) {
    final offline = results.isEmpty || results.every((r) => r == ConnectivityResult.none);
    if (!mounted) return;
    setState(() {
      _isOffline = offline;
      if (offline) {
        _offlineSince ??= DateTime.now();
      } else {
        _offlineSince = null;
      }
    });

    if (offline) {
      _offlineTick ??= Timer.periodic(const Duration(seconds: 30), (_) {
        if (mounted && _isOffline) {
          setState(() {});
        }
      });
    } else {
      _offlineTick?.cancel();
      _offlineTick = null;
      final token = context.read<AuthProvider>().token;
      if (token != null) {
        context.read<AttendanceProvider>().syncPending(token: token);
      }
    }
  }

  bool _shouldShowSyncIndicator(AttendanceProvider attendance) {
    final offlineLongEnough =
        _isOffline && _offlineSince != null && DateTime.now().difference(_offlineSince!) >= const Duration(minutes: 5);
    return offlineLongEnough || attendance.loading || attendance.hasPendingSync;
  }

  @override
  Widget build(BuildContext context) {
    final settings = context.watch<SettingsProvider>().settings;
    final auth = context.watch<AuthProvider>();
    final attendance = context.watch<AttendanceProvider>();
    final profile = auth.profile;
    final avatarUrl = _resolveUserImage(profile?.userImage);
    final showSyncIndicator = _shouldShowSyncIndicator(attendance);
    return Scaffold(
      appBar: AppBar(
        title: Text(settings?.appName ?? 'HRMS-App'),
        actions: [
          if (showSyncIndicator)
            IconButton(
              icon: const Icon(Icons.sync),
              onPressed: null,
              tooltip: attendance.loading
                  ? 'Syncing...'
                  : (attendance.hasPendingSync
                      ? 'Pending sync'
                      : 'Offline 5+ minutes'),
            ),
          IconButton(
            icon: const Icon(Icons.notifications_none),
            onPressed: () {
              Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) => const NotificationsScreen(),
                ),
              );
            },
          ),
        ],
      ),
      body: _pages[_pageIndex()],
      bottomNavigationBar: BottomNavigationBar(
        currentIndex: _currentIndex,
        onTap: (index) {
          if (index == 2) {
            _showRequestsSheet(context);
            return;
          }
          setState(() => _currentIndex = index);
        },
        items: [
          const BottomNavigationBarItem(icon: Icon(Icons.home), label: 'Home'),
          const BottomNavigationBarItem(
              icon: Icon(Icons.fingerprint), label: 'Clock'),
          BottomNavigationBarItem(
            icon: _RequestNavIcon(selected: _currentIndex == 2),
            label: 'Requests',
          ),
          const BottomNavigationBarItem(icon: Icon(Icons.history), label: 'History'),
          BottomNavigationBarItem(
            icon: CircleAvatar(
              radius: 12,
              backgroundColor: Colors.blue.shade50,
              backgroundImage: avatarUrl == null ? null : NetworkImage(avatarUrl),
              child: avatarUrl == null
                  ? const Icon(Icons.person, size: 16)
                  : null,
            ),
            label: 'Profile',
          ),
        ],
      ),
    );
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

  int _pageIndex() {
    if (_currentIndex <= 1) return _currentIndex;
    if (_currentIndex == 3) return 2;
    if (_currentIndex == 4) return 3;
    return 0;
  }

  void _showRequestsSheet(BuildContext context) {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (context) {
        final height = MediaQuery.of(context).size.height * 0.85;
        return SizedBox(
          height: height,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Padding(
                padding: EdgeInsets.fromLTRB(16, 12, 16, 4),
                child: Text(
                  'Requests',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
                ),
              ),
              const Divider(height: 1),
              Expanded(
                child: ListView.builder(
                  padding: const EdgeInsets.all(16),
                  itemCount: requestActions.length,
                  itemBuilder: (context, index) {
                    final action = requestActions[index];
                    return Card(
                      child: ListTile(
                        leading: CircleAvatar(
                          backgroundColor: Colors.blue.shade50,
                          child: Icon(action.icon, color: Colors.blue.shade700),
                        ),
                        title: Text(action.title),
                        subtitle: Text(action.subtitle),
                        trailing: const Icon(Icons.chevron_right),
                        onTap: () {
                          Navigator.of(context).pop();
                          if (action.keyName == 'reports') {
                            Navigator.of(context).push(
                              MaterialPageRoute(
                                builder: (_) => const ReportsScreen(),
                              ),
                            );
                          } else if (action.keyName == 'leave_request') {
                            Navigator.of(context).push(
                              MaterialPageRoute(
                                builder: (_) => const LeaveScreen(),
                              ),
                            );
                          } else if (action.keyName == 'manager_inbox') {
                            Navigator.of(context).push(
                              MaterialPageRoute(
                                builder: (_) => const AttendanceRequestScreen(
                                  initialStatus: 'pending',
                                  managerMode: true,
                                ),
                              ),
                            );
                          } else if (action.keyName == 'geo_audit') {
                            Navigator.of(context).push(
                              MaterialPageRoute(
                                builder: (_) => const AuditScreen(),
                              ),
                            );
                          } else if (action.keyName == 'attendance_request') {
                            Navigator.of(context).push(
                              MaterialPageRoute(
                                builder: (_) => const AttendanceRequestScreen(),
                              ),
                            );
                          } else {
                            Navigator.of(context).push(
                              MaterialPageRoute(
                                builder: (_) => RequestDetailScreen(action: action),
                              ),
                            );
                          }
                        },
                      ),
                    );
                  },
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}

class _RequestNavIcon extends StatelessWidget {
  const _RequestNavIcon({required this.selected});

  final bool selected;

  @override
  Widget build(BuildContext context) {
    final color = selected
        ? Theme.of(context).colorScheme.primary
        : Theme.of(context).bottomNavigationBarTheme.unselectedItemColor ??
            Theme.of(context).colorScheme.onSurfaceVariant;
    return Container(
      padding: const EdgeInsets.all(8),
      decoration: BoxDecoration(
        color: color.withOpacity(0.12),
        shape: BoxShape.circle,
      ),
      child: Icon(Icons.assignment, size: 28, color: color),
    );
  }
}

class _HomeOverview extends StatelessWidget {
  const _HomeOverview({required this.onNavigate});

  final ValueChanged<int> onNavigate;

  @override
  Widget build(BuildContext context) {
    final attendance = context.watch<AttendanceProvider>();
    final auth = context.watch<AuthProvider>();
    final profile = auth.profile;
    final today = attendance.history.isNotEmpty ? attendance.history.first : null;
    final now = DateTime.now();
    final todayStr =
        '${now.year.toString().padLeft(4, '0')}-${now.month.toString().padLeft(2, '0')}-${now.day.toString().padLeft(2, '0')}';
    final isToday = today?.date == todayStr;

    final totalPunches = attendance.history.fold<int>(
      0,
      (sum, record) => sum + record.punchCount,
    );
    final recent = attendance.history.take(3).toList();
    final lastSyncedAt = attendance.lastSyncedAt;
    final lastSyncedLabel = lastSyncedAt == null
      ? '--'
      : '${lastSyncedAt.year.toString().padLeft(4, '0')}-${lastSyncedAt.month.toString().padLeft(2, '0')}-${lastSyncedAt.day.toString().padLeft(2, '0')} ${lastSyncedAt.hour.toString().padLeft(2, '0')}:${lastSyncedAt.minute.toString().padLeft(2, '0')}';

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Card(
          child: ListTile(
            title: Text(isToday ? 'Today' : 'Latest'),
            subtitle: Text(
              today == null
                  ? 'No attendance recorded today'
                  : 'Date: ${today.date}\nIn: ${today.inTime ?? '--'}  Out: ${today.outTime ?? '--'}',
            ),
          ),
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(
              child: _StatCard(
                label: 'Days',
                value: attendance.history.length.toString(),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: _StatCard(
                label: 'Punches',
                value: totalPunches.toString(),
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(
              child: _StatCard(
                label: 'Last In',
                value: today?.inTime ?? '--',
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: _StatCard(
                label: 'Last Out',
                value: today?.outTime ?? '--',
              ),
            ),
          ],
        ),
        const SizedBox(height: 16),
        const Text(
          'Quick Actions',
          style: TextStyle(fontWeight: FontWeight.w600),
        ),
        const SizedBox(height: 8),
        Row(
          children: [
            Expanded(
              child: OutlinedButton.icon(
                onPressed: () => onNavigate(1),
                icon: const Icon(Icons.fingerprint),
                label: const Text('Clock'),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: OutlinedButton.icon(
                onPressed: () => onNavigate(2),
                icon: const Icon(Icons.history),
                label: const Text('History'),
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        if (profile?.canProcessAttendanceRequests == true)
          Card(
            child: ListTile(
              title: const Text('Manager Widget'),
              subtitle: const Text('You can review pending attendance requests from Manager Inbox.'),
              trailing: const Icon(Icons.approval),
            ),
          ),
        if (profile?.canManageEmployees == true)
          Card(
            child: ListTile(
              title: const Text('Admin Widget'),
              subtitle: const Text('Geo/Wi-Fi audit and employee controls available.'),
              trailing: const Icon(Icons.admin_panel_settings),
            ),
          ),
        if (profile?.canViewReports == true)
          Card(
            child: ListTile(
              title: const Text('Reports Widget'),
              subtitle: const Text('Generate and share attendance reports quickly.'),
              trailing: const Icon(Icons.bar_chart),
            ),
          ),
        const SizedBox(height: 16),
        Card(
          child: ListTile(
            title: const Text('Sync Status'),
            subtitle: Text(
              'Pending: ${attendance.pendingSyncCount}\nLast synced: $lastSyncedLabel',
            ),
            trailing: attendance.pendingSyncCount > 0
                ? const Icon(Icons.sync_problem)
                : const Icon(Icons.cloud_done),
          ),
        ),
        const SizedBox(height: 16),
        const Text(
          'Recent Records',
          style: TextStyle(fontWeight: FontWeight.w600),
        ),
        const SizedBox(height: 8),
        if (recent.isEmpty)
          const Text('No recent attendance records found.'),
        for (final record in recent)
          Card(
            child: ListTile(
              title: Text(record.date),
              subtitle: Text(
                'In: ${record.inTime ?? '--'}  Out: ${record.outTime ?? '--'}',
              ),
              trailing: Text('${record.punchCount} logs'),
            ),
          ),
      ],
    );
  }
}

class _ClockTab extends StatefulWidget {
  const _ClockTab();

  @override
  State<_ClockTab> createState() => _ClockTabState();
}

class _ClockTabState extends State<_ClockTab> {
  final _locationService = LocationService();
  final _wifiService = WifiService();
  final _localAuth = LocalAuthentication();
  Map<String, dynamic>? _geofence;
  bool _loading = false;
  String? _message;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load() async {
    final token = context.read<AuthProvider>().token;
    final attendanceProvider = context.read<AttendanceProvider>();
    if (token == null) return;
    final data = await attendanceProvider.loadGeofence(token: token);
    if (!mounted) return;
    setState(() => _geofence = data['geofence'] as Map<String, dynamic>?);
    await attendanceProvider.loadHistory(token: token, days: 7);
  }

  Future<void> _clock() async {
    final token = context.read<AuthProvider>().token;
    final attendanceProvider = context.read<AttendanceProvider>();
    if (token == null) return;

    setState(() {
      _loading = true;
      _message = null;
    });

    try {
      final supported = await _localAuth.isDeviceSupported();
      final canCheck = await _localAuth.canCheckBiometrics;
      if (supported || canCheck) {
        final okAuth = await _localAuth.authenticate(
          localizedReason: 'Verify identity before clocking attendance',
          options: const AuthenticationOptions(
            biometricOnly: false,
            stickyAuth: true,
          ),
        );
        if (!okAuth) {
          setState(() => _message = 'Biometric verification cancelled.');
          return;
        }
      }

      final pos = await _locationService.getCurrentPosition();
      final ssid = await _wifiService.getWifiName();
      final bssid = await _wifiService.getWifiBssid();

      if (!mounted) return;

      final ok = await attendanceProvider.clock(
            token: token,
            lat: pos.latitude,
            lon: pos.longitude,
            accuracy: pos.accuracy,
            wifiSsid: ssid,
            wifiBssid: bssid,
          );
      if (mounted) {
        setState(() {
          _message = attendanceProvider.message;
        });
      }
      if (ok) {
        await attendanceProvider.loadHistory(token: token, days: 7);
      }
    } catch (e) {
      if (mounted) {
        setState(() => _message = e.toString());
      }
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final attendance = context.watch<AttendanceProvider>();
    final today = attendance.history.isNotEmpty ? attendance.history.first : null;

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Card(
          child: ListTile(
            title: const Text('Clock Status'),
            subtitle: Text(
              today == null
                  ? 'No attendance recorded today'
                  : 'Date: ${today.date}\nIn: ${today.inTime ?? '--'}  Out: ${today.outTime ?? '--'}',
            ),
          ),
        ),
        const SizedBox(height: 12),
        if (_geofence != null && _geofence!['latitude'] != null)
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Office Geofence',
                style: TextStyle(fontWeight: FontWeight.w600),
              ),
              const SizedBox(height: 8),
              SizedBox(
                height: 220,
                child: GeofenceMap(
                  center: LatLng(
                    double.tryParse(_geofence!['latitude'].toString()) ?? 0,
                    double.tryParse(_geofence!['longitude'].toString()) ?? 0,
                  ),
                  radiusMeters:
                      double.tryParse(_geofence!['radius_m'].toString()) ?? 150,
                ),
              ),
              const SizedBox(height: 8),
              Card(
                child: ListTile(
                  title: const Text('Office Details'),
                  subtitle: Text(
                    'Radius: ${_geofence!['radius_m'] ?? '--'} m\nGeofence: ${(_geofence!['geofence_enabled'] ?? 0).toString() == '1' ? 'Enabled' : 'Disabled'}\nDefault SSID: ${_geofence!['default_ssid'] ?? '--'}',
                  ),
                ),
              ),
            ],
          ),
        const SizedBox(height: 16),
        PrimaryButton(
          label: 'Clock In/Out',
          onPressed: _loading ? null : _clock,
          loading: _loading,
        ),
        const SizedBox(height: 12),
        const Text(
          'Tips',
          style: TextStyle(fontWeight: FontWeight.w600),
        ),
        const SizedBox(height: 6),
        const Text('1. Enable GPS and allow location permission.'),
        const Text('2. Stay within the branch geofence.'),
        const Text('3. Use office Wi-Fi if required.'),
        if (_message != null)
          Padding(
            padding: const EdgeInsets.only(top: 12),
            child: Text(
              _message!,
              style: const TextStyle(color: Colors.black54),
            ),
          ),
      ],
    );
  }
}

class _StatCard extends StatelessWidget {
  const _StatCard({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              label,
              style: const TextStyle(color: Colors.black54),
            ),
            const SizedBox(height: 6),
            Text(
              value,
              style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
            ),
          ],
        ),
      ),
    );
  }
}
