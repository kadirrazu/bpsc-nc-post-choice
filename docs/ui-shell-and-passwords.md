# Blue shell, icons and password visibility

The public portal, candidate pages, Administrator login and staff backend share a soft-blue full-width header/footer background (#eaf2fb). Header, navigation, body and footer content retain container-xl bounds; the login form keeps its narrow container. Existing body fonts, Bengali choice-title font rules, receipt/PDF layout, version/developer text, menus and permissions stay unchanged.

Choice-list/checkmark branding is used for the SVG favicon, ICO fallback and Apple touch icon. Versioned asset URLs refresh old cached icons. Navigation, account, login and user management actions use a shared inline SVG component. No icon font/CDN dependency is introduced.

Opt-in password fields cover Administrator login, create/edit user passwords and confirmations, and own current/new/confirmation password fields. Each field has an independent eye button with type=button, an accessible label, aria-controls and aria-pressed. Only input type changes; names, values, validation, required flags and autocomplete remain unchanged. Submit, reset and pageshow mask visible fields again. No credentials enter browser storage or network calls. Without JavaScript the normal masked inputs remain usable.

The password script is a versioned public asset included from a shared head partial; shell rules live in the existing shared Blade style partial. No schema changes, package installation or Vite rebuild are required for this patch. README.md stays fixed; this document holds UI decisions.

## Two-row header and root margin reset

Tabler's bundled desktop rule applies margin-left:calc(100vw - 100%) to :root at widths >=992px. Reset html:root margin to zero, plus body/frame margins, so shell backgrounds reach the viewport edge. Keep container-xl padding/margins on inner content; do not use 100vw or hide horizontal overflow to conceal the gap.

The first header row places Choice Taking System on the left and Bangladesh Public Service Commission (BPSC) on the right. Backend navigation occupies a separate second row, with the user dropdown and a POST/CSRF Sign Out action at its right edge. Profile/password actions and role-specific menu items are unchanged. On smaller screens the menu collapses and name/designation remain in the dropdown; the account and Sign Out controls stay available. Public/candidate/login action buttons sit below the shared brand row. Header/footer colors and all body/receipt fonts remain unchanged.
