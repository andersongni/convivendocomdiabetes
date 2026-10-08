output "project_id" {
  value = railway_project.this.id
}

output "environment_id" {
  value = railway_project.this.default_environment.id
}

output "wordpress_service_id" {
  value = railway_service.wordpress.id
}

output "mysql_service_id" {
  value = railway_service.mysql.id
}

output "site_url" {
  value = "https://convivendocomdiabetes.com"
}
