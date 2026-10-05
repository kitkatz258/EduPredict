# EduPredict: Docker + Laravel 12 from scratch

This guide goes from "nothing installed" to "Laravel 12 running in Docker with a database",
then covers the day-to-day workflow with Git, your team, and Cursor.

---------------------------------------------------------------------

## Part 1: Understand what you're building (2 minutes)

Docker runs programs inside isolated boxes called **containers**, so everyone on the team gets the
exact same PHP version, extensions and database, no more "it works on my XAMPP".

| Piece | What it is | In this project |
|---|---|---|
| **Image** | A recipe/template for a container | `php:8.3-apache`, `mariadb:11.4` |
| **Container** | A running instance of an image | `edupredict_app`, `edupredict_db` |
| **Dockerfile** | Your custom recipe for the app image | `Dockerfile` (adds Composer, Node, PHP extensions) |
| **docker-compose.yml** | Describes all containers and how they connect | `docker-compose.yml` |
| **Volume** | Storage that survives container restarts | `db_data` (your database files) |
| **Bind mount** | A host folder shared into a container | `./src` -> `/var/www/html` |

The key idea: **your code lives on your computer (in `src/`) and is shared into the container.**
You edit files normally (in Cursor); PHP runs inside the container.
Commands like `php artisan` and `composer` must be run *inside* the container.

Final structure:

```
edupredict/
├── Dockerfile
├── docker-compose.yml
├── SETUP.md
├── src/          <- the Laravel 12 project lives here
└── ml/           <- (later) Python notebooks for training models; NOT a container
```

---------------------------------------------------------------------

## Part 2: Install Docker

### Windows 10 (22H2+) / Windows 11
1. **Enable virtualization.** Open Task Manager > Performance > CPU and check "Virtualization: Enabled".
   If it says Disabled, enable *Intel VT-x / AMD SVM* in your BIOS/UEFI (search your laptop model + "enable virtualization").
2. **Install WSL 2.** Open PowerShell *as Administrator* and run:
   ```powershell
   wsl --install
   ```
   Restart when asked.
3. **Install Docker Desktop** from https://www.docker.com/products/docker-desktop/ .
   Keep "Use WSL 2 instead of Hyper-V" ticked. Restart if asked.
   (Docker Desktop is free for students/personal/small-team educational use.)
4. **Open Docker Desktop** and wait until it says "Engine running" (bottom-left).
5. **Verify** in PowerShell:
   ```powershell
   docker --version
   docker compose version
   docker run hello-world
   ```
   `hello-world` should print "Hello from Docker!"

### Mac
Install Docker Desktop (Apple Silicon or Intel build), open it, run the same 3 verify commands.

### Linux
Install Docker Engine + the compose plugin from docs.docker.com, add yourself to the `docker` group, log out/in.

### Common problems
- **Docker Desktop must be OPEN** ("Engine running", bottom-left) before any `docker` command works. Error `failed to connect to the docker API ... npipe` = it isn't running.
- **`docker` is not recognized in the terminal**: close and reopen Cursor/PowerShell (the PATH is refreshed on launch).
- **`WSL1 is not supported ...` when running `wsl --status`**: harmless. Docker only needs WSL 2 (the line "Default Version: 2" is what matters).
- **"WSL 2 installation is incomplete" / Docker Desktop stuck on "starting"**: run `wsl --update`, then `wsl --shutdown`, then reopen Docker Desktop. If still stuck, restart the PC.
- **`read-only file system` or `exec format error` from a container**: Docker's stored images got damaged (often by a crash during a download). Quit Docker Desktop, then reopen it. If it persists: Troubleshoot (bug icon) > Clean up data > tick **WSL 2 only** > Delete. Then `docker compose build`, `docker compose pull`, `docker compose up -d`. Project files are not touched.
- **Port already in use**: close XAMPP (Apache/MySQL) or change the left-hand number in `ports:` in `docker-compose.yml`.
- **RAM hog**: create `C:\Users\<you>\.wslconfig` with
  ```
  [wsl2]
  memory=4GB
  ```
  then run `wsl --shutdown`.
- **Always run compose commands from the project root** (the folder with `docker-compose.yml`), not from `src`. `no configuration file provided` means you're in the wrong folder.
- **Use the service names** `app`, `db`, `phpmyadmin` in compose commands (not the container names like `edupredict_pma`).

---------------------------------------------------------------------

## Part 3: Create the project folder

1. Make a folder (e.g. `C:\Projects\edupredict`) and put `Dockerfile` and `docker-compose.yml` in it.
2. Create an **empty** `src` folder next to them.
3. Open the folder in a terminal (PowerShell is fine; in Cursor use Terminal > New Terminal).

> **Windows tip:** if pages feel slow later, Windows-to-Docker file sharing is the cause. The fix is to keep the project
> inside WSL (e.g. `\\wsl$\Ubuntu\home\you\edupredict`) and open that folder in Cursor. Don't worry about it until it's slow.

---------------------------------------------------------------------

## Part 4: Create the Laravel 12 project

**Important:** Laravel 13 was released in March 2026, so plain `composer create-project laravel/laravel`
now gives you Laravel **13**. To get Laravel 12 you must pin the version with `^12.0`.

```bash
# 1. Build the app image (first time takes a few minutes)
docker compose build

# 2. Create Laravel 12 into ./src using Composer *inside* the container
docker compose run --rm --no-deps app composer create-project "laravel/laravel:^12.0" .
```

