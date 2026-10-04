<#
.SYNOPSIS
  Atalho local opcional. O fluxo recomendado e no GitHub Actions.

.DESCRIPTION
  Preferivel: Actions → Terraform → Run workflow com import=true.
  Este script so existe se voce quiser importar fora do CI.
#>
$ErrorActionPreference = "Stop"
Write-Host "Fluxo recomendado esta no GitHub:"
Write-Host "  Actions → Terraform → Run workflow → marque 'import'"
Write-Host ""
Write-Host "Se ainda quiser importar daqui, use WSL/Git Bash:"
Write-Host "  export RAILWAY_API_TOKEN=..."
Write-Host "  ./scripts/terraform-import.sh"
exit 1
