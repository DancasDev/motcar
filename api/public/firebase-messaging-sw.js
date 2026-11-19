importScripts('https://www.gstatic.com/firebasejs/10.12.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.12.0/firebase-messaging-compat.js');

firebase.initializeApp({
    apiKey: "AIzaSyB3PJ2ijtSjb6Zf9TOfSZTBbtYw8akybAw",
    authDomain: "notificaciones-api-49491.firebaseapp.com",
    projectId: "notificaciones-api-49491",
    storageBucket: "notificaciones-api-49491.firebasestorage.app",
    messagingSenderId: "606543124875",
    appId: "1:606543124875:web:789869e9c9da438c84378e",
});

const messaging = firebase.messaging();

messaging.onBackgroundMessage((payload) => {
    console.log('[firebase-messaging-sw.js] Notificación Push recibida en segundo plano:', payload);
    // Si el payload ya incluye el objeto 'notification', el SDK de Firebase la muestra automáticamente.
    // Solo llamamos a showNotification si es un mensaje data-only para evitar duplicados.
    if (!payload.notification && payload.data) {
        const title = payload.data.title || '🔔 Notificación';
        const options = {
            body: payload.data.body || 'Has recibido una nueva notificación.',
            icon: 'https://cdn-icons-png.flaticon.com/512/1827/1827392.png',
            badge: 'https://cdn-icons-png.flaticon.com/512/1827/1827392.png',
            tag: payload.data.tag || payload.data.timestamp || 'single-push-tag',
            data: payload.data || {},
        };
        self.registration.showNotification(title, options);
    }
});

self.addEventListener('notificationclick', function(event) {
    event.notification.close();
    const actionUrl = event.notification.data?.action_url 
        || event.notification.data?.url 
        || event.notification.data?.link 
        || event.notification.data?.FCM_MSG?.notification?.click_action
        || null;

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function(clientList) {
            if (actionUrl) {
                for (let client of clientList) {
                    if (client.url === actionUrl && 'focus' in client) {
                        return client.focus();
                    }
                }
                if (clients.openWindow) {
                    return clients.openWindow(actionUrl);
                }
            } else if (clientList.length > 0) {
                return clientList[0].focus();
            }
        })
    );
});
