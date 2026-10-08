#!/usr/bin/env bash
# SEO smoke CI (bash): robots/sitemap, metas, /diabetes/ vs old-slug, category base.
# Requer: BASE URL; conteudo semeado (ci-seed-content.sh); Yoast ativo.
set -euo pipefail

BASE="${BASE:?}"
BASE="${BASE%/}"
TMP="${TMPDIR:-/tmp}/ccd-ci-seo-$$"
mkdir -p "${TMP}"
trap 'rm -rf "${TMP}"' EXIT

fail=0
ok() { echo "OK  $1${2:+ — $2}"; }
bad() { echo "FAIL $1${2:+ — $2}"; fail=$((fail + 1)); }

extract() {
  python3 - "$1" "$2" <<'PY'
import re, sys
path, kind = sys.argv[1], sys.argv[2]
html = open(path, encoding="utf-8", errors="ignore").read()
if kind == "title":
    m = re.search(r"<title[^>]*>(.*?)</title>", html, re.I | re.S)
    print(re.sub(r"<[^>]+>", "", m.group(1)).strip() if m else "")
elif kind == "desc":
    m = re.search(
        r'<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)["\']',
        html,
        re.I,
    ) or re.search(
        r'<meta[^>]+content=["\']([^"\']*)["\'][^>]+name=["\']description["\']',
        html,
        re.I,
    )
    print(m.group(1).strip() if m else "")
PY
}

echo "[ci-seo] => ${BASE}"

robots="$(curl -sS --max-time 20 "${BASE}/robots.txt" || true)"
if printf '%s\n' "${robots}" | grep -qiE 'sitemap_index\.xml'; then
  ok "robots.txt" "sitemap_index.xml"
else
  bad "robots.txt" "sem sitemap_index.xml"
fi
sitemap_lines="$(printf '%s\n' "${robots}" | grep -ciE '^\s*Sitemap:' || true)"
if [ "${sitemap_lines}" = "1" ]; then
  ok "robots Sitemap unico"
else
  bad "robots Sitemap unico" "linhas=${sitemap_lines}"
fi

code=$(curl -sS -o "${TMP}/sitemap.xml" -w '%{http_code}' --max-time 25 "${BASE}/sitemap_index.xml" || echo 000)
if [ "${code}" = "200" ] && grep -q '<sitemapindex' "${TMP}/sitemap.xml"; then
  ok "sitemap_index.xml"
else
  bad "sitemap_index.xml" "HTTP ${code}"
fi

