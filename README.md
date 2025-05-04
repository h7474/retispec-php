# PHP Microservices Conversion (from Flask)

This project is a conversion of the original Python Flask backend into a PHP microservices architecture. It includes:

*   **Patient Service:** A PHP service to manage patient data (CRUD operations).
*   **Acquisition Service:** A PHP service to manage acquisition data and handle image uploads/downloads.
*   **Nginx Proxy:** An Nginx server acting as a reverse proxy to route requests to the correct PHP service and serve the frontend.
*   **PostgreSQL Database:** A PostgreSQL database to store patient and acquisition data.
*   **Frontend:** A simple HTML/JavaScript frontend for testing the API endpoints.

## Directory Structure

```
php-microservices/
├── acquisition-service/
│   ├── Dockerfile
│   └── src/
│       ├── db.php
│       └── index.php
├── patient-service/
│   ├── Dockerfile
│   └── src/
│       ├── db.php
│       └── index.php
├── frontend/
│   └── index.html
├── nginx/
│   ├── default.conf
│   └── Dockerfile
├── uploads/          # (Created automatically by Docker volume)
├── docker-compose.yml
├── init.sql          # Database initialization script
└── README.md         # This file
```

## Prerequisites

*   Docker
*   Docker Compose (usually included with Docker Desktop or installed as a plugin)

## Running the Application

1.  **Navigate to the Project Directory:**
    Open your terminal and change the directory to `/home/ubuntu/php-microservices` (or wherever you have saved the project files).

    ```bash
    cd /path/to/php-microservices
    ```

2.  **Build and Start Services:**
    Run the following command to build the Docker images (if they don't exist or need updating) and start all the services in detached mode.

    ```bash
    docker compose up --build -d
    ```

    This will start:
    *   The PostgreSQL database container (`db`).
    *   The PHP patient service container (`patient-service`).
    *   The PHP acquisition service container (`acquisition-service`).
    *   The Nginx proxy container (`nginx-proxy`).

3.  **Access the Frontend:**
    Open your web browser and navigate to:
    [http://localhost:8080](http://localhost:8080)

    You should see the test frontend application, which you can use to interact with the Patient and Acquisition APIs.

## Stopping the Application

To stop all the running containers, navigate to the project directory in your terminal and run:

```bash
docker compose down
```

This will stop and remove the containers. The database data will persist in the `pgdata` volume, and uploaded images will persist in the `uploads` volume.

## Notes

*   **Database:** The `init.sql` script automatically creates the necessary `patient` and `acquisition` tables when the database container starts for the first time.
*   **Uploads:** Acquisition images are stored in the `uploads` named volume, which is shared between the `acquisition-service` and `nginx` containers.
*   **Ports:**
    *   Nginx (Frontend/Proxy) is accessible on host port `8080`.
    *   PostgreSQL is accessible on host port `5432` (useful for debugging with a DB client if needed).
    *   The PHP services (9001, 9002) are not directly exposed to the host; Nginx communicates with them internally within the Docker network.
