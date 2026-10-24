# M35 — Free deployment on Render + Supabase

This deploys the thesis demo with **synthetic data only**: Laravel on a Render free web service and PostgreSQL on a Supabase free project. Do not load real student records or grade reports on this deployment.

| Piece | Where | Cost |
|---|---|---|
| Laravel app (`docker/render/Dockerfile`) | Render web service, `render.yaml` | Free |
| PostgreSQL 17 | Supabase project (session pooler) | Free |
| FastAPI research service (`ml/`) | Not deployed. Optional, see the end | — |

## Free-tier limits to know before the defense

- **Render sleeps** after about 15 minutes without traffic. The next visit takes 30–60 seconds while it wakes. Open the site a few minutes before presenting.
- **Supabase pauses** a free project after about 7 days without activity. Open the Supabase dashboard and press **Restore** if it is paused, then wait until it is healthy before opening the app.
- **The Render disk is temporary.** Files are erased on every restart and redeploy. Grade uploads are only held until they are confirmed, so the demo still works. Data in the database is not affected.
- **Email is not sent** (`MAIL_MAILER=log`). Password-reset emails go to the Render log only. An administrator resets passwords instead.
- Limits and plans change. Check render.com/pricing and supabase.com/pricing before relying on them.

## 1. Create the Supabase database

1. Sign in at https://supabase.com and create a **New project**. Region: **Southeast Asia (Singapore)**. Save the database password in a password manager.
2. When it is ready, press **Connect** (top bar) and choose **Session pooler**. Do not use the "Direct connection" host: it is IPv6-only on the free plan and Render cannot reach it.
3. Note these values from the session pooler string `postgresql://postgres.<ref>:[PASSWORD]@aws-0-<region>.pooler.supabase.com:5432/postgres`:
   - `DB_HOST` = `aws-0-<region>.pooler.supabase.com`
   - `DB_USERNAME` = `postgres.<ref>`
   - `DB_PASSWORD` = the database password

Use a new, empty project. The first boot runs every migration and loads the synthetic cohort.

## 2. Generate the application key

From the repo root, with the local stack running:

```powershell
docker compose exec app php artisan key:generate --show
```

Copy the whole output, including `base64:`. Keep it private. **Never change it after the first deploy.** The encrypted socioeconomic fields can only be read with the same key.

## 3. Push the branch to GitHub

Render deploys from GitHub. Push `main` after this branch is reviewed and merged, or deploy the branch directly while testing. A private repository works; Render asks for access to it.

## 4. Create the Render service from the Blueprint

1. Sign in at https://render.com with GitHub.
2. **New → Blueprint**, pick the EduPredict repository and the branch. Render reads `render.yaml`.
3. Fill in the `sync: false` values:

| Key | Value |
|---|---|
| `APP_KEY` | the `base64:...` key from step 2 |
| `DB_HOST` | Supabase pooler host |
| `DB_USERNAME` | `postgres.<ref>` |
| `DB_PASSWORD` | Supabase database password |
| `DEMO_SEED_PASSWORD` | a strong password for every seeded demo account (panel and students use it to sign in) |
| `AI_API_KEY`, `AI_MODEL` | leave empty, or your OpenRouter key and a `:free` model |

4. Press **Apply**. The first build takes about 10 minutes. The first boot runs migrations and the seed, then the health check on `/up` turns green.
5. Open `https://edupredict-<something>.onrender.com`. `APP_URL` is taken from Render's `RENDER_EXTERNAL_URL` automatically.

## 5. Smoke test (record the results)

Sign in with the demo identifiers from the README and the `DEMO_SEED_PASSWORD`:

- Administrator `admin@edupredict.test`: users, eligible students, CSV import, catalogs, Activity Log.
- Dean `dean@edupredict.test`: aggregated dashboard only, no student rows; `/students/{id}` is 403.
- Department Head `depthead@edupredict.test`: `/department/students`, filters, status modal.
- Student `2024-00001`: six-step Assessment, grades paste or upload, Review & Run, Dashboard, History, privacy JSON/PDF download.
- Registration with the eligible-but-unregistered `2024-88888`.
- Check that the page is HTTPS, logout works, and a phone-width screen is usable.

Render **Logs** show Laravel errors (`LOG_CHANNEL=stderr`). `APP_DEBUG` stays `false`.

## Updating and redeploying

Every push to the deployed branch rebuilds the image. On each boot the container runs `migrate --force`, which only applies new migrations. `edupredict:seed-demo-if-empty` runs only while the `users` table is empty, so passwords changed after the first deploy are kept.

To start over with fresh demo data, create a new Supabase project (or drop and recreate its `public` schema) and update `DB_HOST` / `DB_USERNAME` / `DB_PASSWORD`. Do not run `migrate:fresh` against a database people are using.

## Rollback

- Bad code: Render → service → **Events**, then **Rollback** to the previous deploy.
- Bad database change: Supabase free projects have no point-in-time restore. Take a dump before risky migrations: `pg_dump "<session pooler URL>" > backup.sql`.
- If hosting fails on defense day, present from the verified local Docker stack (`docker compose up -d`).

## Optional: FastAPI research service

The student workflow does not use it (`PREDICTOR_DRIVER=heuristic`). Free Render services cannot talk over a private network, so `ml` would need its own public free web service (`ml/Dockerfile`, `PORT` already honoured), a long random `ML_API_TOKEN` set on both services, and `ML_API_BASE_URL=https://<ml-service>.onrender.com` on the app. Both would sleep independently. Keep the research demo on the local stack unless the panel asks to see it hosted.
