class ReportItem {
  final int id;
  final String reportType;
  final String typeLabel;
  final String dateLabel;
  final String branchLabel;
  final String employeesLabel;
  final String fileUrl;
  final String generatedAt;
  final bool canDelete;

  ReportItem({
    required this.id,
    required this.reportType,
    required this.typeLabel,
    required this.dateLabel,
    required this.branchLabel,
    required this.employeesLabel,
    required this.fileUrl,
    required this.generatedAt,
    required this.canDelete,
  });

  factory ReportItem.fromJson(Map<String, dynamic> json) {
    return ReportItem(
      id: int.tryParse(json['id']?.toString() ?? '') ?? 0,
      reportType: json['report_type']?.toString() ?? '',
      typeLabel: json['type_label']?.toString() ?? '',
      dateLabel: json['date_label']?.toString() ?? '',
      branchLabel: json['branch_label']?.toString() ?? '',
      employeesLabel: json['employees_label']?.toString() ?? '',
      fileUrl: json['file_url']?.toString() ?? '',
      generatedAt: json['generated_at']?.toString() ?? '',
      canDelete: json['can_delete'] == true || json['can_delete'] == 1,
    );
  }
}
