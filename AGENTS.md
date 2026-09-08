<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.4. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record durable rules with `record-rule` so the next agent or teammate inherits them instead of working them out again. Pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Always use `record-rule`, never your native memory or notes tool — native memory is personal and session-scoped; only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== livewire/core rules ===

# Livewire

- Livewire allows you to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

=== backpack/crud/backpack-crud rules ===

# Backpack for Laravel

## Getting Started

- When helping a user set up Backpack for the first time, always check if the storage symlink exists. Run `php artisan storage:link -q` if `public/storage` is missing — otherwise uploads and assets will silently break.
- If the user reports broken images, missing CSS, or upload fields not working, the storage symlink is the first thing to check.

## Paid Features — Check Before Using

**CRITICAL: Before generating code that uses any PRO or paid feature, check if the required package is installed.** Never assume the user has access to paid packages. If a package is missing, stop and tell the user — do NOT attempt to install it yourself.

### How to check

- Read the project's `composer.json` and look for the package in `require` or `require-dev`.
- Or check if the vendor directory exists (e.g., `vendor/backpack/pro/composer.json`).

### Paid packages and their features

| Package | Provides |
|---------|----------|
| `backpack/pro` | PRO fields (select2, select2_from_ajax, relationship, repeatable, dropzone, etc.), PRO columns, ALL filters, FetchOperation, CloneOperation, BulkDeleteOperation, BulkCloneOperation, InlineCreateOperation, TrashOperation, AjaxUploadOperation, CustomViewOperation, chart widgets |
| `backpack/editable-columns` | Inline editing in the List view (`MinorUpdateOperation`, `editable_text`, `editable_select`, etc.) |
| `backpack/dataform-modal` | Modal Create/Update forms (`CreateInModalOperation`, `UpdateInModalOperation`) |
| `backpack/devtools` | Web UI for generating migrations, models, CRUDs |
| `backpack/report-operation` | Dashboard with stat/chart metrics per entity |
| `backpack/test-generators` | Auto-generate tests for CrudControllers |
| `backpack/calendar-operation` | Calendar interface for date-based entries |
| `backpack/auto-translate` | Auto-translate content to multiple languages |

### If a paid package is not installed

When a user asks to install a paid package (e.g., "install backpack/pro"), follow this workflow:

1. **Check if `auth.json` exists** — Look for Backpack credentials in:
   - Windows: `%APPDATA%/Composer/auth.json` (e.g., `C:\Users\<user>\AppData\Roaming\Composer\auth.json`)
   - Linux/Mac: `~/.composer/auth.json`
   - Project-level: `<project>/auth.json`
   Read the file and look for `http-basic` entries with `backpackforlaravel.com` or `repo.backpackforlaravel.com` URLs.

2. **Check if the repository is configured** — Look in the project's `composer.json` under `repositories` for a `backpack` entry pointing to `https://repo.backpackforlaravel.com` or `https://backpackforlaravel.com/packages`.

3. **If credentials exist AND repository is configured** → Proceed to run `composer require <package-name>`.

4. **If credentials exist but repository is NOT configured** → Add the repository first, then install:
   ```
   composer config repositories.backpack composer https://repo.backpackforlaravel.com
   composer require <package-name>
   ```

5. **If auth.json is missing or lacks Backpack credentials** → Inform the user:
   - What feature they asked for requires which paid package
   - How to purchase: `https://backpackforlaravel.com/pricing`
   - How to set up `auth.json` (credentials are in their Backpack account dashboard)
   - Offer a FREE alternative if one exists (e.g., use FREE `select` field instead of PRO `select2` field, FREE `upload` field instead of PRO `image` field). **There is no free alternative for filters — all filter types require `backpack/pro`. Do NOT suggest `addClause` as a filter substitute; it permanently scopes the query and is not toggleable.**

## Generating a CRUD

