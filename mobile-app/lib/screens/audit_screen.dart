import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../services/audit_service.dart';
import '../state/auth_provider.dart';

class AuditScreen extends StatefulWidget {
  const AuditScreen({super.key});

  @override
  State<AuditScreen> createState() => _AuditScreenState();
}

class _AuditScreenState extends State<AuditScreen> {
  bool _loading = false;
  String? _message;
  Map<String, dynamic>? _data;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load() async {
    final token = context.read<AuthProvider>().token;
    if (token == null) return;
    final service = context.read<AuditService>();
    setState(() => _loading = true);
    try {
      final data = await service.fetchAudit(token: token);
      setState(() => _data = data);
    } catch (e) {
      setState(() => _message = e.toString());
    } finally {
      setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final geofence = _data?['geofence'] as Map<String, dynamic>?;
    final logs = (_data?['location_logs'] as List<dynamic>? ?? []).whereType<Map<String, dynamic>>().toList();
    return Scaffold(
      appBar: AppBar(title: const Text('Geo/Wi-Fi Audit')),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Card(
              child: ListTile(
                title: const Text('Policy'),
                subtitle: Text(
                  'Geofence: ${(geofence?['geofence_enabled'] ?? 0).toString() == '1' ? 'Enabled' : 'Disabled'}\n'
                  'Wi-Fi Required: ${_data?['wifi_required'] == true ? 'Yes' : 'No'}\n'
                  'Default SSID: ${_data?['default_ssid'] ?? '--'}',
                ),
              ),
            ),
            const SizedBox(height: 12),
            const Text('Recent Location Logs', style: TextStyle(fontWeight: FontWeight.w600)),
            const SizedBox(height: 8),
            if (logs.isEmpty && !_loading) const Text('No location logs found.'),
            for (final log in logs)
              Card(
                child: ListTile(
                  title: Text('${log['latitude']} , ${log['longitude']}'),
                  subtitle: Text('Accuracy: ${log['accuracy_meters'] ?? '--'}\n${log['created_at'] ?? '--'}'),
                ),
              ),
            if (_message != null) ...[
              const SizedBox(height: 8),
              Text(_message!),
            ]
          ],
        ),
      ),
    );
  }
}
