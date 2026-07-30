<?php
/**
 * Relatório de Fila - 
 * Banco: qstatslite e asterisk
 * PHP 5.4+ | MySQL 5.5+
 */

// Define o fuso horário padrão para evitar os warnings
date_default_timezone_set('America/Sao_Paulo');

// Ocultar erros em produção (Mude para E_ALL e 1 se precisar depurar)
error_reporting(0);
ini_set('display_errors', 0);

// ─── Leitura de credenciais ───────────────────────────────────────────────────
function getIssabelConf() {
    $conf = array('mysqlrootpwd' => '');
    if (!file_exists('/etc/issabel.conf')) return $conf;
    $lines = file('/etc/issabel.conf', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') === false) continue;
        list($key, $val) = explode('=', $line, 2);
        $conf[trim($key)] = trim($val);
    }
    return $conf;
}

$issabelConf = getIssabelConf();
$dbHost   = 'localhost';
$dbUser   = 'root';
$dbPass   = isset($issabelConf['mysqlrootpwd']) ? $issabelConf['mysqlrootpwd'] : '';
$dbName   = 'qstatslite';

// ─── Conexão MySQL ────────────────────────────────────────────────────────────
$conn = @mysql_connect($dbHost, $dbUser, $dbPass);
if (!$conn) {
    die('<div style="padding:40px;font-family:monospace;color:red;">Erro ao conectar ao MySQL: ' . mysql_error() . '</div>');
}
mysql_select_db($dbName, $conn) or die('<div style="padding:40px;font-family:monospace;color:red;">Banco qstatslite não encontrado.</div>');
mysql_query("SET NAMES 'utf8'", $conn);

// ─── Parâmetros de filtro ─────────────────────────────────────────────────────
$hoje       = date('Y-m-d');
$dataInicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : $hoje;
$dataFim    = isset($_GET['data_fim'])    ? $_GET['data_fim']    : $hoje;
$export     = isset($_GET['export'])      ? $_GET['export']      : '';

// Tratamento para múltiplas filas
$filaFiltro = isset($_GET['fila']) ? (is_array($_GET['fila']) ? $_GET['fila'] : array($_GET['fila'])) : array();

$dataInicioEsc = mysql_real_escape_string($dataInicio, $conn);
$dataFimEsc    = mysql_real_escape_string($dataFim, $conn);

// ─── Mapeamentos das tabelas de dimensão ─────────────────────────────────────
function buildMap($query, $keyCol, $valCol, $conn) {
    $map = array();
    $r = mysql_query($query, $conn);
    if ($r) while ($row = mysql_fetch_assoc($r)) $map[$row[$keyCol]] = $row[$valCol];
    return $map;
}
$agentMap = buildMap("SELECT agent_id, agent FROM qagent", 'agent_id', 'agent', $conn);
$eventMap = buildMap("SELECT event_id, event FROM qevent", 'event_id', 'event', $conn);
$qnameMap = buildMap("SELECT qname_id, queue FROM qname", 'qname_id', 'queue', $conn);

// ─── Mapeamento de Ramais (Banco Asterisk) ────────────────────────────────────
$ramalMap = array();
$rDevices = @mysql_query("SELECT user, description FROM asterisk.devices", $conn);
if ($rDevices) {
    while ($row = mysql_fetch_assoc($rDevices)) {
        // Usa o nome (description) em maiúsculo como chave, e o número (user) como valor
        $descKey = strtoupper(trim($row['description']));
        if ($descKey != '') {
            $ramalMap[$descKey] = $row['user'];
        }
    }
}

// ─── Lista de filas disponíveis ───────────────────────────────────────────────
$filas = array();
$rFilas = mysql_query("SELECT qname_id, queue FROM qname WHERE queue != 'NONE' ORDER BY queue", $conn);
if ($rFilas) while ($row = mysql_fetch_assoc($rFilas)) $filas[] = $row;

// ─── Condição WHERE base (Múltiplas Filas) ────────────────────────────────────
$whereCond  = "WHERE DATE(qs.datetime) BETWEEN '$dataInicioEsc' AND '$dataFimEsc'";
$filasIn = array();
foreach($filaFiltro as $f) {
    $fClean = mysql_real_escape_string($f, $conn);
    if($fClean != '') $filasIn[] = "'$fClean'";
}
if (count($filasIn) > 0) {
    $whereCond .= " AND qs.qname IN (" . implode(',', $filasIn) . ")";
}

// ─── Nomes das filas para exibição ────────────────────────────────────────────
$nomesFilasSelecionadas = array();
foreach($filaFiltro as $f) {
    if($f != '') $nomesFilasSelecionadas[] = isset($qnameMap[$f]) ? $qnameMap[$f] : $f;
}
$textoFilasExibicao = count($nomesFilasSelecionadas) > 0 ? implode(', ', $nomesFilasSelecionadas) : 'Todas as Filas';


// ─── QUERY PRINCIPAL – Detalhes de chamadas ───────────────────────────────────
$sqlDetalhe = "
SELECT
    qs.queue_stats_id,
    qs.uniqueid,
    qs.datetime,
    qs.qname   AS qname_id,
    qs.qagent  AS agent_id,
    qs.qevent  AS event_id,
    qs.info1,
    qs.info2,
    qs.info3
FROM queue_stats qs
$whereCond
ORDER BY qs.datetime ASC, qs.uniqueid ASC, qs.queue_stats_id ASC
";

$rDetalhe = mysql_query($sqlDetalhe, $conn);