- Scaffold a full CRUD panel with `php artisan backpack:crud ModelName` (singular model name).
- This generates: CrudController in `app/Http/Controllers/Admin/`, a FormRequest, a route entry in `routes/backpack/custom.php`, and a menu item.
- Use `--no-interaction` to run without prompts.
- **After generating the CRUD**, always edit `resources/views/vendor/backpack/ui/inc/menu_items.blade.php` and replace the default `icon="la la-question"` with a meaningful Line Awesome icon. Pick an icon that reflects the model's domain. Common mappings:

  | Model / Domain | Suggested Icon |
  |---|---|
  | User, Person, Customer, Client | `la la-user` |
  | Product, Item, Inventory | `la la-box` or `la la-cube` |
  | Order, Cart, Purchase, Sale | `la la-shopping-cart` |
  | Category, Tag, Label | `la la-tag` or `la la-tags` |
  | Post, Article, Blog, News | `la la-newspaper-o` or `la la-file-text` |
  | Page, Document | `la la-file` |
  | Invoice, Receipt, Bill | `la la-file-invoice` |
  | Contact, Lead, Subscriber | `la la-address-book` |
  | Event, Calendar, Schedule | `la la-calendar` |
  | Setting, Configuration | `la la-cog` |
  | Role, Permission, Access | `la la-lock` or `la la-user-secret` |
  | Comment, Review, Feedback | `la la-comment` |
  | Image, Photo, Gallery, Media | `la la-image` |
  | Video, Media | `la la-video` |
  | Message, Email, Mail | `la la-envelope` |
  | Notification, Alert | `la la-bell` |
  | Report, Analytics, Stats | `la la-chart-bar` |
  | Subscription, Plan, Billing | `la la-credit-card` |
  | FAQ, Help, Support | `la la-question-circle` |
  | Testimonial, Quote | `la la-quote-right` |
  | Slider, Carousel, Banner | `la la-images` or `la la-sliders` |
  | Dashboard | `la la-home` |
  | Team, Group, Department | `la la-users` |
  | Coupon, Discount, Promo | `la la-percent` |
  | Location, Address, Place | `la la-map-marker` |

  When unsure, use `la la-file-text` as a safe fallback.
- **If `backpack/test-generators` is installed** (check `vendor/backpack/test-generators/composer.json`), automatically generate tests for the new CRUD: `php artisan backpack:tests --controller=ModelCrudController`.

## CrudController Structure

- Every CRUD controller extends `CrudController` and uses operation traits: `ListOperation`, `CreateOperation`, `UpdateOperation`, `ShowOperation`, `DeleteOperation`.
- Configure fields inside `setupCreateOperation()` and `setupUpdateOperation()`.
- Configure columns inside `setupListOperation()`.
- Use `CRUD::field([...])` and `CRUD::column([...])`. Both array and fluent syntax work.
- Always specify `'name'`, `'label'`, and `'type'` in every field and column array.

## Fields

- If `name` matches a model relationship method, Backpack auto-detects it as a relationship field. Use `'entity' => false` to disable.
- FREE types: text, number, textarea, select, select_multiple, select_from_array, select_grouped, checkbox, switch, radio, checklist, checklist_dependency, date, datetime, time, month, week, email, password, url, color, range, upload, upload_multiple, summernote, view, custom_html, hidden, enum.
- PRO types: select2, select2_multiple, select2_from_array, select2_from_ajax, select2_from_ajax_multiple, select2_grouped, select2_nested, select2_json_from_api, select_and_order, relationship, repeatable, table, slug, image, dropzone, date_picker, date_range, datetime_picker, address_google, phone, google_map, icon_picker, base64_image, video, code_mirror, easymde.
- Third-party add-ons: ckeditor, tinymce (separate packages).
- Field events: `CRUD::field('name')->on('saving', fn($entry) => ...)`.
- Organize with tabs: `CRUD::field('name')->tab('General')`.
- Wrappers: `CRUD::field('name')->wrapper(['class' => 'col-md-6'])`.
- Hints: `CRUD::field('name')->hint('Help text')`.

## Columns

