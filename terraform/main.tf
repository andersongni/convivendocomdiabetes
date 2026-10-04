locals {
  # Sintaxe de referencia do Railway. Em HCL, $$ vira $ no valor final.
  mysql_host     = "$${{MySQL.MYSQLHOST}}"
  mysql_user     = "$${{MySQL.MYSQLUSER}}"
  mysql_password = "$${{MySQL.MYSQLPASSWORD}}"
  mysql_database = "$${{MySQL.MYSQLDATABASE}}"

  env_id = railway_project.this.default_environment.id
}

resource "railway_project" "this" {
  name         = var.project_name
  workspace_id = var.workspace_id
  private      = true

  default_environment {
    name = "production"
  }
}

# MySQL gerenciado do Railway aparece como imagem mysql + volume.
# Build/deploy extras continuam no railway.toml do app WordPress.
resource "railway_service" "mysql" {
  name         = var.mysql_service_name
  project_id   = railway_project.this.id
  source_image = "mysql:9"

  volume {
    name       = "mysql-volume"
    mount_path = "/var/lib/mysql"
  }

  lifecycle {
    # Regiao/replicas e startCommand do plugin MySQL podem divergir do schema TF.
    ignore_changes = [regions]
  }
}

resource "railway_service" "wordpress" {
  name               = var.wordpress_service_name
  project_id         = railway_project.this.id
  source_repo        = var.github_repo
  source_repo_branch = var.github_branch
  config_path        = "railway.toml"

  lifecycle {
    ignore_changes = [regions]
  }

  depends_on = [railway_service.mysql]
}

resource "railway_service_domain" "wordpress" {
  subdomain      = var.service_subdomain
  environment_id = local.env_id
  service_id     = railway_service.wordpress.id
}

resource "railway_variable" "wordpress_db_host" {
  name           = "WORDPRESS_DB_HOST"
  value          = local.mysql_host
  environment_id = local.env_id
  service_id     = railway_service.wordpress.id
}

resource "railway_variable" "wordpress_db_user" {
  name           = "WORDPRESS_DB_USER"
  value          = local.mysql_user
  environment_id = local.env_id
  service_id     = railway_service.wordpress.id
}

resource "railway_variable" "wordpress_db_password" {
  name           = "WORDPRESS_DB_PASSWORD"
  value          = local.mysql_password
  environment_id = local.env_id
  service_id     = railway_service.wordpress.id
}

resource "railway_variable" "wordpress_db_name" {
  name           = "WORDPRESS_DB_NAME"
  value          = local.mysql_database
  environment_id = local.env_id
  service_id     = railway_service.wordpress.id
}

resource "railway_variable" "wp_admin_user" {
  name           = "WP_ADMIN_USER"
  value          = var.wp_admin_user
  environment_id = local.env_id
  service_id     = railway_service.wordpress.id
}

resource "railway_variable" "wp_admin_password" {
  name           = "WP_ADMIN_PASSWORD"
  value          = var.wp_admin_password
  environment_id = local.env_id
  service_id     = railway_service.wordpress.id
}

resource "railway_variable" "wp_admin_email" {
  name           = "WP_ADMIN_EMAIL"
  value          = var.wp_admin_email
  environment_id = local.env_id
  service_id     = railway_service.wordpress.id
}

resource "railway_variable" "wp_title" {
  name           = "WP_TITLE"
  value          = var.wp_title
  environment_id = local.env_id
  service_id     = railway_service.wordpress.id
}
