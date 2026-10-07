<?php
/**
 * Conteúdos editoriais SEO (pilares atualizados + posts novos).
 *
 * @package CCD
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Links internos reutilizáveis (slug => rótulo).
 *
 * @return array<string, string>
 */
function ccd_seo_editorial_link_labels() {
	return array(
		'diabetes-tipo-2-o-que-e-e-como-e-feito-diagnostico' => 'Diabetes tipo 2: o que é e como é feito o diagnóstico',
		'diabetes-tipo-2-tem-cura-saiba-mais-sobre-os-tratamentos-disponiveis' => 'Diabetes tipo 2 tem cura? Tratamentos disponíveis',
		'hipoglicemia' => 'Hipoglicemia: sinais e o que fazer',
		'quantas-vezes-por-dia-devo-medir-minha-glicemia' => 'Quantas vezes medir a glicemia por dia',
		'jejum-intermitente-e-diabetes-e-permitido-ou-nao' => 'Jejum intermitente e diabetes',
		'entenda-como-o-diabetes-pode-afetar-a-visao' => 'Como o diabetes pode afetar a visão',
		'metade-dos-diabeticos-morre-devido-a-infarto-ou-avc-mas-apenas-3-temem-essa-complicacao' => 'Diabetes, coração e AVC',
		'a-logica-do-cuidado-no-tratamento-de-diabetes' => 'A lógica do cuidado no tratamento',
		'alimentacao-e-diabetes-tipo-2' => 'Alimentação e diabetes tipo 2',
		'sensor-de-glicose-como-funciona' => 'Sensor de glicose: como funciona',
		'o-que-e-hba1c-hemoglobina-glicada' => 'O que é HbA1c (hemoglobina glicada)',
		'viagens-e-diabetes-um-guia-para-se-dar-bem-quando-estiver-longe-de-casa' => 'Viagens e diabetes',
	);
}

/**
 * @param string[] $slugs Slugs.
 * @return string HTML lista.
 */
function ccd_seo_editorial_links_html( array $slugs ) {
	$labels = ccd_seo_editorial_link_labels();
	$items  = array();
	foreach ( $slugs as $slug ) {
		$url = home_url( '/' . $slug . '/' );
		$lab = isset( $labels[ $slug ] ) ? $labels[ $slug ] : $slug;
		$items[] = '<li><a href="' . esc_url( $url ) . '">' . esc_html( $lab ) . '</a></li>';
	}
	if ( ! $items ) {
		return '';
	}
	return '<h2>Leia também</h2><ul class="ccd-editorial-links">' . implode( '', $items ) . '</ul>';
}

/**
 * Posts existentes a reescrever (slug => dados).
 *
 * @return array<string, array{title:string,excerpt:string,categories:string[],content:string,focus:string}>
 */
