# Deploy demo SIPELAB ke Vercel (https://sipelab.vercel.app).
# Pakai (PowerShell, dari folder proyek):  powershell -ExecutionPolicy Bypass -File deploy-vercel.ps1
#
# Deploy dilakukan dari SALINAN folder tanpa .git: paket gratis Vercel (Hobby) memblokir deploy
# bila penulis commit Git bukan pemilik akun Vercel. Isi aplikasinya sama persis.
# Syarat: sudah login sekali (npx vercel@latest login). Rahasia (APP_KEY, database) ada di Vercel.
$ErrorActionPreference = 'Stop'
$node = 'C:\laragon\bin\nodejs\node-v22'
if (Test-Path $node) { $env:Path = "$node;" + $env:Path }

$src = $PSScriptRoot
$dst = Join-Path $env:TEMP 'sipelab-vercel'
if (Test-Path $dst) { Remove-Item -Recurse -Force $dst }
robocopy $src $dst /E /XD .git vendor node_modules tests /XF .env .env.local *.sqlite *.log rahasia-vercel.txt /NFL /NDL /NJH /NJS /NP | Out-Null
if (-not (Test-Path "$dst\.vercel\project.json")) { throw 'Folder .vercel belum ada. Jalankan dulu: npx vercel@latest link --project sipelab' }

Push-Location $dst
try {
    cmd /c "npx --yes vercel@latest deploy --prod --yes"
} finally {
    Pop-Location
    Remove-Item -Recurse -Force $dst -ErrorAction SilentlyContinue
}
Write-Host ''
Write-Host 'Bila ada migrasi database baru, jalankan juga migrasi ke Neon (lihat README-SIPELAB.md bagian 7).'
