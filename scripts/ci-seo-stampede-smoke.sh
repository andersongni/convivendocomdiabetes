#!/usr/bin/env bash
# Smoke: migrações SEO não podem saturar o front (stampede).
# 1) Apaga flags de migração
# 2) Martela TODAS as URLs públicas do WP em paralelo com timeout curto
# 3) Confirma que o front NÃO claimou as opções
# 4) Roda migração via WP-CLI
# 5) Martela de novo (todas as páginas) — deve continuar rápido
#
# Requer: WP_NAME, BASE
set -euo pipefail

WP_NAME="${WP_NAME:?}"
BASE="${BASE:?}"
WP=(docker exec "${WP_NAME}" wp --allow-root --path=/var/www/html)
MAX_TIME="${CCD_STAMPEDE_MAX_TIME:-3}"
PARALLEL="${CCD_STAMPEDE_PARALLEL:-12}"

URLS_FILE="$(mktemp)"
RESULTS_DIR="$(mktemp -d)"
trap 'rm -f "${URLS_FILE}"; rm -rf "${RESULTS_DIR}"' EXIT

echo "[ci-stampede] listando paths publicos do site..."
"${WP[@]}" eval '
$home = untrailingslashit( (string) home_url() );
$paths = array( "/" );
$posts = get_posts( array(
  "post_type"      => array( "post", "page" ),
  "post_status"    => "publish",
  "posts_per_page" => -1,
  "orderby"        => "ID",
  "order"          => "ASC",
) );
foreach ( $posts as $p ) {
  $link = get_permalink( $p );
  if ( ! is_string( $link ) || $link === "" ) {
    continue;
  }
  if ( str_starts_with( $link, $home ) ) {
    $path = substr( $link, strlen( $home ) );
    $paths[] = ( $path === "" || $path === false ) ? "/" : $path;
  }
}
$terms = get_terms( array(
  "taxonomy"   => array( "category", "post_tag" ),
  "hide_empty" => false,
) );
if ( ! is_wp_error( $terms ) ) {
  foreach ( (array) $terms as $t ) {
    $link = get_term_link( $t );
    if ( ! is_string( $link ) || $link === "" ) {
      continue;
    }
    if ( str_starts_with( $link, $home ) ) {
      $path = substr( $link, strlen( $home ) );
      $paths[] = ( $path === "" || $path === false ) ? "/" : $path;
    }
  }
}
$paths = array_values( array_unique( $paths ) );
foreach ( $paths as $path ) {
  if ( $path[0] !== "/" ) {
    $path = "/" . $path;
  }
  echo $path, "\n";
}
' > "${URLS_FILE}"

mapfile -t PATHS < "${URLS_FILE}"
URLS=()
for p in "${PATHS[@]}"; do
  p="$(printf '%s' "${p}" | tr -d '\r' | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')"
  [ -n "${p}" ] || continue
  URLS+=( "${BASE}${p}" )
done
COUNT="${#URLS[@]}"
if [ "${COUNT}" -lt 3 ]; then
  echo "[ci-stampede] FAIL: esperava >=3 URLs publicas, got ${COUNT}"
  printf '%s\n' "${URLS[@]}"
  exit 1
fi
echo "[ci-stampede] ${COUNT} URLs"

hammer() {
  local label="$1"
  local fail=0
  local i=0
  local pids=()
  mkdir -p "${RESULTS_DIR}/${label}"
  echo "[ci-stampede] ${label}: parallel=${PARALLEL} max-time=${MAX_TIME}s ..."

  for url in "${URLS[@]}"; do
    i=$((i + 1))
    (
      code=$(curl -sS -o /dev/null -w '%{http_code}' --max-time "${MAX_TIME}" "${url}" || echo 000)
      echo "${code} ${url}" > "${RESULTS_DIR}/${label}/${i}.txt"
      case "${code}" in
        200|301|302) ;;
        *) exit 1 ;;
      esac
    ) &
    pids+=( "$!" )
    if [ "${#pids[@]}" -ge "${PARALLEL}" ]; then
      for pid in "${pids[@]}"; do
        wait "${pid}" || fail=1
      done
      pids=()
    fi
  done
  if [ "${#pids[@]}" -gt 0 ]; then
    for pid in "${pids[@]}"; do
      wait "${pid}" || fail=1
    done
  fi

  local bad=0
  for f in "${RESULTS_DIR}/${label}"/*.txt; do
    [ -f "${f}" ] || continue
    line="$(cat "${f}")"
    code="${line%% *}"
    case "${code}" in
      200|301|302) ;;
      *)
        echo "[ci-stampede] FAIL ${label}: ${line}"
        bad=1
        ;;
    esac
  done
  if [ "${fail}" != "0" ] || [ "${bad}" != "0" ]; then
    echo "[ci-stampede] FAIL ${label}: timeout/erro em request paralelo (stampede?)"
    ls -1 "${RESULTS_DIR}/${label}" | wc -l | awk '{print "results="$1}'
    grep -hE '^(000|5|4)' "${RESULTS_DIR}/${label}"/*.txt 2>/dev/null | head -n 30 || true
    docker logs "${WP_NAME}" 2>&1 | tail -n 40 || true
    exit 1
  fi
  echo "[ci-stampede] ${label}: OK (${COUNT} URLs)"
}

echo "[ci-stampede] reset flags de migracao..."
"${WP[@]}" option delete ccd_seo_editorial >/dev/null 2>&1 || true
"${WP[@]}" option delete ccd_seo_boost >/dev/null 2>&1 || true
"${WP[@]}" transient delete ccd_seo_editorial_migrating >/dev/null 2>&1 || true
"${WP[@]}" transient delete ccd_seo_boost_migrating >/dev/null 2>&1 || true

# Front com flags ausentes — nao pode migrar nem travar.
hammer "front-before-migrate"

ed="$("${WP[@]}" option get ccd_seo_editorial 2>/dev/null || true)"
bo="$("${WP[@]}" option get ccd_seo_boost 2>/dev/null || true)"
if [ -n "${ed}" ] || [ -n "${bo}" ]; then
  echo "[ci-stampede] FAIL: front claimou migracao (editorial='${ed}' boost='${bo}')"
  exit 1
fi
echo "[ci-stampede] front nao claimou options (OK)"

echo "[ci-stampede] migracao via WP-CLI..."
"${WP[@]}" eval '
if ( function_exists( "ccd_seo_boost_apply" ) ) {
  ccd_seo_boost_apply();
}
if ( function_exists( "ccd_seo_editorial_apply" ) ) {
  ccd_seo_editorial_apply();
}
echo "boost=", (string) get_option( "ccd_seo_boost" ), " editorial=", (string) get_option( "ccd_seo_editorial" ), "\n";
'

ed="$("${WP[@]}" option get ccd_seo_editorial)"
bo="$("${WP[@]}" option get ccd_seo_boost)"
echo "[ci-stampede] apos CLI: editorial=${ed} boost=${bo}"
test -n "${ed}"
test -n "${bo}"

hammer "front-after-migrate"

echo "[ci-stampede] OK"
