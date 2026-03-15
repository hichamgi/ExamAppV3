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