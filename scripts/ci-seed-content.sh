#!/usr/bin/env bash
# Semeia conteudo minimo no WP de CI para SEO/UX/old-slug.
# Requer: WP_NAME, BASE (URL http do container).
set -euo pipefail

WP_NAME="${WP_NAME:?}"
BASE="${BASE:?}"
WP=(docker exec "${WP_NAME}" wp --allow-root --path=/var/www/html)

echo "[ci-seed] tema + plugins SEO..."
"${WP[@]}" theme activate empowerwp >/dev/null 2>&1 \
  || "${WP[@]}" theme activate mesmerize >/dev/null 2>&1 \
  || true
"${WP[@]}" plugin activate wordpress-seo >/dev/null 2>&1 || true
# Yoast: strip category base (ccd-category-urls tambem seta; reforco explicito).
"${WP[@]}" option patch update wpseo_titles stripcategorybase true >/dev/null 2>&1 || true
"${WP[@]}" eval 'if (class_exists("WPSEO_Options")) { WPSEO_Options::set("stripcategorybase", true); }' >/dev/null 2>&1 || true

echo "[ci-seed] paginas chave..."
for spec in \
  'blog|Blog|Artigos, receitas e historias reais sobre diabetes tipo 2 no blog CCD CI.' \
  'contato|Contato|Fale com a Bia Libonati: duvidas, parcerias e imprensa sobre diabetes tipo 2.' \
  'clipping|Clipping|Campanhas e aparicoes da Bia na midia sobre diabetes tipo 2 e educacao.'
do
  IFS='|' read -r slug title excerpt <<<"${spec}"
  if ! "${WP[@]}" post list --post_type=page --name="${slug}" --field=ID 2>/dev/null | grep -qE '^[0-9]+$'; then
    "${WP[@]}" post create \
      --post_type=page \
      --post_status=publish \
      --post_name="${slug}" \
      --post_title="${title}" \
      --post_excerpt="${excerpt}" \
      --porcelain >/dev/null
  fi
done

# /blog/ como arquivo de posts (infinite scroll + body.blog).
BLOG_ID="$("${WP[@]}" post list --post_type=page --name=blog --field=ID 2>/dev/null | head -n1 || true)"
if [ -n "${BLOG_ID}" ]; then
  "${WP[@]}" option update show_on_front page >/dev/null
  # Home: se nao houver front page, usa a mesma blog (arquivo ainda funciona).
  FRONT_ID="$("${WP[@]}" option get page_on_front 2>/dev/null || true)"
  if [ -z "${FRONT_ID}" ] || [ "${FRONT_ID}" = "0" ]; then
    HOME_ID="$("${WP[@]}" post list --post_type=page --name=home --field=ID 2>/dev/null | head -n1 || true)"
    if [ -z "${HOME_ID}" ]; then
      HOME_ID="$("${WP[@]}" post create --post_type=page --post_status=publish --post_name=home --post_title=Home --porcelain)"
    fi
    "${WP[@]}" option update page_on_front "${HOME_ID}" >/dev/null
  fi
  "${WP[@]}" option update page_for_posts "${BLOG_ID}" >/dev/null
fi
"${WP[@]}" option update posts_per_page 3 >/dev/null

echo "[ci-seed] categoria diabetes + post com slug antigo conflitante..."
if ! "${WP[@]}" term get category diabetes --by=slug --field=term_id >/dev/null 2>&1; then
  "${WP[@]}" term create category 'Diabetes' \
    --slug=diabetes \
    --description='Conteudos sobre diabetes tipo 1 e tipo 2: diagnostico, tratamento, rotina e convivencia com a condicao.' \
    >/dev/null
fi
DIABETES_ID="$("${WP[@]}" term get category diabetes --by=slug --field=term_id)"

POST_ID="$("${WP[@]}" post list --post_type=post --name=diabetes-tipo-2-o-que-e-e-como-e-feito-diagnostico --field=ID 2>/dev/null | head -n1 || true)"
if [ -z "${POST_ID}" ]; then
  POST_ID="$("${WP[@]}" post create \
    --post_type=post \
    --post_status=publish \
    --post_name=diabetes-tipo-2-o-que-e-e-como-e-feito-diagnostico \
    --post_title='Diabetes tipo 2: o que e e como e feito diagnostico?' \
    --post_content='Conteudo CI para listagem e regressao de slug antigo.' \
    --porcelain)"
fi
"${WP[@]}" post term set "${POST_ID}" category "${DIABETES_ID}" --by=id >/dev/null
"${WP[@]}" post update "${POST_ID}" --comment_status=open >/dev/null
"${WP[@]}" comment create --comment_post_ID="${POST_ID}" --comment_content='Comentario CI' --comment_author=CI --porcelain >/dev/null 2>&1 || true

echo "[ci-seed] categoria receitas..."
if ! "${WP[@]}" term get category receitas --by=slug --field=term_id >/dev/null 2>&1; then
  "${WP[@]}" term create category 'Receitas' --slug=receitas \
    --description='Receitas zero acucar e praticas para diabetes tipo 2.' >/dev/null
fi

