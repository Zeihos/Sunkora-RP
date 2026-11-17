'use strict';
document.querySelectorAll('.confirm-form').forEach(form=>form.addEventListener('submit',e=>{if(!window.confirm(form.dataset.confirm||'Подтвердить действие?'))e.preventDefault();}));
const toggle=document.querySelector('[data-toggle-nav]'),nav=document.getElementById('site-nav');
if(toggle&&nav)toggle.addEventListener('click',()=>{const opened=nav.classList.toggle('is-open');toggle.setAttribute('aria-expanded',String(opened));});
