@echo off
setlocal
title SIPELAB - Demo
cd /d "%~dp0"
set "PHP=C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe"
if not exist "%PHP%" (
  where php >nul 2>nul || (
    echo PHP 8.2+ tidak ditemukan. Pasang Laragon/XAMPP/Herd, atau ubah baris "set PHP=" di demo.bat.
    pause & exit /b 1
  )
  set "PHP=php"
)
if not exist ".env" copy /y ".env.example" ".env" >nul

echo ==============================================
echo   SIPELAB - Demo dari laptop
echo ==============================================
echo   1. Jalankan (data tetap)
echo   2. Reset data demo lalu jalankan (data bersih)
echo ==============================================
choice /c 12 /n /m "Pilih 1 atau 2: "
if errorlevel 2 goto reset
goto jalan

:reset
echo.
echo Mereset database demo...
"%PHP%" artisan migrate:fresh --seed --force || (pause & exit /b 1)
if exist "storage\app\private\dokumen" (
  for %%F in ("storage\app\private\dokumen\*") do del /q "%%F"
)
"%PHP%" artisan optimize:clear >nul

:jalan
"%PHP%" artisan migrate --force >nul 2>nul
"%PHP%" artisan db:seed --force >nul 2>nul
echo.
echo ----------------------------------------------
echo  Buka di laptop ini : http://localhost:8000
for /f "usebackq delims=" %%I in (`powershell -NoProfile -Command "(Get-NetIPAddress -AddressFamily IPv4 | Where-Object { $_.InterfaceAlias -match 'Wi-Fi|Ethernet' -and $_.IPAddress -notmatch '^(127|169\.254)' }).IPAddress"`) do echo  Dari HP/laptop lain: http://%%I:8000
echo.
echo  Akun demo (sandi Lab12345!):
echo    Pengguna jasa : pengguna@lab.local  (Mandiri: bayar di awal)
echo                    eksternal@lab.local (Eksternal: bayar saat LHU jadi)
echo    Operator      : admin@lab.local, nekropsi@lab.local,
echo                    mikro@lab.local, mikro2@lab.local, kimia@lab.local
echo    Superadmin    : superadmin@lab.local
echo.
echo  Tutup jendela ini (atau Ctrl+C) untuk menghentikan.
echo ----------------------------------------------
"%PHP%" artisan serve --host=0.0.0.0 --port=8000
pause
