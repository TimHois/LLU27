document.querySelectorAll('[data-push]').forEach(panel=>{
  const status=panel.querySelector('[data-status]');
  const supported=isSecureContext&&'serviceWorker' in navigator&&'PushManager' in window&&'Notification' in window;
  if(!supported){status.textContent='Push wird hier nicht unterstützt. Alle übrigen Funktionen bleiben verfügbar.';panel.querySelector('[data-enable]').disabled=true;}
  const call=async(action,subscription)=>{
    const r=await fetch(panel.dataset.api,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':panel.dataset.csrf},body:JSON.stringify({action,subscription})});
    const data=await r.json();if(!r.ok)throw Error(data.error||'Push-Anfrage fehlgeschlagen.');
  };
  panel.querySelector('[data-enable]').addEventListener('click',async()=>{
    try {
      if(!panel.dataset.key)throw Error('Push ist noch nicht eingerichtet.');
      // Permission is requested only by this explicit user gesture.
      if(await Notification.requestPermission()!=='granted')throw Error('Keine Zustimmung. Du kannst Push später in den Browsereinstellungen erlauben.');
      const reg=await navigator.serviceWorker.register(panel.dataset.sw);await navigator.serviceWorker.ready;
      const key=panel.dataset.key;const bytes=Uint8Array.from(atob(key.replace(/-/g,'+').replace(/_/g,'/')+'='.repeat((4-key.length%4)%4)),c=>c.charCodeAt(0));
      const sub=await reg.pushManager.getSubscription()||await reg.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:bytes});
      try{await call('enable',sub.toJSON());}catch(e){await sub.unsubscribe();throw e;}
      status.textContent='Push auf diesem Gerät aktiviert.';
    } catch(e){status.textContent=e.message;}
  });
  const disable=async(all)=>{
    try {
      const reg=supported?await navigator.serviceWorker.getRegistration(panel.dataset.sw):null;
      const sub=reg?await reg.pushManager.getSubscription():null;
      if(all)await call('disable-all');else if(sub)await call('disable',sub.toJSON());
      if(sub)await sub.unsubscribe();status.textContent=all?'Push auf allen Geräten deaktiviert.':'Push auf diesem Gerät deaktiviert.';
    }catch(e){status.textContent=e.message;}
  };
  panel.querySelector('[data-disable]').addEventListener('click',()=>disable(false));
  panel.querySelector('[data-disable-all]').addEventListener('click',()=>disable(true));
});
