class AttendanceRecord {
  final String date;
  final String? inTime;
  final String? outTime;
  final int punchCount;

  AttendanceRecord({
    required this.date,
    required this.inTime,
    required this.outTime,
    required this.punchCount,
  });

  factory AttendanceRecord.fromJson(Map<String, dynamic> json) {
    return AttendanceRecord(
      date: json['date']?.toString() ?? '',
      inTime: json['in_time']?.toString(),
      outTime: json['out_time']?.toString(),
      punchCount: int.tryParse(json['punch_count']?.toString() ?? '') ?? 0,
    );
  }
}
