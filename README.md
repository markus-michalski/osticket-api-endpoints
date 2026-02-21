# API Endpoints Plugin for osTicket

Extends osTicket's REST API with powerful endpoints for advanced ticket management. Enables ticket creation with Markdown formatting, department routing, subticket support, and comprehensive ticket management (update, retrieve, search, delete, statistics).

## Key Features

- Extended Ticket Creation with Markdown formatting, department routing, subticket support
- Full Ticket Management (update, retrieve, search, delete)
- Subticket API for parent-child relationships (requires [Subticket Manager Plugin](https://github.com/clonemeagain/plugin-subticket))
- Ticket Statistics (global, by department, by staff)
- Granular API Key Permissions per endpoint
- Name-Based Filters (human-readable names instead of IDs)
- No Core Modifications (Signal-based architecture, update-safe)
- TDD-Tested with 168 tests

## Requirements

- osTicket **1.18.x**
- PHP **8.1+**
- **Optional**: [Markdown Support Plugin](https://github.com/markus-michalski/osticket-plugins/tree/main/markdown-support) for Markdown rendering

## Installation

1. Download the latest release from [Releases](https://github.com/markus-michalski/osticket-plugins/releases)
2. Upload the `api-endpoints` folder to `/include/plugins/` on your osTicket server
3. Enable in **Admin Panel > Manage > Plugins**

## Documentation

- **English**: https://faq.markus-michalski.net/en/osticket/api-endpoints
- **Deutsch**: https://faq.markus-michalski.net/de/osticket/api-endpoints
- **API Reference**: https://faq.markus-michalski.net/en/osticket/api-endpoints/api-documentation

## License

Released under the GNU General Public License v2, compatible with osTicket core. See [LICENSE](./LICENSE).

## Changelog

See [CHANGELOG.md](./CHANGELOG.md) for version history.
