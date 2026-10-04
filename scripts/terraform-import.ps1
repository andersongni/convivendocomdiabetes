<#
.SYNOPSIS
  Importa o projeto Railway ja existente para o state do Terraform.

.NOTES
  Requer:
    - terraform no PATH
    - RAILWAY_TOKEN (Account/Workspace token)
    - terraform/terraform.tfvars (copie de terraform.tfvars.example)
#>
$ErrorActionPreference = "Stop"
$Root = Split-Path $PSScriptRoot -Parent
Set-Location (Join-Path $Root "terraform")

if (-not $env:RAILWAY_TOKEN) {
  throw "Defina RAILWAY_TOKEN (Account/Workspace token) antes de importar."
}

if (-not (Test-Path "terraform.tfvars")) {
  Copy-Item "terraform.tfvars.example" "terraform.tfvars"
  Write-Host "Criado terraform.tfvars a partir do example — revise a senha se quiser."
}

terraform init -input=false

$ProjectId = "95fcab4e-8a01-4af2-af47-23407e2f66c9"
$WordpressId = "deda0a95-a736-451c-bfa0-ce04e143b98c"
$MysqlId = "f9d67b4c-25c6-4452-b896-130ba077997e"
$EnvName = "production"
$Domain = "convivendocomdiabetes-production.up.railway.app"

terraform import railway_project.this $ProjectId
terraform import railway_service.wordpress $WordpressId
terraform import railway_service.mysql $MysqlId
terraform import "railway_service_domain.wordpress" "${WordpressId}:${EnvName}:${Domain}"

$vars = @(
  "wordpress_db_host:WORDPRESS_DB_HOST",
  "wordpress_db_user:WORDPRESS_DB_USER",
  "wordpress_db_password:WORDPRESS_DB_PASSWORD",
  "wordpress_db_name:WORDPRESS_DB_NAME",
  "wp_admin_user:WP_ADMIN_USER",
  "wp_admin_password:WP_ADMIN_PASSWORD",
  "wp_admin_email:WP_ADMIN_EMAIL",
  "wp_title:WP_TITLE"
)

foreach ($pair in $vars) {
  $tfName, $envName = $pair.Split(":", 2)
  terraform import "railway_variable.${tfName}" "${WordpressId}:${EnvName}:${envName}"
}

Write-Host ""
Write-Host "Import concluido. Rode: terraform plan"
Write-Host "Se o plan estiver limpo (ou so com diffs aceitaveis), faca commit do codigo TF e configure o secret RAILWAY_TOKEN no GitHub."