code=$(curl -sS -o "${TMP}/home.html" -w '%{http_code}' --max-time 30 "${BASE}/" || echo 000)
if [ "${code}" = "200" ]; then ok "home HTTP"; else bad "home HTTP" "status=${code}"; fi
if grep -qiE 'name=["'\'']description["'\'']' "${TMP}/home.html"; then ok "meta description"; else bad "meta description"; fi
if grep -qiE 'rel=["'\'']canonical["'\'']' "${TMP}/home.html"; then ok "canonical"; else bad "canonical"; fi
# grep exit 1 sem match + pipefail derruba o script; contar com || true.
empty_alt=$(grep -coiE '\balt=["'\''][[:space:]]*["'\'']' "${TMP}/home.html" || true)
if [ "${empty_alt}" = "0" ]; then ok "home sem alt vazio"; else bad "home sem alt vazio" "empty=${empty_alt}"; fi
if grep -qE 'ccd-eeat-schema|"@type"[[:space:]]*:[[:space:]]*"Organization"' "${TMP}/home.html"; then
  ok "Organization schema"
else
  bad "Organization schema"
fi

home_title="$(extract "${TMP}/home.html" title)"
if printf '%s\n' "${home_title}" | grep -qiE 'diabetes' && ! printf '%s\n' "${home_title}" | grep -qiE '^In[ií]cio\b'; then
  ok "home title util" "title=${home_title}"
else
  bad "home title util" "title=${home_title}"
fi

cat_hdr="$(curl -sSI --max-time 20 "${BASE}/diabetes/" || true)"
if printf '%s\n' "${cat_hdr}" | grep -qiE 'location:.*diabetes-tipo-2'; then
  bad "categoria /diabetes/ nao vira post" "redirect para post antigo"
else
  ok "categoria /diabetes/ nao vira post"
fi
code=$(curl -sS -o "${TMP}/diabetes.html" -w '%{http_code}' --max-time 30 "${BASE}/diabetes/" || echo 000)
if [ "${code}" = "200" ]; then ok "categoria /diabetes/ HTTP"; else bad "categoria /diabetes/ HTTP" "status=${code}"; fi
cat_title="$(extract "${TMP}/diabetes.html" title)"
if printf '%s\n' "${cat_title}" | grep -qiE 'diabetes' \
  && ! printf '%s\n' "${cat_title}" | grep -qiE 'diagnostico|diagnóstico'; then
  ok "categoria title" "title=${cat_title}"
else
  bad "categoria title" "title=${cat_title}"
fi
# Hero HTML real (sem depender de .header-separator — pode estar desligado no tema).
hero_chunk="$(
  python3 - "${TMP}/diabetes.html" <<'PY'
import re, sys
html = open(sys.argv[1], encoding="utf-8", errors="ignore").read()
m = re.search(
    r'<div[^>]*\bheader-wrapper\b[\s\S]*?<div[^>]*\bheader-separator\b',
    html,
    re.I,
)
if not m:
    m = re.search(
        r'<div[^>]*\bheader-wrapper\b[\s\S]{0,12000}?(?=<div[^>]+id=["\']page-content|id=["\']page-content)',
        html,
        re.I,
    )
if not m:
    m = re.search(r'<div[^>]*\bheader-wrapper\b[\s\S]{0,8000}', html, re.I)
sys.stdout.write(m.group(0) if m else "")
PY
)"
if printf '%s\n' "${hero_chunk}" | grep -qiE '<nav[^>]*ccd-hub-pillars|class=["'\''][^"'\'']*ccd-category-intro|Pilares para come[cç]ar'; then
  bad "categoria hero limpo" "pilares/intro dentro do hero"
elif printf '%s\n' "${hero_chunk}" | grep -qF 'Conteúdos sobre diabetes tipo'; then
  bad "categoria hero limpo" "meta longa no banner"
else
  ok "categoria hero limpo"
fi
if printf '%s\n' "${hero_chunk}" | grep -qiE 'class=["'\''][^"'\'']*hero-title'; then
  ok "categoria hero-title"
else
  bad "categoria hero-title" "ausente no hero"
fi
# Altura util padronizada (ccd-a11y: 11.5rem no .inner-header-description).
if printf '%s\n' "${hero_chunk}" | grep -qE 'inner-header-description'; then
  ok "categoria inner-header-description"
else
  bad "categoria inner-header-description" "markup ausente no hero"
fi

cat_base_hdr="$(curl -sSI --max-time 20 "${BASE}/category/diabetes/" || true)"
cat_base_loc="$(printf '%s\n' "${cat_base_hdr}" | awk 'tolower($1)=="location:"{print $2; exit}' | tr -d '\r')"
if printf '%s\n' "${cat_base_hdr}" | grep -qiE '^HTTP/1\.[01] 301' \
  && printf '%s\n' "${cat_base_loc}" | grep -qiE '/diabetes/?$' \
  && ! printf '%s\n' "${cat_base_loc}" | grep -qi '/category/'; then
  ok "category/diabetes 301 limpo" "location=${cat_base_loc}"
else
  bad "category/diabetes 301 limpo" "location=${cat_base_loc}"
fi

for page in blog contato clipping; do
  code=$(curl -sS -o "${TMP}/${page}.html" -w '%{http_code}' --max-time 30 "${BASE}/${page}/" || echo 000)
  desc="$(extract "${TMP}/${page}.html" desc)"
  dlen=${#desc}
  if [ "${code}" = "200" ] && [ "${dlen}" -ge 70 ] \
    && ! printf '%s\n' "${desc}" | grep -qiE '\[wpforms|&nbsp;|\[ccd_'; then
    ok "${page} meta util" "len=${dlen}"
  else
    bad "${page} meta util" "HTTP ${code} len=${dlen}"
  fi
done

code=$(curl -sS -o "${TMP}/receitas.html" -w '%{http_code}' --max-time 30 "${BASE}/receitas/" || echo 000)
rec_hdr="$(curl -sSI --max-time 20 "${BASE}/receitas/" || true)"
if printf '%s\n' "${rec_hdr}" | grep -qiE 'location:.*receitas-gostosas'; then
  bad "categoria /receitas/ nao vira post"
else
  ok "categoria /receitas/ nao vira post"
fi
if [ "${code}" = "200" ]; then ok "categoria /receitas/ HTTP"; else bad "categoria /receitas/ HTTP" "status=${code}"; fi

code=$(curl -sS -o "${TMP}/hipo.html" -w '%{http_code}' --max-time 30 "${BASE}/hipoglicemia/" || echo 000)
desc="$(extract "${TMP}/hipo.html" desc)"
if [ "${code}" = "200" ] && [ ${#desc} -ge 70 ] && printf '%s\n' "${desc}" | grep -qiE 'hipoglicemia'; then
  ok "hipoglicemia meta util" "len=${#desc}"
else
  bad "hipoglicemia meta util" "HTTP ${code} len=${#desc}"
fi

for pillar in alimentacao-e-diabetes-tipo-2 sensor-de-glicose-como-funciona o-que-e-hba1c-hemoglobina-glicada; do
  code=$(curl -sS -o "${TMP}/${pillar}.html" -w '%{http_code}' --max-time 30 "${BASE}/${pillar}/" || echo 000)
  if [ "${code}" = "200" ] && grep -qE 'ccd-editorial-links|Leia também|Leia tambem' "${TMP}/${pillar}.html"; then
    ok "pilar ${pillar}"
  else
    bad "pilar ${pillar}" "HTTP ${code}"
  fi
done

# Blindagem CSS: header-wrapper esconde pilares mesmo se markup antigo vazar.
if grep -qE 'header-wrapper \.ccd-hub-pillars|\.header-wrapper \.ccd-hub-pillars' "${TMP}/diabetes.html"; then
  ok "css hero esconde pilares"
else
  bad "css hero esconde pilares" "regra ausente"
fi
# <nav class="ccd-hub-pillars"> so em #page-content.
if grep -qE '<nav[^>]*ccd-hub-pillars' "${TMP}/diabetes.html"; then
  if awk '/id=["'\''"]page-content["'\''"]/,/<\/main>/' "${TMP}/diabetes.html" | grep -qE '<nav[^>]*ccd-hub-pillars'; then
    ok "hub pilares em page-content"
  else
    bad "hub pilares em page-content" "nav fora de #page-content"
  fi
  if printf '%s\n' "${hero_chunk}" | grep -qiE '<nav[^>]*ccd-hub-pillars|Pilares para come[cç]ar'; then
    bad "hub pilares fora do markup do hero" "ainda no HTML do banner"
  else
    ok "hub pilares fora do markup do hero"
  fi
else
  bad "hub pilares" "ausentes (esperado em /diabetes/)"
fi

# Masonry: pilares DENTRO de .post-list ficam sob os cards (texto azul no vão).
for hub_page in diabetes receitas; do
  hub_file="${TMP}/${hub_page}.html"
  if [ ! -s "${hub_file}" ]; then
    bad "hub pilares fora do post-list (${hub_page})" "HTML ausente"
    continue
  fi
  nested="$(
    python3 - "${hub_file}" <<'PY'
import re, sys
html = open(sys.argv[1], encoding="utf-8", errors="ignore").read()
m = re.search(r'id=["\']page-content["\']([\s\S]*)', html, re.I)
content = m.group(1) if m else html
end = re.search(r'</main>', content, re.I)
if end:
    content = content[: end.start()]
pl = re.search(r'<div[^>]*\bpost-list\b[^>]*>', content, re.I)
nav = re.search(r'<nav[^>]*\bccd-hub-pillars\b[^>]*>', content, re.I)
if nav and pl and nav.start() > pl.start():
    print("nested")
elif nav:
    print("ok")
else:
    print("missing")
PY
  )"
  case "${nested}" in
    ok) ok "hub pilares fora do post-list (${hub_page})" ;;
    nested) bad "hub pilares fora do post-list (${hub_page})" "nav dentro de .post-list (masonry)" ;;
    *) bad "hub pilares fora do post-list (${hub_page})" "nav ausente" ;;
  esac
done

if [ "${fail}" -gt 0 ]; then
  echo "[ci-seo] Falhou: ${fail} check(s)"
  exit 1
fi
echo "[ci-seo] OK"
