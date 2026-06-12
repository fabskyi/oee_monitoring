// Service Worker — handles background push notifications for OEE Chat
self.addEventListener('install', function(e){ self.skipWaiting(); });
self.addEventListener('activate', function(e){ e.waitUntil(clients.claim()); });

self.addEventListener('message', function(e){
    var data = e.data;
    if(!data || data.type !== 'CHAT_NOTIFY') return;

    var options = {
        body   : data.body,
        icon   : data.icon || '/oee/img/yanmar.png',
        badge  : '/oee/img/yanmar.png',
        tag    : data.tag || 'oee-chat',
        renotify: true,
        vibrate: [200, 100, 200],
        data   : { url: data.url || '/oee/', mode: data.mode, uid: data.uid },
        actions: [
            { action: 'open',    title: 'Buka Chat' },
            { action: 'dismiss', title: 'Tutup'     }
        ]
    };

    e.waitUntil(self.registration.showNotification(data.title, options));
});

self.addEventListener('notificationclick', function(e){
    e.notification.close();
    var data = e.notification.data || {};

    if(e.action === 'dismiss') return;

    e.waitUntil(
        clients.matchAll({ type:'window', includeUncontrolled:true }).then(function(list){
            // Focus existing tab if open
            for(var i=0; i<list.length; i++){
                var c = list[i];
                if(c.url.indexOf('/oee/') !== -1 && 'focus' in c){
                    c.focus();
                    c.postMessage({ type:'OPEN_CHAT', mode: data.mode, uid: data.uid });
                    return;
                }
            }
            // Open new tab
            if(clients.openWindow){
                return clients.openWindow(data.url || '/oee/');
            }
        })
    );
});
