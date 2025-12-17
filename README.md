# API Endpoints Plugin for osTicket

## Overview

Extends osTicket's REST API with powerful endpoints for advanced ticket management. Enables ticket creation with Markdown formatting, department routing, subticket support, and comprehensive ticket management (update, retrieve, search, delete, statistics).

Perfect for integrations that need granular control beyond osTicket's standard API capabilities - ideal for support portals, bug trackers, automation platforms (Zapier/Make.com), and custom workflows.

## Key Features

- ✅ **Extended Ticket Creation** - Markdown formatting, department routing, subticket support
- ✅ **Ticket Management** - Update, retrieve, search, delete tickets via API
- ✅ **Subticket API** - Manage parent-child relationships between tickets (NEW!)
- ✅ **Ticket Statistics** - Get comprehensive stats (global, by department, by staff)
- ✅ **Granular Permissions** - Fine-grained API key permissions (create, read, update, search, delete, subtickets)
- ✅ **Name-Based Filters** - Use human-readable names instead of IDs
- ✅ **No Core Modifications** - Signal-based architecture (update-safe)
- ✅ **TDD-Tested** - 168 tests with comprehensive coverage

## Use Cases

- **Support Portals** - Create tickets directly in specific departments
- **Bug Trackers** - Submit tickets with Markdown-formatted code snippets
- **Follow-Up Workflows** - Automatically create subtickets for escalations
- **Automation Platforms** - Zapier/Make.com integrations with rich formatting
- **Team Management** - Search, update, and analyze tickets programmatically
- **Dashboards** - Display real-time ticket statistics and metrics

## Requirements

- osTicket **1.18.x**
- PHP **8.1+** (uses modern PHP features: enums, union types, named arguments)
- **Optional**: [Markdown Support Plugin](https://github.com/markus-michalski/osticket-plugins/tree/main/markdown-support) for Markdown rendering

## Installation

### Step 1: Install Plugin Files

#### Method 1: ZIP Download (Recommended)

1. Download the latest release from [Releases](https://github.com/markus-michalski/osticket-plugins/releases)
2. Extract the ZIP file
3. Upload the `api-endpoints` folder to `/include/plugins/` on your osTicket server

#### Method 2: Git Repository

```bash
cd /path/to/osticket/include/plugins
git clone https://github.com/markus-michalski/osticket-plugins.git
# Plugin will be in: osticket-plugins/api-endpoints/
```

### Step 2: Enable Plugin in osTicket

1. Login to osTicket Admin Panel
2. Navigate to: **Admin Panel → Manage → Plugins**
3. Find **"API Endpoints"** in the list
4. Click **"Enable"**

The plugin automatically hooks into the API via osTicket's Signal system.

### Step 3: Configure Plugin (Optional)

1. Click **"Configure"** next to "API Endpoints"
2. Toggle individual endpoints on/off as needed
3. Configure API key permissions

## Configuration

| Setting | Description | Default |
|---------|-------------|---------|
| Enable API Endpoints | Globally enable/disable all extended endpoints | ✅ On |
| Enable POST /tickets | Allow ticket creation with extended parameters | ✅ On |
| Enable GET /tickets/:number | Allow retrieving ticket details | ✅ On |
| Enable PATCH /tickets/:number | Allow updating tickets | ✅ On |
| Enable GET /tickets/search | Allow searching tickets | ✅ On |
| Enable DELETE /tickets/:number | Allow deleting tickets | ❌ Off (safety) |
| Enable GET /tickets-stats | Allow retrieving statistics | ✅ On |
| Require Markdown Plugin | Reject `format=markdown` if plugin not active | ❌ Off |

### API Key Permissions

Navigate to: **Admin Panel → Manage → API Keys → Configure**

| Permission | Grants Access To |
|------------|------------------|
| `can_create_tickets` | POST /tickets (create) |
| `can_read_tickets` | GET /tickets/:number (retrieve) |
| `can_update_tickets` | PATCH /tickets/:number (update) |
| `can_search_tickets` | GET /tickets/search (search) |
| `can_delete_tickets` | DELETE /tickets/:number (delete) |
| `can_read_stats` | GET /tickets-stats (statistics) |

## Usage

**For complete API documentation, see:** [api_endpoint_description.md](./api_endpoint_description.md)

### Quick Start Examples

#### Create Ticket with Markdown
```bash
curl -X POST "https://yourdomain.com/api/tickets.json" \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Developer",
    "email": "dev@example.com",
    "subject": "Bug Report",
    "message": "# Bug\n\n```php\n$code = \"example\";\n```",
    "format": "markdown",
    "departmentId": "Development"
  }'
```

#### Update Ticket Status
```bash
curl -X PATCH "https://yourdomain.com/api/tickets-update.php/191215.json" \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"statusId": "Resolved"}'
```

#### Search Tickets
```bash
curl -X GET "https://yourdomain.com/api/tickets-search.php?query=login&status=Open" \
  -H "X-API-Key: YOUR_API_KEY"
