import {
  defineRailway,
  github,
  mysql,
  preserve,
  project,
  redis,
  service,
  volume,
} from "railway/iac";

export default defineRailway(() => {
  const MySQL = mysql("MySQL", { region: "sfo" });
  // Volume novo: chmod + entrypoint inicializam datadir.
  // Volume ja populado (production): entrypoint detecta e sobe o mysqld.
  MySQL.deploy = {
    startCommand:
      "chmod 777 /var/lib/mysql; docker-entrypoint.sh mysqld --innodb-use-native-aio=0 --disable-log-bin --performance_schema=0 --innodb-buffer-pool-size=256M",
  };
  MySQL.networking = { privateNetworkEndpoint: "mysql" };

  // Object cache WordPress (Redis Object Cache plugin + drop-in via wp-boot).
  const Redis = redis("Redis", { region: "sfo" });

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
      // Readiness: /ccdready valida MySQL. Se falhar, o deploy novo nao recebe
      // trafego (previous continua). Liveness estatico: /ccdhealth.
      // Railway so aceita [a-zA-Z0-9/_] (sem ponto) e nao segue 301.
      healthcheckPath: "/ccdready",
      healthcheckTimeout: 300,
      // Blue/green nativo Railway: overlap + drain apos o novo ficar healthy.
      // Com volume de uploads ainda ha remount breve — ver docs/BLUE_GREEN.md.
      overlapSeconds: 90,
      drainingSeconds: 40,
      restartPolicyType: "ALWAYS",
      restartPolicyMaxRetries: 10,
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
      // Redis object cache — ver docs/PERFORMANCE.md
      WP_REDIS_HOST: Redis.env.REDISHOST,
      WP_REDIS_PORT: Redis.env.REDISPORT,
      WP_REDIS_PASSWORD: Redis.env.REDIS_PASSWORD,
      WP_REDIS_USERNAME: Redis.env.REDISUSER,
      WP_REDIS_PREFIX: "ccd_",
      // Opcional: CCD_A11Y_DISABLE=1 desliga tipografia/hero a11y em producao
    },
  });

  return project("convivendocomdiabetes", {
    resources: [convivendocomdiabetes, MySQL, Redis, wpUploads5g, mysqlVolume_zsT],
  });
});
