PRACTICAL 13 — PHP JSON API + Fetch API CRUD

FILES
- database.sql : creates dashboard_db, items and menu_items tables
- db.php       : PDO database connection
- api.php      : JSON API endpoints (read/create/update/delete/get_menu)
- index.php    : frontend dashboard using Fetch API

RUN USING XAMPP
1. Install/start XAMPP. Start Apache and MySQL.
2. Open http://localhost/phpmyadmin
3. Choose Import and import database.sql (or paste its contents into the SQL tab).
4. Copy this folder into C:\xampp\htdocs\practical13_fixed\
5. If your MySQL root account has a password, update $pass in db.php.
6. Open http://localhost/practical13_fixed/index.php
7. Add an item, edit it, delete it, and refresh to verify persistence.

IMPORTANT
- Do not open index.php directly as a file; use localhost so PHP runs.
- The role selector is a classroom demonstration only, not real authentication. A production application must enforce authorization on the server using authenticated sessions.
- If PHP reports that mb_strlen is undefined, enable/install the PHP mbstring extension or replace mb_strlen with strlen for this practical.