// ─── Processar chamadas agrupando por uniqueid ────────────────────────────────
$chamadas = array();
$agentRingNoAnswer = array(); // Guarda quem perdeu a chamada (ring no answer)

if ($rDetalhe) {
    while ($row = mysql_fetch_assoc($rDetalhe)) {
        $uid   = $row['uniqueid'];
        $ev    = isset($eventMap[$row['event_id']]) ? $eventMap[$row['event_id']] : $row['event_id'];
        $agent = isset($agentMap[$row['agent_id']]) ? $agentMap[$row['agent_id']] : $row['agent_id'];
        $fila  = isset($qnameMap[$row['qname_id']]) ? $qnameMap[$row['qname_id']] : $row['qname_id'];

        // Contabiliza se tocou no ramal do agente e ele não atendeu
        if ($ev == 'RINGNOANSWER' && $agent != 'NONE' && $agent != '') {
            if (!isset($agentRingNoAnswer[$agent])) $agentRingNoAnswer[$agent] = 0;
            $agentRingNoAnswer[$agent]++;
        }

        if (!isset($chamadas[$uid])) {
            $chamadas[$uid] = array(
                'uniqueid'       => $uid,
                'datetime'       => $row['datetime'],
                'fila'           => $fila,
                'numero'         => '',
                'agente'         => '',
                'ramal'          => '', // Adicionado campo de ramal
                'status'         => 'ABANDONADA',
                'tempo_espera'   => 0,
                'tempo_falando'  => 0,
                'quem_desligou'  => '',
                'eventos'        => array()
            );
        }

        $chamadas[$uid]['eventos'][] = array('event' => $ev, 'agent' => $agent, 'info1' => $row['info1'], 'info2' => $row['info2'], 'info3' => $row['info3'], 'dt' => $row['datetime']);

        switch ($ev) {
            case 'ENTERQUEUE':
                if ($row['info2'] != '' && $row['info2'] != 'NONE') $chamadas[$uid]['numero'] = $row['info2'];
                if ($row['info1'] != '' && $row['info1'] != 'NONE') $chamadas[$uid]['numero'] = $row['info1']; 
                $chamadas[$uid]['datetime'] = $row['datetime'];
                break;
            case 'CONNECT':
                $chamadas[$uid]['agente']       = $agent;
                // Busca o ramal associado ao nome do agente
                $descKey = strtoupper(trim($agent));
                $chamadas[$uid]['ramal']        = isset($ramalMap[$descKey]) ? $ramalMap[$descKey] : '—';
                $chamadas[$uid]['status']       = 'ATENDIDA';
                $chamadas[$uid]['tempo_espera'] = intval($row['info1']);
                break;
            case 'COMPLETECALLER':
                $chamadas[$uid]['status']       = 'ATENDIDA';
                $chamadas[$uid]['quem_desligou'] = 'CLIENTE';
                $chamadas[$uid]['tempo_falando'] = intval($row['info2']);
                if ($chamadas[$uid]['tempo_espera'] == 0) $chamadas[$uid]['tempo_espera'] = intval($row['info1']);
                break;
            case 'COMPLETEAGENT':
                $chamadas[$uid]['status']       = 'ATENDIDA';
                $chamadas[$uid]['quem_desligou'] = 'AGENTE';
                $chamadas[$uid]['tempo_falando'] = intval($row['info2']);
                if ($chamadas[$uid]['tempo_espera'] == 0) $chamadas[$uid]['tempo_espera'] = intval($row['info1']);
                break;
            case 'ABANDON':
                $chamadas[$uid]['status']       = 'ABANDONADA';
                $chamadas[$uid]['quem_desligou'] = 'CLIENTE';
                $chamadas[$uid]['tempo_espera'] = intval($row['info3']);
                break;
            case 'EXITWITHTIMEOUT':
                $chamadas[$uid]['status']       = 'TIMEOUT';
                $chamadas[$uid]['tempo_espera'] = intval($row['info3']);
                break;
        }
    }
}
$chamadas = array_values($chamadas);

// ─── MÉTRICAS RESUMO ──────────────────────────────────────────────────────────
$totalChamadas    = count($chamadas);
$totalAtendidas   = 0;
$totalAbandonadas = 0;
$totalTimeout     = 0;
$somaEspera       = 0;
$somaFalando      = 0;
$agentStats       = array(); 

foreach ($chamadas as $c) {
    if ($c['status'] == 'ATENDIDA')   $totalAtendidas++;
    elseif ($c['status'] == 'TIMEOUT') $totalTimeout++;
    else                               $totalAbandonadas++;

    $somaEspera   += $c['tempo_espera'];
    $somaFalando  += $c['tempo_falando'];

    if ($c['status'] == 'ATENDIDA') {
        $ag = $c['agente'];
        if (!isset($agentStats[$ag])) $agentStats[$ag] = array('atendidas' => 0, 'nao_atendidas' => 0, 'tempo' => 0);
        $agentStats[$ag]['atendidas']++;
        $agentStats[$ag]['tempo'] += $c['tempo_falando'];
    }
}

// Injeta as chamadas perdidas (RINGNOANSWER) para cada agente
foreach ($agentRingNoAnswer as $ag => $perdidas) {
    if (!isset($agentStats[$ag])) {
        $agentStats[$ag] = array('atendidas' => 0, 'nao_atendidas' => 0, 'tempo' => 0);
    }
    $agentStats[$ag]['nao_atendidas'] += $perdidas;
}

