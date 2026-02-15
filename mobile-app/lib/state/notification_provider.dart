import 'package:flutter/foundation.dart';

import '../models/notification_item.dart';
import '../services/notification_service.dart';

class NotificationProvider extends ChangeNotifier {
  NotificationProvider(this._service);

  final NotificationService _service;

  List<NotificationItem> _items = [];
  bool _loading = false;

  List<NotificationItem> get items => _items;
  bool get loading => _loading;

  Future<void> load({required String token}) async {
    _loading = true;
    notifyListeners();
    try {
      _items = await _service.fetchNotifications(token: token);
    } finally {
      _loading = false;
      notifyListeners();
    }
  }

  Future<void> markRead({required String token, required int id}) async {
    await _service.markRead(token: token, id: id);
    _items = _items.map((item) {
      if (item.id == id) {
        return NotificationItem(
          id: item.id,
          title: item.title,
          message: item.message,
          type: item.type,
          link: item.link,
          isRead: true,
          createdAt: item.createdAt,
        );
      }
      return item;
    }).toList();
    notifyListeners();
  }

  Future<void> markAllRead({required String token}) async {
    await _service.markAllRead(token: token);
    _items = _items
        .map((item) => NotificationItem(
              id: item.id,
              title: item.title,
              message: item.message,
              type: item.type,
              link: item.link,
              isRead: true,
              createdAt: item.createdAt,
            ))
        .toList();
    notifyListeners();
  }

  Future<void> delete({required String token, required int id}) async {
    await _service.delete(token: token, id: id);
    _items = _items.where((item) => item.id != id).toList();
    notifyListeners();
  }
}
