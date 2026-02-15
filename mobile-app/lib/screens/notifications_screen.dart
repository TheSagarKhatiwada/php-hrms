import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../state/auth_provider.dart';
import '../state/notification_provider.dart';

class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key});

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load() async {
    final token = context.read<AuthProvider>().token;
    if (token == null) return;
    await context.read<NotificationProvider>().load(token: token);
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<NotificationProvider>();
    final token = context.read<AuthProvider>().token;

    if (provider.items.isEmpty && !provider.loading) {
      return Scaffold(
        appBar: AppBar(
          title: const Text('Notifications'),
          actions: [
            IconButton(
              icon: const Icon(Icons.done_all),
              onPressed: token == null
                  ? null
                  : () => provider.markAllRead(token: token),
            ),
          ],
        ),
        body: RefreshIndicator(
          onRefresh: _load,
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: const [
              Text('No alerts yet. Pull to refresh.'),
            ],
          ),
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(
        title: const Text('Notifications'),
        actions: [
          IconButton(
            icon: const Icon(Icons.done_all),
            onPressed:
                token == null ? null : () => provider.markAllRead(token: token),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView.builder(
          padding: const EdgeInsets.all(16),
          itemCount: provider.items.length,
          itemBuilder: (context, index) {
            final item = provider.items[index];
            return Card(
              child: ListTile(
                title: Text(item.title),
                subtitle: Text('${item.message}\n${item.createdAt}'),
                trailing: IconButton(
                  icon: const Icon(Icons.check),
                  onPressed: token == null
                      ? null
                      : () => provider.markRead(token: token, id: item.id),
                ),
              ),
            );
          },
        ),
      ),
    );
  }
}
