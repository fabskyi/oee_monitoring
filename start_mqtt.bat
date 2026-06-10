@echo off
title OEE MQTT Subscriber
color 0A
echo ============================================
echo   OEE MQTT Subscriber - Auto Restart Mode
echo ============================================
echo.

:START
echo [%TIME%] Starting mqtt_subscriber.php ...
C:\xampp\php\php.exe -f C:\xampp\htdocs\oee\mqtt_subscriber.php

echo.
echo [%TIME%] Process exited. Restarting in 5 seconds...
timeout /t 5 /nobreak >nul
goto START
