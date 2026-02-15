class LeaveItem {
  final int id;
  final String leaveTypeName;
  final String startDate;
  final String endDate;
  final String status;
  final String reason;
  final String appliedDate;

  LeaveItem({
    required this.id,
    required this.leaveTypeName,
    required this.startDate,
    required this.endDate,
    required this.status,
    required this.reason,
    required this.appliedDate,
  });

  factory LeaveItem.fromJson(Map<String, dynamic> json) {
    return LeaveItem(
      id: int.tryParse(json['id']?.toString() ?? '') ?? 0,
      leaveTypeName: json['leave_type_name']?.toString() ?? '',
      startDate: json['start_date']?.toString() ?? '',
      endDate: json['end_date']?.toString() ?? '',
      status: json['status']?.toString() ?? 'pending',
      reason: json['reason']?.toString() ?? '',
      appliedDate: json['applied_date']?.toString() ?? '',
    );
  }
}
