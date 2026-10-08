variable "project_name" {
  type        = string
  description = "Nome do projeto no Railway"
  default     = "convivendocomdiabetes"
}

variable "workspace_id" {
  type        = string
  description = "ID do workspace Railway (obrigatorio se o token enxergar mais de um)"
  default     = "0a55a186-ed62-4696-b490-b8f83fc1de56"
}

variable "github_repo" {
  type        = string
  description = "Repositorio GitHub no formato owner/repo"
  default     = "andersongni/convivendocomdiabetes"
}

variable "github_branch" {
  type        = string
  description = "Branch de Auto-deploy"
  default     = "main"
}

variable "wordpress_service_name" {
  type    = string
  default = "convivendocomdiabetes"
}

variable "mysql_service_name" {
  type    = string
  default = "MySQL"
}

variable "wp_admin_user" {
  type    = string
  default = "admin"
}

variable "wp_admin_password" {
  type        = string
  description = "Senha inicial do admin (troca obrigatoria no primeiro acesso)"
  sensitive   = true
}

variable "wp_admin_email" {
  type    = string
  default = "admin@convivendocomdiabetes.com"
}

variable "wp_title" {
  type    = string
  default = "Convivendo com Diabetes"
}
