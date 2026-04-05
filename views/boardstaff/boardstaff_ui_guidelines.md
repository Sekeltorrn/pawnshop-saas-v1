# Boardstaff UI & Design Guidelines
**Objective:** All files in the `views/boardstaff/` directory must strictly adhere to this design system. Do NOT use hardcoded hex colors (e.g., #141518, #00ff41, #ff6b00). Use the semantic CSS classes outlined below.

## 1. Core Aesthetic
The aesthetic is "Tactical Data Terminal". It relies on high data density, monospaced/headline fonts, heavy use of uppercase text, wide letter spacing, and semantic background layers.

## 2. Color System (Strictly Enforced)
Use ONLY these semantic Tailwind classes. Do NOT use arbitrary hex values.
* **Backgrounds:** * `bg-surface-container-lowest` (Darkest, for main app background or feed items)
  * `bg-surface-container-low` (For cards and main panels)
  * `bg-surface-container-high` (For table headers, secondary buttons)
  * `bg-surface-container-highest` (For badges, subtle highlights, progress bars)
* **Text & Typography:**
  * `text-on-surface` (Primary white/bright text)
  * `text-on-surface-variant` (Secondary gray/muted text)
  * `text-on-primary` (Text inside a primary-colored button/badge)
* **Accents & Status Colors:**
  * **Primary (Success/Active):** `text-primary`, `bg-primary`, `border-primary`
  * **Secondary (Warning/Standard):** `text-secondary`, `bg-secondary-dim`, `border-secondary-dim`
  * **Tertiary (Neutral/Alternative):** `text-tertiary-dim`, `bg-tertiary-dim`, `border-tertiary-dim`
  * **Error (Danger/Overdue):** `text-error`, `text-error-dim`, `border-error-dim`
* **Borders:**
  * `border-outline-variant/10` or `border-outline-variant/20` (For subtle card borders)

## 3. Typography Rules
* **Headers & Labels:** Must use `font-headline`, `uppercase`, and wide tracking (e.g., `tracking-[0.2em]`, `tracking-[0.3em]`, or `tracking-widest`).
* **Micro-text:** Extensive use of `text-[9px]`, `text-[10px]`, `text-[11px]`, and `text-xs`. 
* **Data Values:** Use `font-bold` or `font-black` for numbers and key data points.

## 4. UI Component Blueprints

**Cards / Panels:**
Always use `rounded-sm border border-outline-variant/10 bg-surface-container-low`.
For status cards, add a left border: `border-l-2 border-primary`.

**Buttons (Primary Action):**
`<button class="p-4 bg-primary text-on-primary rounded-sm transition-all hover:opacity-90 active:scale-[0.98] flex items-center gap-4 uppercase font-headline font-bold text-sm tracking-wider">`

**Tables / Lists:**
* Table container: `bg-surface-container-low border border-outline-variant/10 rounded-sm`
* Table Headers: `bg-surface-container-high border-b border-outline-variant/10 text-[9px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.2em]`
* Table Rows: `hover:bg-surface-container-lowest transition-colors border-b border-outline-variant/10`
* Badges: `text-[10px] font-headline font-bold bg-surface-container-highest px-2 py-1 rounded-sm uppercase`