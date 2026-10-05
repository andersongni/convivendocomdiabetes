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
    source: github("andersongni/convivendocomdiabetes", { checkSuites: false }),
    build: {
      builder: "DOCKERFILE",
      dockerfilePath: "Dockerfile",
    },
    deploy: {
      // Asset estatico: evita 302 do wp-login (SSL/canonical) no healthcheck HTTP
      healthcheckPath: "/wp-includes/js/jquery/jquery.min.js",
      healthcheckTimeout: 300,
    },
    replicas: { sfo: 1 },
    volumeMounts: {
      "/var/www/html/wp-content/uploads": wpUploads5g,
    },
    env: {
      WORDPRESS_DB_HOST: preserve(),
      WORDPRESS_DB_NAME: preserve(),
      WORDPRESS_DB_PASSWORD: preserve(),
      WORDPRESS_DB_USER: preserve(),
      WP_ADMIN_EMAIL: preserve(),
      WP_ADMIN_PASSWORD: preserve(),
      WP_ADMIN_USER: preserve(),
      WP_TITLE: preserve(),
      CCD_RECAPTCHA_SITE_KEY: preserve(),
      CCD_RECAPTCHA_SECRET_KEY: preserve(),
    },
  });

  return project("convivendocomdiabetes", {
    resources: [convivendocomdiabetes, MySQL, wpUploads5g, mysqlVolume_zsT],
  });
});
