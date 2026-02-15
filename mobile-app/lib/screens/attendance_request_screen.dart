import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/attendance_request_item.dart';
import '../services/attendance_service.dart';
import '../state/auth_provider.dart';

class AttendanceRequestScreen extends StatefulWidget {
  const AttendanceRequestScreen({
    super.key,
    this.initialStatus = 'pending',
    this.managerMode = false,
  });

  final String initialStatus;
  final bool managerMode;

  @override
  State<AttendanceRequestScreen> createState() => _AttendanceRequestScreenState();
}

class _AttendanceRequestScreenState extends State<AttendanceRequestScreen> {
  bool _loading = false;
  String? _message;
  bool _showForm = false;
  String _statusFilter = 'pending';
  List<AttendanceRequestItem> _requests = [];
  bool _canProcessRequests = false;

  bool _canRequest = false;
  bool _canRequestForOthers = false;
  bool _canRequestMultiBranch = false;

  int? _selectedBranchId;
  String? _selectedEmployeeId;
  String? _selectedReasonCode;
  DateTime? _selectedDate;
  TimeOfDay? _selectedTime;
  final _remarksController = TextEditingController();

  List<Map<String, dynamic>> _branches = [];
  List<Map<String, dynamic>> _employees = [];
  List<Map<String, dynamic>> _reasons = [];

