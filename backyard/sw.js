'use strict';
// Deliberately no page caching: authenticated pages and CSRF tokens stay private.
self.addEventListener('push',event=>{
  let data={};try{data=event.data.json();}catch{}
  event.waitUntil(self.registration.showNotification('LLU 27 – Supporter Zone',{body:data.body||'Deine Supporter feuern dich an!',icon:new URL('assets/icon-192.png',self.registration.scope).href,tag:'llu27-cheer',data:{url:new URL('dashboard.php#supporter-cheers',self.registration.scope).href}}));
});
self.addEventListener('notificationclick',event=>{
  event.notification.close();
  const url=new URL('dashboard.php#supporter-cheers',self.registration.scope).href;
  event.waitUntil(clients.matchAll({type:'window',includeUncontrolled:true}).then(async list=>{for(const client of list){if(client.url.startsWith(self.registration.scope)){await client.navigate(url);return client.focus();}}return clients.openWindow(url);}));
});
