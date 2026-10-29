# Complaint Management System

A web-based complaint management system for educational institutions. Students submit complaints, a Super Admin assigns them to Admins, Admins resolve them, and everyone can upvote or downvote complaints to show which issues matter most.

Built as a Diploma Computer Engineering Minor Project (Semester V) at S.P.B Patel Engineering College, under Gujarat Technological University.

## Features

### Student
- Submit a complaint with a category (Hostel, Faculty, Facility, Sports, Other) and a description
- Track the status of own complaints: Pending, Work in Progress, Resolved
- Read the admin's resolution note once a complaint is resolved
- Leave a star rating and written review on resolved complaints

### Admin
- See only the complaints assigned to them, split into Active and Resolved sections
- Update the status of a complaint
- Write a resolution note (required when marking a complaint Resolved) that the student can read

### Super Admin
- See all complaints in the system, split into Active and Resolved sections
- Assign each complaint to an admin
- Set importance (Not Important, Normal, Important) and urgency (Not Urgent, Normal, Urgent)
- Flagged tab: complaints still unresolved after more than one day appear here automatically

### Shared
- Public complaint board where every logged-in user can upvote or downvote complaints (one vote per user per complaint)
- Top Complaints board, available to Admins and the Super Admin, ranked by vote score
- Login and logout with role-based redirects and role checks on every protected page

## Tech Stack

| Layer | Technology |
|---|---|
| Front end | HTML5, CSS3 |
| Back end | PHP 8.x (plain PHP, no framework) |
| Database | MySQL / MariaDB, accessed with PDO |
| Local server | XAMPP (Apache, MySQL, PHP) |

Security basics: passwords are hashed with `password_hash()` and checked with `password_verify()`, all queries use prepared statements, and access is controlled with PHP sessions.

## Folder Structure

```
complaint-management/
├── admin/          Admin pages (assigned complaints)
├── super_admin/    Super Admin pages (dashboard, flagged complaints)
├── student/        Student pages (submit, my complaints, public board)
├── login/          Login, logout and login form
├── voting/         Upvote / downvote handling
├── includes/       Shared code (session setup, database connection, header)
├── assets/css/     Stylesheet
├── config.php      Database settings and BASE_URL
└── schema.sql      Database tables and starting accounts
```

## Setup (XAMPP on Linux)

1. **Install and start XAMPP**
   ```
   sudo /opt/lampp/lampp start
   ```
   Check that Apache and MySQL both show as running with `sudo /opt/lampp/lampp status`.

2. **Place the project** in XAMPP's web folder so the path is:
   ```
   /opt/lampp/htdocs/complaint-management/
   ```

3. **Create the database**
   - Open `http://localhost/phpmyadmin`
   - Create a database named `complaint_management`
   - Select it, open the **Import** tab, and import `schema.sql`

4. **Check the settings** in `config.php`:
   - Database name: `complaint_management`
   - Database user: `root` with no password (XAMPP default)
   - `BASE_URL`: `/complaint-management`

5. **Open the app** at `http://localhost/complaint-management/login/`

## Default Accounts

The starting accounts are created by `schema.sql`:

| Role | Username |
|---|---|
| Student | `yash` |
| Admin | `admin` |
| Super Admin | `superadmin` |

The passwords are set in `schema.sql`. Change them if you plan to use the project outside a local demo.

**Tip:** one browser shares a single login session across tabs. To test several roles at once, use a normal window plus a private (incognito) window, or two different browsers.

## Troubleshooting

| Problem | Likely cause and fix |
|---|---|
| MySQL will not start in XAMPP | A system MySQL service is using the same port. Stop it with `sudo systemctl stop mysql` and disable it with `sudo systemctl disable mysql`. |
| Blank page or HTTP 500 error | Read the PHP error log: `sudo tail -n 20 /opt/lampp/logs/php_error_log` |
| "Unknown column" database error | The database and the code are out of sync. Re-import `schema.sql` into a fresh database. |
| A page loads but a button does nothing | Check that the form's `action` points to the PHP file that handles it. |
| Links lead to a 404 page | Check that `BASE_URL` in `config.php` matches the real folder name (`/complaint-management`). |