What those flags mean:
- `run --rm` starts a temporary container and deletes it afterwards.
- `--no-deps` don't start the database yet (not needed for this step).
- `app` is the service name from `docker-compose.yml`.
- The trailing `.` means "install into the current folder" (which is `src/` inside the container).

When it finishes, `src/` is full of Laravel files. Laravel also created `src/.env` and generated an app key for you.

---------------------------------------------------------------------

## Part 5: Connect Laravel to the database

Open `src/.env` and replace the `DB_*` lines with:

```
DB_CONNECTION=mysql   # the container is MariaDB; Laravel's mysql driver works with it
DB_HOST=db
DB_PORT=3306
DB_DATABASE=edupredict
DB_USERNAME=edupredict
DB_PASSWORD=secret
```

Why `DB_HOST=db`? Containers on the same compose network reach each other by **service name**.
Inside the app container, `localhost` means "the app container itself", which has no database.

Also copy the same values into `src/.env.example` so teammates get them.

---------------------------------------------------------------------

## Part 6: Start everything

```bash
docker compose up -d
docker compose exec app php artisan migrate
```

- `up -d` starts all containers in the background. The app waits for the DB's healthcheck to pass first.
- `exec app ...` runs a command inside the running `app` container.

Open:
- **http://localhost:8000** : you should see the Laravel 12 welcome page.
- **http://localhost:8080** : phpMyAdmin (server `db`, user `edupredict`, password `secret`). You should see the `edupredict` database with the migration tables.

If you get a red error page (for example `tempnam(): file created in the system's temporary directory`) or a storage/permission error:
```bash
docker compose exec app chown -R www-data:www-data storage bootstrap/cache
docker compose exec app php artisan view:clear
```
(If it persists on Windows, `docker compose exec app chmod -R 777 storage bootstrap/cache` is fine for local development.)

### Frontend assets (Vite/Tailwind)
```bash
docker compose exec app npm install
docker compose exec app npm run dev -- --host
```
Leave that running while you work on UI; run `npm run build` for a production build.

---------------------------------------------------------------------

## Part 7: Command cheat sheet

```bash
docker compose up -d                          # start
docker compose down                           # stop (database is kept)
docker compose down -v                        # stop AND delete the database volume (fresh start)
docker compose ps                             # what's running
docker compose logs -f app                    # live logs (Ctrl+C to leave)
docker compose exec app bash                  # open a shell inside the Laravel container
docker compose exec app php artisan migrate   # any artisan command
docker compose exec app php artisan migrate:fresh --seed
docker compose exec app composer require vendor/package
docker compose exec app php artisan test
docker compose build --no-cache               # rebuild after editing the Dockerfile
```

Rule of thumb: **anything that is `php`, `composer`, `npm` or `artisan` goes through `docker compose exec app ...`.**

---------------------------------------------------------------------

## Part 8: Git and teamwork

1. In the project root: `git init`, and commit everything (including `src/`). Laravel's own `.gitignore` already excludes `.env` and `vendor/`.
2. Push to a private GitHub repo and add your teammates.
3. **A teammate joining:**
   ```bash
   git clone <repo-url> && cd edupredict
   docker compose up -d --build
   docker compose exec app composer install
   docker compose exec app cp .env.example .env
   docker compose exec app php artisan key:generate
   docker compose exec app php artisan migrate --seed
   ```
4. **Never commit `.env`** (it will hold your AI API key). Share keys privately.
5. **Share the database through migrations and seeders, not the volume.** Anything the app needs to exist
   (PSOC occupations, the intervention list, the institution student list, demo accounts) must be a seeder.
6. Use one branch per feature and merge via pull requests, so you can review what Cursor produced before it lands on `main`.

---------------------------------------------------------------------

## Part 9: Working with Cursor

- Open the **root folder** (`edupredict/`) in Cursor so it can see the Dockerfile, compose file and `src/`.
- Cursor edits files in `src/` directly, since the folder is shared into the container, so changes appear instantly.
- Cursor must run commands through Docker. Put this in your project rules / spec:
  > All PHP, Composer, npm and artisan commands must be run as `docker compose exec app <command>`.
  > The database host is `db`, not `localhost`. The Laravel project is in `src/`. Use Laravel 12 and PHP 8.3.
- Make Cursor work **module by module** and commit after each one. Don't let it build everything in a single pass.
  A single hour-long generation is nearly impossible to review; one module per commit is reviewable.
- After each module, check these before moving on:
  1. `php artisan migrate:fresh --seed` still works from scratch.
  2. `php artisan test` passes (tell Cursor to write feature tests, especially for role-based access).
  3. You can log in as each of the 5 roles and confirm each sees only its own scope.
  4. You can explain in your own words what the module's code does. Your panel will ask.

---------------------------------------------------------------------

## Part 10: Where the ML models fit (no Python container)

```
edupredict/ml/
├── notebooks/        <- training + evaluation (Colab or local venv)
├── data/             <- UCI dropout dataset, employability dataset
└── exported/         <- trained model files that Laravel loads
```

Workflow: train and evaluate in Python (scikit-learn) -> record accuracy/precision/recall/F1/ROC-AUC/confusion matrix
-> export the model -> Laravel loads and runs it for predictions. Python is a development tool here, not a running service.
Decide the export format (ONNX, or coefficients/JSON for simple models) before generating the Laravel code,
because it determines the inference code Cursor writes.