$pctAtendimento = $totalChamadas > 0 ? round(($totalAtendidas / $totalChamadas) * 100, 1) : 0;
$pctAbandono    = $totalChamadas > 0 ? round(($totalAbandonadas / $totalChamadas) * 100, 1) : 0;
$mediaEspera    = $totalChamadas > 0 ? round($somaEspera / $totalChamadas) : 0;
$mediaFalando   = $totalAtendidas > 0 ? round($somaFalando / $totalAtendidas) : 0;

// ─── EXPORTAÇÃO EXCEL ─────────────────────────────────────────────────────────
if ($export == 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="relatorio_fila_' . $dataInicio . '_' . $dataFim . '.xls"');
    header('Pragma: no-cache');
    echo "\xEF\xBB\xBF"; 
    
    // Cabeçalho com o Ramal inserido
    echo "UniqueID\tData/Hora\tFila\tNúmero\tAgente\tRamal\tStatus\tEspera(s)\tDuração(s)\tQuem Desligou\n";
    
    foreach ($chamadas as $c) {
        echo implode("\t", array(
            $c['uniqueid'],
            $c['datetime'],
            $c['fila'],
            $c['numero'],
            $c['agente'],
            $c['ramal'],
            $c['status'],
            $c['tempo_espera'],
            $c['tempo_falando'],
            $c['quem_desligou']
        )) . "\n";
    }

    // Resumo no final da planilha
    echo "\n"; 
    echo "RESUMO GERAL\n";
    echo "Total de Chamadas:\t" . $totalChamadas . "\n";
    echo "Atendidas:\t" . $totalAtendidas . "\t" . $pctAtendimento . "%\n";
    echo "Abandonadas:\t" . $totalAbandonadas . "\t" . $pctAbandono . "%\n";
    echo "Timeout:\t" . $totalTimeout . "\n";
    echo "Média Espera:\t" . gmdate('i:s', $mediaEspera) . "\n";
    echo "Média Duração:\t" . gmdate('i:s', $mediaFalando) . "\n";
    
    exit;
}

