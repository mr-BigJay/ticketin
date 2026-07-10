const CACHE_NAME = 'ticketin-admin-v4';
const OFFLINE_URLS = [
  '/admin/index.php',
  '/admin/manifest.webmanifest'
];

self.addEventListener('install', function(event){
  event.waitUntil(
    caches.open(CACHE_NAME).then(function(cache){
      return cache.addAll(OFFLINE_URLS).catch(function(){
        return undefined;
      });
    })
  );
  self.skipWaiting();
});

self.addEventListener('activate', function(event){
  event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', function(event){
  if(event.request.method !== 'GET'){
    return;
  }

  event.respondWith(
    fetch(event.request).catch(function(){
      return caches.match(event.request);
    })
  );
});

self.addEventListener('push', function(event){
  let data = {
    title: 'Ticketin Admin',
    body: 'اعلان جدید',
    url: '/admin/',
    tag: 'ticketin-admin'
  };

  if(event.data){
    try{
      data = Object.assign(data, event.data.json());
    }catch(error){
      try{
        data.body = event.data.text();
      }catch(innerError){
      }
    }
  }

  const options = {
    body: data.body || 'اعلان جدید',
    icon: '/admin/icons/icon-192.png',
    badge: '/admin/icons/icon-192.png',
    tag: data.tag || 'ticketin-admin',
    renotify: true,
    requireInteraction: false,
    silent: false,
    vibrate: [120, 60, 120],
    data: {
      url: data.url || '/admin/'
    }
  };

  event.waitUntil(
    self.registration.showNotification(data.title || 'Ticketin Admin', options)
  );
});

self.addEventListener('notificationclick', function(event){
  event.notification.close();

  const targetUrl = (event.notification.data && event.notification.data.url) || '/admin/';

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function(clientList){
      for(const client of clientList){
        if(client.url.indexOf('/admin/') !== -1 && 'focus' in client){
          client.navigate(targetUrl);
          return client.focus();
        }
      }

      if(clients.openWindow){
        return clients.openWindow(targetUrl);
      }

      return undefined;
    })
  );
});
