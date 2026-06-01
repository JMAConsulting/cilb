# Nice Change Log

Enhances the contact **Change Log** tab so you can see *what* changed and *when*
without opening every entry. Each change is shown on its own row with an inline
summary of the fields that changed (expand a row to see the full
field / changed‑from / changed‑to detail), plus filters for action and
component and a date range that defaults to the current month.

The extension is licensed under [AGPL-3.0](https://www.gnu.org/licenses/agpl-3.0.html).

## Requirements

* PHP v7.4+
* CiviCRM 5.75+
* **Advanced logging** enabled (Administer » System Settings » Misc »
  Logging). The standard
  Change Log tab is left untouched when advanced logging is off.

## Installation

Install like any other CiviCRM extension: download or clone into your extensions directory, then enable it via Administer > System Settings > Extensions.

## Getting Started

1. Make sure **advanced logging** is enabled.
2. Open any contact record and click the **Change Log** tab.
3. The tab now lists each change with:
   * **Action** — Insert, Update, Delete, Added, etc.
   * **Component** — Contact, Exam, Payment, Activity Contact, Group, …
   * **When** — When the change was made
   * **What changed** — a one‑line summary of the changed fields. Click the
     row to expand the full *Field / Changed From / Changed To* table.
   * **Altered By** — the contact who made the change.
4. Use the **Action** and **Component** checkboxes to filter the visible rows
   instantly, and the **Date** dropdown to change the period.

### Filters

* **Date** — defaults to *This month* to keep the tab fast on busy contacts.
  Presets include Last month, Last 7/30 days, This year, All time and a custom
  range.
* **Action** / **Component** — refine the rows already loaded for the selected
  date range.