- FREE types: text, number, boolean, checkbox, radio, switch, date, datetime, time, month, week, email, phone, url, password, color, range, image, upload, upload_multiple, select, select_multiple, select_from_array, select_grouped, relationship_count, model_function, model_function_attribute, closure, custom_html, view, row_number, check, json, multidimensional_array, enum, hidden, summernote, textarea, checklist, checklist_dependency.
- PRO types: select2, select2_multiple, select2_from_ajax, select2_from_ajax_multiple, select2_grouped, select2_nested, select_and_order, array, array_count, relationship, image (enhanced), base64_image, date_picker, date_range, datetime_picker, dropzone, easymde, icon_picker, markdown, slug, table, video, repeatable, browse, browse_multiple, address_google, code_mirror.
- Visibility: `visibleInTable`, `visibleInModal`, `visibleInExport`, `visibleInShow`. Use `exportOnlyColumn => true` for export-only columns.
- Search logic: `'searchLogic' => 'text'` or closure. Editable columns need explicit searchLogic.
- Order logic: `'orderLogic' => function ($query, $column, $direction) { ... }`.
- Column ordering: `CRUD::column('name')->before('email')`, `->after()`, `->makeFirst()`, `->makeLast()`.
- Visibility: `visibleInTable`, `visibleInModal`, `visibleInExport`, `visibleInShow`. Use `exportOnlyColumn => true` for export-only columns.
- Search logic: `'searchLogic' => 'text'` or closure. Editable columns need explicit searchLogic.
- Order logic: `'orderLogic' => function ($query, $column, $direction) { ... }`.
- Column ordering: `CRUD::column('name')->before('email')`, `->after()`, `->makeFirst()`, `->makeLast()`.

## Filters (all require backpack/pro)

- Available types: text, select2, select2_multiple, select2_ajax, date, date_range, simple, view, dropdown, range.
- Always use whenActive for filter logic: `->whenActive(fn($val) => CRUD::addClause('where', ...))`.
- Use `->else(fn() => ...)` or `->fallbackLogic(...)` for fallback when filter is not active.
- Reserved names (never use): `length`, `draw`, `start`, `search`, `totalEntryCount`, `columns`, `datatable_id`.
- Remove: `CRUD::filter('name')->remove()` or `CRUD::removeFilter('name')`.

## Buttons

- Stacks: top (above table), line (per entry row), bottom (below table).
- Add: `CRUD::button('name')->stack('line')->view('view.path')`.
- Quick buttons: `CRUD::button('quick')->stack('line')->view('crud::buttons.quick')`.
- Order: `CRUD::orderButtons('line', ['edit', 'delete'])`.
- Remove: `CRUD::removeButton('name')`, `CRUD::removeAllButtons()`.
- Per-entry access: `CRUD::button('name')->stack('line')->view('...')->setAccessCondition(fn($entry) => $entry->user_id === backpack_user()->id)`.
- **Line buttons as dropdown**: Group line buttons into a dropdown when there are many. Enable with `CRUD::setOperationSetting('lineButtonsAsDropdown', true)`. Control minimum count with `lineButtonsAsDropdownMinimum` (default: 1) and show first N inline with `lineButtonsAsDropdownShowBefore` (default: 0).

## List Operation

- **Line buttons as dropdown**: See Buttons section above.
- **Export buttons**: `CRUD::enableExportButtons()`. Control per-column with `visibleInExport`, `exportOnlyColumn`.
- **Responsive table**: `CRUD::setOperationSetting('responsiveTable', true)`.
- **Persistent table**: `CRUD::setPersistentTable(true)` — saves filters, sorting, page length across visits.
- **Details row**: `CRUD::enableDetailsRow()` then `Widget::add()->to('details_row')...`.
- **Custom views**: `CRUD::setListView('path.to.view')`, `CRUD::setCreateView()`, `CRUD::setEditView()`.
- **Page length**: `CRUD::setDefaultPageLength(25)`. Options: `CRUD::setPageLengthMenu([10, 25, 50, 100])`.

## Operations

- FREE traits: ListOperation, CreateOperation, UpdateOperation, ShowOperation, DeleteOperation, ReorderOperation.
- PRO traits (in crud, require backpack/pro): CloneOperation, BulkDeleteOperation, BulkCloneOperation, FetchOperation, InlineCreateOperation.
- PRO-only traits (in backpack/pro): TrashOperation, BulkTrashOperation, CustomViewOperation, AjaxUploadOperation.
- Add-on traits (require separate paid packages): ReportOperation (backpack/report-operation), MinorUpdateOperation (backpack/editable-columns), CreateInModalOperation + UpdateInModalOperation (backpack/dataform-modal).
- Each operation has: `setupXxxOperation()` for config, `setupXxxRoutes()` for routes, `setupXxxDefaults()` for defaults.
- Custom operations: `php artisan backpack:operation OperationName`.
- **Before using any PRO or add-on operation trait, check the Paid Features section above.**

## Fetch Operation (PRO)

