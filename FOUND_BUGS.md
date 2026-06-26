# Found bugs & feature requests

All items below were addressed in the latest development pass.

## Bugs (fixed)

- [x] Can't right click on file or folder — row `contextmenu` now stops propagation; tree nodes emit context menu events
- [x] "WordPress (light)" renamed to **VS Code (light)** in settings (internal key unchanged for saved settings)
- [x] Search scope select text invisible on dark theme — `color` + `color-scheme` on app root and scoped select styles

## Features (implemented)

- [x] X icon to clear search text
- [x] Search input debounce (450 ms) to reduce request spam while typing
- [x] Pro search scopes (`folder`, `site`) disabled when not on Pro
- [x] Back / Forward toolbar buttons with navigation history (Alt+Left / Alt+Right)
