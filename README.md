# PHP-HRMS with Docker

This project is configured to run using Docker, which provides both Apache and MySQL servers without requiring local installation.

## Prerequisites

- [Docker](https://www.docker.com/products/docker-desktop/) must be installed on your system
- [Docker Compose](https://docs.docker.com/compose/install/) (usually included with Docker Desktop)

## Getting Started

1. Make sure Docker is running on your system
2. Open a terminal/command prompt in the project root directory
3. Run the following command to start the containers:

```bash
docker-compose up -d
```

4. Wait for the containers to build and start (this may take a few minutes on first run)
5. Access the application at: http://localhost

## Database Connection

The database is automatically configured with the following credentials:
- Host: db (internal Docker network)
- Database: hrms
- Username: hrms_user
- Password: hrms_password

These settings should work automatically with the provided Docker setup.

## Managing Docker Containers

- To stop the containers: `docker-compose down`
- To restart the containers: `docker-compose restart`
- To view container logs: `docker-compose logs`
- To rebuild containers after Dockerfile changes: `docker-compose up -d --build`

## Accessing MySQL Directly

You can connect to MySQL from your host machine using:
- Host: localhost
- Port: 3306
- Username: hrms_user
- Password: hrms_password

## Troubleshooting

- If you encounter database connection issues, ensure the containers are running with `docker-compose ps`
- For web server issues, check logs with `docker-compose logs web`
- For database issues, check logs with `docker-compose logs db`