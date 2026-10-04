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
Write-Host "IDs atuais (pos-recriacao):"
Write-Host "  project   95fcab4e-8a01-4af2-af47-23407e2f66c9"
Write-Host "  wordpress 08b66ddc-0bcb-4b1e-803e-588f8820c612"
Write-Host "  mysql     86bea7c0-4ccd-4545-b937-08bd36b1527b"
Write-Host ""
Write-Host "Se ainda quiser importar daqui, use WSL/Git Bash:"
Write-Host "  export RAILWAY_API_TOKEN=..."
Write-Host "  ./scripts/terraform-import.sh"
exit 1
