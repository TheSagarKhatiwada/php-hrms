import 'package:flutter/material.dart';

class RequestsScreen extends StatelessWidget {
  const RequestsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        const Text(
          'Requests & Actions',
          style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
        ),
        const SizedBox(height: 12),
        for (final action in requestActions)
          _ActionCard(
            title: action.title,
            subtitle: action.subtitle,
            icon: action.icon,
          ),
      ],
    );
  }
}

class RequestAction {
  const RequestAction({
    required this.keyName,
    required this.title,
    required this.subtitle,
    required this.icon,
  });

  final String keyName;
  final String title;
  final String subtitle;
  final IconData icon;
}

const requestActions = [
  RequestAction(
    keyName: 'manager_inbox',
    title: 'Manager Inbox',
    subtitle: 'Review and process pending attendance requests.',
    icon: Icons.approval,
  ),
  RequestAction(
    keyName: 'leave_request',
    title: 'Leave Request',
    subtitle: 'Submit a leave request for approval.',
    icon: Icons.beach_access,
  ),
  RequestAction(
    keyName: 'attendance_request',
    title: 'Attendance Request',
    subtitle: 'Request attendance adjustment for a specific time.',
    icon: Icons.edit_calendar,
  ),
  RequestAction(
    keyName: 'geo_audit',
    title: 'Geo/Wi-Fi Audit',
    subtitle: 'Review location logs and branch attendance policy.',
    icon: Icons.gps_fixed,
  ),
  RequestAction(
    keyName: 'expense_claim',
    title: 'Expense Claim',
    subtitle: 'Log travel or work expenses for reimbursement.',
    icon: Icons.receipt_long,
  ),
  RequestAction(
    keyName: 'reports',
    title: 'Reports',
    subtitle: 'Access attendance and activity summaries.',
    icon: Icons.bar_chart,
  ),
  RequestAction(
    keyName: 'overtime_request',
    title: 'Overtime Request',
    subtitle: 'Send an overtime request for approval.',
    icon: Icons.schedule,
  ),
];

class RequestDetailScreen extends StatelessWidget {
  const RequestDetailScreen({super.key, required this.action});

  final RequestAction action;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(action.title)),
      body: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              action.subtitle,
              style: const TextStyle(color: Colors.black54),
            ),
            const SizedBox(height: 16),
            const Text(
              'Form fields will be added here based on your workflow.',
            ),
          ],
        ),
      ),
    );
  }
}

class _ActionCard extends StatelessWidget {
  const _ActionCard({
    required this.title,
    required this.subtitle,
    required this.icon,
  });

  final String title;
  final String subtitle;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: ListTile(
        leading: CircleAvatar(
          backgroundColor: Colors.blue.shade50,
          child: Icon(icon, color: Colors.blue.shade700),
        ),
        title: Text(title, style: const TextStyle(fontWeight: FontWeight.w600)),
        subtitle: Text(subtitle),
        trailing: const Icon(Icons.chevron_right),
      ),
    );
  }
}