function ccd_seo_editorial_updates() {
	return array(
		'hipoglicemia' => array(
			'title'      => 'Hipoglicemia',
			'excerpt'    => 'O que é hipoglicemia no diabetes, sinais de alerta e como agir com segurança no dia a dia.',
			'focus'      => 'hipoglicemia',
			'categories' => array( 'diabetes', 'diabetes-tipo-1-e-tipo-2' ),
			'content'    => <<<'HTML'
<p>Hipoglicemia é quando a glicose no sangue fica abaixo do valor considerado seguro — em geral, abaixo de 70 mg/dL. Para quem convive com diabetes, reconhecer os sinais cedo faz diferença na rotina e na segurança.</p>
<p>Este texto é informativo e parte da minha experiência convivendo com diabetes tipo 2. Não substitui orientação médica: em dúvida, fale com seu time de saúde.</p>
<h2>Sinais mais comuns</h2>
<p>Os sintomas variam, mas costumam incluir sudorese, tremores, fome intensa, confusão, irritabilidade, tontura, visão turva e, em casos graves, desmaio. Nem sempre a pessoa “sente” a hipoglicemia da mesma forma — por isso o monitoramento ajuda.</p>
<h2>O que fazer na prática</h2>
<ul>
<li>Meça a glicemia se possível.</li>
<li>Ingira carboidrato de ação rápida (conforme orientação do seu médico — por exemplo, glicose, suco ou balinha apropriada).</li>
<li>Espere alguns minutos, reavalie e só então faça a refeição seguinte se estiver estável.</li>
<li>Se houver perda de consciência ou convulsão, é emergência: peça ajuda imediatamente.</li>
</ul>
<h2>Por que acontece?</h2>
<p>Dose de medicamento/insulina desajustada, atraso na refeição, atividade física intensa sem ajuste, álcool ou mudanças na rotina são gatilhos frequentes. Entender o seu padrão com o endocrinologista reduz sustos.</p>
<h2>Prevenção no dia a dia</h2>
<p>Ter identificação de diabetes, levar um “kit” de correção e combinar com família/amigos o que fazer em crise aumenta a segurança. Se você usa sensor ou mede com frequência, veja também <a href="/quantas-vezes-por-dia-devo-medir-minha-glicemia/">quantas vezes medir a glicemia</a> e <a href="/sensor-de-glicose-como-funciona/">como funciona o sensor de glicose</a>.</p>
HTML
			. ccd_seo_editorial_links_html(
				array(
					'quantas-vezes-por-dia-devo-medir-minha-glicemia',
					'sensor-de-glicose-como-funciona',
					'o-que-e-hba1c-hemoglobina-glicada',
					'a-logica-do-cuidado-no-tratamento-de-diabetes',
				)
			),
		),
		'diabetes-tipo-2-o-que-e-e-como-e-feito-diagnostico' => array(
			'title'      => 'Diabetes tipo 2: o que é e como é feito diagnóstico?',
			'excerpt'    => 'Diabetes tipo 2: o que é, diferença entre tipos e quais exames ajudam no diagnóstico.',
			'focus'      => 'diabetes tipo 2 diagnóstico',
			'categories' => array( 'diabetes', 'diabetes-tipo-1-e-tipo-2' ),
			'content'    => <<<'HTML'
<p>O diabetes tipo 2 acontece quando o corpo resiste à insulina e/ou produz insulina de forma insuficiente para manter a glicose sob controle. É a forma mais comum no Brasil e no mundo — e muitas pessoas descobrem a condição só depois de anos de glicemia alterada.</p>
<p>Conteúdo educativo com base em convivência e jornalismo de saúde. Diagnóstico e tratamento são sempre com médico.</p>
<h2>Tipo 1 x tipo 2 (em linhas gerais)</h2>
<p>No tipo 1, há destruição das células que produzem insulina, em geral com início mais abrupto. No tipo 2, predomina resistência à insulina associada a fatores genéticos e de estilo de vida. Só o profissional de saúde fecha o diagnóstico com exames e história clínica.</p>
<h2>Como costuma ser o diagnóstico</h2>
<p>Os exames mais citados em diretrizes incluem glicemia de jejum, hemoglobina glicada (HbA1c) e, em alguns casos, teste de tolerância à glicose. Valores de referência e critérios devem ser interpretados pelo médico — inclusive para pré-diabetes.</p>
<p>Para entender melhor o exame de acompanhamento, leia <a href="/o-que-e-hba1c-hemoglobina-glicada/">o que é HbA1c</a>.</p>
<h2>Sinais que merecem atenção</h2>
<p>Sede excessiva, urinar muito, cansaço, visão turva e infecções recorrentes podem aparecer — mas muita gente é assintomática. Por isso check-ups e rastreamento em quem tem fatores de risco (histórico familiar, sobrepeso, sedentarismo, hipertensão) importam.</p>
<h2>Depois do diagnóstico</h2>
<p>Receber o diagnóstico assusta; eu sei. O caminho costuma envolver alimentação, movimento, medicação quando indicada e acompanhamento. Comece pelos pilares: <a href="/alimentacao-e-diabetes-tipo-2/">alimentação e diabetes tipo 2</a> e <a href="/diabetes-tipo-2-tem-cura-saiba-mais-sobre-os-tratamentos-disponiveis/">tratamentos disponíveis</a>.</p>
HTML
			. ccd_seo_editorial_links_html(
				array(
					'o-que-e-hba1c-hemoglobina-glicada',
					'alimentacao-e-diabetes-tipo-2',
					'diabetes-tipo-2-tem-cura-saiba-mais-sobre-os-tratamentos-disponiveis',
					'a-logica-do-cuidado-no-tratamento-de-diabetes',
				)
			),
		),
		'diabetes-tipo-2-tem-cura-saiba-mais-sobre-os-tratamentos-disponiveis' => array(
			'title'      => 'Diabetes tipo 2 tem cura? Saiba mais sobre os tratamentos disponíveis',
			'excerpt'    => 'Diabetes tipo 2 tem cura? Entenda tratamentos, insulina e o que a ciência diz hoje.',
			'focus'      => 'diabetes tipo 2 tem cura',
			'categories' => array( 'diabetes', 'diabetes-tipo-1-e-tipo-2' ),
			'content'    => <<<'HTML'
<p>“Diabetes tipo 2 tem cura?” é uma das perguntas que mais ouço. Em geral, falamos em <strong>controle</strong> e, em alguns casos, em remissão (glicemia normal sem medicamentos por um período), não em “cura” definitiva garantida para todos.</p>
<p>Informação geral para apoiar conversa com o médico — não é prescrição.</p>
<h2>O que a ciência costuma considerar</h2>
<p>Mudanças intensas de peso e hábitos, sob supervisão, podem levar à remissão em parte das pessoas com tipo 2. Isso não significa abandonar acompanhamento: a condição pode voltar. Cada corpo responde diferente.</p>
<h2>Pilares do tratamento</h2>
<ul>
<li><strong>Estilo de vida:</strong> alimentação, atividade física e sono.</li>
<li><strong>Medicamentos orais e injetáveis</strong> quando indicados (há classes modernas que também ajudam no peso e no risco cardiovascular — a escolha é médica).</li>
<li><strong>Insulina</strong> quando necessária; não é “fracasso”, é ferramenta.</li>
<li><strong>Tecnologia:</strong> glicosímetros, sensores e, em alguns perfis, bombas.</li>
</ul>
<h2>Insulina no tipo 2</h2>
<p>Muita gente associa insulina só ao tipo 1. No tipo 2 ela também pode entrar no plano. O importante é alinhar metas de HbA1c, hipoglicemia e qualidade de vida com o endocrinologista.</p>
<p>Para o dia a dia do monitoramento, veja <a href="/sensor-de-glicose-como-funciona/">sensor de glicose</a> e <a href="/o-que-e-hba1c-hemoglobina-glicada/">HbA1c</a>.</p>
<h2>Como eu enxergo o cuidado</h2>
<p>Tratar diabetes é maratona, não sprint. Informação boa + equipe de saúde + rotina possível vale mais do que promessa milagrosa. Se você está no começo, releia <a href="/diabetes-tipo-2-o-que-e-e-como-e-feito-diagnostico/">o que é e como se diagnostica</a>.</p>
HTML
			. ccd_seo_editorial_links_html(
				array(
					'diabetes-tipo-2-o-que-e-e-como-e-feito-diagnostico',
					'alimentacao-e-diabetes-tipo-2',
					'sensor-de-glicose-como-funciona',
					'metade-dos-diabeticos-morre-devido-a-infarto-ou-avc-mas-apenas-3-temem-essa-complicacao',
				)
			),
		),
		'quantas-vezes-por-dia-devo-medir-minha-glicemia' => array(
			'title'      => 'Quantas vezes por dia devo medir minha glicemia?',
			'excerpt'    => 'Quantas vezes medir a glicemia por dia? Orientações práticas para o controle no cotidiano.',
			'focus'      => 'medir glicemia',
			'categories' => array( 'diabetes' ),
			'content'    => <<<'HTML'
<p>Não existe um número único de medições que sirva para todo mundo. A frequência depende do tipo de diabetes, do tratamento (com ou sem insulina), das metas combinadas com o médico e de situações especiais (doença, exercício, gravidez, mudança de medicação).</p>
<h2>Cenários comuns</h2>
<ul>
<li><strong>Em insulina ou com risco de hipoglicemia:</strong> costuma-se medir com mais frequência (jejum, pré-refeições, antes de dormir, e quando houver sintomas).</li>
<li><strong>Tipo 2 em comprimidos e estável:</strong> o médico pode indicar menos pontos no dia a dia, somados à HbA1c periódica.</li>
<li><strong>Com sensor (CGM/FGM):</strong> a leitura contínua muda a lógica — ainda assim, calibração/confirmação com fita pode ser pedida em alguns casos.</li>
</ul>
<h2>Horários que mais ensinam</h2>
<p>Jejum, 2 horas após a refeição principal e horários de sintoma costumam revelar padrões. Anotar comida, estresse e exercício ao lado do número ajuda a conversa no consultório.</p>
<h2>Quando medir fora do plano</h2>
<p>Mal-estar, treino intenso, viagem, febre ou dúvida de hipoglicemia são bons motivos para checar. Veja também <a href="/hipoglicemia/">hipoglicemia</a> e <a href="/sensor-de-glicose-como-funciona/">sensor de glicose</a>.</p>
<p>O acompanhamento de médio prazo passa pela <a href="/o-que-e-hba1c-hemoglobina-glicada/">hemoglobina glicada (HbA1c)</a>.</p>
HTML
			. ccd_seo_editorial_links_html(
				array(
					'hipoglicemia',
					'sensor-de-glicose-como-funciona',
					'o-que-e-hba1c-hemoglobina-glicada',
					'viagens-e-diabetes-um-guia-para-se-dar-bem-quando-estiver-longe-de-casa',
				)
			),
		),
		'jejum-intermitente-e-diabetes-e-permitido-ou-nao' => array(
			'title'      => 'Jejum intermitente x diabetes: é permitido ou não?',
			'excerpt'    => 'Jejum intermitente e diabetes: quando pode, riscos e por que falar com o médico antes.',
			'focus'      => 'jejum intermitente diabetes',
			'categories' => array( 'diabetes', 'alimentacao' ),
			'content'    => <<<'HTML'
<p>Jejum intermitente ficou popular — e a pergunta “pode quem tem diabetes?” não tem resposta única. Depende do tipo de diabetes, das medicações (especialmente insulina e secretagogos), de histórico de hipoglicemia e da avaliação médica.</p>
<h2>Riscos que não dá para ignorar</h2>
<p>Janelas longas sem comer podem precipitar hipoglicemia em quem usa certos medicamentos. Também há risco de compensar com exageros na janela de alimentação ou de piorar a relação com a comida.</p>
<h2>Quando pode entrar na conversa</h2>
<p>Algumas pessoas com tipo 2, sob supervisão, usam estratégias de restrição de horário como parte de um plano maior (peso, resistência à insulina). Isso não é “faça você mesmo” com base em rede social.</p>
<h2>O que eu reforço</h2>
<ul>
<li>Fale com endocrinologista e, se possível, nutricionista antes de mudar o padrão de refeições.</li>
<li>Tenha plano claro para medir glicemia e corrigir hipoglicemia.</li>
<li>Priorize qualidade da comida na janela em que come — veja <a href="/alimentacao-e-diabetes-tipo-2/">alimentação e diabetes tipo 2</a>.</li>
</ul>
<p>Se o jejum não for para você, tudo bem: consistência vence moda. Outros caminhos passam por <a href="/a-logica-do-cuidado-no-tratamento-de-diabetes/">cuidado contínuo</a> e monitoramento adequado.</p>
HTML
			. ccd_seo_editorial_links_html(
				array(
					'alimentacao-e-diabetes-tipo-2',
					'hipoglicemia',
					'quantas-vezes-por-dia-devo-medir-minha-glicemia',
					'diabetes-tipo-2-tem-cura-saiba-mais-sobre-os-tratamentos-disponiveis',
				)
			),
		),
		'entenda-como-o-diabetes-pode-afetar-a-visao' => array(
			'title'      => 'Entenda como o diabetes pode afetar a visão',
			'excerpt'    => 'Como o diabetes pode afetar a visão: riscos oculares e a importância do controle glicêmico.',
			'focus'      => 'diabetes visão',
			'categories' => array( 'diabetes' ),
			'content'    => <<<'HTML'
<p>A glicose alta crônica pode lesar vasos pequenos da retina e de outras estruturas do olho. Retinopatia diabética é uma das complicações mais conhecidas — e boa parte da prevenção está no controle ao longo do tempo, não só em “quando a vista embaça”.</p>
<h2>O que pode acontecer</h2>
<ul>
<li>Visão turva em picos de glicemia (às vezes reversível quando o controle melhora).</li>
<li>Retinopatia, edema macular, maior risco de catarata e glaucoma em alguns perfis.</li>
<li>Em estágios avançados, risco de perda visual importante.</li>
</ul>
<h2>Prevenção prática</h2>
<p>Controle de glicemia/HbA1c, pressão e colesterol, não fumar e <strong>consulta periódica com oftalmologista</strong> (mesmo sem sintoma) são a base. Quem já tem alterações precisa de seguimento mais próximo.</p>
<p>Para o panorama do controle, combine este tema com <a href="/o-que-e-hba1c-hemoglobina-glicada/">HbA1c</a> e o risco <a href="/metade-dos-diabeticos-morre-devido-a-infarto-ou-avc-mas-apenas-3-temem-essa-complicacao/">cardiovascular</a> — complicações micro e macrovasculares andam no mesmo pacote de cuidado.</p>
<h2>Quando buscar atendimento rápido</h2>
<p>Manchas súbitas, perda de campo visual, flashes ou piora rápida da visão pedem avaliação urgente. Não espere “passar sozinho”.</p>
HTML
			. ccd_seo_editorial_links_html(
				array(
					'o-que-e-hba1c-hemoglobina-glicada',
					'metade-dos-diabeticos-morre-devido-a-infarto-ou-avc-mas-apenas-3-temem-essa-complicacao',
					'diabetes-tipo-2-o-que-e-e-como-e-feito-diagnostico',
					'a-logica-do-cuidado-no-tratamento-de-diabetes',
				)
			),
		),
		'metade-dos-diabeticos-morre-devido-a-infarto-ou-avc-mas-apenas-3-temem-essa-complicacao' => array(
			'title'      => 'Diabetes, coração e AVC: por que o risco cardiovascular importa',
			'excerpt'    => 'Diabetes, coração e AVC: por que o risco cardiovascular merece atenção no dia a dia.',
			'focus'      => 'diabetes coração AVC',
			'categories' => array( 'diabetes' ),
			'content'    => <<<'HTML'
<p>Quem vive com diabetes ouve muito sobre glicemia — e menos sobre coração e cérebro. Porém o risco de infarto e AVC está elevado em muita gente com a condição, especialmente quando somam pressão alta, colesterol alterado, tabagismo ou rim comprometido.</p>
<p>Atualizei este texto para falar do risco de forma clara, sem alarmismo e sem substituir o cardiologista/endocrinologista.</p>
<h2>Por que o risco sobe</h2>
<p>Glicose alta, inflamação e alterações nos vasos aceleram aterosclerose. Por isso o cuidado “completo” olha HbA1c, pressão, lipídios, peso e rim — não só o número da ponta do dedo.</p>
<h2>O que ajuda na prevenção</h2>
<ul>
<li>Parar de fumar.</li>
<li>Tratar pressão e colesterol conforme indicação médica.</li>
<li>Movimento regular e alimentação sustentável — veja <a href="/alimentacao-e-diabetes-tipo-2/">alimentação e diabetes tipo 2</a>.</li>
<li>Aderir aos medicamentos (incluindo os que protegem coração/rim quando prescritos).</li>
</ul>
<h2>Sinais de alerta</h2>
<p>Dor no peito, falta de ar intensa, fraqueza súbita em um lado do corpo, fala embolada ou dor de cabeça explosiva pedem emergência (SAMU 192).</p>
<p>Cuidado integral também inclui olho e nervos: leia <a href="/entenda-como-o-diabetes-pode-afetar-a-visao/">diabetes e visão</a>.</p>
HTML
			. ccd_seo_editorial_links_html(
				array(
					'alimentacao-e-diabetes-tipo-2',
					'o-que-e-hba1c-hemoglobina-glicada',
					'entenda-como-o-diabetes-pode-afetar-a-visao',
					'diabetes-tipo-2-tem-cura-saiba-mais-sobre-os-tratamentos-disponiveis',
				)
			),
		),
		'a-logica-do-cuidado-no-tratamento-de-diabetes' => array(
			'title'      => 'A lógica do cuidado no tratamento de diabetes',
			'excerpt'    => 'A lógica do cuidado no tratamento do diabetes: rotina, adesão e convivência com a condição.',
			'focus'      => 'cuidado tratamento diabetes',
			'categories' => array( 'diabetes' ),
			'content'    => <<<'HTML'
<p>Tratar diabetes não é só “zerar açúcar”. É montar um sistema que caiba na sua vida: medições, comida, movimento, remédios, sono e rede de apoio. Sem lógica de cuidado, qualquer plano bonito no papel vira abandono em duas semanas.</p>
<h2>Os três eixos</h2>
<ol>
<li><strong>Entender a condição</strong> — o que é tipo 2, o que a HbA1c mostra, o que é hipoglicemia.</li>
<li><strong>Executar o básico repetível</strong> — horários de medicação, refeições possíveis, caminhada que você realmente faz.</li>
<li><strong>Ajustar com a equipe</strong> — levar números, dúvidas e dificuldades ao consultório sem culpa.</li>
</ol>
<h2>Ferramentas que sustentam o cuidado</h2>
<p><a href="/quantas-vezes-por-dia-devo-medir-minha-glicemia/">Medir glicemia</a>, eventualmente usar <a href="/sensor-de-glicose-como-funciona/">sensor</a>, acompanhar <a href="/o-que-e-hba1c-hemoglobina-glicada/">HbA1c</a> e cuidar da <a href="/alimentacao-e-diabetes-tipo-2/">alimentação</a> formam a espinha dorsal. Tecnologia ajuda; hábito segura.</p>
<h2>Saúde emocional conta</h2>
<p>Cansaço de tratamento é real. Pedir ajuda psicológica ou grupos de apoio não é fraqueza. O objetivo é conviver melhor — não viver em guerra com a glicemia.</p>
HTML
			. ccd_seo_editorial_links_html(
				array(
					'diabetes-tipo-2-o-que-e-e-como-e-feito-diagnostico',
					'alimentacao-e-diabetes-tipo-2',
					'hipoglicemia',
					'metade-dos-diabeticos-morre-devido-a-infarto-ou-avc-mas-apenas-3-temem-essa-complicacao',
				)
			),
		),
	);
}

