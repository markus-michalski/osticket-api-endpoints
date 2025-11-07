# Changelog - api-endpoints

All notable changes to this plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **Subticket API Endpoints** (#904125)
  - `GET /api/tickets-subtickets-parent.php/{child_id}.json` - Get parent ticket of a subticket
  - `GET /api/tickets-subtickets-list.php/{parent_id}.json` - Get list of child tickets
  - `POST /api/tickets-subtickets-create.php` - Create parent-child relationship
  - `DELETE /api/tickets-subtickets-unlink.php` - Remove parent-child relationship
  - New permission: `can_manage_subtickets` for API keys
  - Department-level authorization for subticket operations
  - Comprehensive API documentation in `docs/SUBTICKET_API.md`
  - 13 integration tests (8 workflow + 5 plugin-unavailable scenarios)

- **Infrastructure Improvements**
  - `SubticketApiController` with permission system and plugin detection
  - `SubticketTestDataFactory` for test fixture generation
  - `XmlHelper` class for XML conversion (eliminates code duplication)
  - Production-mode check for test API key bypass
  - HTTP status code validation for exception handling

### Changed

- **Code Quality Enhancements**
  - Optimized `getList()` method to reduce N+1 query pattern
  - Added JSON encoding with `JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE` flags
  - Improved null handling in ticket data formatting
  - Added strict type comparison in `in_array()` calls
  - Enhanced error handling with validated HTTP status codes

- **Testing**
  - Total test count: 77 tests (64 unit + 13 integration)
  - Added integration test infrastructure with `SubticketTestDataFactory`
  - Bootstrap now loads controller classes and test fixtures

### Security

- Added production-mode protection for `setTestApiKey()` method
- Enhanced XML output escaping for injection prevention
- Implemented department-level access control for subticket operations
- Improved input validation with type casting and range checks

### Fixed
- Nothing yet