  @override
  void initState() {
    super.initState();
    _statusFilter = widget.initialStatus;
    _showForm = !widget.managerMode;
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      await _loadOptions();
      await _loadRequests();
    });
  }

  @override
  void dispose() {
    _remarksController.dispose();
    super.dispose();
  }

  Future<void> _loadOptions() async {
    final token = context.read<AuthProvider>().token;
    if (token == null) return;
    final service = context.read<AttendanceService>();
    setState(() => _loading = true);
    try {
      final data = await service.fetchRequestOptions(
        token: token,
        branchId: _selectedBranchId,
      );
      if (!mounted) return;
      setState(() {
        _canRequest = data['can_request'] == true;
        _canRequestForOthers = data['can_request_for_others'] == true;
        _canRequestMultiBranch = data['can_request_multi_branch'] == true;
        _selectedBranchId = data['branch_id'] is int
            ? data['branch_id'] as int
            : int.tryParse(data['branch_id']?.toString() ?? '');
        _branches = (data['branches'] as List<dynamic>? ?? [])
            .whereType<Map<String, dynamic>>()
            .toList();
        _employees = (data['employees'] as List<dynamic>? ?? [])
            .whereType<Map<String, dynamic>>()
            .toList();
        _reasons = (data['reasons'] as List<dynamic>? ?? [])
            .whereType<Map<String, dynamic>>()
            .toList();
        if (_employees.isNotEmpty && _selectedEmployeeId == null) {
          _selectedEmployeeId = _employees.first['emp_id']?.toString();
        }
        if (_reasons.isNotEmpty && _selectedReasonCode == null) {
          _selectedReasonCode = _reasons.first['code']?.toString();
        }
      });
    } catch (e) {
      setState(() => _message = e.toString());
    } finally {
      setState(() => _loading = false);
    }
  }

  Future<void> _loadRequests() async {
    final token = context.read<AuthProvider>().token;
    if (token == null) return;
    final service = context.read<AttendanceService>();
    setState(() => _loading = true);
    try {
      final data = await service.fetchRequests(
        token: token,
        status: _statusFilter == 'all' ? null : _statusFilter,
      );
      final canProcess = data['can_process'] == true;
      final rows = data['items'] as List<dynamic>? ?? [];
      setState(() {
        _canProcessRequests = canProcess;
        _requests = rows
            .whereType<Map<String, dynamic>>()
            .map(AttendanceRequestItem.fromJson)
            .toList();
      });
    } catch (e) {
      setState(() => _message = e.toString());
    } finally {
      setState(() => _loading = false);
    }
  }

  Future<void> _reviewRequest(AttendanceRequestItem request, String action) async {
    final token = context.read<AuthProvider>().token;
    if (token == null) return;
    final service = context.read<AttendanceService>();

    setState(() {
      _loading = true;
      _message = null;
    });

    try {
      final result = await service.reviewRequest(
        token: token,
        requestId: request.id,
        action: action,
      );
      final success = result['success'] == true;
      setState(() => _message = result['message']?.toString());
      if (success) {
        await _loadRequests();
      }
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
      initialDate: _selectedDate ?? now,
      firstDate: DateTime(now.year - 1, now.month, now.day),
      lastDate: now,
    );
    if (date != null) {
      setState(() => _selectedDate = date);
    }
  }

  Future<void> _pickTime() async {
    final time = await showTimePicker(
      context: context,
      initialTime: _selectedTime ?? TimeOfDay.now(),
    );
    if (time != null) {
      setState(() => _selectedTime = time);
    }
  }

  String _formatDate(DateTime date) {
    return '${date.year.toString().padLeft(4, '0')}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';
  }

  String _formatTime(TimeOfDay time) {
    final hour = time.hour.toString().padLeft(2, '0');
    final minute = time.minute.toString().padLeft(2, '0');
    return '$hour:$minute:00';
  }

  InputDecoration _dropdownDecoration(String label) {
    return InputDecoration(
      labelText: label,
      isDense: true,
      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
    );
  }

  Future<void> _submit() async {
    if (!_canRequest) {
      setState(() => _message = 'You do not have permission to request attendance.');
      return;
    }
    if (_selectedEmployeeId == null || _selectedEmployeeId!.isEmpty) {
      setState(() => _message = 'Select an employee.');
      return;
    }
    if (_selectedDate == null || _selectedTime == null) {
      setState(() => _message = 'Select date and time.');
      return;
    }
    if (_selectedReasonCode == null || _selectedReasonCode!.isEmpty) {
      setState(() => _message = 'Select a reason.');
      return;
    }

    final token = context.read<AuthProvider>().token;
    if (token == null) return;
    final service = context.read<AttendanceService>();

    setState(() {
      _loading = true;
      _message = null;
    });

    try {
      final date = _formatDate(_selectedDate!);
      final time = _formatTime(_selectedTime!);
      final result = await service.submitRequest(
        token: token,
        employeeId: _selectedEmployeeId!,
        requestDate: date,
        requestTime: time,
        reasonCode: _selectedReasonCode!,
        remarks: _remarksController.text.trim().isEmpty
            ? null
            : _remarksController.text.trim(),
      );
      final success = result['success'] == true;
      setState(() => _message = result['message']?.toString());
      if (success) {
        _remarksController.clear();
        setState(() => _showForm = false);
        await _loadRequests();
      }
    } catch (e) {
      setState(() => _message = e.toString());
    } finally {
      setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Attendance Request')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          if (!_showForm) ...[
            Row(
              children: [
                Expanded(
                  child: DropdownButtonFormField<String>(
                    value: _statusFilter,
                    isExpanded: true,
                    items: const [
                      DropdownMenuItem(value: 'pending', child: Text('Pending')),
                      DropdownMenuItem(value: 'approved', child: Text('Approved')),
                      DropdownMenuItem(value: 'rejected', child: Text('Rejected')),
                      DropdownMenuItem(value: 'cancelled', child: Text('Cancelled')),
                      DropdownMenuItem(value: 'all', child: Text('All')),
                    ],
                    onChanged: (value) {
                      if (value != null) {
                        setState(() => _statusFilter = value);
                        _loadRequests();
                      }
                    },
                    decoration: _dropdownDecoration('Status'),
                  ),
                ),
                const SizedBox(width: 12),
                IconButton(
                  onPressed: _loadRequests,
                  icon: const Icon(Icons.refresh),
                ),
              ],
            ),
            const SizedBox(height: 12),
            if (_requests.isEmpty && !_loading)
              const Text('No attendance requests found.'),
            for (final request in _requests)
              Card(
                child: ListTile(
                  onTap: () => _showRequestTimeline(request),
                  title: Text('${request.employeeName} (${request.employeeId})'),
                  subtitle: Text(
                    '${request.requestDate} ${request.requestTime}\n${request.reasonLabel}\n${request.branchName}',
                  ),
                  trailing: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      _StatusChip(status: request.status),
                      if (_canProcessRequests && request.status == 'pending')
                        Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            IconButton(
                              tooltip: 'Approve',
                              onPressed: _loading
                                  ? null
                                  : () => _reviewRequest(request, 'approve'),
                              icon: const Icon(Icons.check_circle, color: Colors.green),
                            ),
                            IconButton(
                              tooltip: 'Reject',
                              onPressed: _loading
                                  ? null
                                  : () => _reviewRequest(request, 'reject'),
                              icon: const Icon(Icons.cancel, color: Colors.red),
                            ),
                          ],
                        ),
                    ],
                  ),
                ),
              ),
          ] else
            Card(
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Request Attendance',
                      style: TextStyle(fontWeight: FontWeight.w600),
                    ),
                    const SizedBox(height: 8),
                    if (_branches.isNotEmpty)
                      DropdownButtonFormField<int>(
                        value: _selectedBranchId,
                        isExpanded: true,
                        items: _branches
                            .map((branch) => DropdownMenuItem<int>(
                                  value: branch['id'] as int?,
                                  child: Text(branch['name']?.toString() ?? ''),
                                ))
                            .toList(),
                        onChanged: _canRequestMultiBranch
                            ? (value) {
                                setState(() {
                                  _selectedBranchId = value;
                                  _selectedEmployeeId = null;
                                });
                                _loadOptions();
                              }
                            : null,
                        decoration: _dropdownDecoration('Branch'),
                      ),
                    if (_employees.isNotEmpty) const SizedBox(height: 8),
                    if (_employees.isNotEmpty)
                      DropdownButtonFormField<String>(
                        value: _selectedEmployeeId,
                        isExpanded: true,
                        items: _employees
                            .map((employee) => DropdownMenuItem<String>(
                                  value: employee['emp_id']?.toString(),
                                  child: Text(employee['name']?.toString() ?? ''),
                                ))
                            .toList(),
                        onChanged: _canRequestForOthers
                            ? (value) =>
                                setState(() => _selectedEmployeeId = value)
                            : null,
                        decoration: _dropdownDecoration('Employee'),
                      ),
                    const SizedBox(height: 8),
                    OutlinedButton.icon(
                      onPressed: _pickDate,
                      icon: const Icon(Icons.calendar_today),
                      label: Text(
                        _selectedDate == null
                            ? 'Select Date'
                            : _formatDate(_selectedDate!),
                      ),
                    ),
                    const SizedBox(height: 8),
                    OutlinedButton.icon(
                      onPressed: _pickTime,
                      icon: const Icon(Icons.access_time),
                      label: Text(
                        _selectedTime == null
                            ? 'Select Time'
                            : _formatTime(_selectedTime!),
                      ),
                    ),
                    const SizedBox(height: 8),
                    DropdownButtonFormField<String>(
                      value: _selectedReasonCode,
                      isExpanded: true,
                      items: _reasons
                          .map((reason) => DropdownMenuItem<String>(
                                value: reason['code']?.toString(),
                                child: Text(reason['label']?.toString() ?? ''),
                              ))
                          .toList(),
                      onChanged: (value) =>
                          setState(() => _selectedReasonCode = value),
                      decoration: _dropdownDecoration('Reason'),
                    ),
                    const SizedBox(height: 8),
                    TextField(
                      controller: _remarksController,
                      decoration: const InputDecoration(
                        labelText: 'Remarks',
                        hintText: 'Optional contextual notes',
                      ),
                      maxLines: 2,
                    ),
                    const SizedBox(height: 12),
                    ElevatedButton.icon(
                      onPressed: _loading ? null : _submit,
                      icon: const Icon(Icons.send),
                      label: const Text('Submit Request'),
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
          if (!_showForm)
            ElevatedButton.icon(
              onPressed: _canRequest
                  ? () => setState(() => _showForm = true)
                  : null,
              icon: const Icon(Icons.add),
              label: const Text('Request Attendance'),
            ),
          if (_showForm)
            OutlinedButton(
              onPressed: () => setState(() => _showForm = false),
              child: const Text('Back to Requests'),
            ),
        ],
      ),
    );
  }

  void _showRequestTimeline(AttendanceRequestItem request) {
    showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      builder: (context) {
        return SafeArea(
          child: ListView(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
            children: [
              const Text(
                'Request Timeline',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 12),
              _timelineRow(
                icon: Icons.send,
                color: Colors.blue,
                title: 'Requested',
                detail: '${request.requestedBy.isEmpty ? request.employeeName : request.requestedBy}\n${request.createdAt.isEmpty ? '--' : request.createdAt}',
              ),
              _timelineRow(
                icon: request.status == 'approved'
                    ? Icons.check_circle
                    : request.status == 'rejected'
                        ? Icons.cancel
                        : Icons.hourglass_top,
                color: request.status == 'approved'
                    ? Colors.green
                    : request.status == 'rejected'
                        ? Colors.red
                        : Colors.orange,
                title: 'Status: ${request.status}',
                detail:
                    '${request.reviewedBy.isEmpty ? 'Reviewer: --' : 'Reviewer: ${request.reviewedBy}'}\n${request.reviewedAt.isEmpty ? 'Reviewed at: --' : 'Reviewed at: ${request.reviewedAt}'}\n${request.reviewNotes.isEmpty ? 'Notes: --' : 'Notes: ${request.reviewNotes}'}',
              ),
              if (request.remarks.isNotEmpty)
                _timelineRow(
                  icon: Icons.notes,
                  color: Colors.grey,
                  title: 'Remarks',
                  detail: request.remarks,
                ),
            ],
          ),
        );
      },
    );
  }

  Widget _timelineRow({
    required IconData icon,
    required Color color,
    required String title,
    required String detail,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          CircleAvatar(
            radius: 14,
            backgroundColor: color.withOpacity(0.15),
            child: Icon(icon, size: 16, color: color),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(fontWeight: FontWeight.w600)),
                const SizedBox(height: 2),
                Text(detail),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _StatusChip extends StatelessWidget {
  const _StatusChip({required this.status});

  final String status;

  @override
  Widget build(BuildContext context) {
    Color color;
    switch (status) {
      case 'approved':
        color = Colors.green;
        break;
      case 'rejected':
        color = Colors.red;
        break;
      case 'cancelled':
        color = Colors.grey;
        break;
      default:
        color = Colors.orange;
    }
    return Chip(
      label: Text(status),
      backgroundColor: color.withOpacity(0.15),
      labelStyle: TextStyle(color: color),
    );
  }
}
