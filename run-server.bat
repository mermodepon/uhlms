@echo off
setlocal

set "PROJECT_DIR=%~dp0"
set "PROJECT_DIR=%PROJECT_DIR:~0,-1%"
set "RUNNER=%PROJECT_DIR%\scripts\run_cloudflare.ps1"
set "NO_PAUSE="

if /I "%~1"=="--no-pause" (
    set "NO_PAUSE=1"
    shift
)

if not exist "%RUNNER%" (
    echo Cloudflare runner was not found:
    echo %RUNNER%
    echo.
    echo Make sure scripts\run_cloudflare.ps1 exists in this project.
    set "EXIT_CODE=1"
    goto done
)

if defined NO_PAUSE (
    powershell -NoProfile -ExecutionPolicy Bypass -File "%RUNNER%" -ProjectPath "%PROJECT_DIR%"
) else (
    powershell -NoProfile -ExecutionPolicy Bypass -File "%RUNNER%" -ProjectPath "%PROJECT_DIR%" %*
)
set "EXIT_CODE=%ERRORLEVEL%"

:done
echo.
if "%EXIT_CODE%"=="0" (
    echo Done.
) else (
    echo Failed with exit code %EXIT_CODE%.
)

if not defined NO_PAUSE pause
exit /b %EXIT_CODE%
