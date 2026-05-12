# TradeMeter Deployment Script
# Usage: ./tasks/deploy_app.ps1 "Your commit message"

param(
    [string]$Message = "Update and deploy TradeMeter app"
)

$ErrorActionPreference = "Stop"

Write-Host "--- Starting Deployment Process ---" -ForegroundColor Cyan

# 1. Add changes
Write-Host "[1/4] Staging changes..."
git add .

# 2. Commit
Write-Host "[2/4] Committing changes..."
git commit -m $Message

# 3. Push to GitHub
Write-Host "[3/4] Pushing to GitHub (origin)..."
git push origin master

# 4. Push to Heroku
Write-Host "[4/4] Pushing to Heroku..."
git push heroku master

Write-Host "Deployment Complete!" -ForegroundColor Green
