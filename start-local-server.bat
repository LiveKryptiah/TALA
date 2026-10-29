@echo off
title TALA Website Server
cd /d "%~dp0"
echo ========================================================
echo       Starting TALA Website on Localhost
echo ========================================================
echo.

:: Detect MySQL executable and datadir
set "MYSQL_BIN="
set "MYSQL_DATA="
if exist "%USERPROFILE%\mysql8\bin\mysqld.exe" (
    set "MYSQL_BIN=%USERPROFILE%\mysql8\bin\mysqld.exe"
    set "MYSQL_DATA=%USERPROFILE%\mysql8\data"
) else if exist "C:\Users\azare\mysql8\bin\mysqld.exe" (
    set "MYSQL_BIN=C:\Users\azare\mysql8\bin\mysqld.exe"
    set "MYSQL_DATA=C:\Users\azare\mysql8\data"
) else if exist "C:\mysql8\bin\mysqld.exe" (
    set "MYSQL_BIN=C:\mysql8\bin\mysqld.exe"
    set "MYSQL_DATA=C:\mysql8\data"
)

:: Detect PHP executable
set "PHP_BIN="
where php >nul 2>nul
if %errorlevel% equ 0 (
    set "PHP_BIN=php"
) else if exist "%USERPROFILE%\php\php.exe" (
    set "PHP_BIN=%USERPROFILE%\php\php.exe"
) else if exist "C:\Users\azare\php82\php.exe" (
    set "PHP_BIN=C:\Users\azare\php82\php.exe"
) else if exist "C:\xampp\php\php.exe" (
    set "PHP_BIN=C:\xampp\php\php.exe"
)

:: Check MySQL
tasklist /fi "imagename eq mysqld.exe" 2>NUL | find /i "mysqld.exe" >NUL
if errorlevel 1 (
    echo [1/3] Starting MySQL Server...
    if defined MYSQL_BIN (
        start "" /b "%MYSQL_BIN%" --datadir="%MYSQL_DATA%" --port=3306
        timeout /t 2 /nobreak >NUL
    ) else (
        echo [!] Warning: mysqld.exe not found.
    )
) else (
    echo [1/3] MySQL Server is already running.
)

:: Launch browser
echo [2/3] Opening browser to http://localhost:8080/
start http://localhost:8080/

:: Start PHP web server
echo [3/3] Starting PHP Development Server on http://localhost:8080/
echo.
echo ========================================================
echo  Server is running at: http://localhost:8080/
echo  Press Ctrl+C in this window to stop the PHP server.
echo ========================================================
echo.
if defined PHP_BIN (
    "%PHP_BIN%" -S localhost:8080
) else (
    echo [ERROR] PHP executable was not found!
    pause
)