```

#### Get Statistics
```bash
curl -X GET "https://yourdomain.com/api/tickets-stats.php" \
  -H "X-API-Key: YOUR_API_KEY"
```

**See [api_endpoint_description.md](./api_endpoint_description.md) for complete documentation.**

## Troubleshooting

### Problem: Markdown not rendering

**Check:**
- Install [Markdown Support Plugin](https://github.com/markus-michalski/osticket-plugins/tree/main/markdown-support)
- Enable "Markdown Plugin" in osTicket Admin Panel → Manage → Plugins
- Verify `format=markdown` parameter in API request

### Problem: Department not found

**Check:**
- Department name is spelled correctly (case-insensitive)
- Department is active (not archived)
- Use department ID instead: `"departmentId": 5`

### Problem: API key permission denied

**Check:**
- Navigate to: Admin Panel → Manage → API Keys → Configure
- Enable required permission checkboxes for your API key
- After plugin updates, disable + re-enable plugin to update database schema

### Problem: Cannot delete tickets

**Check:**
- DELETE endpoint is enabled in plugin configuration
- API key has `can_delete_tickets` permission explicitly granted
- DELETE permission is disabled by default for safety

### Problem: Using NGINX instead of Apache

**Important:** This plugin automatically configures Apache `.htaccess` rewrite rules during installation. If you're using **NGINX**, you need to manually add the following configuration to your NGINX server block:

```nginx
# osTicket API Endpoints Plugin - NGINX Configuration
# Add this to your server block (inside the /api location or at root level)

# Endpoints with path info (e.g., /api/tickets-update.php/123456.json)
location ~ ^/api/tickets-(update|get|delete)\.php/ {
    fastcgi_split_path_info ^(/api/tickets-(update|get|delete)\.php)(/.+)$;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_param PATH_INFO $fastcgi_path_info;
    include fastcgi_params;
    fastcgi_pass unix:/var/run/php/php-fpm.sock;  # Or 127.0.0.1:9000
}

# Endpoints without path info (e.g., /api/tickets-search.php?query=test)
location ~ ^/api/tickets-(search|stats)\.php$ {
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;
    fastcgi_pass unix:/var/run/php/php-fpm.sock;  # Or 127.0.0.1:9000
}
```

**After adding the configuration:**
1. Test the configuration: `sudo nginx -t`
2. Reload NGINX: `sudo systemctl reload nginx`
3. Test the API endpoints with curl or your API client

**Note:** Adjust `fastcgi_pass` to match your PHP-FPM socket path:
- Common paths: `unix:/var/run/php/php8.1-fpm.sock`, `127.0.0.1:9000`, or `unix:/run/php-fpm/www.sock`

## 📄 License

This Plugin is released under the GNU General Public License v2, compatible with osTicket core.

See [LICENSE](./LICENSE) for details.

## 💬 Support

For questions or issues, please create an issue on GitHub:
https://github.com/markus-michalski/osticket-plugins/issues

## 🤝 Contributing

Developed by [Markus Michalski](https://github.com/markus-michalski/osticket-plugins)

Inspired by the osTicket community's need for extended API capabilities and automation workflows.

## Changelog

See [CHANGELOG.md](./CHANGELOG.md) for version history.

## Subticket API

Manage parent-child relationships between tickets with dedicated REST endpoints.

**Requirements:**
- API key with `can_manage_subtickets` permission
- [Subticket Manager Plugin](https://github.com/clonemeagain/plugin-subticket) installed

**Endpoints:**
- `GET /api/tickets-subtickets-parent.php/{child_id}.json` - Get parent ticket
- `GET /api/tickets-subtickets-list.php/{parent_id}.json` - Get all children
- `POST /api/tickets-subtickets-create.php` - Create parent-child link
- `DELETE /api/tickets-subtickets-unlink.php` - Remove parent-child link

📖 **Full Documentation:** [docs/SUBTICKET_API.md](docs/SUBTICKET_API.md)

**Example:**
```bash
# Link tickets
curl -X POST "https://your-osticket.com/api/tickets-subtickets-create.php" \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"parent_id": 100, "child_id": 200}'

# Get children
curl -X GET "https://your-osticket.com/api/tickets-subtickets-list.php/100.json" \
  -H "X-API-Key: YOUR_API_KEY"
```

