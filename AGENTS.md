# AGENTS.md

## Shared Components Index

Before creating new UI for features, review `src/modules/shared/components` and reuse these components when they fit the need.

Reusing an existing shared component is mandatory when it already covers the need or can cover it with a small, reasonable extension. Do not create native UI elements or new components from scratch if a shared component already applies.

Current shared components:

- `AppActionMenu`: compact contextual actions menu for secondary item actions such as edit and delete.
  Path: `src/modules/shared/components/AppActionMenu.vue`
- `AppButton`: base button with variants `primary`, `secondary`, `ghost`.
  Path: `src/modules/shared/components/AppButton.vue`
- `AppCard`: bordered surface card with optional padding and muted surface mode.
  Path: `src/modules/shared/components/AppCard.vue`
- `AppContextTabs`: contextual horizontal tabs with optional icons and configurable top or bottom active indicator.
  Path: `src/modules/shared/components/AppContextTabs.vue`
- `AppIconButton`: compact circular button for icon-only actions.
  Path: `src/modules/shared/components/AppIconButton.vue`
- `AppInput`: labeled input wrapper compatible with native input attributes via `$attrs`.
  Path: `src/modules/shared/components/AppInput.vue`
- `AppLink`: shared link component compatible with `href` and Vue Router `to`, with variants `primary`, `secondary`, `subtle`.
  Path: `src/modules/shared/components/AppLink.vue`
- `AppModal`: base modal mobile-first with header, content area and footer actions.
  Path: `src/modules/shared/components/AppModal.vue`
- `AppPasswordInput`: password input with show/hide action.
  Path: `src/modules/shared/components/AppPasswordInput.vue`
- `AppSearchSelect`: single-select searchable dropdown styled as an input, with option filtering and one-item selection.
  Path: `src/modules/shared/components/AppSearchSelect.vue`
- `AppSwitch`: boolean on/off switch for compact settings and per-item activation controls.
  Path: `src/modules/shared/components/AppSwitch.vue`
- `AppText`: shared paragraph/text primitive with tone and size options.
  Path: `src/modules/shared/components/AppText.vue`
- `AppToggleButton`: segmented toggle button for selecting one option from a small set.
  Path: `src/modules/shared/components/AppToggleButton.vue`
- `AppTitle`: shared heading primitive with semantic tag and size options.
  Path: `src/modules/shared/components/AppTitle.vue`

Barrel export:

- `src/modules/shared/components/index.ts`

## Icons

When a feature needs icons, use `@heroicons/vue` as the default icon library.

## Maintenance Rule

Whenever a new reusable shared component is created, update this file in the same task so the index stays current.

Whenever a feature is implemented, prefer existing shared components first. Only create a new shared component when the pattern is clearly reusable or repeated.
