import {
  defineRailway,
  github,
  mysql,
  preserve,
  project,
  service,
  volume,
} from "railway/iac";

export default defineRailway(() => {
  const MySQL = mysql("MySQL", { region: "sfo" });
  MySQL.deploy = {
    startCommand:
      "mysqld --innodb-use-native-aio=0 --disable-log-bin --performance_schema=0 --innodb-buffer-pool-size=256M",
  };
  MySQL.networking = { privateNetworkEndpoint: "mysql" };

  const wpUploads5g = volume("wp-uploads-5g", {
    alerts: { usage: { "100": {}, "80": {}, "95": {} } },
    allowOnlineResize: true,
    region: "sfo",
    sizeMB: 5000,
  });

  const mysqlVolume_zsT = volume("mysql-volume-_zsT", {
    alerts: { usage: { "100": {}, "80": {}, "95": {} } },
    allowOnlineResize: true,
    region: "sfo",
    sizeMB: 500,
  });

  const convivendocomdiabetes = service("convivendocomdiabetes", {
    // Wait for CI: Railway so dispara deploy apos workflows de push em main passarem.
    source: github("andersongni/convivendocomdiabetes", { checkSuites: true }),
    build: {
      builder: "DOCKERFILE",
      dockerfilePath: "Dockerfile",
    },
    deploy: {
      // Canonico: /ccdhealth — Alias Apache → ccd-health-ok.txt (+ fallback mu-plugin).
      // Railway so aceita [a-zA-Z0-9/_] (sem ponto) e nao segue 301.
      healthcheckPath: "/ccdhealth",
      healthcheckTimeout: 300,
    },
    replicas: { sfo: 1 },
    volumeMounts: {
      "/var/www/html/wp-content/uploads": wpUploads5g,
    },
    // Vars do Railway (nao usar .env local). Catalogo: .env.railway.example
    // Codigo/UX do repo promove via git; DB de usuarios e secrets ficam em preserve().
    env: {
      WORDPRESS_DB_HOST: preserve(),
      WORDPRESS_DB_NAME: preserve(),
      WORDPRESS_DB_PASSWORD: preserve(),
      WORDPRESS_DB_USER: preserve(),
      WP_ADMIN_EMAIL: preserve(),
      WP_ADMIN_PASSWORD: preserve(),
      WP_ADMIN_USER: preserve(),
      WP_TITLE: preserve(),
      WP_HOME: "https://convivendocomdiabetes.com",
      WP_SITEURL: "https://convivendocomdiabetes.com",
      CCD_RECAPTCHA_SITE_KEY: preserve(),
      CCD_RECAPTCHA_SECRET_KEY: preserve(),
      // Search Console meta token — ver docs/SEO.md
      CCD_GOOGLE_SITE_VERIFICATION: preserve(),
      // Opcional: CCD_A11Y_DISABLE=1 desliga tipografia/hero a11y em producao
    },
  });

  return project("convivendocomdiabetes", {
    resources: [convivendocomdiabetes, MySQL, wpUploads5g, mysqlVolume_zsT],
  });
});
