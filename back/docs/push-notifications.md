# Push Notifications (FCM + Laravel Notifications)

## 1) Instalar dependencia

```bash
cd back
composer install
```

Si no existe en `vendor`, instala:

```bash
cd back
composer require laravel-notification-channels/fcm -W
```

## 2) Variables de entorno

En `.env`:

```env
FIREBASE_CREDENTIALS=/absolute/path/to/firebase-service-account.json
FIREBASE_PROJECT_ID=your-firebase-project-id
```

`FIREBASE_CREDENTIALS` debe apuntar al JSON de Service Account de Firebase.

## 3) Endpoint de ejemplo (Laravel)

`POST /api/v1/notifications/push/device` (requiere `auth:api`)

Body:

```json
{
  "token": "FCM_DEVICE_TOKEN",
  "title": "Pedido confirmado",
  "body": "Tu pedido #1001 está en preparación",
  "data": {
    "type": "order_update",
    "order_id": "1001"
  }
}
```

## 4) Ejemplo Flutter (enviar token al backend y recibir push)

Dependencia:

```yaml
firebase_messaging: ^15.1.0
```

Código:

```dart
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';

Future<void> registerPushToken(String apiToken) async {
  final messaging = FirebaseMessaging.instance;

  await messaging.requestPermission();
  final fcmToken = await messaging.getToken();
  if (fcmToken == null) return;

  await http.post(
    Uri.parse('https://tu-api.com/api/v1/notifications/push/device'),
    headers: {
      'Authorization': 'Bearer $apiToken',
      'Content-Type': 'application/json',
    },
    body: jsonEncode({
      'token': fcmToken,
      'title': 'Hola desde Laravel',
      'body': 'Este push llegó por FCM channel',
      'data': {
        'screen': 'orders',
        'id': '1001',
      },
    }),
  );
}

void setupForegroundListener() {
  FirebaseMessaging.onMessage.listen((RemoteMessage message) {
    // Maneja la notificación en foreground.
    print('Push title: ${message.notification?.title}');
    print('Push body: ${message.notification?.body}');
    print('Push data: ${message.data}');
  });
}
```
