<style>
.choice-home { min-height:100vh; }
.choice-home .public-frame { min-height:100vh; display:flex; flex-direction:column; padding-top:20px; padding-bottom:20px; }
.choice-home .choice-header { border:1px solid #dce5ef; border-radius:12px; padding:20px 24px; }
.choice-home .public-header-content { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px; }
.choice-home .choice-brand { font-size:17px; line-height:1.45; }
.choice-home .choice-brand small { font-size:12px; }
.choice-home .public-main { flex:1; padding:28px 0; }
.choice-home .public-time-hint { font-size:12px; color:#68768a; margin-bottom:14px; }
.choice-home .public-intro-block { padding:0 24px; }
.choice-home .card-body,.choice-home .card-header { padding-left:24px; padding-right:24px; }
.choice-home .public-intro { max-width:720px; line-height:1.6; }
.choice-home .public-events { border-color:#dce5ef; border-radius:12px; }
.choice-home .public-event { border-top:1px solid #e5ebf2; padding-top:22px; margin-top:22px; }
.choice-home .public-event-heading { display:flex; justify-content:space-between; align-items:flex-start; gap:20px; }
.choice-home .public-event-title { color:#245d96; font-size:20px; font-weight:700; line-height:1.4; margin:0 0 12px; overflow-wrap:anywhere; }
.choice-home .public-event-meta { display:flex; flex-wrap:wrap; gap:12px 32px; margin:0 0 12px; }
.choice-home .public-event-meta dt { font-size:11px; font-weight:600; color:#6d7b8d; letter-spacing:.035em; text-transform:uppercase; margin-bottom:3px; }
.choice-home .public-event-meta dd { font-size:13px; color:#35465d; margin:0; }
.choice-home .choice-instructions { color:#526278; font-size:13px; line-height:1.6; white-space:pre-line; }
.choice-home .public-submit { flex-shrink:0; margin-top:2px; }
.choice-home .public-empty { border:1px solid #efd4d7; border-left:4px solid #c23d4b; background:#fff6f6; color:#96303b; border-radius:8px; padding:18px 20px; margin-top:18px; }
.choice-home .public-empty p { margin:6px 0 0; font-size:13px; line-height:1.6; }
.choice-home .public-footer { border:1px solid #dce5ef; border-top:2px solid #cad8e8; background:#edf2f8; border-radius:12px; padding:18px 24px; display:flex; flex-wrap:wrap; justify-content:space-between; gap:8px 24px; color:#526278; font-size:12px; line-height:1.6; }
.choice-home .public-footer-credit { color:#64748b; }
.choice-home .public-footer-credit strong { font-weight:700; }
@media(max-width:575px) {
    .choice-home .public-frame { padding-top:12px; padding-bottom:12px; }
    .choice-home .choice-header,.choice-home .public-footer { padding:16px; }
    .choice-home .choice-brand { font-size:15px; }
    .choice-home .public-main { padding:22px 0; }
    .choice-home .public-intro-block { padding:0 16px; }
    .choice-home .card-body,.choice-home .card-header { padding-left:16px; padding-right:16px; }
    .choice-home .public-event-heading { flex-direction:column; gap:8px; }
    .choice-home .public-event-title { font-size:18px; }
    .choice-home .public-event-meta { gap:12px 22px; }
    .choice-home .public-submit { width:100%; }
}
.choice-home .candidate-event-title { color:#245d96; line-height:1.4; }
</style>
