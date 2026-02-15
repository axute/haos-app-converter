### Frontend Features

This documentation provides an overview of all frontend functions of the HAOS Add-on Converter, divided into views and sections.

#### 1. App List (Main View)
The main view is used to manage existing apps and start global actions.

- **App Cards**: Displays all generated apps with name, description, slug, and version.
- **App Actions**:
    - **Edit**: Opens the configuration form to edit an existing app.
    - **Delete**: Permanently deletes an app from the file system after confirmation.
    - **Download**: Packs the app as a zip file for manual download.
    - **Export/Convert**: Re-converts the Docker image into the app format (useful for image updates).
    - **Check Update**: Checks via API if a newer version of the Docker image is available.
    - **Info/Metadata**: Expands additional metadata of the app (e.g., path on the host).
- **Global Actions**:
    - **Upload App**: Allows uploading an app as a zip file (including validation of `config.yaml`).
    - **Create New App**: Opens an empty form to create a new app.
    - **Export Converter**: Converts the converter itself (self-update/export) with optional tag selection.
    - **Settings**: Opens the repository settings.
- **Logs**: An integrated log viewer shows the current server logs directly in the web interface.

#### 2. Converter Form (Create & Edit)
The form is divided into logical sections to simplify the configuration of complex add-ons.

##### Section 1: Basic Information
- **Name & Description**: Fields for the display name and a short description.
- **Long Description**: A Markdown editor (EasyMDE) for detailed documentation (`README.md`).
- **Icon Upload**: Selection of a PNG icon with automatic Base64 preview.
- **Docker Image & Tag**: 
    - Entry of the image (e.g., `nginx`).
    - **Tag Fetching**: Automatic loading of available tags via the API.
    - **PM Detection**: Automatic detection of the package manager in the image (apk, apt, etc.).
- **Version Fixation**: Optional coupling of the app version to the Docker image tag.

##### Section 2: Ingress & Web UI
- **Home Assistant Ingress**: Activation of HA's own proxy.
    - Configuration of port, path, and stream support.
    - **Panel Options**: Icon and title for the entry in the HA sidebar.
    - **Admin-Only**: Access restricted to HA administrators.
- **Web UI Port**: Definition of an alternative port if Ingress is not used.

##### Section 3: Environment & Configuration
- **Environment Variables**:
    - **Autocomplete**: Suggests variables already defined in the Docker image.
    - **Editable**: Marking of variables that the user can later adjust in the HA interface.
- **Volume Mappings**: Configuration of mounts (e.g., `data`, `config`, `ssl`) with mode (Read/Write or Read-Only).
- **tmpfs**: Activation of a RAM-based file system for `/tmp`.

##### Section 4: Advanced & Quirks Mode
- **Quirks Mode**: Activates manual overrides for the entrypoint.
- **Startup Script**: Integrated editor for a custom `run.sh` that starts before the app.
- **Bashio Version**: Selection of the Bashio library to be used.
- **User Environment**: Allows end users to add their own environment variables in HA.

##### Section 5: Health & Watchdog
- **Timeout**: Time span for container start/stop.
- **Watchdog**: Configuration of TCP or HTTP health checks for automatic monitoring of the app.

##### Section 6: Capabilities (Permissions)
- **HA Interfaces**: Access to HA API, Hassio API, or Docker API.
- **Hardware Access**: Sharing of audio, video, GPIO, USB, UART, Udev, etc.
- **Network**: Host networking, IPC, DBUS, etc.
- **Security**: Privileged mode, AppArmor profiles, kernel modules.

##### Versioning & Saving
- **Version Update**: Buttons for major, minor, or fix (patch) version increments.
- **Path Display**: After successful saving, the absolute path to the generated app is displayed.

#### 3. Repository Settings
- **Management**: Editing of name, maintainer information, and repository URL.
- **Synchronization**: Saves the global metadata in `repository.yaml`.

#### 4. UI/UX Features
- **Responsive Design**: Optimized for desktop and mobile views.
- **Interactive Elements**: 
    - Accordion structure for clear forms.
    - Confirmation dialogs for critical actions (delete, cancel).
    - Progress indicators for uploads and API queries.
    - Real-time warnings for invalid environment variables.
