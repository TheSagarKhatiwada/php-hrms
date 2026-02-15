class AppSettings {
  final String appName;
  final String? primaryColor;
  final String? secondaryColor;
  final String? companyLogo;

  AppSettings({
    required this.appName,
    required this.primaryColor,
    required this.secondaryColor,
    required this.companyLogo,
  });

  factory AppSettings.fromJson(Map<String, dynamic> json) {
    return AppSettings(
      appName: json['app_name']?.toString() ?? 'HRMS-App',
      primaryColor: json['primary_color']?.toString(),
      secondaryColor: json['secondary_color']?.toString(),
      companyLogo: json['company_logo']?.toString(),
    );
  }
}
