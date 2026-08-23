@echo off
setlocal
cd /d "%~dp0"

where codex.cmd >nul 2>nul
if errorlevel 1 (
  echo Codex CLI was not found. Install it, sign in with your ChatGPT account, then run this launcher again.
  exit /b 1
)

echo Starting the Campaign Operator in Codex.
echo This is an interactive session. It will ask before CRM writes and never sends outreach.
echo.
call codex.cmd "Read AGENT.md and README.md in the current folder. Act as the Campaign Operator. Use run.php to access the CRM through MCP. Begin by asking what ideal customer profile and lead-search query I want to run. Do not alter project code, use external browser automation, or make any CRM write unless I explicitly approve it."
