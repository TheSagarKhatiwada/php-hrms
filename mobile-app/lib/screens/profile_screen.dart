import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../core/constants.dart';
import '../state/auth_provider.dart';
import '../state/settings_provider.dart';

class ProfileScreen extends StatelessWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final profile = auth.profile;

    if (profile == null) {
      return const Center(child: Text('No profile loaded'));
    }

    final cachedImage = auth.profileImageBytes;
    final imageUrl = _resolveUserImage(profile.userImage);
    final active = (profile.status ?? '').toLowerCase() == 'active';

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Container(
          decoration: BoxDecoration(
            border: Border.all(color: Colors.green, width: 3),
            borderRadius: BorderRadius.circular(16),
          ),
          child: ClipRRect(
            borderRadius: BorderRadius.circular(13),
            child: Stack(
              children: [
                SizedBox(
                  height: 220,
                  width: double.infinity,
                  child: cachedImage != null
                      ? Image.memory(cachedImage, fit: BoxFit.cover)
                      : (imageUrl != null
                          ? Image.network(imageUrl, fit: BoxFit.cover)
                          : Container(
                              color: Colors.grey.shade200,
                              alignment: Alignment.center,
                              child: const Icon(Icons.person, size: 72),
                            )),
                ),
                Positioned(
                  right: 12,
                  bottom: 12,
                  child: CircleAvatar(
                    radius: 14,
                    backgroundColor: active ? Colors.green : Colors.red,
                    child: Icon(
                      active ? Icons.check : Icons.close,
                      color: Colors.white,
                      size: 16,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 12),
        Text(
          profile.name,
          textAlign: TextAlign.center,
          style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w700),
        ),
        const SizedBox(height: 4),
        Text(
          profile.designation?.isNotEmpty == true ? profile.designation! : '--',
          textAlign: TextAlign.center,
          style: const TextStyle(fontSize: 15),
        ),
        const SizedBox(height: 2),
        Text(
          profile.branchLabel?.isNotEmpty == true ? profile.branchLabel! : '--',
          textAlign: TextAlign.center,
          style: const TextStyle(fontSize: 15),
        ),
        const SizedBox(height: 12),
        Card(
          child: Column(
            children: [
              ListTile(
                title: const Text('Basic Details'),
                trailing: const Icon(Icons.chevron_right),
                onTap: () => _showDetails(context, 'Basic Details', [
                  'Employee ID: ${profile.empId}',
                  'Email: ${profile.email}',
                  'Join Date: ${profile.joinDate ?? '--'}',
                  'Status: ${profile.status ?? '--'}',
                ]),
              ),
              const Divider(height: 1),
              ListTile(
                title: const Text('Contact Details'),
                trailing: const Icon(Icons.chevron_right),
                onTap: () => _showDetails(context, 'Contact Details', [
                  'Phone: ${profile.phone ?? '--'}',
                  'Address: ${profile.address ?? '--'}',
                ]),
              ),
              const Divider(height: 1),
              ListTile(
                title: const Text('Assigned Details'),
                trailing: const Icon(Icons.chevron_right),
                onTap: () => _showDetails(context, 'Assigned Details', [
                  'Designation: ${profile.designation ?? '--'}',
                  'Branch: ${profile.branchLabel ?? '--'}',
                ]),
              ),
              const Divider(height: 1),
              ListTile(
                title: const Text('Office Contact'),
                trailing: const Icon(Icons.chevron_right),
                onTap: () => _showDetails(context, 'Office Contact', [
                  'Office Email: ${profile.officeEmail ?? '--'}',
                  'Office Phone: ${profile.officePhone ?? '--'}',
                ]),
              ),
              const Divider(height: 1),
              ListTile(
                title: const Text('Theme Selector'),
                trailing: const Icon(Icons.chevron_right),
                onTap: () => _showThemeSelector(context),
              ),
              const Divider(height: 1),
              ListTile(
                title: const Text('Help'),
                trailing: const Icon(Icons.chevron_right),
                onTap: () => _showDetails(context, 'Help', [
                  'For assistance, contact HR or system administrator.',
                ]),
              ),
            ],
          ),
        ),
        const SizedBox(height: 12),
        ElevatedButton.icon(
          onPressed: auth.logout,
          icon: const Icon(Icons.logout),
          label: const Text('Log Out'),
        ),
      ],
    );
  }

  void _showDetails(BuildContext context, String title, List<String> lines) {
    showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      builder: (context) {
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700)),
                const SizedBox(height: 10),
                for (final line in lines) ...[
                  Text(line),
                  const SizedBox(height: 6),
                ],
              ],
            ),
          ),
        );
      },
    );
  }

  void _showThemeSelector(BuildContext context) {
    final settings = context.read<SettingsProvider>();
    showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      builder: (context) {
        return SafeArea(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const ListTile(title: Text('Theme')),
              RadioListTile<ThemeMode>(
                title: const Text('System'),
                value: ThemeMode.system,
                groupValue: settings.themeMode,
                onChanged: (value) {
                  if (value != null) {
                    settings.setThemeMode(value);
                    Navigator.of(context).pop();
                  }
                },
              ),
              RadioListTile<ThemeMode>(
                title: const Text('Light'),
                value: ThemeMode.light,
                groupValue: settings.themeMode,
                onChanged: (value) {
                  if (value != null) {
                    settings.setThemeMode(value);
                    Navigator.of(context).pop();
                  }
                },
              ),
              RadioListTile<ThemeMode>(
                title: const Text('Dark'),
                value: ThemeMode.dark,
                groupValue: settings.themeMode,
                onChanged: (value) {
                  if (value != null) {
                    settings.setThemeMode(value);
                    Navigator.of(context).pop();
                  }
                },
              ),
            ],
          ),
        );
      },
    );
  }

  String? _resolveUserImage(String? path) {
    if (path == null || path.trim().isEmpty) return null;
    final trimmed = path.trim();
    if (trimmed.startsWith('http://') || trimmed.startsWith('https://')) {
      return trimmed;
    }
    final root = apiRootUrl.endsWith('/') ? apiRootUrl.substring(0, apiRootUrl.length - 1) : apiRootUrl;
    final normalized = trimmed.startsWith('/') ? trimmed : '/$trimmed';
    return '$root$normalized';
  }
}
