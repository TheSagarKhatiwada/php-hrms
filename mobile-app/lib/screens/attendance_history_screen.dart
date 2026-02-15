import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../services/attendance_service.dart';
import '../state/attendance_provider.dart';
import '../state/auth_provider.dart';

class AttendanceHistoryScreen extends StatefulWidget {
  const AttendanceHistoryScreen({super.key});

  @override
  State<AttendanceHistoryScreen> createState() => _AttendanceHistoryScreenState();
}

class _AttendanceHistoryScreenState extends State<AttendanceHistoryScreen> {
  DateTime? _startDate;
  DateTime? _endDate;
  DateTimeRange? _dateRange;
  bool _canViewAllBranches = false;
  bool _canViewEmployees = false;
  int? _selectedBranchId;
  String? _selectedEmployeeId;
  List<Map<String, dynamic>> _branches = [];
  List<Map<String, dynamic>> _employees = [];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load() async {
    final token = context.read<AuthProvider>().token;
    if (token == null) return;
    await context.read<AttendanceProvider>().loadHistory(token: token, days: 14);
    await _loadFilters();
  }

  Future<void> _loadRange() async {
    final token = context.read<AuthProvider>().token;
    if (token == null) return;
    final startDate = _dateRange?.start ?? _startDate;
    final endDate = _dateRange?.end ?? _endDate;
    final start = startDate == null ? null : _formatDate(startDate);
    final end = endDate == null ? null : _formatDate(endDate);
    await context
        .read<AttendanceProvider>()
        .loadHistory(
          token: token,
          start: start,
          end: end,
          branchId: _selectedBranchId,
          employeeId: _selectedEmployeeId,
        );
  }

  Future<void> _loadFilters() async {
    final token = context.read<AuthProvider>().token;
    if (token == null) return;
    final service = context.read<AttendanceService>();
    final data = await service.fetchFilters(
      token: token,
      branchId: _selectedBranchId,
    );
    if (!mounted) return;
    setState(() {
      _canViewAllBranches = data['can_view_all_branches'] == true;
      _canViewEmployees = data['can_view_employees'] == true;
      _selectedBranchId = data['branch_id'] is int
          ? data['branch_id'] as int
          : int.tryParse(data['branch_id']?.toString() ?? '');
      _branches = (data['branches'] as List<dynamic>? ?? [])
          .whereType<Map<String, dynamic>>()
          .toList();
      _employees = (data['employees'] as List<dynamic>? ?? [])
          .whereType<Map<String, dynamic>>()
          .toList();
    });
  }

  Future<void> _pickDateRange() async {
    final now = DateTime.now();
    final picked = await showDateRangePicker(
      context: context,
      initialDateRange: _dateRange,
      firstDate: DateTime(now.year - 2),
      lastDate: DateTime(now.year + 1),
    );
    if (picked != null) {
      setState(() {
        _dateRange = picked;
        _startDate = picked.start;
        _endDate = picked.end;
      });
    }
  }

  String _formatRange(DateTimeRange range) {
    return '${_formatDate(range.start)} to ${_formatDate(range.end)}';
  }

  String _formatDate(DateTime date) {
    return '${date.year.toString().padLeft(4, '0')}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';
  }

