# n8n Host Manager API Documentation

This API enables external systems (such as WHMCS, billing platforms, or custom dashboards) to manage n8n instances, packages, and system resources.

## Base URL

All API requests should be sent to:
```
https://your-panel-domain.com/api/integration
```

## Authentication

The API uses **Bearer Token** authentication. You must include your API Token in the `Authorization` header for every request.

```http
Authorization: Bearer <your_api_token>
Content-Type: application/json
Accept: application/json
```

*You can generate API Tokens from the "Manage API Tokens" section in your user profile.*

---

## Endpoints

### 1. Connection & System

#### Test Connection
Verify your API token is valid and the server is reachable.

*   **Endpoint:** `GET /connection/test`
*   **Response:**
    ```json
    {
      "status": "success",
      "message": "Connection successful",
      "user": {
        "id": 1,
        "name": "Admin User",
        "email": "admin@example.com"
      }
    }
    ```

#### Get System Stats
Retrieve server health metrics and usage counts.

*   **Endpoint:** `GET /system/stats`
*   **Response:**
    ```json
    {
      "status": "success",
      "server_status": "online",
      "load_averages": { "1": 0.5, "5": 0.3, "15": 0.1 },
      "counts": {
        "users": 5,
        "instances_total": 10,
        "instances_running": 8,
        "instances_stopped": 2
      }
    }
    ```

---

### 2. Instance Management

#### Create Instance
Provision a new n8n instance for an existing user.

*   **Endpoint:** `POST /instances/create`
*   **Body Parameters:**
    *   `email` (string, required): Existing user email.
    *   `package_id` (int, required): ID of the resource package.
    *   `name` (string, required): Unique instance name (alpha-dash).
    *   `version` (string, optional): n8n version tag (default: 'latest').
*   **Response:**
    ```json
    {
      "status": "success",
      "instance_id": 12,
      "domain": "my-instance.panel-domain.com",
      "user_id": 5
    }
    ```

#### Get Instance Stats
Get real-time resource usage for a specific instance.

*   **Endpoint:** `GET /instances/{id}/stats`
*   **Response:**
    ```json
    {
      "status": "success",
      "domain": "my-instance.panel-domain.com",
      "instance_status": "running",
      "cpu_percent": 0.15,
      "memory_usage": "250MiB",
      "memory_limit": "1GiB",
      "memory_percent": 24.4
    }
    ```

#### Instance Power Actions
Perform power operations on an instance.

*   **Start:** `POST /instances/{id}/start`
*   **Stop:** `POST /instances/{id}/stop`
*   **Suspend:** `POST /instances/{id}/suspend` (Stops and marks as suspended)
*   **Unsuspend:** `POST /instances/{id}/unsuspend` (Unmarks and starts)
*   **Terminate:** `POST /instances/{id}/terminate` (Permanently deletes data)

**Response:**
```json
{ "status": "success" }
```

#### Upgrade Package
Change the resource package for an instance. New limits are applied immediately.

*   **Endpoint:** `POST /instances/{id}/upgrade`
*   **Body Parameters:**
    *   `package_id` (int, required): New package ID.
*   **Response:**
    ```json
    {
      "status": "success",
      "message": "Package updated and resources applied.",
      "new_package": "Pro Plan"
    }
    ```

---

### 3. Packages & Resellers

#### List Packages
Get all available resource packages.

*   **Endpoint:** `GET /packages`
*   **Response:**
    ```json
    {
      "status": "success",
      "packages": [
        { "id": 1, "name": "Starter", "cpu_limit": 1.0, "ram_limit": 1.0, "disk_limit": 10 }
      ]
    }
    ```

#### Get Package Details
*   **Endpoint:** `GET /packages/{id}`

#### Create Reseller
Create a new user with 'reseller' role.

*   **Endpoint:** `POST /resellers`
*   **Body Parameters:**
    *   `name`, `email`, `password` (all required)
*   **Response:**
    ```json
    { "status": "success", "user_id": 15 }
    ```

---

## Error Handling

The API returns standard HTTP status codes:
*   `200/201`: Success
*   `401`: Unauthenticated (Missing/Invalid Token)
*   `403`: Unauthorized (Insufficient Permissions or IP not whitelisted)
*   `404`: Resource Not Found
*   `422`: Validation Error
*   `429`: Too Many Requests
*   `500`: Server Error
