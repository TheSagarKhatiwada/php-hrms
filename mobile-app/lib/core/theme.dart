import 'package:flutter/material.dart';

ThemeData buildAppTheme({
  Color? primary,
  Color? secondary,
  Brightness brightness = Brightness.light,
}) {
  const fallback = Color(0xFF1565C0);
  final seed = primary ?? fallback;
  final scheme = ColorScheme.fromSeed(
    seedColor: seed,
    secondary: secondary,
    brightness: brightness,
  );
  final isDark = brightness == Brightness.dark;

  return ThemeData(
    colorScheme: scheme,
    useMaterial3: true,
    scaffoldBackgroundColor: isDark ? const Color(0xFF0F1115) : const Color(0xFFF6F7FB),
    appBarTheme: AppBarTheme(
      backgroundColor: isDark ? const Color(0xFF0F1115) : Colors.white,
      foregroundColor: isDark ? Colors.white : const Color(0xFF1C1C1C),
      elevation: 0.5,
      centerTitle: false,
    ),
    cardTheme: CardThemeData(
      color: isDark ? const Color(0xFF161A20) : Colors.white,
      elevation: 1,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
    ),
    inputDecorationTheme: InputDecorationTheme(
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
      ),
      filled: true,
      fillColor: isDark ? const Color(0xFF161A20) : Colors.white,
    ),
    bottomNavigationBarTheme: BottomNavigationBarThemeData(
      backgroundColor: isDark ? const Color(0xFF0F1115) : Colors.white,
      selectedItemColor: scheme.primary,
      unselectedItemColor: isDark ? const Color(0xFF9AA4B2) : const Color(0xFF7A8699),
      selectedIconTheme: IconThemeData(color: scheme.primary),
      unselectedIconTheme:
          IconThemeData(color: isDark ? const Color(0xFF9AA4B2) : const Color(0xFF7A8699)),
      showUnselectedLabels: true,
      type: BottomNavigationBarType.fixed,
    ),
  );
}
