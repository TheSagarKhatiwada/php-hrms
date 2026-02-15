class AttendanceRequestItem {
  final int id;
  final String employeeName;
  final String employeeId;
  final String branchName;
  final String requestDate;
  final String requestTime;
  final String reasonLabel;
  final String status;
  final String requestedBy;
  final String remarks;
  final String createdAt;
  final String reviewedAt;
  final String reviewNotes;
  final String reviewedBy;

  AttendanceRequestItem({
    required this.id,
    required this.employeeName,
    required this.employeeId,
    required this.branchName,
    required this.requestDate,
    required this.requestTime,
    required this.reasonLabel,
    required this.status,
    required this.requestedBy,
    required this.remarks,
    required this.createdAt,
    required this.reviewedAt,
    required this.reviewNotes,
    required this.reviewedBy,
  });

  factory AttendanceRequestItem.fromJson(Map<String, dynamic> json) {
    return AttendanceRequestItem(
      id: int.tryParse(json['id']?.toString() ?? '') ?? 0,
      employeeName: json['employee_name']?.toString() ?? '',
      employeeId: json['employee_id']?.toString() ?? '',
      branchName: json['branch_name']?.toString() ?? '',
      requestDate: json['request_date']?.toString() ?? '',
      requestTime: json['request_time']?.toString() ?? '',
      reasonLabel: json['reason_label']?.toString() ?? '',
      status: json['status']?.toString() ?? 'pending',
      requestedBy: json['requested_by_name']?.toString() ?? '',
      remarks: json['remarks']?.toString() ?? '',
      createdAt: json['created_at']?.toString() ?? '',
      reviewedAt: json['reviewed_at']?.toString() ?? '',
      reviewNotes: json['review_notes']?.toString() ?? '',
      reviewedBy: json['reviewed_by_name']?.toString() ?? '',
    );
  }
}
