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
empty_alt=$(grep -oiE '\balt=["'\''][[:space:]]*["'\'']' "${TMP}/home.html" | wc -l | tr -d ' ')
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
# Hero da categoria: so titulo (sem pilares/listas que inflam a faixa azul).
# Bloco Mesmerize: .header-wrapper … até .header-separator.
hero_chunk="$(awk '/header-wrapper/,/header-separator/' "${TMP}/diabetes.html" || true)"
if printf '%s\n' "${hero_chunk}" | grep -qiE 'ccd-hub-pillars|ccd-category-intro|Pilares para come[cç]ar'; then
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

if grep -qE 'ccd-hub-pillars|Pilares para come[cç]ar' "${TMP}/diabetes.html"; then
  # Deve existir na pagina, mas fora do hero (conteudo / loop).
  if printf '%s\n' "${hero_chunk}" | grep -qiE 'ccd-hub-pillars|Pilares para come[cç]ar'; then
    bad "hub /diabetes/ pilares fora do hero" "ainda no banner"
  else
    ok "hub /diabetes/ pilares fora do hero"
  fi
else
  bad "hub /diabetes/ pilares fora do hero" "pilares ausentes na pagina"
fi

if [ "${fail}" -gt 0 ]; then
  echo "[ci-seo] Falhou: ${fail} check(s)"
  exit 1
fi
echo "[ci-seo] OK"
