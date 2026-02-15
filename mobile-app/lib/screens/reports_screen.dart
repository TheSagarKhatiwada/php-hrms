import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:share_plus/share_plus.dart';
import 'package:url_launcher/url_launcher.dart';

import '../models/report_item.dart';
import '../services/report_service.dart';
import '../state/auth_provider.dart';

class ReportsScreen extends StatefulWidget {
  const ReportsScreen({super.key});

  @override
  State<ReportsScreen> createState() => _ReportsScreenState();
}

class _ReportsScreenState extends State<ReportsScreen> {
  String _reportType = 'daily';
  DateTime? _date;
  DateTime? _rangeStart;
  DateTime? _rangeEnd;
  bool _loading = false;
  String? _message;
  List<ReportItem> _reports = [];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _loadReports());
  }

  Future<void> _loadReports() async {
    final token = context.read<AuthProvider>().token;
    if (token == null) return;
    final service = context.read<ReportService>();
    setState(() => _loading = true);
    try {
      final items = await service.listReports(token: token, type: _reportType);
      setState(() => _reports = items);
    } catch (e) {
      setState(() => _message = e.toString());
    } finally {
      setState(() => _loading = false);
    }
  }

  Future<void> _generate() async {
    final token = context.read<AuthProvider>().token;
    if (token == null) return;
    final service = context.read<ReportService>();
    setState(() {
      _loading = true;
      _message = null;
    });
    try {
      final date = _reportType == 'daily' && _date != null
          ? _formatDate(_date!)
          : null;
      final range = _reportType != 'daily' && _rangeStart != null && _rangeEnd != null
          ? '${_formatDisplayDate(_rangeStart!)} - ${_formatDisplayDate(_rangeEnd!)}'
          : null;
      await service.generateReport(
        token: token,
        reportType: _reportType,
        date: date,
        range: range,
      );
      await _loadReports();
      setState(() => _message = 'Report generated');
    } catch (e) {
      setState(() => _message = e.toString());
    } finally {
      setState(() => _loading = false);
    }
  }

  Future<void> _pickDate() async {
    final now = DateTime.now();
    final date = await showDatePicker(
      context: context,
      initialDate: _date ?? now,
      firstDate: DateTime(now.year - 2),
      lastDate: DateTime(now.year + 1),
    );
    if (date != null) {
      setState(() => _date = date);
    }
  }

  Future<void> _pickRangeStart() async {
    final now = DateTime.now();
    final date = await showDatePicker(
      context: context,
      initialDate: _rangeStart ?? now,
      firstDate: DateTime(now.year - 2),
      lastDate: DateTime(now.year + 1),
    );
    if (date != null) {
      setState(() => _rangeStart = date);
    }
  }

  Future<void> _pickRangeEnd() async {
    final now = DateTime.now();
    final date = await showDatePicker(
      context: context,
      initialDate: _rangeEnd ?? now,
      firstDate: DateTime(now.year - 2),
      lastDate: DateTime(now.year + 1),
    );
    if (date != null) {
      setState(() => _rangeEnd = date);
    }
  }

  String _formatDate(DateTime date) {
    return '${date.year.toString().padLeft(4, '0')}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';
  }

  String _formatDisplayDate(DateTime date) {
    return '${date.day.toString().padLeft(2, '0')}/${date.month.toString().padLeft(2, '0')}/${date.year.toString().padLeft(4, '0')}';
  }

  Future<void> _deleteReport(ReportItem item) async {
    final token = context.read<AuthProvider>().token;
    if (token == null) return;
    final service = context.read<ReportService>();
    setState(() => _loading = true);
    try {
      await service.deleteReport(token: token, id: item.id);
      await _loadReports();
    } catch (e) {
      setState(() => _message = e.toString());
    } finally {
      setState(() => _loading = false);
    }
  }

  Future<void> _openReport(ReportItem item) async {
    if (item.fileUrl.isEmpty) return;
    final uri = Uri.tryParse(item.fileUrl);
    if (uri == null) return;
    await launchUrl(uri, mode: LaunchMode.externalApplication);
  }

  Future<void> _shareReport(ReportItem item) async {
    if (item.fileUrl.isEmpty) return;
    await Share.share(item.fileUrl, subject: 'Attendance Report');
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Attendance Reports')),
      body: RefreshIndicator(
        onRefresh: _loadReports,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Card(
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Generate Report',
                      style: TextStyle(fontWeight: FontWeight.w600),
                    ),
                    const SizedBox(height: 8),
                    DropdownButtonFormField<String>(
                      value: _reportType,
                      items: const [
                        DropdownMenuItem(value: 'daily', child: Text('Daily')),
                        DropdownMenuItem(value: 'periodic', child: Text('Periodic')),
                        DropdownMenuItem(value: 'timesheet', child: Text('Timesheet')),
                      ],
                      onChanged: (value) {
                        if (value != null) {
                          setState(() => _reportType = value);
                        }
                      },
                      decoration: const InputDecoration(labelText: 'Report Type'),
                    ),
                    const SizedBox(height: 8),
                    if (_reportType == 'daily')
                      OutlinedButton.icon(
                        onPressed: _pickDate,
                        icon: const Icon(Icons.calendar_today),
                        label: Text(
                          _date == null
                              ? 'Select Date'
                              : _formatDate(_date!),
                        ),
                      )
                    else
                      Row(
                        children: [
                          Expanded(
                            child: OutlinedButton.icon(
                              onPressed: _pickRangeStart,
                              icon: const Icon(Icons.calendar_today),
                              label: Text(
                                _rangeStart == null
                                    ? 'Start Date'
                                    : _formatDisplayDate(_rangeStart!),
                              ),
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: OutlinedButton.icon(
                              onPressed: _pickRangeEnd,
                              icon: const Icon(Icons.calendar_today),
                              label: Text(
                                _rangeEnd == null
                                    ? 'End Date'
                                    : _formatDisplayDate(_rangeEnd!),
                              ),
                            ),
                          ),
                        ],
                      ),
                    const SizedBox(height: 8),
                    ElevatedButton.icon(
                      onPressed: _loading ? null : _generate,
                      icon: const Icon(Icons.playlist_add_check),
                      label: const Text('Generate Report'),
                    ),
                    if (_message != null)
                      Padding(
                        padding: const EdgeInsets.only(top: 8),
                        child: Text(
                          _message!,
                          style: const TextStyle(color: Colors.black54),
                        ),
                      ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 12),
            const Text(
              'Generated Reports',
              style: TextStyle(fontWeight: FontWeight.w600),
            ),
            const SizedBox(height: 8),
            if (_reports.isEmpty && !_loading)
              const Text('No reports found.'),
            for (final report in _reports)
              Card(
                child: ListTile(
                  title: Text(report.typeLabel.isNotEmpty
                      ? report.typeLabel
                      : report.reportType),
                  subtitle: Text('${report.dateLabel}\n${report.branchLabel}'),
                  trailing: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      if (report.fileUrl.isNotEmpty)
                        IconButton(
                          icon: const Icon(Icons.open_in_new),
                          onPressed: () => _openReport(report),
                        ),
                      if (report.fileUrl.isNotEmpty)
                        IconButton(
                          icon: const Icon(Icons.share_outlined),
                          onPressed: () => _shareReport(report),
                        ),
                      if (report.canDelete)
                        IconButton(
                          icon: const Icon(Icons.delete_outline),
                          onPressed: () => _deleteReport(report),
                        ),
                    ],
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}
