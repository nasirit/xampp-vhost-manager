# XAMPP VHost & Windows Host Automator

A lightweight, single-file PHP utility designed to automate the tedious process of setting up local development domains on **XAMPP for Windows**. 

With a simple web interface, this tool automatically creates target directories, sets up dual VirtualHosts (the domain and an `admin.` subdomain), updates the Windows `hosts` file, restarts the Apache service safely, and provides an active list with easy delete options.

---

## Features

- **Auto Path Detection:** Dynamically detects your XAMPP installation path based on the script location.
- **Dual VirtualHost Generation:** Automatically generates configurations for both the main domain and its corresponding `admin.` subdomain.
- **Directory Creation:** Instantly creates the target folder inside `htdocs` with a default `index.php`.
- **Windows Hosts Management:** Safely appends `127.0.0.1` map entries to your Windows system hosts file (`C:/Windows/System32/drivers/etc/hosts`).
- **Active VHost Management (List & Delete):** View all custom-configured virtual hosts in a table with direct browser shortcuts and a **Delete** button that removes configurations from both config files and cleans up the Windows hosts file.
- **Asynchronous Apache Restart:** Restarts Apache gracefully without abruptly severing active HTTP connections using non-blocking background shell calls.
- **Security Guard:** Restricted to local access (`localhost` / `127.0.0.1` / `::1`) by default.

---

## Prerequisites

1. **XAMPP for Windows** installed (typically under `D:/xampp` or `C:/xampp`).
2. **Administrator Privileges:** Because the script modifies system files (`httpd-vhosts.conf` and Windows `hosts`) and restarts Windows services, the Apache/PHP server executing this script must run with Administrator permissions.

---

## Installation & Usage

1. Clone or place this project file as `index.php` inside a directory within your XAMPP `htdocs` folder (e.g., `D:/xampp/htdocs/vhost-manager/index.php`).
2. Ensure your XAMPP Apache service is running and accessible via `http://localhost/...`.
3. Open your browser and navigate to the script URL (e.g., `http://localhost/vhost-manager/`).
4. Enter your desired **Domain Name** (e.g., `nasiritx.com`) and your **Directory Name** (e.g., `nasiritx`).
5. Click **Create & Setup VHost**. The tool will handle directory setup, configuration writing, and Apache restarting automatically!

---

## Security Note

This tool includes a built-in IP restriction rule that only allows access from `localhost`. If accessed externally, it restricts access to prevent unauthorized system modifications. Keep this script strictly for local development environments.

---

## License

This project is open-source and available under the [MIT License](LICENSE).