/**
 * Posts novos (pilares).
 *
 * @return array<string, array{title:string,excerpt:string,categories:string[],content:string,focus:string}>
 */
function ccd_seo_editorial_new_posts() {
	return array(
		'alimentacao-e-diabetes-tipo-2' => array(
			'title'      => 'Alimentação e diabetes tipo 2: o que importa no dia a dia',
			'excerpt'    => 'Alimentação e diabetes tipo 2: prato, carboidratos, horários e hábitos sustentáveis para o cotidiano.',
			'focus'      => 'alimentação diabetes tipo 2',
			'categories' => array( 'diabetes', 'alimentacao' ),
			'content'    => <<<'HTML'
<p>Alimentação é um dos pilares do convívio com diabetes tipo 2 — e também um dos temas mais cheios de mito. Não se trata de dieta da moda, e sim de um padrão que você consegue manter: variedade, porções conscientes e menos ultra processados açucarados.</p>
<p>Conteúdo educativo; plano alimentar individual é com nutricionista/médico.</p>
<h2>O que costuma funcionar na prática</h2>
<ul>
<li>Priorizar vegetais, proteínas e fibras no prato.</li>
<li>Distribuir carboidratos ao longo do dia, conforme orientação.</li>
<li>Reduzir bebidas açucaradas e “beliscos” sem perceber.</li>
<li>Planejar lanches para evitar hipoglicemia se você usa medicação de risco.</li>
</ul>
<h2>Carboidrato não é vilão absoluto</h2>
<p>Qualidade, quantidade e contexto (o que mais tem no prato, o que você fez de atividade) importam mais do que terrorismo nutricional. Medir a resposta glicêmica — com fita ou <a href="/sensor-de-glicose-como-funciona/">sensor</a> — ensina o seu corpo.</p>
<h2>Jejum e dietas da moda</h2>
<p>Antes de jejum intermitente ou low carb extremo, leia <a href="/jejum-intermitente-e-diabetes-e-permitido-ou-nao/">jejum e diabetes</a> e converse com a equipe. O melhor plano é o que você segue sem se machucar.</p>
<h2>Receitas e rotina</h2>
<p>No hub <a href="/receitas/">Receitas</a> você encontra ideias diets. Para o quadro geral da condição, volte a <a href="/diabetes-tipo-2-o-que-e-e-como-e-feito-diagnostico/">diagnóstico do tipo 2</a> e ao acompanhamento pela <a href="/o-que-e-hba1c-hemoglobina-glicada/">HbA1c</a>.</p>
HTML
			. ccd_seo_editorial_links_html(
				array(
					'jejum-intermitente-e-diabetes-e-permitido-ou-nao',
					'sensor-de-glicose-como-funciona',
					'o-que-e-hba1c-hemoglobina-glicada',
					'diabetes-tipo-2-tem-cura-saiba-mais-sobre-os-tratamentos-disponiveis',
				)
			),
		),
		'sensor-de-glicose-como-funciona' => array(
			'title'      => 'Sensor de glicose: como funciona e para quem faz sentido',
			'excerpt'    => 'Sensor de glicose (CGM/FGM): como funciona, o que mostra e como usar os dados no dia a dia com diabetes.',
			'focus'      => 'sensor de glicose',
			'categories' => array( 'diabetes' ),
			'content'    => <<<'HTML'
<p>O sensor de glicose mede a glicose no líquido intersticial e mostra tendas e setas de direção — uma mudança enorme em relação à “foto” da ponta do dedo. Há modelos de monitoramento contínuo (CGM) e flash; a indicação e o acesso (SUS/plano/particular) variam.</p>
<h2>O que o sensor mostra bem</h2>
<ul>
<li>Tendência (subindo, estável, caindo).</li>
<li>Padrões após refeições, exercício e noite.</li>
<li>Alertas de hipo/hiper em vários modelos.</li>
</ul>
<h2>Limitações importantes</h2>
<p>Há atraso em relação ao sangue capilar em mudanças rápidas. Em sintomas de hipoglicemia ou decisões críticas, o médico pode pedir confirmação com glicosímetro. Calibração e tempo de uso seguem o manual de cada marca.</p>
<h2>Como usar os dados</h2>
<p>Olhe além do número isolado: tempo no alvo, picos pós-prandiais e madrugada. Leve relatórios à consulta. Combine com a frequência de checagens em <a href="/quantas-vezes-por-dia-devo-medir-minha-glicemia/">medir a glicemia</a> e com a prevenção de <a href="/hipoglicemia/">hipoglicemia</a>.</p>
<p>No médio prazo, o sensor não substitui a <a href="/o-que-e-hba1c-hemoglobina-glicada/">HbA1c</a> — os dois se complementam.</p>
HTML
			. ccd_seo_editorial_links_html(
				array(
					'quantas-vezes-por-dia-devo-medir-minha-glicemia',
					'hipoglicemia',
					'o-que-e-hba1c-hemoglobina-glicada',
					'alimentacao-e-diabetes-tipo-2',
				)
			),
		),
		'o-que-e-hba1c-hemoglobina-glicada' => array(
			'title'      => 'O que é HbA1c (hemoglobina glicada) e por que ela importa',
			'excerpt'    => 'HbA1c (hemoglobina glicada): o que mede, com que frequência repetir e como usar o resultado no cuidado do diabetes.',
			'focus'      => 'HbA1c hemoglobina glicada',
			'categories' => array( 'diabetes', 'diabetes-tipo-1-e-tipo-2' ),
			'content'    => <<<'HTML'
<p>A HbA1c (hemoglobina glicada) estima a exposição média à glicose nas últimas ~8–12 semanas. É um dos exames mais usados para diagnosticar, acompanhar e ajustar o tratamento do diabetes — sempre interpretado pelo médico, junto com sintomas e outras medidas.</p>
<h2>O que o número representa</h2>
<p>Quanto mais tempo a glicose fica alta, maior tende a ser a HbA1c. Metas individuais variam (idade, comorbidades, risco de hipoglicemia). Por isso “copiar a meta do vizinho” não funciona.</p>
<h2>Com que frequência fazer</h2>
<p>Em muitos adultos estáveis, a cada 3 meses; em quem mudou tratamento ou está fora da meta, o intervalo pode ser menor. Quem está muito estável há tempo às vezes espaça sob orientação.</p>
<h2>HbA1c x sensor x ponta do dedo</h2>
<p>A HbA1c é média de longo prazo. O <a href="/sensor-de-glicose-como-funciona/">sensor</a> e a fita mostram o agora e os padrões do dia. Os três se conversam — nenhum sozinho conta a história completa. Veja também <a href="/quantas-vezes-por-dia-devo-medir-minha-glicemia/">frequência de medições</a>.</p>
<h2>Quando a HbA1c “mente”</h2>
<p>Anemias, hemoglobinopatias, transfusão e gravidez podem alterar a leitura. Só o laboratório/médico contextualiza.</p>
<p>Se você está entendendo o diagnóstico, volte a <a href="/diabetes-tipo-2-o-que-e-e-como-e-feito-diagnostico/">diabetes tipo 2 e diagnóstico</a>.</p>
HTML
			. ccd_seo_editorial_links_html(
				array(
					'diabetes-tipo-2-o-que-e-e-como-e-feito-diagnostico',
					'sensor-de-glicose-como-funciona',
					'alimentacao-e-diabetes-tipo-2',
					'metade-dos-diabeticos-morre-devido-a-infarto-ou-avc-mas-apenas-3-temem-essa-complicacao',
				)
			),
		),
	);
}

/**
 * Links curatoriais por hub de categoria.
 *
 * @return array<string, string[]>
 */
function ccd_seo_editorial_hub_slugs() {
	return array(
		'diabetes' => array(
			'diabetes-tipo-2-o-que-e-e-como-e-feito-diagnostico',
			'diabetes-tipo-2-tem-cura-saiba-mais-sobre-os-tratamentos-disponiveis',
			'alimentacao-e-diabetes-tipo-2',
			'o-que-e-hba1c-hemoglobina-glicada',
			'sensor-de-glicose-como-funciona',
			'hipoglicemia',
		),
		'alimentacao' => array(
			'alimentacao-e-diabetes-tipo-2',
			'jejum-intermitente-e-diabetes-e-permitido-ou-nao',
			'o-que-e-hba1c-hemoglobina-glicada',
		),
		'receitas' => array(
			'alimentacao-e-diabetes-tipo-2',
			'jejum-intermitente-e-diabetes-e-permitido-ou-nao',
		),
		'diabetes-tipo-1-e-tipo-2' => array(
			'diabetes-tipo-2-o-que-e-e-como-e-feito-diagnostico',
			'o-que-e-hba1c-hemoglobina-glicada',
			'hipoglicemia',
		),
	);
}
