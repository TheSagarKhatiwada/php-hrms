import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/leave_item.dart';
import '../services/leave_service.dart';
import '../state/auth_provider.dart';

class LeaveScreen extends StatefulWidget {
  const LeaveScreen({super.key});

  @override
  State<LeaveScreen> createState() => _LeaveScreenState();
}

class _LeaveScreenState extends State<LeaveScreen> {
  bool _loading = false;
  String? _message;
  int? _leaveTypeId;
  DateTimeRange? _range;
  final TextEditingController _reasonController = TextEditingController();
  List<Map<String, dynamic>> _types = [];
  List<LeaveItem> _items = [];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  @override
  void dispose() {
    _reasonController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    final token = context.read<AuthProvider>().token;
    if (token == null) return;
    final service = context.read<LeaveService>();
    setState(() => _loading = true);
    try {
      final options = await service.fetchOptions(token: token);
      final items = await service.fetchRequests(token: token);
      setState(() {
        _types = (options['types'] as List<dynamic>? ?? []).whereType<Map<String, dynamic>>().toList();
        if (_types.isNotEmpty && _leaveTypeId == null) {
          _leaveTypeId = int.tryParse(_types.first['id']?.toString() ?? '');
        }
        _items = items;
      });
    } catch (e) {
      setState(() => _message = e.toString());
    } finally {
      setState(() => _loading = false);
    }
  }

  Future<void> _pickRange() async {
    final now = DateTime.now();
    final picked = await showDateRangePicker(
      context: context,
      firstDate: DateTime(now.year - 1),
      lastDate: DateTime(now.year + 1),
      initialDateRange: _range,
    );
    if (picked != null) {
      setState(() => _range = picked);
    }
  }

  String _fmt(DateTime d) => '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

  Future<void> _submit() async {
    final token = context.read<AuthProvider>().token;
    if (token == null) return;
    if (_leaveTypeId == null || _range == null || _reasonController.text.trim().length < 5) {
      setState(() => _message = 'Select leave type, date range, and valid reason.');
      return;
    }
    final service = context.read<LeaveService>();
    setState(() {
      _loading = true;
      _message = null;
    });
    try {
      final result = await service.submitRequest(
        token: token,
        leaveTypeId: _leaveTypeId!,
        startDate: _fmt(_range!.start),
        endDate: _fmt(_range!.end),
        reason: _reasonController.text.trim(),
      );
      setState(() => _message = result['message']?.toString() ?? 'Submitted');
      _reasonController.clear();
      await _load();
    } catch (e) {
      setState(() => _message = e.toString());
    } finally {
      setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Leave Request')),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Card(
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Apply Leave', style: TextStyle(fontWeight: FontWeight.w600)),
                    const SizedBox(height: 8),
                    DropdownButtonFormField<int>(
                      value: _leaveTypeId,
                      isExpanded: true,
                      items: _types
                          .map((t) => DropdownMenuItem<int>(
                                value: int.tryParse(t['id']?.toString() ?? ''),
                                child: Text(t['name']?.toString() ?? ''),
                              ))
                          .toList(),
                      onChanged: (v) => setState(() => _leaveTypeId = v),
                      decoration: const InputDecoration(labelText: 'Leave Type'),
                    ),
                    const SizedBox(height: 8),
                    OutlinedButton.icon(
                      onPressed: _pickRange,
                      icon: const Icon(Icons.date_range),
                      label: Text(_range == null ? 'Select Date Range' : '${_fmt(_range!.start)} to ${_fmt(_range!.end)}'),
                    ),
                    const SizedBox(height: 8),
                    TextField(
                      controller: _reasonController,
                      minLines: 2,
                      maxLines: 3,
                      decoration: const InputDecoration(labelText: 'Reason'),
                    ),
                    const SizedBox(height: 8),
                    ElevatedButton.icon(
                      onPressed: _loading ? null : _submit,
                      icon: const Icon(Icons.send),
                      label: const Text('Submit'),
                    ),
                    if (_message != null) ...[
                      const SizedBox(height: 8),
                      Text(_message!),
                    ]
                  ],
                ),
              ),
            ),
            const SizedBox(height: 12),
            const Text('My Leave Requests', style: TextStyle(fontWeight: FontWeight.w600)),
            const SizedBox(height: 8),
            if (_items.isEmpty && !_loading) const Text('No leave requests found.'),
            for (final item in _items)
              Card(
                child: ListTile(
                  title: Text(item.leaveTypeName),
                  subtitle: Text('${item.startDate} to ${item.endDate}\n${item.reason}'),
                  trailing: Text(item.status),
                ),
              ),
          ],
        ),
      ),
    );
  }
}
