# EduPredict — WSL2 / Ubuntu Team Setup Guide

**For:** EduPredict developers using Windows 11 (also applicable to supported Windows 10 versions)  
**Repository:** https://github.com/kitkatz258/EduPredict  
**Purpose:** Run Laravel and Docker from Ubuntu's native Linux filesystem for faster development.  
**Companion documents:** `README.md` and `SETUP.md` (this guide does not replace them).

> **Important:** Run commands in the terminal specified in each section. **PowerShell** means Windows PowerShell; **Ubuntu** means the Ubuntu Bash terminal, including Cursor's WSL terminal. Do not clone into `C:\projects`, `/mnt/c`, or a Windows Desktop/Documents folder. Use a directory under your Ubuntu home folder instead.

## 0. Before you start

- Windows 11 or a supported Windows 10 release; administrator access for installation.
- Hardware virtualization enabled (Task Manager → Performance → CPU → **Virtualization: Enabled**). If disabled, enable it in BIOS/UEFI with help from your device documentation.
- Enough free disk space and RAM for Docker, PHP dependencies, and the database.
- Internet access; GitHub access to the EduPredict repository.
- Install **Docker Desktop** (https://www.docker.com/products/docker-desktop/) and **Cursor** (https://cursor.com/) when instructed.
- You do **not** need to install PHP, Composer, Node.js, MariaDB, or XAMPP on Windows: the project Docker image supplies the required app tooling.

## 1. Install WSL2 and Ubuntu (PowerShell as Administrator)

Open **PowerShell as Administrator** and run:

```powershell
wsl --install -d Ubuntu
```

Restart Windows if prompted. Open Ubuntu from the Start menu, or run:

```powershell
wsl -d Ubuntu
```

On first launch, create your own **Ubuntu username and password**. The password may appear invisible as you type; this is normal. These credentials are local to your machine.

Back in **PowerShell**, verify:

```powershell
wsl --update
wsl -l -v
wsl --set-default Ubuntu
```

The `Ubuntu` entry should have **VERSION 2** and an asterisk (`*`) for the default distribution. If Ubuntu is VERSION 1, run `wsl --set-version Ubuntu 2`. Do not select `docker-desktop` as your development distro.

If `wsl --install` fails, follow Microsoft's WSL installation instructions: https://learn.microsoft.com/windows/wsl/install.

## 2. Prepare Ubuntu (Ubuntu terminal)

```bash
sudo apt update
sudo apt install -y git ca-certificates curl
mkdir -p ~/projects
cd ~/projects
```

Verify you're in native Linux storage:

```bash
pwd
findmnt -T "$HOME/projects"
```

Expected: a path under `/home/<your-ubuntu-username>/projects`, typically on **ext4**. If you see `/mnt/c` or `9p`, stop and correct the location before cloning.

## 3. Install and configure Docker Desktop (Windows UI)

1. Download and install Docker Desktop: https://www.docker.com/products/docker-desktop/.
2. Start Docker Desktop and wait until the Docker Engine is running.
3. Open **Settings → General** and enable the **WSL 2 based engine** (if the option is shown).
4. Open **Settings → Resources → WSL Integration**.
5. Enable integration for **Ubuntu** and apply/restart if prompted.
6. Leave the special `docker-desktop` distribution managed by Docker Desktop; don't develop inside it.

In **Ubuntu**, check:

```bash
docker --version
docker compose version
docker info --format '{{.ServerVersion}}'
```

These should succeed without `sudo`. If they fail, ensure Docker Desktop is running and Ubuntu integration is enabled; reopen Ubuntu and try again. Do not install a competing Docker Engine inside Ubuntu unless the team intentionally changes its Docker setup.

## 4. Clone EduPredict into Ubuntu (Ubuntu terminal)

```bash
cd ~/projects
git clone https://github.com/kitkatz258/EduPredict.git
cd EduPredict
pwd
git status
git remote -v
```

Expected project location:

```text
/home/<your-ubuntu-username>/projects/EduPredict
```

The project root contains `docker-compose.yml`, `Dockerfile`, `README.md`, `SETUP.md`, and `src/` (Laravel). If you already have a Windows clone, **do not copy its `vendor/`, `node_modules/`, caches, or `.env` blindly**. Check for uncommitted work before switching; the two clones do not synchronize automatically.

## 5. Open the Ubuntu repository in Cursor (Windows Cursor UI)

1. Install/open Cursor: https://cursor.com/.
2. Open the Command Palette: **Ctrl+Shift+P**.
3. Choose **WSL: Connect to WSL using Distro...** (wording may vary).
4. Select **Ubuntu**, **not `docker-desktop`**.
5. Choose **File → Open Folder** and open `/home/<your-ubuntu-username>/projects/EduPredict`.
6. Open Cursor's integrated terminal and run:

```bash
pwd
git rev-parse --show-toplevel
```

Both should refer to the Ubuntu EduPredict repository. The Cursor window should show **[WSL: Ubuntu]**. If Cursor reports `wsl+docker-desktop` or `execvpe(bash) failed`, reconnect explicitly to **Ubuntu**; `docker-desktop` is the wrong distro.

> Cursor's built-in Source Control works with the Ubuntu Git repository. GitHub Desktop on Windows may have limited support for WSL network paths; do not use its old `C:\projects\EduPredict` repository to commit new Ubuntu edits.

## 6. First-time Laravel and database setup (Ubuntu / Cursor WSL terminal)

Run these commands **from the EduPredict repository root**, not from `src/`:

```bash
cd ~/projects/EduPredict
# Verify Docker Compose points to Ubuntu's src directory:
docker compose config
# Build and start the containers:
docker compose up -d --build
# Install PHP and frontend dependencies inside the app container:
docker compose exec -T app composer install
docker compose exec -T app npm install
```

Create a **new local** Laravel environment file from the tracked example:

```bash
cp src/.env.example src/.env
```

Open `src/.env` in Cursor and confirm local settings, especially `DB_HOST=db`, `DB_PORT=3306`, the database name/user/password matching `docker-compose.yml`, and `APP_URL=http://localhost:8000`. Use the repo's `README.md` for current application-specific settings. Do not share or commit `.env`.

**Only for a brand-new local database with no existing encrypted data**, generate a new application key:

```bash
docker compose exec -T app php artisan key:generate
```

> **Never generate a new `APP_KEY` for an existing database containing encrypted records.** Restore the original key from its secure backup instead.

Because Apache runs as `www-data`, ensure the `.env` file is readable by its group without making it world-readable:

```bash
sudo chgrp 33 src/.env
chmod 640 src/.env
docker compose exec -T app sh -c 'su -s /bin/sh www-data -c "test -r /var/www/html/.env" && echo ENV_READABLE'
```

Expected: `ENV_READABLE`. Keep the file owned by your Ubuntu user; don't use `chmod 777` or `chmod 644` for `.env`.

For **a brand-new, disposable local development database only**, initialize and seed it:

```bash
docker compose exec -T app php artisan migrate:fresh --seed
```

**Warning:** `migrate:fresh --seed` **deletes existing tables and data**. For a database you want to preserve, use `docker compose exec -T app php artisan migrate` instead. Ask the team before resetting any shared or important data.

Build frontend assets:

```bash
docker compose exec -T app npm run build
```

If Laravel reports storage/cache write permission errors, inspect permissions first; for a standard local bind mount, the following can help:

```bash
docker compose exec -T app sh -c 'chown -R www-data:www-data storage bootstrap/cache && chmod -R ug+rwX storage bootstrap/cache'
```

Do not change permissions recursively across the entire repository.

## 7. Verify the fast Linux mount — mandatory

Run from Ubuntu:

```bash
cd ~/projects/EduPredict
findmnt -T "$PWD/src"
docker compose config | grep -A 4 'type: bind'
docker inspect edupredict_app --format '{{range .Mounts}}{{println .Source "->" .Destination}}{{end}}'
docker compose exec -T app df -T /var/www/html
```

**Correct mount:**

```text
/home/<your-ubuntu-username>/projects/EduPredict/src -> /var/www/html
```

The container's `/var/www/html` should be backed by native Linux storage (in our verified setup: `ext4`), **not `C:\` / `9p`**. This matters: an old Windows-backed mount made Laravel take around 8 seconds per request on the original developer's machine.

If Compose is correct but the running app container still points to `C:\projects\EduPredict\src`, recreate **only** the app container:

```bash
docker compose up -d --no-deps --force-recreate app
```

Then rerun `docker inspect` and `df -T`. Do not use `docker compose down -v` to fix a mount: that removes the MariaDB data volume.

## 8. Open and test the application

| Service | Local URL |
| --- | --- |
| EduPredict / Laravel | http://localhost:8000 |
| Login | http://localhost:8000/login |
| phpMyAdmin | http://localhost:8080 |
| Vite (only when dev server is running) | http://localhost:5173 |

Check containers and HTTP responses:

```bash
docker compose ps
curl -s -o /dev/null -w 'Health: HTTP %{http_code} / %{time_total}s\n' http://localhost:8000/up
curl -s -o /dev/null -w 'Login: HTTP %{http_code} / %{time_total}s\n' http://localhost:8000/login
```

Expected: HTTP **200** for both. On the original developer's machine after the mount and permission fixes, the login route returned about **0.10 seconds**; this is an observation, not a guarantee for every computer. Test actual browser login, dashboards, Livewire interactions, OCR uploads, and analytics separately.

For UI development, optionally start Vite in a separate Ubuntu terminal:

```bash
cd ~/projects/EduPredict
docker compose exec app npm run dev -- --host
```

Stop it with Ctrl+C. `npm run build` is sufficient when you do not need the live development server.

## 9. Daily workflow

In **Cursor [WSL: Ubuntu]** or Ubuntu Bash:

```bash
cd ~/projects/EduPredict
docker compose up -d              # Start services
docker compose ps                 # Check services
docker compose logs --tail=50 app # Inspect Laravel/Apache container output
# ... develop and test ...
docker compose down               # Stop services; preserve named database volume
```

After pulling dependency changes:

```bash
docker compose exec -T app composer install
docker compose exec -T app npm install
docker compose exec -T app npm run build
```

After pulling migrations (when preserving existing data):

```bash
docker compose exec -T app php artisan migrate
```

Run tests:

```bash
docker compose exec -T app php artisan test
```

Never run `docker compose down -v` or `migrate:fresh` on a database you want to keep.

## 10. Team Git workflow

Each teammate has **their own** local Ubuntu clone, local Docker containers, and local database. GitHub shares **code**, not local databases or `.env` files.

```bash
cd ~/projects/EduPredict
git switch main
git pull --ff-only
git switch -c feature/describe-your-task
# Edit and test files in Cursor
git status
git add <specific-files>
git commit -m "Describe your change"
git push -u origin feature/describe-your-task
```

Open a Pull Request on GitHub, request review, and merge only after checks pass. If the repository requires GitHub authentication, use an approved credential manager or SSH key; **never put tokens in the repository**. Coordinate database migrations and shared files before merging.

Do not commit `.env`, API keys, passwords, real student records, OCR uploads containing personal data, database dumps, or other secrets. Each teammate can use separate demo data and local development keys.

## 11. Troubleshooting quick reference

| Symptom | Check / action |
| --- | --- |
| Cursor connects to `wsl+docker-desktop` and Bash fails | Use **WSL: Connect to WSL using Distro → Ubuntu**; in PowerShell: `wsl --set-default Ubuntu`. |
| `docker` not available in Ubuntu | Start Docker Desktop; enable **Settings → Resources → WSL Integration → Ubuntu**; reopen Ubuntu. |
| Laravel takes many seconds while static CSS is fast | Verify `docker inspect` mount and `df -T` in container. It must use the Ubuntu `ext4` project, not `C:\` / `9p`. |
| Compose config points to Ubuntu, container still points to Windows | Run `docker compose up -d --no-deps --force-recreate app`; inspect mount again. |
| HTTP 500: `No application encryption key has been specified` | Confirm `src/.env` has `APP_KEY`; verify Apache (`www-data`) can read `.env`. **Do not regenerate an existing key.** |
| `.env` exists but is `-rw-------` owned by Ubuntu UID 1000 | `sudo chgrp 33 src/.env && chmod 640 src/.env`; verify `www-data` readability. |
| `no configuration file provided` | Run Docker Compose commands from repository root, not `src/`. |
| Port 8000, 8080, 3307, or 5173 already in use | Identify the other process/container before changing the corresponding host port in Compose. |
| Database connection fails | Check `docker compose ps`, `docker compose logs --tail=50 db`, and `DB_HOST=db`, `DB_PORT=3306` inside Laravel. |
| `permission denied` writing Laravel logs/cache | Check `storage/` and `bootstrap/cache/` permissions, not just `.env`. |
| Changes in Cursor do not show on localhost | Verify Cursor is **[WSL: Ubuntu]** and Docker mounts that same repository; refresh/rebuild frontend assets if necessary. |
| GitHub Desktop shows different changes | It may still be viewing `C:\projects\EduPredict`. Use Cursor WSL Source Control or Ubuntu Git. |

## 12. Setup completion checklist

- [ ] Ubuntu installed and running as WSL **version 2**
- [ ] Docker Desktop WSL integration enabled for **Ubuntu**
- [ ] Repo cloned to `/home/<username>/projects/EduPredict`
- [ ] Cursor shows **[WSL: Ubuntu]** and terminal `pwd` matches repository
- [ ] `docker compose ps` shows healthy/running services
- [ ] App container bind mount source is `/home/.../EduPredict/src`
- [ ] `df -T /var/www/html` inside app is native Linux storage, not Windows `9p`
- [ ] `.env` is private, readable by Apache, and has the **correct** `APP_KEY`
- [ ] Local database migrated/seeded appropriately
- [ ] Frontend assets built
- [ ] `/up` and `/login` return HTTP 200
- [ ] Browser login, dashboards, and Livewire actions work
- [ ] Git remote and team branch workflow verified

## References

- EduPredict repository and current project documentation: https://github.com/kitkatz258/EduPredict
- Microsoft — Install WSL: https://learn.microsoft.com/windows/wsl/install
- Microsoft — Basic WSL commands: https://learn.microsoft.com/windows/wsl/basic-commands
- Docker — Docker Desktop WSL 2 backend: https://docs.docker.com/desktop/features/wsl/
- Cursor: https://cursor.com/

**Reminder:** The fourth planned FastAPI/model-serving container is not required for this setup. Add it later when the team finalizes the trained ML model and its integration contract.
