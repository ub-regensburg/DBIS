# DBIS Server
The DBIS server contains all functions for storing, querying, and displaying databases.

## Folder strucutre
```
DBIS-Server/
│── apache/        # Configuration files for the Apache server
│── app/           # Input for Webpack (JS, SCSS); contains frontend logic
│── config/        # Configuration for SlimPHP
│   └── routes.php # Definition of API routes for requests
│── public/        # Mapped to /var/www/public
│   └── index.php  # Entry point of the application; runs SlimPHP
│── src/           # Source code for SlimPHP
│   ├── Domain/    # Domain code (technology-independent)
│   ├── Action/    # "Controllers" for handling requests
│   └── Infrastr./ # Technology-dependent code; adapters for external libraries
```
