/* Password values stay in the input; only visibility changes. No storage or network access. */
(() => {
    'use strict';
    const eye='<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>';
    const eyeOff='<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.5 5.3A12 12 0 0 1 12 5c6.5 0 10 7 10 7a18 18 0 0 1-3.1 4M6 6.5A19 19 0 0 0 2 12s3.5 7 10 7a12 12 0 0 0 5.5-1.3"/></svg>';
    const controls=[];
    function init() {
        document.querySelectorAll('input[data-password-toggle]').forEach(input => {
            if (input.dataset.passwordReady) return;
            input.dataset.passwordReady='true';
            const wrapper=document.createElement('div'); wrapper.className='password-field';
            if (input.classList.contains('mb-3')) { input.classList.remove('mb-3'); wrapper.classList.add('mb-3'); }
            input.before(wrapper); wrapper.append(input);
            const button=document.createElement('button'); button.type='button'; button.className='password-toggle';
            const label=input.labels?.[0]?.textContent?.replace(/\*/g,'').trim() || 'password';
            if (input.id) button.setAttribute('aria-controls',input.id);
            const mask=visible => {
                input.type=visible ? 'text':'password';
                const text=(visible ? 'Hide ':'Show ')+label;
                button.setAttribute('aria-label',text); button.setAttribute('title',text); button.setAttribute('aria-pressed',String(visible));
                button.innerHTML=visible ? eyeOff:eye;
            };
            mask(false); button.addEventListener('click',() => mask(input.type==='password'));
            wrapper.append(button); controls.push({input,mask});
        });
    }
    const hide=() => controls.forEach(control => control.mask(false));
    if (document.readyState==='loading') document.addEventListener('DOMContentLoaded',init,{once:true}); else init();
    document.addEventListener('submit',hide,true);
    document.addEventListener('reset',hide,true);
    window.addEventListener('pageshow',hide);
})();
