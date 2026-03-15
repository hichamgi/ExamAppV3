@echo off

where php >nul 2>nul
if errorlevel 1 (
  echo PHP introuvable
  exit /b 1
)

where composer >nul 2>nul
if errorlevel 1 (
  echo Composer introuvable
  exit /b 1
)

composer install

if not exist .env if exist .env.example copy .env.example .env

if not exist storage\cache mkdir storage\cache
if not exist storage\logs mkdir storage\logs
if not exist storage\pdf mkdir storage\pdf

echo Installation terminee.
echo Etapes suivantes :
echo 1) Configurer .env
echo 2) Importer database\schema.sql
echo 3) Lancer le worker PDF : php worker\pdf_worker.php