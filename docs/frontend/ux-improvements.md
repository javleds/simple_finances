# UX Improvements

> Documento de análisis y planificación previo al refactor; no describe por completo el estado actual. Para las decisiones vigentes consulta [interfaz PrimeVue](primevue.md), [diseño móvil](../specs/002-unificar-diseno-movil.md) y [validación](../testing/strategy.md).

## Context

These notes come from a mobile Playwright walkthrough of the SPA. The screenshots were generated for product documentation and feature planning, not as an E2E test suite.

Reviewed areas:

- Auth: login, register, password recovery, password reset, verification, terms, privacy.
- Main app: dashboard, accounts, subscriptions, distribution, transactions, invitations, profile, settings.
- Account detail: transactions, goals, invitations, users.
- Interactive states: create/edit forms, filters, action menus, and representative modals.

## Highest Priority

### Fix Bottom Navigation Overlap

The bottom navigation overlaps page content in several mobile screenshots, especially list endings, profile form controls, and modal backdrops.

Recommended changes:

- Add consistent bottom padding to scrollable page containers.
- Include `safe-area-inset-bottom` in the mobile layout spacing.
- Verify long forms, empty states, and infinite-scroll footers with the bottom nav visible.

### Disable Devtools Overlay for Documentation Captures

The Vue Devtools overlay appears in screenshots and can cover important controls, including auth submit buttons.

Recommended changes:

- Disable `vite-plugin-vue-devtools` when running documentation captures.
- Use an environment flag if devtools should stay enabled during normal local development.

### Improve Icon-Only Action Labels

Several primary action buttons are visually clear but rely on position or icon meaning. This makes automated capture flows and accessibility weaker.

Recommended changes:

- Add explicit `aria-label` values such as `Crear cuenta`, `Nueva transacción`, `Nueva meta`, and `Abrir filtros`.
- Keep labels consistent across modules.
- Prefer stable accessible names over selector-specific automation hooks.

## Product Language

### Remove Internal Terminology

Some UI copy exposes implementation concepts such as `facility`, API behavior, or `user_id`.

Recommended changes:

- Replace internal vocabulary with user-facing language.
- Avoid explaining missing backend/frontend capabilities directly in the UI.
- Use Spanish consistently across product copy.

Examples:

- Prefer `organización`, `espacio`, or another product term over `facility`.
- Replace technical copy in the users modal with a direct invitation workflow.

### Rework Add User Flow

The `Agregar usuario` modal currently explains that direct user selection is not available and sends users back to invitations conceptually.

Recommended changes:

- Remove the add-user action until it performs a complete workflow, or
- Route users directly to `Nueva invitación`, or
- Implement a searchable user selector if direct member attachment is a real product requirement.

## Navigation

### Clarify Bottom Navigation Labels

Labels such as `Subs`, `Distro`, and `Config` save space but reduce clarity for new users.

Recommended changes:

- Prefer full labels when possible: `Suscripciones`, `Distribución`, `Configuración`.
- If space is too tight, validate abbreviations with real mobile screenshots and avoid ambiguous labels.

### Make Primary Row Actions More Discoverable

List item menus hide important actions behind the three-dot control.

Recommended changes:

- Keep destructive and secondary actions inside the menu.
- Consider exposing one primary action directly when it is common, such as opening detail, completing a pending transaction, or editing a recent item.

## Forms And Modals

### Explain Disabled Submit States

Form submit buttons are disabled until required input is valid, but the UI does not always explain what is missing.

Recommended changes:

- Show validation feedback after field blur or submit attempt.
- Keep disabled visual state, but avoid making users guess why an action is unavailable.
- Use concise field-level messages.

### Keep Modal Footers Clear Of Navigation

Modal action footers are visually consistent, but the fixed bottom nav remains visible behind modal overlays and can create visual noise.

Recommended changes:

- Ensure the modal overlay visually separates the active task from app navigation.
- Validate stacked modal/footer/nav behavior on narrow mobile viewports.

## Dashboard And Empty States

### Make Empty States Actionable

Empty states are consistent, but several stop at explanation only.

Recommended changes:

- Add contextual actions when there is a natural next step.
- Examples: `Crear transacción`, `Crear meta`, `Invitar usuario`, `Crear suscripción`.
- Keep non-actionable empty states only when no meaningful action exists.

### Clarify Transaction Scope

The global transactions screen can show zero values even when account-level transactions exist, because it has a narrower scope.

Recommended changes:

- Make the scope visible near the summary: selected period, completed-only behavior, user-owned transactions, or account filtering.
- Consider an empty-state hint explaining why no transactions are shown.

## Visual Hierarchy

### Lower Theme Selector Priority In Auth

The theme selector appears before the primary auth task and consumes significant vertical space on mobile.

Recommended changes:

- Move theme selection lower on auth pages, or
- Render it as a compact footer control, or
- Keep the full selector only in settings.

### Preserve Existing Strengths

The app already has a strong mobile-first foundation:

- Consistent card, modal, input, and button styling.
- Clear financial amount formatting.
- Good spacing inside list items and forms.
- Reusable component patterns across modules.

Future UX work should preserve this consistency while improving interaction clarity, copy, and mobile layout behavior.
