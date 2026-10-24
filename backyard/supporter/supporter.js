(() => {
  const toggle=document.querySelector('.menu-toggle'), shade=document.querySelector('.nav-shade');
  const close=()=>{document.body.classList.remove('nav-open');toggle?.setAttribute('aria-expanded','false');if(shade)shade.hidden=true;};
  toggle?.addEventListener('click',()=>{const open=document.body.classList.toggle('nav-open');toggle.setAttribute('aria-expanded',String(open));shade.hidden=!open;});
  shade?.addEventListener('click',close);
  document.querySelectorAll('#supporter-nav a').forEach(a=>a.addEventListener('click',close));
  document.querySelector('.collapse-nav')?.addEventListener('click',()=>document.body.classList.toggle('nav-compact'));
  document.addEventListener('keydown',e=>{if(e.key==='Escape')close();});
})();
