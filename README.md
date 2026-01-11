# n8n Host Manager Panel

**A robust, backend-first control panel built with Laravel for provisioning and managing n8n instances via Docker.**
Designed for VPS hosting environments and SaaS providers, this system offers **role-based access**, **automated provisioning**, **resource management**, and a **comprehensive API** for external integrations (WHMCS).

---

## 🚀 Key Features

### 🖥️ Dashboard & Monitoring
* **System Status:** Real-time monitoring of Hostname, OS, Kernel Version, IP Addresses, and Uptime.
* **Instance Overview:** View all hosted instances with quick status indicators (Running, Stopped, Paused).
* **Resource Stats:** Live CPU and Memory usage tracking per instance.
* **Modern UI:** Responsive, Slate-themed interface with dark mode support and sidebar search.

### 📦 Instance Management
* **Automated Provisioning:** One-click deployment of n8n containers with Nginx reverse proxy and SSL configuration.
* **Power Controls:** Start, Stop, Restart, Suspend, and Unsuspend instances instantly.
* **Resource Packages:** Define limits for CPU, RAM, and Disk usage via configurable packages.
* **Live Logs:** View real-time container logs with download and clipboard copy support.
* **Upgrades:** Seamlessly upgrade instance resource packages with immediate effect.

### 🔑 Role-Based Access Control (RBAC)
* **Granular Permissions:** Built on Spatie Permissions. Assign specific capabilities (e.g., `manage_instances`, `view_logs`) to custom roles.
* **User Roles:**
  * **Admin:** Full system access, including server power management and API logs.
  * **Reseller:** Manage assigned users and instances.
  * **Client:** Access to personal instances only.

### 🔌 Integration API
A full-featured REST API designed for integration with billing systems like **WHMCS**.
* **Endpoints:** Create, Terminate, Suspend, Unsuspend, Upgrade, and Get Stats.
* **Security:** Authenticated via Sanctum Tokens with IP Whitelisting capabilities.
* **Logging:** Comprehensive database logging of all API requests with sensitive data redaction.
* **Documentation:** Built-in API documentation panel with dynamic curl examples.

### ⚙️ System Administration
* **Server Management:** Update Hostname, Reboot Server, and Restart Services (Nginx, MySQL, Docker) directly from the panel.
* **API Logs:** Audit trail of all external API interactions.
* **Orphan Discovery:** Detect and import unmanaged Docker containers into the panel.

---

## 🛠️ Technical Stack

* **Framework:** Laravel 11 (PHP 8.2+)
* **Database:** MySQL / MariaDB (or SQLite)
* **Containerization:** Docker (managed via `sudo` process wrappers)
* **Web Server:** Nginx (Automated VHost management)
* **Frontend:** Blade Templates + Bootstrap 5 (No heavy Node.js build steps required)

---

## 📥 Installation

1. **Clone the Repository**
   ```bash
   git clone https://github.com/your-repo/n8n-panel.git
   cd n8n-panel
   ```

2. **Install Dependencies**
   ```bash
   composer install
   ```

3. **Environment Setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   *Configure your database settings in `.env`.*

4. **Database Migration & Seeding**
   ```bash
   php artisan migrate --seed
   ```
   *Seeds default roles (Admin, Reseller) and permissions.*

5. **Server Configuration**
   Ensure the web user (e.g., `www-data`) has `sudo` privileges for specific commands (`docker`, `systemctl`, `hostnamectl`) without password prompt. Add to `/etc/sudoers`:
   ```bash
   www-data ALL=(root) NOPASSWD: /usr/bin/docker, /usr/bin/systemctl, /usr/bin/hostnamectl, /usr/sbin/reboot
   ```

---

## 🔗 API Usage

The panel provides a dedicated API for external integrations.

**Base URL:** `https://your-panel.com/api/integration`

### Authentication
Include your API Token in the header:
`Authorization: Bearer <your-token>`

### Common Endpoints
* `POST /instances/create` - Provision a new instance.
* `POST /instances/{id}/start` - Start an instance.
* `POST /instances/{id}/stop` - Stop an instance.
* `GET /instances/{id}/stats` - Get JSON-formatted resource usage.
* `POST /instances/{id}/upgrade` - Change resource package.

*Check the **"Manage API Tokens"** section in the user profile for full documentation and example requests.*

---

## 📝 License

This software is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
