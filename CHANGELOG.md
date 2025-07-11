# Release Notes

## v1.2.0 `2025-06-17`

### Added

- Introduced `ShouldSyncToCrmOnSave` to automatically initiate CRM sync after model save.

### Changed

- Renamed `CrmTrait` to `HasCrmSync` in `namespace Wazza\SyncModelToCrm\Traits;`.
- Renamed `CrmController` to `CrmSyncController` in `namespace Wazza\SyncModelToCrm\Http\Controllers;`.
- Enhanced inline documentation in both trait files to better explain their use cases.
- Updated code to use the singleton instance for CRM sync instead of creating new instances. e.g. `app(CrmSyncController::class)` instead of `new CrmSyncController()`

### Important changes from v1.1.0
- Bulk update `CrmTrait` to `HasCrmSync` for all `Wazza\SyncModelToCrm\Traits\HasCrmSync` used files.

## v1.1.0 `2025-06-04`

### Stable Release 🚀

Our first stable release. The package has been in beta for a few months without any major issues. We will keep on improving the codebase and continue to add more sync functionality.

- Added the ability for the `smtc_external_key_lookup` mapping table to support both `uuid` and `int` foreign keys.
- Fixed a bug in the `HasCrmSync` file where the Model instance was not given but the Model name (string).
- Fixed PHP class types.
- Documentation updates.

## v1.0.0-beta `2024-09-06`

### Beta Release 🥳

Although only supporting `User` (Contact) and `Entity` (Company) synchronization for now, it should be working as intended.

## v0.3.0-alpha `2024-07-19`

Moving closer to Beta release. The aim for this release is to complete all unit testing and finalize a few loose ends.

### Added

-   `CODE_OF_CONDUCT.md` file.
-   `CONTRIBUTING.md` file.
-   `SECURITY.md` file.
-   Unit tests.

### Updated

-   Composer packages.

## v0.2.0-alpha `2024-07-04`

This marks the initial Alpha release of the project. The codebase is functional, though unit tests have not yet been implemented. Documentation remains incomplete, and several development tasks are still pending. Further Alpha updates will be released soon.

### Added

-   **all files**: First official deployment _(...as Alpha release)_.
