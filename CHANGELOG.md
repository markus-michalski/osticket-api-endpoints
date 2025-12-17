# Changelog - api-endpoints

All notable changes to this plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Nothing yet

### Changed
- Nothing yet

### Fixed
- Nothing yet

## [2.0.0] - 2025-12-17

### Changed
- Update PHP requirement to 8.1+ and test count to 168
- update package validation for new services and enums
- extract TicketService for CRUD operations
- extract validation & permission logic into services
- Extract ApiBootstrap and modernize to PHP 8.1+
- added zip to .gitignore
- Auto-sync from Markus-PC

### Fixed
- Update CI matrix to PHP 8.2+ (PHPUnit 11 requirement)
- Restore original composer.json for PHP 7.4+ compatibility
- Restore original composer.lock for PHP 8.1 compatibility

## [1.0.0] - 2025-11-08

### Added
- Add XML support to tickets-stats.php and tickets-statuses.php
- Add test infrastructure for integration tests
- Register DELETE Unlink Subticket endpoint in deployment system
- Implement DELETE Unlink Subticket endpoint
- Register POST Create Subticket Link endpoint in deployment system
- Implement POST Create Subticket Link with department authorization
- Register GET List Children endpoint in deployment system
- Implement GET List Children endpoint with DRY refactoring
- Register GET Parent Endpoint in deployment system
- Add subticket permission system and controller foundation
- Add tickets-statuses API endpoint for dynamic status lookup

### Changed
- Reset CHANGELOG.md für Release 1.0.0
- Add GitHub Actions workflow for automated testing
- Remove all debug logging from production code
- Remove test script with API key from git tracking
- Add cleanup step before CREATE subticket tests
- Change subticket endpoints to use ticket numbers instead of IDs
- Bump version to 1.2.1 for deployment
- Simplify SubticketApiController by removing plugin checks
- Replace dynamic .htaccess generation with static template
- debug: write to file instead of error_log to bypass config issues
- debug: add logging to tickets-get.php to diagnose wildcard routing
- debug: add constructor logging to track class instantiation
- debug: test both pre_uninstall AND uninstall hooks
- debug: add test logs to identify which hook is actually called
- cleanup only on uninstall, not on disable
- Remove redundant permission note and highlight important message
- Replace endpoint enable checkboxes with info section
- Add debug logging for subticket checkbox visibility issue
- Make API file deployment and .htaccess management dynamic
- Add comprehensive subticket API documentation
- Apply code review fixes from agent reviews
- Add SubticketPluginUnavailableTest integration tests
- Add SubticketWorkflowTest integration tests
- Add RED phase tests for DELETE Unlink Subticket endpoint
- Add RED phase tests for POST Create Subticket Link endpoint
- Add RED phase tests for GET List Children endpoint
- Add RED phase tests for GET Parent Ticket endpoint
- Add CLAUDE.md to .gitignore
- Initial repository setup for API Endpoints Plugin

### Fixed
- Behebe verbleibende 11 Test-Failures (11 → 0)
- Implement plugin availability control for tests (27 → 16 failures)
- Resolve all test errors (22 → 0) by fixing ID/Number confusion
- Pin doctrine/instantiator to ^1.5 for PHP 7.4 compatibility
- Improve test infrastructure and controller error handling
- Use HTTP 422 instead of 409 for duplicate relationships
- Use array data from getChildren instead of Ticket lookup
- Load SubticketPlugin class in all subticket API endpoints
- Use direct SubticketPlugin instantiation instead of PluginManager
- Use correct Subticket Manager Plugin method signatures
- Use correct unlinkTicket method instead of non-existent removeLink
- Add shutdown handler workaround for removeLink exit issue
- Correct PATH_INFO format in test URLs (use /.json and /.xml instead of .json and .xml)
- Resolve HTTP 500 errors in topicId update and parent lookup
- Always replace .htaccess rules on plugin enable to apply template updates
- Allow PATH_INFO suffix in .htaccess rules for stats/statuses/search
- Change getApiKey() visibility from private to protected
- Remove trailing slash from tickets-search.php .htaccess rule
- Add missing files to .releaseinclude for deployment
- Correct TEST_CURL_COMMANDS.sh BASE_URL for direct API access
- correct .htaccess trailing slash logic for subticket endpoints
- move cleanup to enable() - osTicket doesn't call uninstall hooks
- use pre_uninstall hook instead of uninstall for cleanup
- remove debug logging and make installed_version field visible
- remove obsolete endpoint validation from pre_save
- add debug logging to cleanup methods for troubleshooting
- Cleanup works now even after plugin deletion
- Simplify subticket checkbox rendering - always show without plugin check
- Show can_manage_subtickets checkbox even without Subticket Manager Plugin
- Use setTopicId() method instead of direct property access
- Address critical security and performance issues from Phase 2 review

### Security
- Remove API key from error logs
- Fix critical vulnerabilities in legacy API endpoints

