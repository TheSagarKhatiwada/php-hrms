class UserProfile {
  final String empId;
  final String name;
  final String email;
  final String? officeEmail;
  final String? phone;
  final String? officePhone;
  final String? address;
  final String? joinDate;
  final String? status;
  final String? userImage;
  final String? designation;
  final String? branchLabel;
  final int? roleId;
  final bool canProcessAttendanceRequests;
  final bool canViewReports;
  final bool canManageEmployees;

  UserProfile({
    required this.empId,
    required this.name,
    required this.email,
    required this.officeEmail,
    required this.phone,
    required this.officePhone,
    required this.address,
    required this.joinDate,
    required this.status,
    required this.userImage,
    required this.designation,
    required this.branchLabel,
    required this.roleId,
    required this.canProcessAttendanceRequests,
    required this.canViewReports,
    required this.canManageEmployees,
  });

  factory UserProfile.fromJson(Map<String, dynamic> json) {
    return UserProfile(
      empId: json['emp_id']?.toString() ?? '',
      name: json['name']?.toString() ?? '',
      email: json['email']?.toString() ?? '',
      officeEmail: json['office_email']?.toString(),
      phone: json['phone']?.toString(),
      officePhone: json['office_phone']?.toString(),
      address: json['address']?.toString(),
      joinDate: json['join_date']?.toString(),
      status: json['status']?.toString(),
      userImage: json['user_image']?.toString(),
      designation: json['designation']?.toString(),
      branchLabel: json['branch_label']?.toString(),
      roleId: int.tryParse(json['role_id']?.toString() ?? ''),
      canProcessAttendanceRequests: json['can_process_attendance_requests'] == true || json['can_process_attendance_requests'] == 1,
      canViewReports: json['can_view_reports'] == true || json['can_view_reports'] == 1,
      canManageEmployees: json['can_manage_employees'] == true || json['can_manage_employees'] == 1,
    );
  }
}
