@echo off
setlocal

set "PROJECT_DIR=%~dp0"
set "PROJECT_DIR=%PROJECT_DIR:~0,-1%"
set "STOPPER=%PROJECT_DIR%\scripts\stop_cloudflare.ps1"
set "NO_PAUSE="

if /I "%~1"=="--no-pause" (
    set "NO_PAUSE=1"
    shift
)

if not exist "%STOPPER%" (
    echo Cloudflare stopper was not found:
    echo %STOPPER%
    echo.
    echo Make sure scripts\stop_cloudflare.ps1 exists in this project.
    set "EXIT_CODE=1"
    goto done
)

if defined NO_PAUSE (
    powershell -NoProfile -ExecutionPolicy Bypass -File "%STOPPER%" -ProjectPath "%PROJECT_DIR%" %1 %2 %3 %4 %5 %6 %7 %8 %9
) else (
    powershell -NoProfile -ExecutionPolicy Bypass -File "%STOPPER%" -ProjectPath "%PROJECT_DIR%" %*
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
