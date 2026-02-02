# HAOS Add-on Converter

This tool is a web-based converter that transforms any Docker image into a Home Assistant add-on.

## Features

- **One-Step Form**: The former multi-step wizard has been reduced to a clear one-step form for faster editing.
- **Version Management**: Dedicated buttons for **Major**, **Minor**, and **Fix updates** are available when editing add-ons, handling versioning automatically.
- **Storage Mappings (Map)**: Support for Home Assistant storage mappings (`config`, `ssl`, `share`, `media`, `addons`, `backup`) with configurable access modes (`RW`/`RO`).
- **Icon Support**: Upload a custom PNG icon or use the default icon.
- **Ingress Support**: 
  - Configuration of Home Assistant Ingress for seamless access to the web interface.
  - Customizable **Panel Icon** (MDI) for the sidebar.
  - **Ingress Stream** support for WebSockets/VNC.
- **Web UI Configuration**: Automatic generation of the `webui` URL (e.g., `http://[HOST]:[PORT:xxxx]/`) if Ingress is disabled.
- **Port Mappings**: Definition of mappings between container ports and host ports.
- **Backup Integration**: Mark add-ons as backup-compatible (supports `hot` backup mode).
- **Environment Variables**: Definition of fixed environment variables.
  - **Note**: Environment variables are fixed within the add-on configuration and cannot be changed via the Home Assistant GUI after installation. This ensures maximum compatibility with existing Docker images without requiring internal modifications.
- **Clean Dockerfiles**: The generated `Dockerfile` is kept minimal. It uses the specified base image and does not override the default `CMD` or `ENTRYPOINT` (unless during self-conversion), preserving the original image's behavior.
- **Simplified Config**: The `config.yaml` is kept clean by omitting unused optional fields like `options` and `schema`.
- **Self-Conversion**: The converter can export itself as a Home Assistant add-on with one click (including a special icon and `mdi:toy-brick` panel icon).
- **Global Settings**: Configuration of repository name and maintainer in a separate view.
- **Add-on Management**: List, edit, and delete created add-ons.

## Prerequisites

- PHP 8.3 or higher (or Docker)
- Composer (if not run via Docker)

## Installation & Usage

### Option 1: With Docker (Recommended)
You can use the pre-built image from GHCR:
```bash
docker run -d -p 8985:80 -v $(pwd)/data:/var/www/html/data ghcr.io/axute/haos-addon-converter:latest
```
Or use docker-compose:
1. Start the container:
   ```bash
   docker-compose up -d --build
   ```
2. Open the converter in your browser: [http://localhost:8985](http://localhost:8985)

### Option 2: Local with PHP
1. Install dependencies:
   ```bash
   composer install
   ```
2. Start the PHP web server:
   ```bash
   php -S localhost:8000 -t public
   ```
3. Open the converter in your browser: [http://localhost:8000](http://localhost:8000)

## Project Structure

Generated add-ons are created in the `/data/{addon-slug}` directory, as described in the [Home Assistant documentation](https://developers.home-assistant.io/docs/apps/tutorial).

Each add-on directory contains:
- `config.yaml`: Home Assistant configuration
- `Dockerfile`: Based on the selected Docker image
- `icon.png`: The add-on icon (automatically created during self-conversion or manual upload)

A global `repository.yaml` is maintained in the main data directory.

## Environment Variables

- `CONVERTER_DATA_DIR`: (Optional) Path to the data directory. Default is `./data`. When the converter runs as an HA add-on, this is automatically set to `/addons`.
