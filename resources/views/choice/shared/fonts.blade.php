<style>
@font-face { font-family:NikoshChoice; src:url("{{ asset('fonts/Nikosh.ttf') }}") format('truetype'); font-weight:normal; font-style:normal; font-display:swap; unicode-range:U+0980-09FF,U+200C-200D; }
.choice-title-bn { font-family:NikoshChoice,var(--tblr-font-sans-serif, sans-serif); }
</style>
<script>
(() => {
    const selector='[data-choice-title], .choice-transfer strong, input[aria-label="Choice title"]';
    const update=element => element.classList.toggle('choice-title-bn', /[\u0980-\u09FF]/u.test(element.value ?? element.textContent ?? ''));
    const scan=() => document.querySelectorAll(selector).forEach(update);
    document.addEventListener('input',event => { if(event.target.matches(selector)) update(event.target); });
    document.addEventListener('DOMContentLoaded',() => {
        scan();
        new MutationObserver(scan).observe(document.body,{childList:true,subtree:true,characterData:true});
    });
})();
</script>
