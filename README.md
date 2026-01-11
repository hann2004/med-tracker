# Med Tracker (Arba Minch)

<p align="center">
	<img src="https://img.shields.io/badge/PHP-8.x-777BB4?logo=php&logoColor=white" alt="PHP" />
	<img src="https://img.shields.io/badge/MySQL-8-00618A?logo=mysql&logoColor=white" alt="MySQL" />
	<img src="https://img.shields.io/badge/Chart.js-4-FF6384?logo=chartdotjs&logoColor=white" alt="Chart.js" />
	<img src="https://img.shields.io/badge/Font%20Awesome-6-228BE6?logo=fontawesome&logoColor=white" alt="Font Awesome" />
</p>

Med Tracker is a PHP/MySQL web application for searching medicines across pharmacies in Arba Minch, managing pharmacy inventory, and providing admin analytics. It supports three roles: `admin`, `pharmacy`, and `user` with role‑based dashboards and real data visualizations.

## Features

- Medicine search with location context and availability
- Pharmacy inventories with quantities, pricing, expiry, and low‑stock indicators
- Admin panels: users, pharmacies (verification), medicines, categories
- Search analytics (live charts) and system activity overview
- User and pharmacy authentication with verification flow
- Clean, minimal dashboards with auto‑refreshing charts

## Tech Stack

- PHP 8+ (procedural style)
- MySQL 8 (views, triggers, stored procedures)
- XAMPP/LAMPP (Apache + MySQL) on Linux
- Chart.js for analytics visualizations
- Font Awesome, Inter font, lightweight custom CSS

## Prerequisites

- Linux with LAMPP/XAMPP installed (Apache + MySQL running)
- PHP extensions: `mysqli`
- Git (optional, for pulling the repo)

## Setup

1. Copy the project to Apache web root:
	 - Path used here: `/opt/lampp/htdocs/med-tracker`

2. Create the database and sample data:
	 - Start MySQL (e.g., `sudo /opt/lampp/lampp start`)
	 - Import the schema:
		 - Open phpMyAdmin or run from terminal:
			 ```sql
			 SOURCE /opt/lampp/htdocs/med-tracker/database/advanced_schema.sql;
			 ```
		 - This creates database `med_tracker_pro` with tables, views, triggers, and inserts sample data.

3. Configure database connection:
	 - Edit `config/database.php` if needed (host, username, password). Default assumes local MySQL with a user that can access `med_tracker_pro`.

4. (Optional) Email config:
	 - See `config/email_config.php` if you plan to send emails (verification/reset). This app runs without outbound email by default.

5. Visit the application:
	 - Admin: `http://localhost/med-tracker/admin/dashboard.php`
	 - Pharmacy: `http://localhost/med-tracker/pharmacy/dashboard.php`
	 - User: `http://localhost/med-tracker/user/dashboard.php`
	 - Login: `http://localhost/med-tracker/login.php`

## Default Credentials (Sample Data)

- Admin: `admin` / `password123`
- Pharmacy owners: `enat_pharmacy`, `model_pharmacy`, `beminet_pharmacy`, `mihret_pharmacy`, `nechisar_pharmacy`, `covenant_pharmacy` — all `password123`
- Users: `john_doe`, `mary_smith`, `david_williams` — all `password123`

## Roles & Dashboards

- Admin
	- Dashboard: system overview cards and recent activities
	- Management: Users, Pharmacies (verify), Medicines, Categories
	- Analytics: Reports, Search Analytics (live charts)
	- Minimal topbar (Profile/Logout), footer pinned

- Pharmacy
	- Dashboard: doughnut chart (inventory value by category), auto‑refresh
	- Inventory: list, filters; add view for creating medicines/inventory
	- Reviews page

- User
	- Dashboard & Search pages focusing on discovery

## Key Pages & Endpoints

- Admin
	- Pages: `admin/dashboard.php`, `admin/manage_users.php`, `admin/manage_pharmacies.php`, `admin/manage_medicines.php`, `admin/categories.php`, `admin/profile.php`
	- Analytics: `admin/search_analytics.php`
	- JSON: `admin/search_analytics_data.php`

- Pharmacy
	- Pages: `pharmacy/dashboard.php`, `pharmacy/inventory.php`, `pharmacy/reviews.php`, `pharmacy/profile.php`
	- JSON: `pharmacy/dashboard_data.php`

- Search
	- Public: `search.php`, `search_api.php`

## Database Highlights

- Database: `med_tracker_pro`
- Tables: users, pharmacies, medicines, medicine_categories, pharmacy_inventory, searches, views, reviews_and_ratings, medicine_requests, notifications, audit_logs, etc.
- Views: `vw_medicine_availability`, `vw_pharmacy_stats`, `vw_popular_searches`, `vw_expiring_medicines`
- Procedures: `sp_search_medicine`, `sp_get_pharmacy_inventory_stats`, `sp_get_user_dashboard_stats`
- Triggers for inventory history, rating updates, session hygiene

## Development Notes

- Charts auto‑refresh every 60s with animations disabled to avoid layout shift
- Minimalist admin topbar; settings/backup/system logs links removed
- Verify pharmacy action also marks the owner user as verified
- Inventory add form is shown only when `view=add` on pharmacy inventory

## Directory Structure

```
admin/                    # admin dashboards & management
config/                   # database & email config
database/                 # schema and sample data
includes/                 # auth + domain functions
pharmacy/                 # pharmacy dashboards
user/                     # user dashboards
js/                       # frontend JS
styles/                   # CSS
```

## Running Locally (LAMPP)

1. Start services:
	 ```bash
	 sudo /opt/lampp/lampp start
	 ```
2. Ensure database `med_tracker_pro` exists and is populated (see Setup).
3. Browse to `http://localhost/med-tracker/login.php` and sign in using sample credentials.

## Contributing

- Use feature branches and pull requests
- Keep changes minimal and focused; prefer updating docs alongside code changes

## Disclaimer

This app ships with sample data and basic auth flows for demonstration. Review and harden configurations for production use (password policies, email verification, rate limiting, etc.).