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
- `AppDatePicker`: app-styled wrapper around `@vuepic/vue-datepicker` for date fields that need to work well inside forms and modals.
  Path: `src/modules/shared/components/AppDatePicker.vue`
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
- `AppPercentageSplitEditor`: interactive horizontal percentage splitter with drag handles and exact numeric adjustment that keeps the total at 100%.
  Path: `src/modules/shared/components/AppPercentageSplitEditor.vue`
- `AppSearchSelect`: app-styled single-select searchable dropdown wrapper built on `@vueform/multiselect`.
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

## Preferred Feature Libraries

When a new feature needs one of these capabilities, prefer these installed libraries before introducing alternatives:

- Date picker: use `@vuepic/vue-datepicker`.
  Prefer it for date selection inside forms and modals. It supports teleport/overlay scenarios and can be themed to match the app.

- Searchable select / single select with search: use `@vueform/multiselect`.
  Prefer it for searchable selectors such as financial goals and similar fields that need option filtering inside forms or modals.

- Form validation: use `vee-validate` with `zod` and `@vee-validate/zod`.
  Prefer this stack for new form validation work, including required fields, typed schemas, error messages, and integration with existing Vue form components.

- Charts: use `vue-echarts` with `echarts`.
  Prefer this stack for dashboard charts, especially bar charts and other analytic visualizations that need theming and responsive behavior.

Before adding a different library for any of these concerns, justify why the installed option is not a fit.

## Maintenance Rule

Whenever a new reusable shared component is created, update this file in the same task so the index stays current.

Whenever a feature is implemented, prefer existing shared components first. Only create a new shared component when the pattern is clearly reusable or repeated.
