import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';

class GeofenceMap extends StatelessWidget {
  const GeofenceMap({
    super.key,
    required this.center,
    required this.radiusMeters,
  });

  final LatLng center;
  final double radiusMeters;

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(16),
      child: FlutterMap(
        options: MapOptions(
          initialCenter: center,
          initialZoom: 16,
          interactionOptions: const InteractionOptions(flags: InteractiveFlag.all),
        ),
        children: [
          TileLayer(
            urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
            userAgentPackageName: 'com.hrms.hrms_app',
          ),
          CircleLayer(
            circles: [
              CircleMarker(
                point: center,
                color: const Color(0x331565C0),
                borderColor: const Color(0xFF1565C0),
                borderStrokeWidth: 2,
                radius: radiusMeters,
              )
            ],
          ),
          MarkerLayer(
            markers: [
              Marker(
                point: center,
                width: 40,
                height: 40,
                child: const Icon(Icons.location_on, color: Color(0xFF1565C0)),
              )
            ],
          ),
        ],
      ),
    );
  }
}