// ─── EXPORTAÇÃO PDF (HTML print-friendly) ────────────────────────────────────
if ($export == 'pdf') {
    header('Content-Type: text/html; charset=UTF-8');
    ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Relatório PDF </title>
<style>
    body{font-family:Arial,sans-serif;font-size:10px;margin:10px;}
    h1{font-size:14px;text-align:center;}
    h2{font-size:12px;margin-top:15px;}
    table{width:100%;border-collapse:collapse;margin-bottom:10px;}
    th{background:#f1f2f6;color:#333;padding:4px;text-align:left;font-size:9px;border-bottom:2px solid #ccc;}
    td{padding:3px 4px;border-bottom:1px solid #ddd;font-size:9px;}
    tr:nth-child(even){background:#f9f9f9;}
    .kpi{display:inline-block;width:100px;border:1px solid #ccc;padding:5px;margin:3px;text-align:center;border-radius:4px;}
    .kpi-val{font-size:16px;font-weight:bold;color:#1a3a5c;}
    .kpi-lbl{font-size:8px;color:#666;}
    @media print{.no-print{display:none;}}
    .badge-atendida{color:green;font-weight:bold;}
    .badge-abandonada{color:red;font-weight:bold;}
    .badge-timeout{color:orange;font-weight:bold;}
</style>
<script>window.onload=function(){window.print();}</script>
</head>
<body>
<div class="no-print" style="padding:10px;background:#eee;margin-bottom:15px;">
    <button onclick="window.print()">🖨️ Imprimir / Salvar PDF</button>
    <button onclick="window.history.back()">← Voltar</button>
</div>
<h1>📊 Relatório de Fila - </h1>
<p style="text-align:center;color:#666;">Período: <?php echo $dataInicio; ?> até <?php echo $dataFim; ?> | Fila(s): <?php echo htmlspecialchars($textoFilasExibicao); ?></p>

<h2>Resumo Geral</h2>
<div>
    <div class="kpi"><div class="kpi-val"><?php echo $totalChamadas; ?></div><div class="kpi-lbl">Total Chamadas</div></div>
    <div class="kpi"><div class="kpi-val" style="color:green;"><?php echo $totalAtendidas; ?></div><div class="kpi-lbl">Atendidas</div></div>
    <div class="kpi"><div class="kpi-val" style="color:red;"><?php echo $totalAbandonadas; ?></div><div class="kpi-lbl">Abandonadas</div></div>
    <div class="kpi"><div class="kpi-val" style="color:orange;"><?php echo $totalTimeout; ?></div><div class="kpi-lbl">Timeout</div></div>
    <div class="kpi"><div class="kpi-val"><?php echo $pctAtendimento; ?>%</div><div class="kpi-lbl">% Atendimento</div></div>
    <div class="kpi"><div class="kpi-val"><?php echo $pctAbandono; ?>%</div><div class="kpi-lbl">% Abandono</div></div>
    <div class="kpi"><div class="kpi-val"><?php echo gmdate('i:s',$mediaEspera); ?></div><div class="kpi-lbl">Média Espera</div></div>
    <div class="kpi"><div class="kpi-val"><?php echo gmdate('i:s',$mediaFalando); ?></div><div class="kpi-lbl">Média Fala</div></div>
</div>

<h2>Estatísticas por Agente</h2>
<table>
    <thead><tr><th>Agente</th><th>Atendidas</th><th>Não Atendidas</th><th>Tempo Total</th><th>Média Fala</th></tr></thead>
    <tbody>
    <?php foreach ($agentStats as $ag => $st): if($ag=='SEM AGENTE' || $ag=='NONE') continue; ?>
    <tr>
        <td><?php echo htmlspecialchars($ag); ?></td>
        <td><?php echo $st['atendidas']; ?></td>
        <td><?php echo $st['nao_atendidas']; ?></td>
        <td><?php echo gmdate('H:i:s',$st['tempo']); ?></td>
        <td><?php echo $st['atendidas']>0 ? gmdate('i:s',round($st['tempo']/$st['atendidas'])) : '00:00'; ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<h2>Detalhamento de Chamadas</h2>
<table>
    <thead><tr><th>#</th><th>Data/Hora</th><th>Fila</th><th>Número</th><th>Agente</th><th>Ramal</th><th>Status</th><th>Espera</th><th>Fala</th><th>Desligou</th></tr></thead>
    <tbody>
    <?php $i=1; foreach ($chamadas as $c): ?>
    <tr>
        <td><?php echo $i++; ?></td>
        <td><?php echo $c['datetime']; ?></td>
        <td><?php echo htmlspecialchars($c['fila']); ?></td>
        <td><?php echo htmlspecialchars($c['numero']); ?></td>
        <td><?php echo htmlspecialchars($c['agente'] ?: '—'); ?></td>
        <td><?php echo htmlspecialchars($c['ramal'] ?: '—'); ?></td>
        <td class="badge-<?php echo strtolower($c['status']); ?>"><?php echo $c['status']; ?></td>
        <td><?php echo gmdate('i:s',$c['tempo_espera']); ?></td>
        <td><?php echo $c['tempo_falando']>0 ? gmdate('i:s',$c['tempo_falando']) : '-'; ?></td>
        <td><?php echo htmlspecialchars($c['quem_desligou']); ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</body>
</html>
    <?php
    exit;
}

// ─── Dados para gráficos (JSON) ───────────────────────────────────────────────
$porHora = array();
for ($h = 0; $h < 24; $h++) $porHora[$h] = array('atendidas' => 0, 'abandonadas' => 0, 'timeout' => 0);
foreach ($chamadas as $c) {
    $hora = intval(date('G', strtotime($c['datetime'])));
    if ($c['status'] == 'ATENDIDA')    $porHora[$hora]['atendidas']++;
    elseif ($c['status'] == 'TIMEOUT') $porHora[$hora]['timeout']++;
    else                               $porHora[$hora]['abandonadas']++;
}
$horasLabels    = json_encode(array_map(function($h){ return str_pad($h,2,'0',STR_PAD_LEFT).':00'; }, array_keys($porHora)));
$horasAtendidas = json_encode(array_map(function($v){ return $v['atendidas']; }, $porHora));
$horasAbandono  = json_encode(array_map(function($v){ return $v['abandonadas']; }, $porHora));
$horasTimeout   = json_encode(array_map(function($v){ return $v['timeout']; }, $porHora));

$agentChartData = array();
foreach ($agentStats as $ag => $st) {
    if ($ag == 'SEM AGENTE' || $ag == 'NONE') continue;
    $agentChartData[$ag] = $st;
}
arsort($agentChartData);
$agLabels = array(); $agAtend = array(); $agNaoAtend = array(); $cnt = 0;
foreach ($agentChartData as $ag => $st) {
    $agLabels[] = $ag; $agAtend[] = $st['atendidas']; $agNaoAtend[] = $st['nao_atendidas'];
    if (++$cnt >= 15) break;
}
$agLabelsJson   = json_encode($agLabels);
$agAtendJson    = json_encode($agAtend);
$agNaoAtendJson = json_encode($agNaoAtend);

$faixasEspera = array('0-30s' => 0, '31-60s' => 0, '61-120s' => 0, '121-300s' => 0, '>300s' => 0);
foreach ($chamadas as $c) {
    $e = $c['tempo_espera'];
    if      ($e <= 30)  $faixasEspera['0-30s']++;
    elseif  ($e <= 60)  $faixasEspera['31-60s']++;
    elseif  ($e <= 120) $faixasEspera['61-120s']++;
    elseif  ($e <= 300) $faixasEspera['121-300s']++;
    else                $faixasEspera['>300s']++;
}
$faixasLabels = json_encode(array_keys($faixasEspera));
$faixasVals   = json_encode(array_values($faixasEspera));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Relatório de Fila — </title>
<script src="https://cdn.jsdelivr.net/npm/chart.js@2.9.4/dist/Chart.min.js"></script>
<style>
/* ─── TEMA CLARO - Reset & Base ─────────────────────────────────────────────────── */
*{box-sizing:border-box;margin:0;padding:0;}
body{
    font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;
    background:#f4f6f9; 
    color:#333; 
    min-height:100vh;
}

/* ─── Header ───────────────────────────────────────────────────────── */
.header{
    background:#ffffff;
    padding:15px 30px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    border-bottom:3px solid #2e86c1;
    box-shadow:0 2px 10px rgba(0,0,0,0.05);
}
.header h1{
    font-size:22px;
    font-weight:700;
    color:#1a3a5c;
    letter-spacing:1px;
    display:flex;
    align-items:center;
}
.header h1 span{color:#e67e22;}

/* ─── Filter Bar ───────────────────────────────────────────────────── */
.filter-bar{
    background:#ffffff;
    padding:15px 30px;
    display:flex;
    flex-wrap:wrap;
    gap:15px;
    align-items:flex-end;
    border-bottom:1px solid #ddd;
    box-shadow:0 1px 3px rgba(0,0,0,0.03);
}
.filter-group{display:flex;flex-direction:column;gap:4px;}
.filter-group label{font-size:11px;color:#555;text-transform:uppercase;font-weight:600;letter-spacing:.5px;}
.filter-group input,
.filter-group select{
    background:#fff;
    border:1px solid #ccc;
    color:#333;
    padding:6px 10px;
    border-radius:5px;
    font-size:13px;
    min-width:140px;
}
.filter-group input:focus,
.filter-group select:focus{outline:none;border-color:#2e86c1;}
.btn{
    padding:7px 18px;
    border:none;
    border-radius:5px;
    cursor:pointer;
    font-size:13px;
    font-weight:600;
    transition:all .2s;
    height:32px;
}
.btn-primary{background:#2e86c1;color:#fff;}
.btn-primary:hover{background:#1a6fa0;}
.btn-excel{background:#1e8449;color:#fff;}
.btn-excel:hover{background:#196f3d;}
.btn-pdf{background:#922b21;color:#fff;}
.btn-pdf:hover{background:#7b241c;}

/* ─── Main ─────────────────────────────────────────────────────────── */
.main{padding:25px 30px;}
.section-title{
    font-size:16px;
    font-weight:700;
    color:#1a3a5c;
    margin-bottom:15px;
    padding-bottom:8px;
    border-bottom:2px solid #ddd;
    display:flex;
    align-items:center;
    gap:8px;
}
.section-title::before{content:'';display:block;width:4px;height:18px;background:#e67e22;border-radius:2px;}

/* ─── KPI Cards ────────────────────────────────────────────────────── */
.kpi-grid{
    display:grid;
    grid-template-columns:repeat(auto-fill,minmax(150px,1fr));
    gap:15px;
    margin-bottom:30px;
}
.kpi-card{
    background:#ffffff;
    border:1px solid #e0e0e0;
    border-radius:10px;
    padding:18px 15px;
    text-align:center;
    transition:transform .2s,box-shadow .2s;
    position:relative;
    overflow:hidden;
    box-shadow:0 2px 5px rgba(0,0,0,0.02);
}
.kpi-card::before{
    content:'';
    position:absolute;
    top:0;left:0;right:0;
    height:3px;
    background:var(--accent,#2e86c1);
}
.kpi-card:hover{transform:translateY(-2px);box-shadow:0 8px 15px rgba(0,0,0,0.08);}
.kpi-val{font-size:28px;font-weight:800;color:var(--accent,#1a3a5c);line-height:1;}
.kpi-lbl{font-size:11px;color:#666;margin-top:6px;text-transform:uppercase;letter-spacing:.5px;font-weight:600;}
.kpi-sub{font-size:10px;color:#999;margin-top:3px;}

/* ─── Charts Grid ──────────────────────────────────────────────────── */
.charts-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:20px;
    margin-bottom:30px;
}
.chart-card{
    background:#ffffff;
    border:1px solid #e0e0e0;
    border-radius:10px;
    padding:20px;
    box-shadow:0 2px 5px rgba(0,0,0,0.02);
}
.chart-card.wide{grid-column:1/-1;}
.chart-title{font-size:13px;font-weight:700;color:#555;margin-bottom:15px;text-transform:uppercase;letter-spacing:.5px;}
.chart-wrap{position:relative;height:220px;}
.chart-wrap-tall{position:relative;height:300px;}

/* ─── Table ────────────────────────────────────────────────────────── */
.table-card{
    background:#ffffff;
    border:1px solid #e0e0e0;
    border-radius:10px;
    overflow:hidden;
    margin-bottom:30px;
    box-shadow:0 2px 5px rgba(0,0,0,0.02);
}
.table-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:15px 20px;
    background:#f8f9fa;
    border-bottom:1px solid #e0e0e0;
}
.table-search{
    background:#fff;
    border:1px solid #ccc;
    color:#333;
    padding:6px 12px;
    border-radius:5px;
    font-size:12px;
    width:220px;
}
.table-scroll{overflow-x:auto;}
table.data-table{width:100%;border-collapse:collapse;}
table.data-table thead tr{background:#f1f2f6;}
table.data-table th{
    padding:10px 14px;
    font-size:11px;
    text-align:left;
    color:#444;
    text-transform:uppercase;
    font-weight:700;
    letter-spacing:.4px;
    white-space:nowrap;
    border-bottom:2px solid #ddd;
    cursor:pointer;
    user-select:none;
}
table.data-table th:hover{color:#1a3a5c;}
table.data-table td{
    padding:9px 14px;
    font-size:12px;
    border-bottom:1px solid #eee;
    white-space:nowrap;
    color:#555;
}
table.data-table tr:hover td{background:#f1f8ff;}
table.data-table tr:nth-child(even) td{background:#fafbfc;}
.badge{
    display:inline-block;
    padding:2px 8px;
    border-radius:12px;
    font-size:10px;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:.3px;
}
.badge-atendida{background:rgba(30,132,73,.1);color:#1e8449;border:1px solid #1e8449;}
.badge-abandonada{background:rgba(169,50,38,.1);color:#a93226;border:1px solid #a93226;}
.badge-timeout{background:rgba(175,122,25,.1);color:#af7a19;border:1px solid #af7a19;}
.badge-agente{background:rgba(46,134,193,.1);color:#2e86c1;border:1px solid #2e86c1;}
.badge-cliente{background:rgba(142,68,173,.1);color:#8e44ad;border:1px solid #8e44ad;}

/* ─── Pagination / Info ─────────────────────────────────────────────── */
.table-footer{
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:10px 20px;
    background:#f8f9fa;
    border-top:1px solid #e0e0e0;
    font-size:12px;
    color:#666;
}
.pagination{display:flex;align-items:center;gap:6px;}
.pag-btn{
    padding:5px 12px;
    background:#fff;
    border:1px solid #ccc;
    color:#333;
    border-radius:4px;
    cursor:pointer;
    font-size:12px;
    font-weight:600;
}
.pag-btn:disabled{opacity:0.5;cursor:not-allowed;}
.pag-btn:not(:disabled):hover{background:#2e86c1;color:#fff;border-color:#2e86c1;}

/* ─── Progress Ring ─────────────────────────────────────────────────── */
.ring-wrap{display:flex;gap:30px;justify-content:center;align-items:center;flex-wrap:wrap;}
.ring-item{text-align:center;}
.ring-item svg{display:block;margin:auto;}
.ring-lbl{font-size:11px;font-weight:700;margin-top:6px;text-transform:uppercase;}

@media(max-width:768px){
    .charts-grid{grid-template-columns:1fr;}
    .chart-card.wide{grid-column:1;}
    .filter-bar{flex-direction:column;align-items:stretch;}
}
</style>
</head>
<body>

<div class="header">
    <div>
        <h1>
            <img src="/favicon.ico" alt="" style="height:26px; margin-right:10px; border-radius:4px;">
            Relatório de <span>&nbsp;Fila</span>&nbsp;— 
        </h1>
        <div style="font-size:12px;color:#666;margin-top:4px;margin-left:36px;">
            qstatslite · Período: <strong><?php echo $dataInicio; ?></strong> até <strong><?php echo $dataFim; ?></strong>
             · Fila(s): <strong style="color:#e67e22;"><?php echo htmlspecialchars($textoFilasExibicao); ?></strong>
        </div>
    </div>
</div>

<form method="GET" action="">
<div class="filter-bar">
    <div class="filter-group">
        <label>Data Início</label>
        <input type="date" name="data_inicio" value="<?php echo htmlspecialchars($dataInicio); ?>">
    </div>
    <div class="filter-group">
        <label>Data Fim</label>
        <input type="date" name="data_fim" value="<?php echo htmlspecialchars($dataFim); ?>">
    </div>
    <div class="filter-group">
        <label>Filas <span style="font-weight:normal;font-size:9px;color:#888;">(Segure CTRL p/ várias)</span></label>
        <select name="fila[]" multiple size="3" style="height:55px;">
            <?php foreach ($filas as $f): ?>
            <option value="<?php echo $f['qname_id']; ?>" <?php echo in_array($f['qname_id'], $filaFiltro) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($f['queue']); ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div style="display:flex;gap:8px;height:55px;align-items:flex-end;">
        <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
        <button type="submit" name="export" value="excel" class="btn btn-excel">📥 Excel</button>
        <button type="submit" name="export" value="pdf" class="btn btn-pdf">🖨️ PDF</button>
    </div>
</div>
</form>

<div class="main">

<div class="section-title">Indicadores Gerais</div>
<div class="kpi-grid">
    <div class="kpi-card" style="--accent:#3498db;">
        <div class="kpi-val"><?php echo $totalChamadas; ?></div>
        <div class="kpi-lbl">Total Chamadas</div>
    </div>
    <div class="kpi-card" style="--accent:#27ae60;">
        <div class="kpi-val"><?php echo $totalAtendidas; ?></div>
        <div class="kpi-lbl">Atendidas</div>
        <div class="kpi-sub"><?php echo $pctAtendimento; ?>% do total</div>
    </div>
    <div class="kpi-card" style="--accent:#c0392b;">
        <div class="kpi-val"><?php echo $totalAbandonadas; ?></div>
        <div class="kpi-lbl">Abandonadas</div>
        <div class="kpi-sub"><?php echo $pctAbandono; ?>% do total</div>
    </div>
    <div class="kpi-card" style="--accent:#f39c12;">
        <div class="kpi-val"><?php echo $totalTimeout; ?></div>
        <div class="kpi-lbl">Timeout</div>
        <div class="kpi-sub"><?php echo $totalChamadas>0?round(($totalTimeout/$totalChamadas)*100,1):0; ?>%</div>
    </div>
    <div class="kpi-card" style="--accent:#8e44ad;">
        <div class="kpi-val"><?php echo gmdate('i:s',$mediaEspera); ?></div>
        <div class="kpi-lbl">Média Espera</div>
        <div class="kpi-sub">mm:ss</div>
    </div>
    <div class="kpi-card" style="--accent:#16a085;">
        <div class="kpi-val"><?php echo gmdate('i:s',$mediaFalando); ?></div>
        <div class="kpi-lbl">Média Fala</div>
        <div class="kpi-sub">mm:ss</div>
    </div>
    <div class="kpi-card" style="--accent:#2c3e50;">
        <div class="kpi-val"><?php echo count($agentStats); ?></div>
        <div class="kpi-lbl">Agentes Ativos</div>
    </div>
</div>

<div class="charts-grid">
    <div class="chart-card">
        <div class="chart-title">📊 Distribuição de Chamadas</div>
        <div class="chart-wrap"><canvas id="chartDonut"></canvas></div>
    </div>
    <div class="chart-card">
        <div class="chart-title">🎯 % Atendimento vs Abandono</div>
        <div class="ring-wrap" style="height:220px;display:flex;align-items:center;justify-content:center;gap:40px;">
            <?php
            function svgRing($pct, $color, $label) {
                $r = 45; $cx = 55; $cy = 55; $circ = 2 * M_PI * $r;
                $dash = ($pct / 100) * $circ; $gap = $circ - $dash;
                echo '<div class="ring-item">';
                echo '<svg width="110" height="110" viewBox="0 0 110 110">';
                echo '<circle cx="'.$cx.'" cy="'.$cy.'" r="'.$r.'" fill="none" stroke="#f1f2f6" stroke-width="12"/>';
                echo '<circle cx="'.$cx.'" cy="'.$cy.'" r="'.$r.'" fill="none" stroke="'.$color.'" stroke-width="12" stroke-dasharray="'.$dash.' '.$gap.'" stroke-dashoffset="'.($circ/4).'" stroke-linecap="round"/>';
                echo '<text x="'.$cx.'" y="'.($cy+6).'" text-anchor="middle" font-size="16" font-weight="800" fill="'.$color.'">'.$pct.'%</text>';
                echo '</svg><div class="ring-lbl" style="color:'.$color.';">'.$label.'</div></div>';
            }
            svgRing($pctAtendimento, '#27ae60', 'Atendimento');
            svgRing($pctAbandono,    '#c0392b', 'Abandono');
            $pctTimeout = $totalChamadas > 0 ? round(($totalTimeout/$totalChamadas)*100,1) : 0;
            svgRing($pctTimeout,     '#f39c12', 'Timeout');
            ?>
        </div>
    </div>
    <div class="chart-card wide">
        <div class="chart-title">🕐 Chamadas por Hora do Dia</div>
        <div class="chart-wrap-tall"><canvas id="chartHoras"></canvas></div>
    </div>
    <div class="chart-card wide">
        <div class="chart-title">👤 Performance por Agente</div>
        <div class="chart-wrap-tall"><canvas id="chartAgentes"></canvas></div>
    </div>
</div>

<div class="section-title">Detalhamento de Chamadas</div>
<div class="table-card">
    <div class="table-header">
        <span style="font-size:13px;color:#555;font-weight:600;">
            Total: <?php echo $totalChamadas; ?> registros
        </span>
        <input type="text" class="table-search" id="tblSearch" placeholder="🔍 Buscar chamada..." onkeyup="processTable()">
    </div>
    <div class="table-scroll">
    <table class="data-table" id="detailTable">
        <thead>
            <tr>
                <th onclick="sortTable(0)"># ↕</th>
                <th onclick="sortTable(1)">Data/Hora ↕</th>
                <th onclick="sortTable(2)">Fila ↕</th>
                <th onclick="sortTable(3)">Número ↕</th>
                <th onclick="sortTable(4)">Agente ↕</th>
                <th onclick="sortTable(5)">Ramal ↕</th>
                <th onclick="sortTable(6)">Status ↕</th>
                <th onclick="sortTable(7)">Espera ↕</th>
                <th onclick="sortTable(8)">Fala ↕</th>
                <th onclick="sortTable(9)">Desligou ↕</th>
            </tr>
        </thead>
        <tbody id="detailTbody">
        <?php $i = 1; foreach ($chamadas as $c): ?>
        <tr>
            <td style="font-weight:600;"><?php echo $i++; ?></td>
            <td><?php echo htmlspecialchars($c['datetime']); ?></td>
            <td style="font-weight:600;color:#d35400;"><?php echo htmlspecialchars($c['fila']); ?></td>
            <td><?php echo htmlspecialchars($c['numero'] ?: '—'); ?></td>
            <td style="font-weight:600;"><?php echo htmlspecialchars($c['agente'] ?: '—'); ?></td>
            <td style="font-weight:600;color:#2980b9;"><?php echo htmlspecialchars($c['ramal'] ?: '—'); ?></td>
            <td>
                <?php if($c['status']=='ATENDIDA'): ?>
                    <span class="badge badge-atendida">✓ Atendida</span>
                <?php elseif($c['status']=='TIMEOUT'): ?>
                    <span class="badge badge-timeout">⏱ Timeout</span>
                <?php else: ?>
                    <span class="badge badge-abandonada">✗ Abandono</span>
                <?php endif; ?>
            </td>
            <td><?php echo $c['tempo_espera']>0 ? gmdate('i:s',$c['tempo_espera']) : '—'; ?></td>
            <td><?php echo $c['tempo_falando']>0 ? gmdate('i:s',$c['tempo_falando']) : '—'; ?></td>
            <td>
                <?php if($c['quem_desligou']=='AGENTE'): ?>
                    <span class="badge badge-agente">Agente</span>
                <?php elseif($c['quem_desligou']=='CLIENTE'): ?>
                    <span class="badge badge-cliente">Cliente</span>
                <?php else: ?>
                    <span style="color:#999;">—</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <div class="table-footer">
        <span id="tableInfo">Calculando...</span>
        <div class="pagination" id="paginationControls">
            </div>
    </div>
</div>

</div><script>
// Cor padrão das linhas dos gráficos (Tema Claro)
Chart.defaults.global.defaultFontColor = '#666';
var gridColor = '#eaeaea';

// ─── Donut ──────────────────────────────────────────────────────
(function(){
    var ctx = document.getElementById('chartDonut').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Atendidas', 'Abandonadas', 'Timeout'],
            datasets: [{
                data: [<?php echo $totalAtendidas; ?>, <?php echo $totalAbandonadas; ?>, <?php echo $totalTimeout; ?>],
                backgroundColor: ['#2ecc71', '#e74c3c', '#f1c40f'],
                borderColor: ['#fff','#fff','#fff'],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            legend: { position: 'bottom', labels: { fontSize: 11, padding: 15 } },
            cutoutPercentage: 65
        }
    });
})();

// ─── Bar: Por hora ────────────────────────────────────────────────────────────
(function(){
    var ctx = document.getElementById('chartHoras').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo $horasLabels; ?>,
            datasets: [
                { label: 'Atendidas',   data: <?php echo $horasAtendidas; ?>, backgroundColor: '#2ecc71' },
                { label: 'Abandonadas', data: <?php echo $horasAbandono; ?>,  backgroundColor: '#e74c3c' },
                { label: 'Timeout',     data: <?php echo $horasTimeout; ?>,   backgroundColor: '#f1c40f' }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            scales: {
                xAxes: [{ gridLines: { color: gridColor } }],
                yAxes: [{ gridLines: { color: gridColor }, ticks: { beginAtZero: true } }]
            }
        }
    });
})();

// ─── Bar: Agentes ────────────────────────────────────────────────────────────
(function(){
    var ctx = document.getElementById('chartAgentes').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo $agLabelsJson; ?>,
            datasets: [
                { label: 'Atendidas',     data: <?php echo $agAtendJson; ?>,    backgroundColor: '#3498db' },
                { label: 'Não Atendidas', data: <?php echo $agNaoAtendJson; ?>, backgroundColor: '#e74c3c' }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            scales: {
                xAxes: [{ gridLines: { color: gridColor } }],
                yAxes: [{ gridLines: { color: gridColor }, ticks: { beginAtZero: true } }]
            }
        }
    });
})();

// ─── Lógica de Paginação, Busca e Ordenação da Tabela ─────────────────────────
var currentPage = 1;
var rowsPerPage = 15;
var allRows = [];
var filteredRows = [];
var sortCol = -1;
var sortAsc = true;

document.addEventListener("DOMContentLoaded", function() {
    var tbody = document.getElementById('detailTbody');
    allRows = Array.prototype.slice.call(tbody.getElementsByTagName('tr'));
    
    // Preparar cache de texto para busca rápida
    allRows.forEach(function(r) {
        r.setAttribute('data-search', r.textContent.toLowerCase());
    });
    
    processTable(); 
});

function processTable() {
    var q = document.getElementById('tblSearch').value.toLowerCase();
    
    // 1. Filtrar
    filteredRows = allRows.filter(function(r) {
        return r.getAttribute('data-search').indexOf(q) > -1;
    });

    // 2. Ordenar
    if (sortCol > -1) {
        filteredRows.sort(function(a, b) {
            var av = a.cells[sortCol].textContent.trim();
            var bv = b.cells[sortCol].textContent.trim();
            var an = parseFloat(av.replace(/[^0-9.\-]/g,''));
            var bn = parseFloat(bv.replace(/[^0-9.\-]/g,''));
            if (!isNaN(an) && !isNaN(bn) && av.indexOf(':') === -1 && bv.indexOf(':') === -1) {
                return sortAsc ? an - bn : bn - an;
            }
            return sortAsc ? av.localeCompare(bv) : bv.localeCompare(av);
        });
    }

    // 3. Paginar
    var totalPages = Math.ceil(filteredRows.length / rowsPerPage);
    if(currentPage > totalPages) currentPage = totalPages || 1;

    var start = (currentPage - 1) * rowsPerPage;
    var end = start + rowsPerPage;

    allRows.forEach(function(r) { r.style.display = 'none'; });
    
    var tbody = document.getElementById('detailTbody');
    filteredRows.forEach(function(r, index) {
        tbody.appendChild(r);
        if (index >= start && index < end) {
            r.style.display = '';
        }
    });

    // 4. Atualizar textos
    var showingStart = filteredRows.length > 0 ? start + 1 : 0;
    var showingEnd = Math.min(end, filteredRows.length);
    document.getElementById('tableInfo').innerHTML = 'Exibindo <strong>' + showingStart + ' até ' + showingEnd + '</strong> de ' + filteredRows.length + ' registros';
    
    renderPaginationControls(totalPages);
}

function renderPaginationControls(totalPages) {
    var pagDiv = document.getElementById('paginationControls');
    pagDiv.innerHTML = '';
    
    if (totalPages <= 1) return;

    var btnPrev = document.createElement('button');
    btnPrev.className = 'pag-btn';
    btnPrev.textContent = '« Anterior';
    btnPrev.disabled = currentPage === 1;
    btnPrev.onclick = function() { currentPage--; processTable(); };
    pagDiv.appendChild(btnPrev);

    var span = document.createElement('span');
    span.style.fontWeight = '600';
    span.textContent = ' Pág ' + currentPage + ' de ' + totalPages + ' ';
    pagDiv.appendChild(span);

    var btnNext = document.createElement('button');
    btnNext.className = 'pag-btn';
    btnNext.textContent = 'Próxima »';
    btnNext.disabled = currentPage === totalPages || totalPages === 0;
    btnNext.onclick = function() { currentPage++; processTable(); };
    pagDiv.appendChild(btnNext);
}

function sortTable(colIndex) {
    if (sortCol === colIndex) {
        sortAsc = !sortAsc; 
    } else {
        sortCol = colIndex;
        sortAsc = true; 
    }
    currentPage = 1;
    processTable();
}
</script>

</body>
</html>