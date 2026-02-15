# Repository
## configuration

| Key | Required | Description | Supported       |
| --- | -------- | ----------- |-----------------|
| `name` | yes | Name of the repository | ✅ UI + Settings |
| `url` | no | Homepage of the repository. Here you can explain the various apps.| ✅ UI + Settings |
| `maintainer` | no | Contact info of the maintainer.| ✅ UI + Settings |


# Addon
## Required configuration options

| Key | Type | Description | Supported |
| --- | ---- | ----------- | --------- |
| `name` | string | The name of the app. | ✅ UI + Generator |
| `version` | string | Version of the app. If you are using a docker image with the `image` option, this needs to match the tag of the image that will be used. | ✅ UI + Generator |
| `slug` | string | Slug of the app. This needs to be unique in the scope of the repository that the app is published in and URI friendly. | ✅ UI + Generator (Auto) |
| `description` | string | Description of the app. | ✅ UI + Generator |
| `arch` | list | A list of supported architectures: `armhf`, `armv7`, `aarch64`, `amd64`, `i386`. | ✅ Generator (Auto-detected) |

## Optional configuration options

| Key | Type | Default | Description | Supported |
| --- | ---- | -------- | ----------- | --------- |
| `machine` | list | | Default is support of all machine types. You can configure the app to only run on specific machines. You can use `!` before a machine type to negate it. | ❌ No |
| `url` | url | | Homepage of the app. Here you can explain the app and options. | ✅ UI + Generator |
| `startup` | string | `application` | `initialize` will start the app on setup of Home Assistant. `system` is for things like databases and not dependent on other things. `services` will start before Home Assistant, while `application` is started afterwards. Finally `once` is for applications that don't run as a daemon. | ⚠️ Fixed to `application` |
| `webui` | string | | A URL for the web interface of this app. Like `http://[HOST]:[PORT:2839]/dashboard`, the port needs the internal port, which will be replaced with the effective port. It is also possible to bind the protocol part to a configuration option with: `[PROTO:option_name]://[HOST]:[PORT:2839]/dashboard` and it's looked up if it is `true` and it's going to `https`. | ✅ UI + Generator |
| `boot` | string | `auto` | `auto` start at boot is controlled by the system and `manual` configures the app to only be started manually. If addon should never be started at boot automatically, use `manual_only` to prevent users from changing it. | ⚠️ Fixed to `auto` |
| `ports` | dict | | Network ports to expose from the container. Format is `"container-port/type": host-port`. If the host port is `null` then the mapping is disabled. | ✅ UI + Generator |
| `ports_description` | dict | | Network ports description mapping. Format is `"container-port/type": "description of this port"`. Alternatively use Port description translations. | ✅ UI + Generator |
| `host_network` | bool | `false` | If `true`, the app runs on the host network. | ✅ UI + Generator (Feature Flag) |
| `host_ipc` | bool | `false` | Allow the IPC namespace to be shared with others. | ✅ UI + Generator (Feature Flag) |
| `host_dbus` | bool | `false` | Map the host D-Bus service into the app. | ✅ UI + Generator (Feature Flag) |
| `host_pid` | bool | `false` | Allow the container to run on the host PID namespace. Works only for not protected apps. **Warning:** Does not work with S6 Overlay. If need this to be `true` and you use the normal app base image you disable S6 by overriding `/init`. Or use an alternate base image. | ✅ UI + Generator (Feature Flag) |
| `host_uts` | bool | `false` | Use the hosts UTS namespace. | ✅ UI + Generator (Feature Flag) |
| `devices` | list | | Device list to map into the app. Format is: `<path_on_host>`. E.g., `/dev/ttyAMA0` | ❌ No |
| `homeassistant` | string | | Pin a minimum required Home Assistant Core version for the app. Value is a version string like `2022.10.5`. | ❌ No |
| `hassio_role` | str | `default` |Role-based access to Supervisor API. Available: `default`, `homeassistant`, `backup`, `manager` or `admin` | ❌ No |
| `hassio_api` | bool | `false` | This app can access the Supervisor's REST API. Use `http://supervisor`. | ✅ UI + Generator (Feature Flag) |
| `homeassistant_api` | bool | `false` | This app can access the Home Assistant REST API proxy. Use `http://supervisor/core/api`. | ✅ UI + Generator (Feature Flag) |
| `docker_api` | bool | `false` | Allow read-only access to the Docker API for the app. Works only for not protected apps. | ✅ UI + Generator (Feature Flag) |
| `privileged` | list | | Privilege for access to hardware/system. Available access: `BPF`, `CHECKPOINT_RESTORE`, `DAC_READ_SEARCH`, `IPC_LOCK`, `NET_ADMIN`, `NET_RAW`, `PERFMON`, `SYS_ADMIN`, `SYS_MODULE`, `SYS_NICE`, `SYS_PTRACE`, `SYS_RAWIO`, `SYS_RESOURCE` or `SYS_TIME`. | ✅ UI + Generator (Feature Flag) |
| `full_access` | bool | `false` | Give full access to hardware like the privileged mode in Docker. Works only for not protected apps. Consider using other app options instead of this, like `devices`. If you enable this option, don't add `devices`, `uart`, `usb` or `gpio` as this is not needed. | ✅ UI + Generator (Feature Flag) |
| `apparmor` | bool/string | `true` | Enable or disable AppArmor support. If it is enabled, you can also use custom profiles with the name of the profile. | ✅ UI + Generator (Feature Flag) |
| `map` | list | | List of Home Assistant directory types to bind mount into your container. Possible values: `homeassistant_config`, `addon_config`, `ssl`, `addons`, `backup`, `share`, `media`, `all_addon_configs`, and `data`. Defaults to read-only, which you can change by adding the property `read_only: false`. By default, all paths map to `/<type-name>` inside the addon container, but an optional `path` property can also be supplied to configure the path (Example: `path: /custom/config/path`). If used, the path must not be empty, unique from any other path defined for the addon, and not the root path. Note that the `data` directory is always mapped and writable, but the `path` property can be set using the same conventions. | ✅ UI + Generator |
| `environment` | dict | | A dictionary of environment variables to run the app with. | ✅ UI + Generator |
| `audio` | bool | `false` | Mark this app to use the internal audio system. We map a working PulseAudio setup into the container. If your application does not support PulseAudio, you may need to install: Alpine Linux `alsa-plugins-pulse` or Debian/Ubuntu `libasound2-plugins`. | ✅ UI + Generator (Feature Flag) |
| `video` | bool | `false` | Mark this app to use the internal video system. All available devices will be mapped into the app. | ✅ UI + Generator (Feature Flag) |
| `gpio` | bool | `false` | If this is set to `true`, `/sys/class/gpio` will map into the app for access to the GPIO interface from the kernel. Some libraries also need  `/dev/mem` and `SYS_RAWIO` for read/write access to this device. On systems with AppArmor enabled, you need to disable AppArmor or provide your own profile for the app, which is better for security. | ✅ UI + Generator (Feature Flag) |
| `usb` | bool | `false` | If this is set to `true`, it would map the raw USB access `/dev/bus/usb` into the app with plug&play support. | ✅ UI + Generator (Feature Flag) |
| `uart` | bool | `false` | Default `false`. Auto mapping all UART/serial devices from the host into the app. | ✅ UI + Generator (Feature Flag) |
| `udev` | bool | `false` | Default `false`. Setting this to `true` gets the host udev database read-only mounted into the app. | ✅ UI + Generator (Feature Flag) |
| `devicetree` | bool | `false` | If this is set to `true`, `/device-tree` will map into the app. | ✅ UI + Generator (Feature Flag) |
| `kernel_modules` | bool | `false` | Map host kernel modules and config into the app (readonly) and give you `SYS_MODULE` permission. | ✅ UI + Generator (Feature Flag) |
| `stdin` | bool | `false` | If enabled, you can use the STDIN with Home Assistant API. | ✅ UI + Generator (Feature Flag) |
| `legacy` | bool | `false` | If the Docker image has no `hass.io` labels, you can enable the legacy mode to use the config data. | ✅ UI + Generator (Feature Flag) |
| `options` | dict | | Default options value of the app. | ✅ UI + Generator (for user env) |
| `schema` | dict | | Schema for options value of the app. It can be `false` to disable schema validation and options. | ✅ UI + Generator (for user env) |
| `image` | string | | For use with Docker Hub and other container registries. This should be set to the name of the image only (E.g, `ghcr.io/home-assistant/{arch}-addon-example`). If you use this option, set the active docker tag using the `version` option. | ✅ UI + Generator |
| `codenotary` | string | | For use with Codenotary CAS. This is the E-Mail address used to verify your image with Codenotary (E.g, `example@home-assistant.io`). This should match the E-Mail address used as the signer in the app's extended build options | ❌ No |
| `timeout` | integer | 10 | Default 10 (seconds). The timeout to wait until the Docker daemon is done or will be killed. | ✅ UI + Generator |
| `tmpfs` | bool | `false` | If this is set to `true`, the containers `/tmp` uses tmpfs, a memory file system. | ✅ UI + Generator |
| `discovery` | list | | A list of services that this app provides for Home Assistant. | ✅ UI + Generator (Feature Flag) |
| `services` | list | | A list of services that will be provided or consumed with this app. Format is `service`:`function` and functions are: `provide` (this app can provide this service), `want` (this app can use this service) or `need` (this app needs this service to work correctly). | ❌ No |
| `auth_api` | bool | `false` | Allow access to Home Assistant user backend. | ✅ UI + Generator (Feature Flag) |
| `ingress` | bool | `false` | Enable the ingress feature for the app. | ✅ UI + Generator |
| `ingress_port` | integer | `8099` | For apps that run on the host network, you can use `0` and read the port later via the API. | ✅ UI + Generator |
| `ingress_entry` | string | `/` | Modify the URL entry point. | ✅ UI + Generator |
| `ingress_stream` | bool | `false` | When enabled, requests to the app are streamed | ✅ UI + Generator |
| `panel_icon` | string | `mdi:puzzle` | [MDI icon](https://materialdesignicons.com/) for the menu panel integration. | ✅ UI + Generator |
| `panel_title` | string | | Defaults to the app name, but can be modified with this option. | ✅ UI + Generator |
| `panel_admin` | bool | `true` | Make the menu entry only available to users in the admin group. | ✅ UI + Generator |
| `backup` | string | `hot` | `hot` or `cold`. If `cold`, the supervisor turns the app off before taking a backup (the `pre/post` options are ignored when `cold` is used). | ✅ UI + Generator |
| `backup_pre` | string | | Command to execute in the context of the app before the backup is taken. | ❌ No |
| `backup_post` | string | | Command to execute in the context of the app after the backup was taken. | ❌ No |
| `backup_exclude` | list | | List of files/paths (with glob support) that are excluded from backups. | ❌ No |
| `advanced` | bool | `false` | Set this to `true` to require the user to have enabled "Advanced" mode for it to show. | ✅ UI + Generator (Feature Flag) |
| `stage` | string | `stable` | Flag app with follow attribute: `stable`, `experimental` or `deprecated`. Apps set to `experimental` or `deprecated` will not show up in the store unless the user enables advanced mode. | ❌ No |
| `init` | bool | `true` | Set this to `false` to disable the Docker default system init. Use this if the image has its own init system (Like [s6-overlay](https://github.com/just-containers/s6-overlay)). *Note: Starting in V3 of S6 setting this to `false` is required or the addon won't start, see [here](https://developers.home-assistant.io/blog/2022/05/12/s6-overlay-base-images) for more information.* | ❌ No (Default: `true`) |
| `watchdog` | string | | A URL for monitoring the app health. Like `http://[HOST]:[PORT:2839]/dashboard`, the port needs the internal port, which will be replaced with the effective port. It is also possible to bind the protocol part to a configuration option with: `[PROTO:option_name]://[HOST]:[PORT:2839]/dashboard` and it's looked up if it is `true` and it's going to `https`. For simple TCP port monitoring you can use `tcp://[HOST]:[PORT:80]`. It works for apps on the host or internal network. | ✅ UI + Generator |
| `realtime` | bool | `false` | Give app access to host schedule including `SYS_NICE` for change execution time/priority. | ✅ UI + Generator (Feature Flag) |
| `journald` | bool | `false` | If set to `true`, the host's system journal will be mapped read-only into the app. Most of the time the journal will be in `/var/log/journal` however on some hosts you will find it in `/run/log/journal`. Apps relying on this capability should check if the directory `/var/log/journal` is populated and fallback on `/run/log/journal` if not. | ✅ UI + Generator (Feature Flag) |
| `breaking_versions` | list | | List of breaking versions of the addon. A manual update will always be required if the update is to a breaking version or would cross a breaking version, even if users have auto-update enabled for the addon. | ❌ No |
| `ulimits` | dict | | Dictionary of resource limit (ulimit) settings for the app container. Each limit can be either a plain integer value or a dictionary with the keys `soft` and `hard`, each taking a plain integer for fine-grained control. Individual values must not be larger than the host's hard limit (inspectable by `ulimit -Ha`; e.g. 524288 in case of the `nofile` limit in the Home Assistant Operating System). | ❌ No |

