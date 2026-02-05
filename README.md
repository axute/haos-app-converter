# HAOS Add-on Converter

![Logo](icon.png)

This tool is a web-based converter that transforms any Docker image into a Home Assistant add-on.

## Features

### 🚀 Core Converter Features
- **Smart Entrypoint Preservation**: Uses `crane` to automatically detect and preserve the original `ENTRYPOINT` and `CMD` of any Docker image.
- **Package Manager Detection**: 
  - **Two-Stage Analysis**: Automatically detects the package manager (`apk`, `apt`, `yum`, etc.) by first checking the image history and then falling back to a deep filesystem scan using `crane export`.
  - **Smart Caching**: Results are cached globally to ensure lightning-fast responses for previously analyzed images.
- **Environment Variables**:
  - **Static Variables**: Fixed within the add-on configuration.
  - **Editable Variables**: Can be changed via the Home Assistant GUI after installation using a universal wrapper script.

### 🛠️ Quirks Mode (Advanced Features)

An optional mode that activates the `run.sh` wrapper to enable advanced features:
- **Editable Variables**: Enables the "Editable in HA GUI" option for environment variables.
- **Auto-Tool Installation**: Automatically attempts to install `bash`, `jq`, and `curl` if a package manager is detected.
- **Bashio Integration**: Automatically installs [bashio](https://github.com/hassio-addons/bashio) if `bash`, `jq`, and `curl` are available, providing powerful helper functions for your scripts.
- **Custom Startup Script**: Inject custom shell commands that run before the main application starts (via a syntax-highlighted editor).
- **⚠️ Risk Note**: Quirks mode replaces the original entrypoint with a wrapper script. While it attempts to preserve the original behavior, complex images might require manual adjustments.

### 🧩 Other Features

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
- **Backup Integration**: Full support for `disabled`, `hot` (online), and `cold` (offline) backup modes with detailed descriptions.

### 🎨 User Experience & UI
- **Accordion-Based Form**: Streamlined editing process organized into four clear sections:
  1. **Basic Information**: Name, version, image, and long description (Markdown).
  2. **Ingress & Web UI**: Access settings and backup modes.
  3. **Ports & Storage**: Port mappings and HA folder access.
  4. **Environment Variables**: Static/editable variables, Quirks mode, and Startup scripts.
- **Intelligent Docker Image Selection**: 
  - Separate inputs for Image Name and Tag.
  - **Manual Tag Fetcher**: Dedicated button (🔍) to fetch available tags directly from the registry using `crane`.
  - **Automatic PM Detection**: Real-time identification of the package manager with a manual refresh option (🔄).
  - **Smart Sorting**: Tags are sorted by version, with `latest` at the top and technical tags (signatures/hashes) at the bottom.
- **Add-on Documentation (Markdown)**: 
  - Integrated **EasyMDE** editor with syntax highlighting and live preview.
  - Automatic `README.md` generation for the HA Add-on Store.
- **Version Management**: Dedicated buttons for **Major**, **Minor**, and **Fix updates** with automatic version incrementing.
- **Icon Support**: Custom PNG upload support with preview or use of default icons.
- **Self-Conversion**: Export the converter itself as an HA add-on with one click (includes version selection from GHCR).
- **Management Tools**: 
  - List view showing add-on name, description, version, base image, and **detected package manager badge**.
  - Edit and delete created add-ons.
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

### Option 2: Home Assistant Add-on
You can also use this converter as a Home Assistant add-on by adding the following repository to your Home Assistant instance:
[https://github.com/axute/hassio-addons-converted](https://github.com/axute/hassio-addons-converted)

### Option 3: Local with PHP
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
- `metadata.json`: Internal metadata (e.g., detected package manager) - keeps `config.yaml` clean for Home Assistant
- `Dockerfile`: Based on the selected Docker image
- `README.md`: Detailed add-on description (Markdown)
- `icon.png`: The add-on icon (automatically created during self-conversion or manual upload)
- `start.sh`: (Optional) User-defined startup script executed before the main app
- `run.sh`: (Optional) Wrapper script for environment variable and startup script support
- `original_entrypoint` / `original_cmd`: (Optional) Stored metadata for entrypoint preservation

A global `repository.yaml` is maintained in the main data directory.

## Environment Variables

- `CONVERTER_DATA_DIR`: (Optional) Path to the data directory. Default is `./data`. When the converter runs as an HA add-on, this is automatically set to `/addons`.
