# HAOS Add-on Converter

This tool is a web-based converter that transforms any Docker image into a Home Assistant add-on.

## Features

### 🚀 Core Converter Features
- **Smart Entrypoint Preservation**: Uses `crane` to automatically detect and preserve the original `ENTRYPOINT` and `CMD` of any Docker image.
- **Environment Variables**:
  - **Static Variables**: Fixed within the add-on configuration.
  - **Editable Variables**: Can be changed via the Home Assistant GUI after installation using a universal wrapper script.
  - **Risk Note**: Uses a wrapper script that replaces the entrypoint; includes warning for complex images.
- **Universal Shell Support**: POSIX-compliant `/bin/sh` wrapper script, ensuring compatibility with minimalist images (e.g., Alpine) without `jq` or `bash` dependencies.
- **Clean Dockerfiles**: Minimal generated `Dockerfile`. Standard add-ons use `FROM`, while advanced ones integrate the wrapper logic automatically.
- **Simplified Config**: Clean `config.yaml` that only includes `options` and `schema` when necessary.

### 🔌 Home Assistant Integration
- **Ingress Support**: 
  - Seamless access to the web interface via Home Assistant Ingress.
  - Customizable **Panel Icon** (MDI) for the sidebar.
  - **Ingress Stream** support for WebSockets/VNC.
- **Web UI Configuration**: Automatic `webui` URL generation (e.g., `http://[HOST]:[PORT:xxxx]/`) if Ingress is disabled.
- **Storage Mappings (Map)**: Full support for HA storage folders (`config`, `ssl`, `share`, etc.) with `RW`/`RO` modes.
- **Port Mappings**: Easy definition of container-to-host port mappings.
- **Backup Integration**: Automatic `hot` backup mode support.

### 🎨 User Experience & UI
- **One-Step Form**: Streamlined editing process with all information on a single page.
- **Add-on Documentation (Markdown)**: 
  - Integrated **EasyMDE** editor with syntax highlighting and live preview.
  - Automatic `README.md` generation for the HA Add-on Store.
- **Version Management**: Dedicated buttons for **Major**, **Minor**, and **Fix updates** with automatic version incrementing.
- **Icon Support**: Custom PNG upload support or use of default icons.
- **Self-Conversion**: Export the converter itself as an HA add-on with one click (includes version selection from GHCR).
- **Management Tools**: 
  - List, edit, and delete created add-ons.
  - Global repository settings (Name, Maintainer).

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
- `README.md`: Detailed add-on description (Markdown)
- `icon.png`: The add-on icon (automatically created during self-conversion or manual upload)
- `run.sh`: (Optional) Wrapper script for environment variable support
- `original_entrypoint` / `original_cmd`: (Optional) Stored metadata for entrypoint preservation

A global `repository.yaml` is maintained in the main data directory.

## Environment Variables

- `CONVERTER_DATA_DIR`: (Optional) Path to the data directory. Default is `./data`. When the converter runs as an HA add-on, this is automatically set to `/addons`.
