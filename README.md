# Sathsewa Welfare Society Member Management System

A modernized, web-based platform for managing the members, payments, and benefits of the Sathsewa Welfare Society. This application transitions the society from physical ledger books to an efficient, searchable digital system.

## Features

- **Member Management**: Add, edit, delete, and search members including details like Membership Number, NIC, and joining dates.
- **Dependent Tracking**: Manage a list of dependents for each member, useful for processing death donations.
- **Payment Ledger**: Track monthly contributions (Member Fees, Share Capital, Special Charges) in a grid view that mirrors traditional physical record books.
- **Benefit Payouts**: Record and track special benefits given to members, including Death Gratuities and Special Donations (sick leave, festival advances).
- **Comprehensive Reporting**: Generate detailed individual or full society reports with capabilities to print, export to CSV, or export to Excel.
- **Modern User Interface**: Built with an elegant design featuring glassmorphism, responsive data tables, beautiful icons, and interactive alert dialogs.

## Tech Stack

### Frontend
- **Vue 3** (Composition API, `<script setup>`)
- **Vite** for fast, optimized builds
- **Tailwind CSS v4** + Custom CSS for responsive, modern styling
- **SweetAlert2** for beautiful popup alerts and confirmation dialogs
- **Lucide Vue Next** for lightweight, crisp SVG action icons

### Backend
- **PHP** (Raw API endpoints)
- **MySQL** Database
- **PDO** for secure database connections and prepared statements

## Prerequisites

- **XAMPP / WAMP** or any environment running PHP 8.x and MySQL.
- **Node.js** (v18+) for running the Vite development server.

## Installation & Setup

### 1. Database Setup
1. Start Apache and MySQL in your XAMPP control panel.
2. The database schema and default admin user are provided in `database.sql`.
3. You can import this file via phpMyAdmin or the command line into a database named `sathsewa_society`.
   *(Default Admin Credentials - Username: `admin` | Password: `admin`)*

### 2. Backend Configuration
1. Ensure the project is located within your web server's document root (e.g., `C:\xampp\htdocs\sathsewa`).
2. Verify the database credentials in `api/db.php`. Ensure the password matches your local MySQL `root` user password.

### 3. Frontend Setup
1. Open a terminal in the project root directory.
2. Install the necessary dependencies:
   ```bash
   npm install
   ```
3. Start the development server:
   ```bash
   npm run dev
   ```
4. Access the application in your browser (typically at `http://localhost:5173` or `http://localhost:5174`). Note that API requests are automatically proxied to `http://localhost/sathsewa`.

## Key Commands
- `npm run dev`: Starts the local development server with hot-module replacement.
- `npm run build`: Compiles and minifies the frontend for production deployment.
- `npm run preview`: Previews the built production application locally.