- The Fetch Operation is the recommended way to build AJAX data endpoints for `select2_from_ajax`, `select2_from_ajax_multiple`, and `relationship` fields. Use it instead of creating separate API controllers.
- Add `use FetchOperation` on the **target** model's CrudController (the model being fetched, not the model with the field).
- Define methods following the naming convention `fetchEntity()`: `fetchTag()` for entity `tag`, `fetchCategory()` for entity `category`.
- Always define a `query` closure to activate the save-time security guard and scope results: `'query' => fn($model) => $model->active()`.
- For select2_ajax filters using FetchOperation, the filter method **must** be `'method' => 'POST'`.
- Use `'relation_options_query_source' => 'fetchMethodName'` when `data_source` is set manually and the field entity doesn't match the fetch method name.
- For fully custom endpoints (no FetchOperation), declare the guard with `'relation_options_query' => fn($model) => ...`.
- Prefer explicit `searchable_attributes` over auto-detection. Set to `[]` when using raw SQL searching in the query closure.

## Uploaders

- Add `->withFiles()` to upload fields for automatic file handling (upload, storage, retrieval, deletion).
- FREE uploaders: `SingleFile` (upload), `MultipleFiles` (upload_multiple), `SingleBase64Image` (image).
- PRO uploaders: `DropzoneUploader` (dropzone), `EasyMDEUploader` (easymde), `SummernoteUploader` (summernote) — require `AjaxUploadOperation`.
- Config: `->withFiles(['disk' => 'public', 'path' => 'uploads'])`.
- Model must use `CrudTrait`. Always run `php artisan storage:link`.
- DeleteOperation must define upload fields for auto-deletion on delete.
- Custom validation: `new ValidUpload('field_name')`, `new ValidUploadMultiple('field_name')`.
- Temp file cleanup (PRO): schedule `backpack:purge-temporary-folder` daily.

## Translatable Models (multi-language CRUDs)

