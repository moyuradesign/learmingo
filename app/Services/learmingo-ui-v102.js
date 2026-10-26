/* LEARMINGO_FRONTEND_SCRIPT_BRIDGE_V102 */
/* Learmingo frontend theme & navigation — external script for strict CSP. */
(()=>{'use strict';
const root=document.documentElement;
const key='learmingo.theme.v1';
const system=()=>window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light';
const getSaved=()=>{try{return localStorage.getItem(key)}catch(e){return null}};
const save=(value)=>{try{localStorage.setItem(key,value)}catch(e){/* private browsing may deny storage */}};
const desired=()=>{let v=getSaved()||root.dataset.defaultTheme||'system';return v==='dark'||v==='light'?v:system()};
const refresh=()=>{const theme=desired();root.dataset.theme=theme;const meta=document.querySelector('meta[name="theme-color"]');if(meta)meta.content=theme==='dark'?'#0d1422':'#ffffff';document.querySelectorAll('[data-theme-toggle]').forEach(btn=>{btn.setAttribute('aria-label',theme==='dark'?'Switch to light mode':'Switch to dark mode');const text=btn.querySelector('span');if(text)text.textContent=theme==='dark'?'Switch to light mode':'Switch to dark mode';});};
refresh();
if(window.matchMedia){const m=window.matchMedia('(prefers-color-scheme: dark)');if(m.addEventListener)m.addEventListener('change',()=>{if(!getSaved()||getSaved()==='system')refresh()})}
document.addEventListener('click',event=>{const btn=event.target.closest('[data-theme-toggle]');if(btn){save(root.dataset.theme==='dark'?'light':'dark');refresh();return}document.querySelectorAll('[data-user-menu],[data-mobile-menu]').forEach(menu=>{if(menu.open&&!menu.contains(event.target))menu.open=false})});
document.addEventListener('keydown',event=>{if(event.key==='Escape')document.querySelectorAll('[data-user-menu],[data-mobile-menu]').forEach(menu=>{menu.open=false})});
})();
