terraform {
  required_version = ">= 1.5.0"

  required_providers {
    railway = {
      source  = "terraform-community-providers/railway"
      version = "~> 0.6.2"
    }
  }

  # State persistido no GitHub na branch `tfstate` pelo workflow
  # .github/workflows/terraform.yml (sem precisar rodar terraform na sua maquina).
}
