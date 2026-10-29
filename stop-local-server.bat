@echo off
title Stop TALA Local Server
echo Stopping TALA local servers...

set "MYSQLADMIN="
if exist "%USERPROFILE%\mysql8\bin\mysqladmin.exe" (
    set "MYSQLADMIN=%USERPROFILE%\mysql8\bin\mysqladmin.exe"
) else if exist "C:\Users\azare\mysql8\bin\mysqladmin.exe" (
    set "MYSQLADMIN=C:\Users\azare\mysql8\bin\mysqladmin.exe"
) else (
    set "MYSQLADMIN=mysqladmin"
)

"%MYSQLADMIN%" -u root shutdown 2>NUL
taskkill /f /im mysqld.exe 2>NUL
taskkill /f /im php.exe 2>NUL
echo Servers stopped.
pause