  @override
  Widget build(BuildContext context) {
    final attendance = context.watch<AttendanceProvider>();
    final records = attendance.history;
    final latest = records.isNotEmpty ? records.first.date : '--';
    final oldest = records.isNotEmpty ? records.last.date : '--';
    final monthCounts = <String, int>{};
    for (final record in records) {
      final date = record.date;
      final month = date.length >= 7 ? date.substring(0, 7) : date;
      monthCounts[month] = (monthCounts[month] ?? 0) + 1;
    }
    final monthEntries = monthCounts.entries.toList()
      ..sort((a, b) => b.key.compareTo(a.key));

    if (records.isEmpty && !attendance.loading) {
      return RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: const [
            Text(
              'Attendance History',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
            ),
            SizedBox(height: 8),
            Text('No attendance records yet. Pull to refresh.'),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.builder(
        padding: const EdgeInsets.all(16),
        itemCount: records.length + 3,
        itemBuilder: (context, index) {
          if (index == 0) {
            return Card(
              child: ListTile(
                title: const Text('Summary'),
                subtitle: Text(
                  'Records: ${records.length}\nRange: $oldest to $latest',
                ),
              ),
            );
          }
          if (index == 1) {
            return Card(
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Filter by Date Range',
                      style: TextStyle(fontWeight: FontWeight.w600),
                    ),
                    const SizedBox(height: 8),
                    if (_canViewAllBranches || _canViewEmployees)
                      Column(
                        children: [
                          if (_canViewAllBranches)
                            DropdownButtonFormField<int>(
                              value: _selectedBranchId,
                              items: _branches
                                  .map((branch) => DropdownMenuItem<int>(
                                        value: branch['id'] as int?,
                                        child: Text(
                                          branch['name']?.toString() ?? 'Branch',
                                        ),
                                      ))
                                  .toList(),
                              onChanged: _canViewAllBranches
                                  ? (value) {
                                      setState(() {
                                        _selectedBranchId = value;
                                        _selectedEmployeeId = null;
                                      });
                                      _loadFilters();
                                    }
                                  : null,
                              decoration: const InputDecoration(
                                labelText: 'Branch',
                              ),
                            ),
                          if (_canViewEmployees) const SizedBox(height: 8),
                          if (_canViewEmployees)
                            DropdownButtonFormField<String>(
                              value: _selectedEmployeeId,
                              items: _employees
                                  .map((employee) => DropdownMenuItem<String>(
                                        value: employee['emp_id']?.toString(),
                                        child: Text(
                                          employee['name']?.toString() ?? 'Employee',
                                        ),
                                      ))
                                  .toList(),
                              onChanged: _canViewEmployees
                                  ? (value) {
                                      setState(() => _selectedEmployeeId = value);
                                    }
                                  : null,
                              decoration: const InputDecoration(
                                labelText: 'Employee',
                              ),
                            ),
                          const SizedBox(height: 8),
                        ],
                      ),
                    Row(
                      children: [
                        Expanded(
                          child: OutlinedButton.icon(
                            onPressed: _pickDateRange,
                            icon: const Icon(Icons.date_range),
                            label: Text(
                              _dateRange == null
                                  ? 'Select Date Range'
                                  : _formatRange(_dateRange!),
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        Expanded(
                          child: ElevatedButton.icon(
                            onPressed: _loadRange,
                            icon: const Icon(Icons.search),
                            label: const Text('Apply Filter'),
                          ),
                        ),
                        const SizedBox(width: 12),
                        OutlinedButton(
                          onPressed: () {
                            setState(() {
                              _dateRange = null;
                              _startDate = null;
                              _endDate = null;
                              _selectedEmployeeId = null;
                              if (!_canViewAllBranches) {
                                _selectedBranchId = _selectedBranchId;
                              } else {
                                _selectedBranchId = null;
                              }
                            });
                            _load();
                          },
                          child: const Text('Reset'),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            );
          }
          if (index == 2) {
            return Card(
              child: ListTile(
                title: const Text('Monthly Snapshot'),
                subtitle: Text(
                  monthEntries.isEmpty
                      ? 'No data'
                      : monthEntries
                          .take(3)
                          .map((e) => '${e.key}: ${e.value} days')
                          .join('\n'),
                ),
              ),
            );
          }
          final record = records[index - 3];
          return Card(
            child: ListTile(
              title: Text(record.date),
              subtitle: Text('In: ${record.inTime ?? '--'}  Out: ${record.outTime ?? '--'}'),
              trailing: Text('${record.punchCount} logs'),
            ),
          );
        },
      ),
    );
  }
}