echo "[ci-seed] pilares editoriais + hipoglicemia..."
for spec in \
  'alimentacao-e-diabetes-tipo-2|Alimentacao e diabetes tipo 2|Alimentacao e diabetes tipo 2: prato, carboidratos e habitos sustentaveis no cotidiano CI.' \
  'sensor-de-glicose-como-funciona|Sensor de glicose: como funciona|Sensor de glicose CGM: como funciona e como usar os dados no dia a dia CI.' \
  'o-que-e-hba1c-hemoglobina-glicada|O que e HbA1c|HbA1c hemoglobina glicada: o que mede e como usar o resultado no cuidado CI.' \
  'hipoglicemia|Hipoglicemia: o que fazer|O que e hipoglicemia no diabetes, sinais de alerta e como agir com seguranca no dia a dia.'
do
  IFS='|' read -r slug title excerpt <<<"${spec}"
  pid="$("${WP[@]}" post list --post_type=post --name="${slug}" --field=ID 2>/dev/null | head -n1 || true)"
  if [ -z "${pid}" ]; then
    pid="$("${WP[@]}" post create \
      --post_type=post \
      --post_status=publish \
      --post_name="${slug}" \
      --post_title="${title}" \
      --post_content='<p>Conteudo CI.</p><h2>Leia tambem</h2><ul class="ccd-editorial-links"><li><a href="/diabetes/">Diabetes</a></li></ul>' \
      --post_excerpt="${excerpt}" \
      --porcelain)"
  fi
  "${WP[@]}" post term set "${pid}" category "${DIABETES_ID}" --by=id >/dev/null
  "${WP[@]}" post update "${pid}" --comment_status=open >/dev/null
  if [ "${slug}" = "hipoglicemia" ]; then
    "${WP[@]}" comment list --post_id="${pid}" --format=count 2>/dev/null | grep -qE '^[1-9]' \
      || "${WP[@]}" comment create --comment_post_ID="${pid}" --comment_content='Comentario CI hipo' --comment_author=CI --porcelain >/dev/null 2>&1 \
      || true
  fi
done

# Posts extras para /blog/ ter 2+ paginas (posts_per_page=3).
echo "[ci-seed] posts extras para infinite scroll..."
for i in 1 2 3 4 5; do
  slug="ci-blog-extra-${i}"
  if ! "${WP[@]}" post list --post_type=post --name="${slug}" --field=ID 2>/dev/null | grep -qE '^[0-9]+$'; then
    "${WP[@]}" post create \
      --post_type=post \
      --post_status=publish \
      --post_name="${slug}" \
      --post_title="CI blog extra ${i}" \
      --post_content="<p>Post extra ${i} para scroll infinito.</p>" \
      --porcelain >/dev/null
  fi
done

echo "[ci-seed] sync SEO boost/editorial via WP-CLI (nunca via GET /)..."
"${WP[@]}" option delete ccd_seo_boost >/dev/null 2>&1 || true
"${WP[@]}" option delete ccd_seo_editorial >/dev/null 2>&1 || true
"${WP[@]}" option delete ccd_category_urls >/dev/null 2>&1 || true
"${WP[@]}" eval '
if ( function_exists( "ccd_seo_boost_apply" ) ) {
  ccd_seo_boost_apply();
}
if ( function_exists( "ccd_seo_editorial_apply" ) ) {
  ccd_seo_editorial_apply();
}
' >/dev/null
"${WP[@]}" rewrite structure '/%postname%/' --hard >/dev/null 2>&1 || true
"${WP[@]}" rewrite flush --hard >/dev/null 2>&1 || true

# Reinsere _wp_old_slug DEPOIS do purge do ensure — testa filtros block_old_slug / guess_404.
echo "[ci-seed] reintroduz old-slug conflitante (diabetes)..."
"${WP[@]}" post meta update "${POST_ID}" _wp_old_slug diabetes >/dev/null 2>&1 || true

# Yoast indexaveis / metas das paginas chave (forca metadesc se filtro runtime falhar).
"${WP[@]}" eval '
$pages = array(
  "blog" => "Artigos, receitas e historias reais sobre diabetes tipo 2, rotina e saude no blog Convivendo com Diabetes.",
  "contato" => "Fale com a Bia Libonati: duvidas, parcerias e imprensa sobre diabetes tipo 2 e convivencia com a condicao.",
  "clipping" => "Portfolio da Bia Libonati na midia: campanhas, entrevistas e aparicoes sobre diabetes e saude.",
);
foreach ($pages as $slug => $desc) {
  $p = get_page_by_path($slug);
  if ($p) {
    update_post_meta((int) $p->ID, "_yoast_wpseo_metadesc", $desc);
  }
}
$posts = array(
  "hipoglicemia" => "O que e hipoglicemia no diabetes, sinais de alerta e como agir com seguranca no dia a dia.",
);
foreach ($posts as $slug => $desc) {
  $q = get_posts(array("name" => $slug, "post_type" => "post", "post_status" => "publish", "numberposts" => 1));
  if ($q) {
    update_post_meta((int) $q[0]->ID, "_yoast_wpseo_metadesc", $desc);
  }
}
' >/dev/null 2>&1 || true

echo "[ci-seed] upload smoke..."
printf 'ci-upload' | docker exec -i "${WP_NAME}" sh -c 'mkdir -p /var/www/html/wp-content/uploads/ci && cat > /var/www/html/wp-content/uploads/ci/probe.txt && chown -R www-data:www-data /var/www/html/wp-content/uploads/ci'

echo "[ci-seed] OK"
