<style>
/* Tabler reserves a scrollbar-sized margin on :root at desktop widths. */
html:root { margin:0 !important; padding:0; }
body.choice-public { margin:0; padding:0; }
:is(.choice-home,.backend-shell) { min-height:100vh; }
:is(.choice-home,.backend-shell) .public-frame { min-height:100vh; display:flex; flex-direction:column; width:100%; max-width:none; margin:0; }
:is(.choice-home,.backend-shell) .choice-header { border:0; border-bottom:1px solid #cbdced; border-radius:0; background:#eaf2fb; padding:0; width:100%; margin:0; }
.choice-brand-row { display:flex; justify-content:space-between; align-items:center; gap:16px 32px; padding-top:20px; padding-bottom:20px; }
.choice-commission { color:#244c75; font-size:17px; font-weight:700; line-height:1.45; text-align:right; }
.choice-header-actions { display:flex; justify-content:flex-end; align-items:center; flex-wrap:wrap; gap:8px; padding-bottom:16px; }
.choice-menu-row.navbar { background:#f2f7fd; box-shadow:none; border:0; border-top:1px solid #d5e2f0; padding:8px 0; min-height:60px; }
.choice-menu-container { display:flex; align-items:center; flex-wrap:wrap; gap:8px 20px; }
.choice-menu-row .navbar-collapse { min-width:0; }
.choice-account-actions { margin-left:auto; display:flex; align-items:center; gap:12px; order:1; }
.choice-account-trigger { display:flex; align-items:center; gap:8px; color:#244c75; text-decoration:none; }
.choice-account-label { display:block; max-width:200px; font-size:13px; line-height:1.35; }
.choice-account-name,.choice-account-designation { display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.choice-account-designation { font-size:11px; color:#64748b; }
@media(max-width:1199px) { .choice-account-label { display:none; } }
@media(max-width:767px) {
    .choice-brand-row { flex-wrap:wrap; gap:8px 16px; padding-top:16px; padding-bottom:16px; }
    .choice-commission { width:100%; font-size:15px; }
    .choice-menu-row .navbar-collapse { flex-basis:100%; width:100%; order:2; }
    .choice-menu-row .navbar-nav { padding-top:8px; }
}
:is(.choice-home,.backend-shell) .public-header-content { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px; }
:is(.choice-home,.backend-shell) .choice-brand { font-size:17px; line-height:1.45; color:#244c75; display:inline-flex; align-items:center; gap:10px; min-width:0; max-width:100%; white-space:normal; }
:is(.choice-home,.backend-shell) .choice-brand > span { min-width:0; }
.backend-shell .navbar-brand { min-width:0; white-space:normal; margin:0; }
:is(.choice-home,.backend-shell) .choice-brand small { font-size:12px; }
.choice-home .public-main { flex:1; padding-top:28px; padding-bottom:28px; }
.choice-home .public-time-hint { font-size:12px; color:#68768a; margin-bottom:14px; }
.choice-home .public-intro-block { padding:0 24px; }
.choice-home .card-body,.choice-home .card-header { padding-left:24px; padding-right:24px; }
.choice-home .public-intro { max-width:720px; line-height:1.6; }
.choice-home .public-events { font-family:inherit; border-color:#dce5ef; border-radius:12px; }
.choice-home .public-event { border-top:1px solid #e5ebf2; padding-top:22px; margin-top:22px; }
.choice-home .public-event-heading { display:flex; justify-content:space-between; align-items:flex-start; gap:20px; }
.choice-home .public-event-title { color:#245d96; font-size:20px; font-weight:700; line-height:1.4; margin:0 0 12px; overflow-wrap:anywhere; }
.choice-home .public-event-meta { display:flex; flex-wrap:wrap; gap:12px 32px; margin:0 0 12px; }
.choice-home .public-event-meta dt { font-family:inherit; font-size:12px; font-weight:400; color:#6d7b8d; letter-spacing:normal; text-transform:none; margin-bottom:3px; }
.choice-home .public-event-meta dd { font-family:inherit; font-size:14px; font-weight:700; margin:0; }
.choice-home .public-meta-post-code dd { color:#245d96; }
.choice-home .public-meta-unit dd { color:#0f766e; }
.choice-home .public-meta-closes dd { color:#9a5b13; }
.choice-home .choice-instructions { color:#526278; font-size:13px; line-height:1.6; white-space:pre-line; }
.choice-home .public-submit { flex-shrink:0; margin-top:2px; }
.choice-home .public-empty { border:1px solid #efd4d7; border-left:4px solid #c23d4b; background:#fff6f6; color:#96303b; border-radius:8px; padding:18px 20px; margin-top:18px; }
.choice-home .public-empty p { margin:6px 0 0; font-size:13px; line-height:1.6; }
:is(.choice-home,.backend-shell) .public-footer { border:0; border-top:1px solid #cbdced; background:#eaf2fb; border-radius:0; padding:18px 0; color:#526278; font-size:12px; line-height:1.6; }
:is(.choice-home,.backend-shell) .public-footer-content { display:flex; flex-wrap:wrap; justify-content:space-between; gap:8px 24px; }
:is(.choice-home,.backend-shell) .choice-brand > .icon { width:30px; height:30px; flex-shrink:0; }
.auth-main { flex:1; display:flex; align-items:center; padding:24px 0; }
:is(.choice-home,.backend-shell) .public-footer-credit { color:#64748b; }
:is(.choice-home,.backend-shell) .public-footer-credit strong { font-weight:700; }
@media(max-width:575px) {
    .backend-shell .navbar-brand { flex:1; }
    .choice-account-label { display:none; }
    :is(.choice-home,.backend-shell) .public-footer { padding:16px 0; }
    :is(.choice-home,.backend-shell) .choice-brand { font-size:15px; }
    .choice-home .public-main { padding-top:22px; padding-bottom:22px; }
    .choice-home .public-intro-block { padding:0 16px; }
    .choice-home .card-body,.choice-home .card-header { padding-left:16px; padding-right:16px; }
    .choice-home .public-event-heading { flex-direction:column; gap:8px; }
    .choice-home .public-event-title { font-size:18px; }
    .choice-home .public-event-meta { gap:12px 22px; }
    .choice-home .public-submit { width:100%; }
}
.choice-home .candidate-event-title { color:#245d96; line-height:1.4; }
</style>