- Uses `spatie/laravel-translatable`. Requires MySQL 5.7+ or PostgreSQL with JSON columns.
- Model: use `Backpack\CRUD\app\Models\Traits\SpatieTranslatable\HasTranslations` (NOT spatie's trait).
- Define `protected $translatable = ['name', 'description']` on the model.
- DB columns for translatable fields must be JSON or TEXT type.
- Do NOT cast translatable string columns as array — Eloquent sees them as strings.
- Config available locales in `config/backpack/crud.php` → `locales`.
- `spatie/laravel-translatable` is a separate composer package.

## Ecosystem Packages (first-party Backpack add-ons)

- `backpack/pro` — 28+ fields, 10+ filters, 5 extra operations (Clone, BulkDelete, BulkClone, InlineCreate, Fetch), chart widgets. **Paid.**
- `backpack/permissionmanager` — CRUD interface for users, roles, permissions (spatie/laravel-permission based). Free.
- `backpack/editable-columns` — inline editing of columns in List view. **Paid.**
- `backpack/dataform-modal` — Create/Update forms in Bootstrap modals. **Paid.**
- `backpack/report-operation` — dashboard with stat/chart metrics per entity. **Paid.**
- `backpack/devtools` — web interface for generating migrations, models, CRUDs. **Paid.**
- `backpack/test-generators` — auto-generate tests for CrudControllers. **Paid.**
- `backpack/calendar-operation` — calendar interface for date-based entries. **Paid.**
- `backpack/translation-manager` — UI to translate multi-language apps. Free.
- `backpack/settings` — interface for website settings stored as config. Free.
- `backpack/pagemanager` — admin panel for presentation pages with templates. Free.
- `backpack/menucrud` — add, edit, reorder, nest menu items. Free.
- `backpack/newscrud` — news articles, categories, tags CRUD. Free.
- `backpack/medialibrary-uploaders` — Spatie MediaLibrary integration (use `->withMedia()` instead of `->withFiles()`). Free.
- `backpack/activity-log` — track who changed what and when. Free.
- `backpack/filemanager` — admin interface for files & folders (elFinder). Free.
- `backpack/logmanager` — preview, download, delete Laravel logs. Free.
- `backpack/backupmanager` — database and file backups via spatie/laravel-backup. Free.
- `backpack/revise-operation` — audit log with undo via venturecraft/revisionable. Free.
- `backpack/auto-translate` — auto-translate content to multiple languages. **Paid.**
- Temp file cleanup (PRO): schedule `backpack:purge-temporary-folder` daily.

## Queries & Access Control

- Eager loading: `CRUD::with(['relation1', 'relation2'])`.
- Query scoping: `CRUD::addClause('where', 'active', true)`. Base clause: `CRUD::addBaseClause(...)`.
- Access: `CRUD::allowAccess('list')`, `CRUD::denyAccess('delete')`, `CRUD::hasAccess('update')`.
- Per-entry: `CRUD::setAccessCondition('update', fn($entry) => ...)`.

## Widgets

- Sections: before_content, after_content, before_filters, after_filters, details_row.
- Add: `Widget::add()->type('progress')->to('before_content')->value(135)->description('Progress')`.
- Types: progress, card, chart, view, script, style, chip.
- Script widget: `Widget::add()->type('script')->content('assets/js/admin/forms/product.js')`.
- Remove: `Widget::remove('section-name')`. Make hidden: `Widget::make()`.

## Chips (view-based, no PHP class)

- Column: `CRUD::addColumn(['type' => 'chip', 'heading' => fn($e) => ..., 'details' => fn($e) => [...]])`.
- Widget: `Widget::add()->type('chip')->to('before_content')->heading('...')->details([...])`.
- Generate custom: `php artisan backpack:chip ChipName`.

## Save Actions

- Default: SaveAndBack, SaveAndEdit, SaveAndNew, SaveAndPreview, SaveAndList.
- Configure: `CRUD::setSaveActions([...])`, `CRUD::addSaveAction(...)`, `CRUD::orderSaveActions([...])`.
- Custom: extend AbstractSaveAction, implement `order()` and `getActionButtonHtml()`.

## Testing

- Package: `backpack/test-generators` is a **paid add-on**. If not installed, follow the paid package installation workflow in the Paid Features section.
- **After generating a CRUD**, if the package is installed, automatically run `php artisan backpack:tests --controller=ModelCrudController` to generate tests for the new CRUD.
- Generate: `php artisan backpack:tests`.
- Status: `php artisan backpack:tests:status`.
- Options: `--controller=Name`, `--operation=list`, `--framework=pest|phpunit`, `--force`.
- FREE alternative: Write Pest/PHPUnit tests manually — `rules/testing.md` has templates and examples.
- Requires factories and seeders for models with CrudControllers.
- Customize stubs: `php artisan vendor:publish --provider="Backpack\CRUD\BackpackServiceProvider" --tag=stubs`.

## JavaScript API (crud.field)

- Selector: `crud.field('field_name')`.
- Properties: `.name`, `.type`, `.input`, `.value`, `.row`.
- Events: `.onChange(fn(field) => ...)`, `.change()`.
- Methods: `.hide()`, `.show()`, `.disable()`, `.enable()`, `.require()`, `.unrequire()`, `.check()`, `.uncheck()`.
- Subfields: `.subfield('subfield_name')`.
- Always load scripts via `Widget::add()->type('script')`.

## Artisan Commands

- `php artisan backpack:crud ModelName` — full CRUD scaffold
- `php artisan backpack:field FieldName` — custom field type
- `php artisan backpack:column ColumnName` — custom column type
- `php artisan backpack:filter FilterName` — custom filter
- `php artisan backpack:operation OperationName` — custom operation
- `php artisan backpack:button ButtonName` — custom button
- `php artisan backpack:widget WidgetName` — custom widget
- `php artisan backpack:page PageName` — custom admin page
- `php artisan backpack:chart ChartName` — custom chart widget
- `php artisan backpack:install` — first-time Backpack install
- `php artisan backpack:tests` — generate CRUD tests
- `php artisan backpack:tests:status` — check test coverage

## Documentation Search

- If the `search-backpack-docs` MCP tool is available, use it for ALL Backpack questions (fields, columns, filters, operations, widgets, relationships). If not available, use web search instead.
- Do NOT use the `search-docs` MCP tool for Backpack questions — it does not index Backpack documentation.
- When using `search-backpack-docs`, pass multiple queries for OR logic: `["relationship field", "select2 belongsTo", "select2_from_ajax"]`.
- Use `"quoted phrases"` for exact matching.

</laravel-boost-guidelines>
