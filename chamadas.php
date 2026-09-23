<?php
session_start();

/*
|--------------------------------------------------------------------------
| AUTENTICAÇÃO
|--------------------------------------------------------------------------
| Mantém a proteção da página para professores.
| Caso seu sistema use outro mecanismo de login, ajuste apenas esta parte.
*/
if (!isset($_SESSION['usuario_id']) || ($_SESSION['tipo'] ?? '') != 'professor') {
    header("Location: ../login.php");
    exit;
}

$professor_id = $_SESSION['usuario_id'];
$nome = $_SESSION['nome'] ?? '';

/*
|--------------------------------------------------------------------------
| ARQUIVO JSON
|--------------------------------------------------------------------------
| Ajuste este caminho caso o seu JSON esteja em outra pasta.
*/
$json_file = __DIR__ . '/dados/infrequencias.json';

/*
|--------------------------------------------------------------------------
| VARIÁVEIS
|--------------------------------------------------------------------------
*/
$dados_json = [];
$infrequencias = [];
$turmas = [];
$alunos_por_turma = [];
$matriz_presencas = [];
$dias = [];
$turma_info = null;

$erro_json = '';

/*
|--------------------------------------------------------------------------
| CARREGAR JSON
|--------------------------------------------------------------------------
*/
if (!file_exists($json_file)) {

    $erro_json = "O arquivo JSON não foi encontrado: " . $json_file;

} else {

    $conteudo_json = file_get_contents($json_file);

    if ($conteudo_json === false) {

        $erro_json = "Não foi possível ler o arquivo JSON.";

    } else {

        $dados_json = json_decode($conteudo_json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {

            $erro_json = "Erro ao interpretar o JSON: " . json_last_error_msg();

            $dados_json = [];

        } else {

            /*
            |--------------------------------------------------------------------------
            | PEGAR OS REGISTROS
            |--------------------------------------------------------------------------
            */
            if (isset($dados_json['infrequencias']) && is_array($dados_json['infrequencias'])) {
                $infrequencias = $dados_json['infrequencias'];
            }

            /*
            |--------------------------------------------------------------------------
            | CONSTRUIR LISTA DE TURMAS A PARTIR DO JSON
            |--------------------------------------------------------------------------
            */
            foreach ($infrequencias as $registro) {

                if (!isset($registro['turma_id'])) {
                    continue;
                }

                $turma_id = (string) $registro['turma_id'];

                if (!isset($turmas[$turma_id])) {

                    $turmas[$turma_id] = [
                        'id' => $registro['turma_id'],
                        'curso_nome' => $registro['curso_nome'] ?? '',
                        'turma_nome' => $registro['turma_nome'] ?? '',
                        'ano' => $registro['ano'] ?? '',
                        'total_alunos' => 0
                    ];
                }

                /*
                |--------------------------------------------------------------------------
                | ALUNOS DA TURMA
                |--------------------------------------------------------------------------
                */
                if (isset($registro['aluno_id'])) {

                    $aluno_id = (string) $registro['aluno_id'];

                    if (!isset($alunos_por_turma[$turma_id])) {
                        $alunos_por_turma[$turma_id] = [];
                    }

                    if (!isset($alunos_por_turma[$turma_id][$aluno_id])) {

                        $alunos_por_turma[$turma_id][$aluno_id] = [
                            'id' => $registro['aluno_id'],
                            'nome' => $registro['aluno_nome'] ?? 'Aluno sem nome'
                        ];
                    }
                }
            }

            /*
            |--------------------------------------------------------------------------
            | CONTAR ALUNOS
            |--------------------------------------------------------------------------
            */
            foreach ($turmas as $turma_id => &$turma) {

                $turma['total_alunos'] = isset($alunos_por_turma[$turma_id])
                    ? count($alunos_por_turma[$turma_id])
                    : 0;
            }

            unset($turma);

            /*
            |--------------------------------------------------------------------------
            | ORDENAR TURMAS
            |--------------------------------------------------------------------------
            */
            uasort($turmas, function ($a, $b) {

                $curso_a = mb_strtolower($a['curso_nome'] ?? '', 'UTF-8');
                $curso_b = mb_strtolower($b['curso_nome'] ?? '', 'UTF-8');

                if ($curso_a === $curso_b) {
                    return strcmp(
                        $a['ano'] ?? '',
                        $b['ano'] ?? ''
                    );
                }

                return strcmp($curso_a, $curso_b);
            });
        }
    }
}

$total_turmas = count($turmas);

/*
|--------------------------------------------------------------------------
| PARÂMETROS DE BUSCA
|--------------------------------------------------------------------------
*/
$turma_busca = $_GET['turma'] ?? '';

$tipo_periodo = $_GET['tipo_periodo'] ?? 'periodo';

$data_inicio = $_GET['data_inicio'] ?? '';
$data_fim = $_GET['data_fim'] ?? '';

$ano_busca = $_GET['ano'] ?? date('Y');

$mes_busca = $_GET['mes'] ?? '';

/*
|--------------------------------------------------------------------------
| DESCRIÇÃO DO PERÍODO
|--------------------------------------------------------------------------
*/
$periodo_descricao = '';

/*
|--------------------------------------------------------------------------
| SE UMA TURMA FOI SELECIONADA
|--------------------------------------------------------------------------
*/
if ($turma_busca && empty($erro_json)) {

    $turma_id_string = (string) $turma_busca;

    /*
    |--------------------------------------------------------------------------
    | LOCALIZAR TURMA
    |--------------------------------------------------------------------------
    */
    if (isset($turmas[$turma_id_string])) {

        $turma_info = $turmas[$turma_id_string];

    } else {

        /*
        | Caso o ID venha como inteiro/string diferente,
        | procura manualmente.
        */
        foreach ($turmas as $turma) {

            if ((string) $turma['id'] === $turma_id_string) {

                $turma_info = $turma;
                break;
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | LISTA DE ALUNOS DA TURMA
    |--------------------------------------------------------------------------
    */
    $alunos = [];

    if (isset($alunos_por_turma[$turma_id_string])) {

        $alunos = array_values($alunos_por_turma[$turma_id_string]);

        usort($alunos, function ($a, $b) {

            return strcasecmp(
                $a['nome'] ?? '',
                $b['nome'] ?? ''
            );
        });
    }

    /*
    |--------------------------------------------------------------------------
    | FILTRAR REGISTROS DO JSON
    |--------------------------------------------------------------------------
    */
    $registros_filtrados = [];

    foreach ($infrequencias as $registro) {

        /*
        | Somente registros da turma selecionada
        */
        if (
            !isset($registro['turma_id']) ||
            (string) $registro['turma_id'] !== $turma_id_string
        ) {
            continue;
        }

        /*
        |--------------------------------------------------------------------------
        | FILTRO POR PERÍODO
        |--------------------------------------------------------------------------
        */
        if ($tipo_periodo === 'periodo') {

            if ($data_inicio && $data_fim) {

                $data_registro = $registro['data'] ?? '';

                if ($data_registro < $data_inicio || $data_registro > $data_fim) {
                    continue;
                }

                $periodo_descricao =
                    "de " .
                    date('d/m/Y', strtotime($data_inicio)) .
                    " até " .
                    date('d/m/Y', strtotime($data_fim));
            }
        }

        /*
        |--------------------------------------------------------------------------
        | FILTRO POR MÊS
        |--------------------------------------------------------------------------
        */
        elseif ($tipo_periodo === 'mes') {

            if ($mes_busca) {

                $data_registro = $registro['data'] ?? '';

                if (
                    date('m', strtotime($data_registro)) !== str_pad($mes_busca, 2, '0', STR_PAD_LEFT)
                    ||
                    date('Y', strtotime($data_registro)) != $ano_busca
                ) {
                    continue;
                }

                $meses = [
                    '01' => 'Janeiro',
                    '02' => 'Fevereiro',
                    '03' => 'Março',
                    '04' => 'Abril',
                    '05' => 'Maio',
                    '06' => 'Junho',
                    '07' => 'Julho',
                    '08' => 'Agosto',
                    '09' => 'Setembro',
                    '10' => 'Outubro',
                    '11' => 'Novembro',
                    '12' => 'Dezembro'
                ];

                $mes_formatado = str_pad(
                    $mes_busca,
                    2,
                    '0',
                    STR_PAD_LEFT
                );

                $periodo_descricao =
                    ($meses[$mes_formatado] ?? $mes_formatado)
                    . "/" .
                    $ano_busca;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | FILTRO POR ANO
        |--------------------------------------------------------------------------
        */
        elseif ($tipo_periodo === 'ano') {

            if ($ano_busca) {

                $data_registro = $registro['data'] ?? '';

                if (
                    date('Y', strtotime($data_registro)) != $ano_busca
                ) {
                    continue;
                }

                $periodo_descricao =
                    "ano " . $ano_busca;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | REGISTRO PASSOU PELOS FILTROS
        |--------------------------------------------------------------------------
        */
        $registros_filtrados[] = $registro;
    }

    /*
    |--------------------------------------------------------------------------
    | SE NÃO HOUVE FILTRO DE DATA, PEGAR TODOS OS REGISTROS DA TURMA
    |--------------------------------------------------------------------------
    */
    if ($tipo_periodo === 'periodo' && (!$data_inicio || !$data_fim)) {
        $periodo_descricao = '';
    }

    /*
    |--------------------------------------------------------------------------
    | MONTAR LISTA DE DATAS
    |--------------------------------------------------------------------------
    */
    $dias_unicos = [];

    foreach ($registros_filtrados as $registro) {

        if (!empty($registro['data'])) {
            $dias_unicos[$registro['data']] = true;
        }
    }

    $dias = array_keys($dias_unicos);

    sort($dias);

    /*
    |--------------------------------------------------------------------------
    | MONTAR MATRIZ
    |--------------------------------------------------------------------------
    |
    | matriz_presencas[aluno_id][data] = status
    |
    | O JSON enviado contém "falta".
    |--------------------------------------------------------------------------
    */
    foreach ($registros_filtrados as $registro) {

        if (
            !isset($registro['aluno_id']) ||
            !isset($registro['data'])
        ) {
            continue;
        }

        $aluno_id = (string) $registro['aluno_id'];
        $data = $registro['data'];

        $status = strtolower(
            trim($registro['status'] ?? '')
        );

        $matriz_presencas[$aluno_id][$data] = $status;
    }
}

/*
|--------------------------------------------------------------------------
| FUNÇÕES AUXILIARES
|--------------------------------------------------------------------------
*/

/*
| Escape HTML
*/
function e($valor)
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
}

/*
| Dias da semana em português
*/
function diaSemanaPt($data)
{
    $dias = [
        'Sun' => 'Dom',
        'Mon' => 'Seg',
        'Tue' => 'Ter',
        'Wed' => 'Qua',
        'Thu' => 'Qui',
        'Fri' => 'Sex',
        'Sat' => 'Sáb'
    ];

    $dia = date('D', strtotime($data));

    return $dias[$dia] ?? $dia;
}

?>

<!DOCTYPE html>

<html lang="pt-BR">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1.0"

>

<title>Buscar Chamadas - Professor</title>

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
>

<style>

body{
    margin:0;
    font-family:Arial, sans-serif;
    background:url('../img/fundo.jpg') no-repeat center center fixed;
    background-size:cover;
}

body::before{
    content:"";
    position:fixed;
    inset:0;
    background:rgba(0,64,128,0.6);
    z-index:-1;
}

.container{
    max-width:1200px;
    margin:30px auto;
    background:#fff;
    padding:25px;
    border-radius:12px;
    position:relative;
    z-index:1;
    box-sizing:border-box;
}

.topo{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:30px;
    flex-wrap:wrap;
    gap:15px;
}

.topo h2{
    margin:0;
    color:#333;
}

.btn-voltar{
    background:#004080;
    color:#fff;
    padding:8px 14px;
    border-radius:6px;
    text-decoration:none;
    font-size:14px;
}

.btn-voltar:hover{
    background:#003366;
}

/* Filtros */

.filtros{
    background:#f8f9fa;
    padding:20px;
    border-radius:8px;
    margin-bottom:30px;
    display:flex;
    flex-wrap:wrap;
    gap:15px;
    align-items:flex-end;
}

.filtro-group{
    flex:1;
    min-width:150px;
}

.filtro-group label{
    display:block;
    margin-bottom:5px;
    font-weight:bold;
    color:#004080;
    font-size:14px;
}

.filtro-group select,
.filtro-group input{
    width:100%;
    padding:8px;
    border:1px solid #ced4da;
    border-radius:4px;
    font-size:14px;
    box-sizing:border-box;
}

/* Radio */

.filtro-radio-group{
    display:flex;
    flex-direction:row;
    gap:20px;
    align-items:center;
    flex-wrap:wrap;
    margin-top:5px;
}

.filtro-radio-item{
    display:flex;
    align-items:center;
    gap:5px;
}

.filtro-radio-item label{
    margin-bottom:0;
    font-weight:normal;
    color:#333;
    cursor:pointer;
}

/* Botões */

.acoes-filtro{
    display:flex;
    gap:10px;
    align-items:center;
}

.btn-buscar{
    background:#28a745;
    color:#fff;
    padding:8px 20px;
    border:none;
    border-radius:4px;
    cursor:pointer;
    height:38px;
    font-weight:bold;
    display:inline-flex;
    align-items:center;
    gap:5px;
}

.btn-buscar:hover{
    background:#218838;
}

.btn-buscar:disabled{
    opacity:.6;
    cursor:not-allowed;
}

.btn-limpar{
    background:#6c757d;
    color:#fff;
    padding:8px 20px;
    border:none;
    border-radius:4px;
    cursor:pointer;
    height:38px;
    text-decoration:none;
    display:inline-flex;
    align-items:center;
    gap:5px;
    box-sizing:border-box;
}

.btn-limpar:hover{
    background:#5a6268;
}

/* Informações */

.info-busca{
    background:#e7f3ff;
    border-left:4px solid #004080;
    padding:15px;
    border-radius:4px;
    margin-bottom:20px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    flex-wrap:wrap;
    gap:15px;
}

.info-busca h3{
    margin:0;
    color:#004080;
    font-size:18px;
}

.info-busca .total-dias{
    background:#004080;
    color:#fff;
    padding:5px 15px;
    border-radius:20px;
    font-size:14px;
}

/* Tabela */

.tabela-container{
    overflow-x:auto;
    margin-top:20px;
    border-radius:8px;
    border:1px solid #dee2e6;
    max-height:500px;
    overflow-y:auto;
    position:relative;
    box-shadow:0 2px 10px rgba(0,0,0,0.1);
}

table{
    width:100%;
    border-collapse:collapse;
    background:#fff;
    font-size:13px;
    min-width:100%;
}

th{
    background:#004080;
    color:#fff;
    padding:8px 4px;
    text-align:center;
    font-size:12px;
    position:sticky;
    top:0;
    z-index:10;
    white-space:nowrap;
    min-width:45px;
    cursor:pointer;
    transition:background 0.2s;
}

th:hover{
    background:#0055aa;
}

th:first-child{
    position:sticky;
    left:0;
    z-index:20;
    background:#004080;
    text-align:left;
    min-width:150px;
    padding-left:10px;
    cursor:default;
}

th:first-child:hover{
    background:#004080;
}

td{
    padding:6px 2px;
    border-bottom:1px solid #dee2e6;
    text-align:center;
    vertical-align:middle;
    font-size:12px;
}

td:first-child{
    position:sticky;
    left:0;
    background:#fff;
    font-weight:bold;
    z-index:5;
    border-right:2px solid #004080;
    text-align:left;
    padding-left:10px;
    min-width:150px;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
}

tr:hover td:first-child{
    background:#f8f9fa;
}

tr:hover td{
    background:#f8f9fa;
}

.coluna-destaque{
    background-color:#e6f3ff !important;
}

th.coluna-destaque{
    background-color:#0055aa !important;
}

/* Status */

.status-presenca{
    display:inline-block;
    width:24px;
    height:24px;
    line-height:24px;
    border-radius:50%;
    text-align:center;
    font-weight:bold;
    font-size:12px;
}

.status-presente{
    background:#d4edda;
    color:#28a745;
}

.status-faltou{
    background:#f8d7da;
    color:#dc3545;
}

.status-sem-registro{
    background:#e9ecef;
    color:#666;
}

.data-header{
    font-weight:bold;
}

.data-header .dia-semana{
    display:block;
    font-size:9px;
    font-weight:normal;
    opacity:0.9;
    text-transform:uppercase;
}

/* Avisos */

.aviso{
    background:#fff3cd;
    border-left:4px solid #ffc107;
    padding:15px;
    border-radius:4px;
    margin-bottom:20px;
    color:#856404;
}

.aviso i{
    margin-right:10px;
    font-size:18px;
}

.aviso-erro{
    background:#f8d7da;
    border-left:4px solid #dc3545;
    color:#721c24;
}

/* Empty */

.empty-state{
    text-align:center;
    padding:60px 20px;
    background:#f8f9fa;
    border-radius:8px;
    color:#666;
}

.empty-state i{
    font-size:48px;
    color:#004080;
    margin-bottom:15px;
    opacity:0.5;
}

.empty-state h3{
    margin:0 0 10px 0;
    color:#333;
}

.empty-state p{
    margin:0;
    font-size:14px;
}

/* Estatísticas */

.resumo-stats{
    display:flex;
    gap:15px;
    margin-top:15px;
    flex-wrap:wrap;
}

.stat-resumo{
    background:#f8f9fa;
    border-radius:8px;
    padding:10px 15px;
    border:1px solid #dee2e6;
    font-size:13px;
}

.stat-resumo strong{
    color:#004080;
    margin-right:5px;
}

/* Legenda */

.legenda{
    display:flex;
    gap:15px;
    flex-wrap:wrap;
    align-items:center;
    margin-top:15px;
    font-size:13px;
}

.legenda-item{
    display:flex;
    align-items:center;
    gap:6px;
}

/* Responsividade */

@media(max-width:768px){

    .container{
        margin:10px;
        padding:15px;
    }

    .filtros{
        flex-direction:column;
    }

    .filtro-group{
        width:100%;
    }

    .filtro-radio-group{
        flex-direction:column;
        align-items:flex-start;
        gap:10px;
    }

    .acoes-filtro{
        width:100%;
        justify-content:flex-start;
        flex-wrap:wrap;
    }

    .info-busca{
        flex-direction:column;
        align-items:flex-start;
    }

    th:first-child{
        min-width:120px;
    }

    td:first-child{
        min-width:120px;
    }
}

</style>

</head>

<body>

<div class="container">

```
<div class="topo">

    <h2>
        <i class="fa-solid fa-search"></i>
        Buscar Chamadas
    </h2>

    <a
        href="painel.php"
        class="btn-voltar"
    >
        <i class="fa-solid fa-arrow-left"></i>
        Voltar
    </a>

</div>

<?php if ($erro_json): ?>

    <div class="aviso aviso-erro">

        <i class="fa-solid fa-circle-exclamation"></i>

        <strong>Erro ao carregar os dados:</strong>

        <?= e($erro_json) ?>

    </div>

<?php endif; ?>


<?php if (!$erro_json && $total_turmas == 0): ?>

    <div class="aviso">

        <i class="fa-solid fa-exclamation-triangle"></i>

        <strong>Nenhuma turma encontrada!</strong>

        Não há dados de turmas no arquivo JSON.

    </div>

<?php endif; ?>


<!-- Formulário -->

<form
    method="GET"
    class="filtros"
>

    <div class="filtro-group">

        <label>

            <i class="fa-solid fa-school"></i>

            Turma:

        </label>

        <select
            name="turma"
            required
            <?= $total_turmas == 0 ? 'disabled' : '' ?>
        >

            <option value="">
                Selecione uma turma
            </option>

            <?php foreach ($turmas as $turma): ?>

                <option
                    value="<?= e($turma['id']) ?>"
                    <?= ((string)$turma_busca === (string)$turma['id']) ? 'selected' : '' ?>
                >

                    <?= e($turma['turma_nome']) ?>

                    <?php if ($turma['total_alunos'] > 0): ?>

                        (<?= e($turma['total_alunos']) ?> alunos)

                    <?php endif; ?>

                </option>

            <?php endforeach; ?>

        </select>

    </div>


    <div class="filtro-group">

        <label>

            <i class="fa-solid fa-calendar"></i>

            Tipo de Período:

        </label>

        <div class="filtro-radio-group">

            <div class="filtro-radio-item">

                <input
                    type="radio"
                    name="tipo_periodo"
                    id="tipo_periodo"
                    value="periodo"
                    <?= $tipo_periodo == 'periodo' ? 'checked' : '' ?>
                    onchange="togglePeriodo()"
                >

                <label for="tipo_periodo">
                    Período
                </label>

            </div>


            <div class="filtro-radio-item">

                <input
                    type="radio"
                    name="tipo_periodo"
                    id="tipo_mes"
                    value="mes"
                    <?= $tipo_periodo == 'mes' ? 'checked' : '' ?>
                    onchange="togglePeriodo()"
                >

                <label for="tipo_mes">
                    Mês
                </label>

            </div>


            <div class="filtro-radio-item">

                <input
                    type="radio"
                    name="tipo_periodo"
                    id="tipo_ano"
                    value="ano"
                    <?= $tipo_periodo == 'ano' ? 'checked' : '' ?>
                    onchange="togglePeriodo()"
                >

                <label for="tipo_ano">
                    Ano
                </label>

            </div>

        </div>

    </div>


    <!-- Período -->

    <div
        class="filtro-group"
        id="periodo-group"
        style="display: <?= $tipo_periodo == 'periodo' ? 'block' : 'none' ?>;"
    >

        <label>

            <i class="fa-solid fa-calendar-alt"></i>

            Data Início:

        </label>

        <input
            type="date"
            name="data_inicio"
            value="<?= e($data_inicio) ?>"
        >


        <label style="margin-top:10px;">

            <i class="fa-solid fa-calendar-alt"></i>

            Data Fim:

        </label>

        <input
            type="date"
            name="data_fim"
            value="<?= e($data_fim) ?>"
        >

    </div>


    <!-- Mês -->

    <div
        class="filtro-group"
        id="mes-group"
        style="display: <?= $tipo_periodo == 'mes' ? 'block' : 'none' ?>;"
    >

        <label>

            <i class="fa-solid fa-calendar-alt"></i>

            Mês:

        </label>

        <select name="mes">

            <option value="">
                Selecione
            </option>

            <?php
            $meses_select = [
                '01' => 'Janeiro',
                '02' => 'Fevereiro',
                '03' => 'Março',
                '04' => 'Abril',
                '05' => 'Maio',
                '06' => 'Junho',
                '07' => 'Julho',
                '08' => 'Agosto',
                '09' => 'Setembro',
                '10' => 'Outubro',
                '11' => 'Novembro',
                '12' => 'Dezembro'
            ];
            ?>

            <?php foreach ($meses_select as $numero => $nome_mes): ?>

                <option
                    value="<?= $numero ?>"
                    <?= $mes_busca == $numero ? 'selected' : '' ?>
                >
                    <?= $nome_mes ?>
                </option>

            <?php endforeach; ?>

        </select>

    </div>


    <!-- Ano -->

    <div
        class="filtro-group"
        id="ano-group"
        style="display: <?= $tipo_periodo == 'ano' ? 'block' : 'none' ?>;"
    >

        <label>

            <i class="fa-solid fa-calendar"></i>

            Ano:

        </label>

        <select name="ano">

            <?php

            /*
            | Cria anos com base no ano atual e nos anos
            | encontrados no JSON.
            */

            $anos_json = [];

            foreach ($infrequencias as $registro) {

                if (!empty($registro['data'])) {
                    $anos_json[date('Y', strtotime($registro['data']))] = true;
                }
            }

            $anos = array_keys($anos_json);

            $anos[] = date('Y');
            $anos[] = date('Y') - 1;
            $anos[] = date('Y') - 2;
            $anos[] = date('Y') - 3;

            $anos = array_unique($anos);

            rsort($anos);

            ?>

            <?php foreach ($anos as $a): ?>

                <option
                    value="<?= e($a) ?>"
                    <?= $ano_busca == $a ? 'selected' : '' ?>
                >
                    <?= e($a) ?>
                </option>

            <?php endforeach; ?>

        </select>

    </div>


    <!-- Ações -->

    <div class="acoes-filtro">

        <button
            type="submit"
            class="btn-buscar"
            <?= $total_turmas == 0 ? 'disabled' : '' ?>
        >

            <i class="fa-solid fa-search"></i>

            Buscar

        </button>


        <a
            href="chamada.php"
            class="btn-limpar"
        >

            <i class="fa-solid fa-eraser"></i>

            Limpar

        </a>

    </div>

</form>


<?php if ($turma_busca && $turma_info && count($dias) > 0): ?>

    <!-- Informações -->

    <div class="info-busca">

        <h3>

            <i class="fa-solid fa-school"></i>

            <?= e($turma_info['turma_nome']) ?>

        </h3>


        <div>

            <span class="total-dias">

                <i class="fa-solid fa-calendar"></i>

                <?= count($dias) ?> dia(s)

                <?php if ($periodo_descricao): ?>

                    - <?= e($periodo_descricao) ?>

                <?php endif; ?>

            </span>

        </div>

    </div>


    <!-- Tabela -->

    <div class="tabela-container">

        <table id="tabela-chamadas">

            <thead>

                <tr>

                    <th>
                        Aluno
                    </th>

                    <?php foreach ($dias as $index => $dia): ?>

                        <th
                            class="data-header"
                            onclick="destacarColuna(this)"
                            data-coluna="<?= $index ?>"
                            title="Clique para destacar esta coluna"
                        >

                            <?= date('d/m', strtotime($dia)) ?>

                            <span class="dia-semana">

                                <?= e(diaSemanaPt($dia)) ?>

                            </span>

                        </th>

                    <?php endforeach; ?>

                </tr>

            </thead>


            <tbody>

                <?php foreach ($alunos as $aluno): ?>

                    <tr>

                        <td
                            title="<?= e($aluno['nome']) ?>"
                        >

                            <?= e($aluno['nome']) ?>

                        </td>


                        <?php foreach ($dias as $dia): ?>

                            <td>

                                <?php

                                $aluno_id = (string) $aluno['id'];

                                $status =
                                    $matriz_presencas[$aluno_id][$dia]
                                    ?? null;

                                ?>


                                <?php if ($status === 'falta'): ?>

                                    <span
                                        class="status-presenca status-faltou"
                                        title="Faltou"
                                    >

                                        <i class="fa-solid fa-xmark"></i>

                                    </span>


                                <?php elseif (
                                    $status === 'presente'
                                    ||
                                    $status === 'presença'
                                    ||
                                    $status === 'presenca'
                                ): ?>

                                    <span
                                        class="status-presenca status-presente"
                                        title="Presente"
                                    >

                                        <i class="fa-solid fa-check"></i>

                                    </span>


                                <?php else: ?>

                                    <span
                                        class="status-presenca status-sem-registro"
                                        title="Sem registro no JSON"
                                    >
                                        -
                                    </span>

                                <?php endif; ?>

                            </td>

                        <?php endforeach; ?>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    </div>


    <!-- Resumo -->

    <div class="resumo-stats">

        <div class="stat-resumo">

            <strong>
                Total de alunos:
            </strong>

            <?= count($alunos) ?>

        </div>


        <div class="stat-resumo">

            <strong>
                Total de dias:
            </strong>

            <?= count($dias) ?>

        </div>


        <div class="stat-resumo">

            <strong>
                Registros de falta:
            </strong>

            <?= count($registros_filtrados) ?>

        </div>


        <div class="stat-resumo">

            <strong>
                Clique nas datas
            </strong>

            para destacar a coluna

        </div>

    </div>


    <!-- Legenda -->

    <div class="legenda">

        <div class="legenda-item">

            <span class="status-presenca status-presente">
                <i class="fa-solid fa-check"></i>
            </span>

            Presente

        </div>


        <div class="legenda-item">

            <span class="status-presenca status-faltou">
                <i class="fa-solid fa-xmark"></i>
            </span>

            Falta

        </div>


        <div class="legenda-item">

            <span class="status-presenca status-sem-registro">
                -
            </span>

            Sem registro

        </div>

    </div>


<?php elseif ($turma_busca && $turma_info && count($dias) == 0): ?>


    <!-- Nenhum resultado -->

    <div class="empty-state">

        <i class="fa-solid fa-calendar-xmark"></i>

        <h3>
            Nenhuma chamada encontrada
        </h3>

        <p>
            Não há registros para a turma e o período selecionados no arquivo JSON.
        </p>

    </div>


<?php elseif ($turma_busca && !$turma_info): ?>


    <div class="empty-state">

        <i class="fa-solid fa-school-circle-xmark"></i>

        <h3>
            Turma não encontrada
        </h3>

        <p>
            A turma selecionada não foi encontrada no arquivo JSON.
        </p>

    </div>


<?php endif; ?>
```

</div>

<script>

/*
|--------------------------------------------------------------------------
| Mostrar/ocultar filtros de período
|--------------------------------------------------------------------------
*/
function togglePeriodo() {

    const selecionado =
        document.querySelector(
            'input[name="tipo_periodo"]:checked'
        );

    if (!selecionado) {
        return;
    }

    const tipo = selecionado.value;

    document.getElementById('periodo-group').style.display =
        tipo === 'periodo' ? 'block' : 'none';

    document.getElementById('mes-group').style.display =
        tipo === 'mes' ? 'block' : 'none';

    document.getElementById('ano-group').style.display =
        tipo === 'ano' ? 'block' : 'none';
}


/*
|--------------------------------------------------------------------------
| Destacar coluna
|--------------------------------------------------------------------------
*/
function destacarColuna(elemento) {

    /*
    | Remove destaque de todas as células
    */
    const todasColunas =
        document.querySelectorAll('th, td');

    todasColunas.forEach(function(celula) {

        celula.classList.remove(
            'coluna-destaque'
        );

    });


    /*
    | Descobrir a posição real da coluna
    */
    const cabecalhos =
        document.querySelectorAll(
            'thead tr th'
        );

    let colunaReal = 0;

    for (
        let i = 0;
        i < cabecalhos.length;
        i++
    ) {

        if (cabecalhos[i] === elemento) {

            colunaReal = i;

            break;
        }
    }


    /*
    | Destacar cabeçalho
    */
    if (cabecalhos[colunaReal]) {

        cabecalhos[colunaReal]
            .classList.add(
                'coluna-destaque'
            );
    }


    /*
    | Destacar células da coluna
    */
    const linhas =
        document.querySelectorAll(
            'tbody tr'
        );

    linhas.forEach(function(linha) {

        const celulas =
            linha.querySelectorAll('td');

        if (celulas[colunaReal]) {

            celulas[colunaReal]
                .classList.add(
                    'coluna-destaque'
                );
        }

    });

}


/*
|--------------------------------------------------------------------------
| ESC remove destaque
|--------------------------------------------------------------------------
*/
document.addEventListener(
    'keydown',
    function(e) {

        if (e.key === 'Escape') {

            const todasColunas =
                document.querySelectorAll(
                    'th, td'
                );

            todasColunas.forEach(
                function(celula) {

                    celula.classList.remove(
                        'coluna-destaque'
                    );

                }
            );
        }

    }
);


/*
|--------------------------------------------------------------------------
| Inicializar período
|--------------------------------------------------------------------------
*/
document.addEventListener(
    'DOMContentLoaded',
    function() {

        togglePeriodo();

    }
);

</script>

</body>

</html>
