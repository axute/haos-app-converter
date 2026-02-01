# HAOS Add-on Converter

This tool is a web-based wizard that converts any Docker image into a Home Assistant Add-on.

## Features

- **Step-by-Step Wizard**: Easily create Home Assistant Add-ons from Docker images.
- **Icon Support**: Upload a custom PNG icon that will be stored as `icon.png` in the add-on directory.
- **Ingress Support**: 
  - Configure Home Assistant Ingress for seamless web interface access.
  - Custom **Panel Icon** (MDI) for the sidebar.
  - **Ingress Stream** support for WebSockets/VNC.
- **Web-UI Configuration**: Automatically generates the `webui` URL (e.g., `http://[HOST]:[PORT:xxxx]/`) when Ingress is disabled.
- **Port-Mappings**: Define mappings between container ports and host ports.
- **Backup Integration**: Mark your add-ons as backup-compatible (supports `hot` backup mode).
- **Environment Variables**: Define fixed or user-editable environment variables.
- **Self-Conversion**: The converter can export itself as a Home Assistant Add-on with a single click.
- **Global Settings**: Configure your repository name and maintainer globally in a separate settings view.
- **Add-on Management**: List, edit, and delete your created add-ons.

## Prerequisites

- PHP 8.0 or higher (or Docker)
- Composer (if not running via Docker)

## Installation & Usage

### Option 1: Using Docker (Recommended)
1. Start the container:
   ```bash
   docker-compose up -d --build
   ```
2. Open the wizard in your browser: [http://localhost:8080](http://localhost:8080)
   *(Note: The default port in `docker-compose.yaml` is 8985, so use [http://localhost:8985](http://localhost:8985) if not changed)*

### Option 2: Local with PHP
1. Install dependencies:
   ```bash
   composer install
   ```
2. Start PHP web server:
   ```bash
   php -S localhost:8000 -t public
   ```
3. Open the wizard in your browser: [http://localhost:8000](http://localhost:8000)

## Project Structure

Generated add-ons are created in the `/data/{addon-slug}` directory, as described in the [Home Assistant Documentation](https://developers.home-assistant.io/docs/apps/tutorial).

Each add-on directory contains:
- `config.yaml`: Home Assistant configuration
- `Dockerfile`: Based on the selected Docker image
- `icon.png`: (Optional) The add-on icon

A global `repository.yaml` is maintained in the root of the data directory.

## Environment Variables

- `CONVERTER_DATA_DIR`: (Optional) Path to the data directory. Defaults to `./data`. When running as an HA Add-on, set this to `/data`.
