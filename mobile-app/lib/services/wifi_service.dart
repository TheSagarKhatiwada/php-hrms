import 'package:network_info_plus/network_info_plus.dart';

class WifiService {
  final NetworkInfo _info = NetworkInfo();

  Future<String?> getWifiName() async {
    final name = await _info.getWifiName();
    if (name == null) return null;
    return name.replaceAll('"', '');
  }

  Future<String?> getWifiBssid() async {
    final bssid = await _info.getWifiBSSID();
    if (bssid == null) return null;
    return bssid.toLowerCase();
  }
}
