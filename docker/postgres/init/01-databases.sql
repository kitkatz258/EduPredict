-- Local PostgreSQL roles and databases. The password on edupredict matches the
-- local MariaDB compose password. It is not a Supabase or defense credential.
-- edupredict_defense has no login password. Set one outside git before any
-- future defense use: ALTER ROLE edupredict_defense LOGIN PASSWORD '...';

CREATE ROLE edupredict LOGIN PASSWORD 'secret' NOSUPERUSER NOCREATEDB NOCREATEROLE;
CREATE ROLE edupredict_defense NOLOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE;

CREATE DATABASE edupredict WITH OWNER edupredict ENCODING 'UTF8' TEMPLATE template0;
CREATE DATABASE edupredict_testing WITH OWNER edupredict ENCODING 'UTF8' TEMPLATE template0;
CREATE DATABASE edupredict_defense WITH OWNER edupredict_defense ENCODING 'UTF8' TEMPLATE template0;

REVOKE ALL ON DATABASE edupredict_defense FROM PUBLIC;
REVOKE CONNECT ON DATABASE edupredict_defense FROM edupredict;
