# DSA Sales Portal - PHP Version

This is the PHP port of the DSA Sales Portal, originally developed as a React/TypeScript application.

## Features

- User authentication and role management
- Employee dashboard with application statistics
- Admin dashboard for system oversight
- Loan application submission and tracking
- Document management
- User approval workflow
- KYC verification system
- Comprehensive security and audit logging

## Setup Instructions

1. Clone this repository
2. Set up a MySQL database and import the database schema from `database/schema.sql`
3. Configure your database connection in `config/database.php`
4. Ensure your web server points to the project's root directory
5. Navigate to the application in your web browser

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server with mod_rewrite enabled
- GD library for image processing

## Folder Structure

- `/config` - Configuration files
- `/includes` - Common include files
- `/pages` - Page templates
- `/assets` - Static assets (CSS, JavaScript, images)
- `/uploads` - Document upload directory

## User Roles

- **DSA** - Direct Sales Agent
- **Freelancer** - Independent sales agents
- **Finonest Employee** - Internal company employees
- **Admin** - System administrators


## License

Copyright © 2024 Finonest. All rights reserved.
