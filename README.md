# n8n Control Panel

**A lightweight, backend-first control panel built with Laravel for managing n8n instances and Docker containers.**
Designed for VPS hosting environments, this system supports **role-based access**, **container management**, and **user-level resource control**.

---

## Features

### 1. **User Management & Authentication**

* Secure login system with **email/password authentication**.
* Forgot password functionality.
* **Role-based access**:

  * **Admin** – Full access to all system features.
  * **Reseller** – Limited access to assigned containers and user resources.
* Middleware guards prevent unauthorized access to protected routes.

---

### 2. **Role & Permission Management**

* Powered by **Spatie Laravel Permission** package.
* **Assign roles** to users (admin or reseller).
* Role-based dashboard routing:

  * Admins are directed to the admin panel.
  * Resellers are directed to a reseller-specific dashboard.
* Easily expandable with custom permissions for advanced access control.

---

### 3. **Docker Container Management**

* Integrates **Spatie Docker package** for Laravel-friendly Docker API.
* **Container CRUD operations**:

  * List all containers (with filtering by user if applicable).
  * Start, stop, and remove containers directly from the dashboard.
  * Create new containers with configurable options.
* **Container monitoring**:

  * View container IDs, names, status, and resource usage.
  * Supports `running`, `stopped`, and `paused` states.
* Role-based container management:

  * Admins can manage all containers.
  * Resellers manage only assigned containers.

---

### 4. **Resource Management**

* Assign CPU and RAM limits per container (optional).
* Monitor container resource consumption.
* Ensure isolated resources for multiple users or resellers.

---

### 5. **Dashboard & UI**

* Minimal, backend-first **Blade templates** (no npm/Vite required).
* User-friendly tables for container management.
* Action buttons for **Start**, **Stop**, and **Remove** operations.
* Extendable layout for future enhancements.

---

### 6. **Security & Permissions**

* All routes protected via **Laravel middleware**.
* Role-based route guards prevent unauthorized operations.
* Web server restricted from directly manipulating Docker — all operations run via controlled Laravel services.
* No frontend dependencies — reduces attack surface and simplifies server management.

---

### 7. **Extensibility**

* **Add new roles or permissions** using Spatie’s role management.
* Easily integrate **additional n8n instance management features**:

  * Environment variable updates per instance.
  * Logs access.
  * Container rebuild/restart operations.
* Can be extended with APIs for automated VPS provisioning or SaaS reseller dashboards.

---

### 8. **Developer-Friendly Backend**

* Fully Laravel-powered architecture.
* Clean MVC separation.
* Services for Docker abstraction (`DockerService`) to allow centralized container management logic.
* Easily extendable for custom business logic, automated workflows, or integration with n8n APIs.

---

### 9. **Key Advantages**

* Lightweight and minimal — no npm, no Vite required.
* Supports multi-user environment with secure role-based access.
* Centralized Docker container management for VPS hosting.
* Modular and developer-friendly design, making it ideal for SaaS hosting, reseller platforms, or internal automation dashboards.

