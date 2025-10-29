<?php
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['papel'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
include_once "../includes/db.php";
$bd = new Banco();
$conn = $bd->getConexao();
$admin_id = (int)$_SESSION['usuario_id'];
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }

// Tabelas necessárias podem não existir antes da migração
function table_exists(mysqli $conn, string $table): bool {
  try {
    $dbRes = $conn->query("SELECT DATABASE() AS db");
    $dbRow = $dbRes ? $dbRes->fetch_assoc() : null; $schema = $dbRow ? $dbRow['db'] : null;
    if (!$schema) return false;
    $stmt = $conn->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=? LIMIT 1");
    $stmt->bind_param('ss', $schema, $table);
    $stmt->execute(); $r=$stmt->get_result(); $ok = $r && $r->num_rows>0; $stmt->close();
    return $ok;
  } catch (Throwable $e) { return false; }
}
$hasMeta = table_exists($conn, 'Meta');
$hasMetaBarbeiro = table_exists($conn, 'MetaBarbeiro');
$hasComissao = table_exists($conn, 'ComissaoLancamento');
$tablesReady = $hasMeta && $hasMetaBarbeiro && $hasComissao;

// Unidade do admin
$sql = $conn->prepare("SELECT Unidade_idUnidade FROM Administrador WHERE idAdministrador=?");
$sql->bind_param("i", $admin_id);
$sql->execute();
$sql->bind_result($unidade_id);
$sql->fetch();
$sql->close();
if (empty($unidade_id)) {
    header('Location: index_admin.php?ok=0&msg=' . urlencode('Associe uma unidade ao admin para acessar Metas.'));
    exit;
}

$okMsg = null; $errMsg = null;

// Ações POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$tablesReady) {
        $errMsg = 'Funcionalidade indisponível: execute a migração banco/migracao_2025_10.sql para criar as tabelas de Metas.';
    } else {
        $token = $_POST['csrf_token'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'], $token)) {
            $errMsg = 'Falha de segurança. Atualize a página e tente novamente.';
        } else {
            $acao = $_POST['acao'] ?? '';
            if ($acao === 'criar') {
                $period = $_POST['periodicidade'] === 'Semanal' ? 'Semanal' : 'Mensal';
                $inicio = $_POST['inicio'] ?? '';
                $fim    = $_POST['fim'] ?? '';
                $base   = $_POST['base'] === 'Atendimentos' ? 'Atendimentos' : 'Receita';
                $obj    = (float)str_replace([','], ['.'], $_POST['objetivo']);
                $pct    = (float)str_replace([','], ['.'], $_POST['percentual']);
                // Limite: no máximo 5 metas ativas por unidade
                $c = $conn->prepare("SELECT COUNT(*) FROM Meta WHERE Unidade_idUnidade=? AND ativo=1");
                $c->bind_param("i", $unidade_id); $c->execute(); $c->bind_result($qtdAtivas); $c->fetch(); $c->close();
                if ((int)$qtdAtivas >= 5) {
                    $errMsg = 'Limite de 5 metas ativas atingido. Feche alguma meta antes de criar outra.';
                } elseif (!$inicio || !$fim || $obj <= 0 || $pct <= 0) {
                    $errMsg = 'Preencha período, objetivo e percentual corretamente.';
                } else {
                    // Validação de intervalo por periodicidade (inclusive)
                    $dIni = DateTime::createFromFormat('Y-m-d', $inicio);
                    $dFim = DateTime::createFromFormat('Y-m-d', $fim);
                    if (!$dIni || !$dFim) {
                        $errMsg = 'Datas inválidas. Use o seletor de data.';
                    } elseif ($dFim < $dIni) {
                        $errMsg = 'Data final não pode ser menor que a inicial.';
                    } else {
                        $diffDays = (int)$dIni->diff($dFim)->days + 1;
                        if ($period === 'Semanal' && $diffDays > 7) {
                            $errMsg = 'Para meta semanal selecione no máximo 7 dias.';
                        } elseif ($period === 'Mensal' && $diffDays > 30) {
                            $errMsg = 'Para meta mensal selecione no máximo 30 dias.';
                        } else {
                            $ins = $conn->prepare("INSERT INTO Meta (Unidade_idUnidade, periodicidade, inicio, fim, base, objetivoValor, percentualFlat, ativo) VALUES (?,?,?,?,?,?,?,1)");
                            $ins->bind_param("issssdd", $unidade_id, $period, $inicio, $fim, $base, $obj, $pct);
                            if ($ins->execute()) { $okMsg = 'Meta criada com sucesso.'; } else { $errMsg = 'Erro ao criar meta.'; }
                            $ins->close();
                        }
                    }
                }
            } elseif ($acao === 'fechar') {
                $metaId = (int)$_POST['idMeta'];
                // Carregar meta
                $m = $conn->prepare("SELECT idMeta, inicio, fim, base, objetivoValor, percentualFlat FROM Meta WHERE idMeta=? AND Unidade_idUnidade=?");
                $m->bind_param("ii", $metaId, $unidade_id);
                $m->execute();
                $m->bind_result($idMeta,$mInicio,$mFim,$mBase,$mObj,$mPct);
                if ($m->fetch()) {
                    $m->close();
                    // Agregação por barbeiro no período (Finalizados)
                    $q = $conn->prepare("SELECT b.idBarbeiro, b.nomeBarbeiro, COUNT(DISTINCT a.idAgendamento) AS atend, COALESCE(SUM(ahs.precoFinal),0) AS receita FROM Agendamento a JOIN Barbeiro b ON b.idBarbeiro=a.Barbeiro_idBarbeiro JOIN Agendamento_has_Servico ahs ON ahs.Agendamento_idAgendamento=a.idAgendamento WHERE a.Unidade_idUnidade=? AND a.data BETWEEN ? AND ? AND a.statusAgendamento='Finalizado' GROUP BY b.idBarbeiro, b.nomeBarbeiro");
                    $q->bind_param("iss", $unidade_id, $mInicio, $mFim);
                    $q->execute();
                    $r = $q->get_result();
                    while ($row = $r->fetch_assoc()) {
                        $barbId = (int)$row['idBarbeiro'];
                        // objetivo (override?)
                        $obj = $mObj;
                        $ov = $conn->prepare("SELECT objetivoOverride FROM MetaBarbeiro WHERE Meta_idMeta=? AND Barbeiro_idBarbeiro=?");
                        $ov->bind_param("ii", $metaId, $barbId);
                        $ov->execute();
                        $ov->bind_result($ovVal);
                        if ($ov->fetch() && $ovVal !== null) { $obj = (float)$ovVal; }
                        $ov->close();

                        $atend = (int)$row['atend'];
                        $receita = (float)$row['receita'];
                        $realizadoBase = ($mBase === 'Atendimentos') ? (float)$atend : $receita;
                        $atingiu = $realizadoBase >= (float)$obj ? 1 : 0;
                        $valor = $atingiu ? round($receita * ($mPct/100.0), 2) : 0.00; // comissão sempre sobre receita

                        // Evita duplicar lançamentos caso fechem mais de uma vez
                        $ck = $conn->prepare("SELECT 1 FROM ComissaoLancamento WHERE Meta_idMeta=? AND Barbeiro_idBarbeiro=? LIMIT 1");
                        $ck->bind_param("ii", $metaId, $barbId); $ck->execute(); $ck->store_result();
                        if ($ck->num_rows === 0) {
                            $insL = $conn->prepare("INSERT INTO ComissaoLancamento (Meta_idMeta, Barbeiro_idBarbeiro, periodoInicio, periodoFim, baseRealizado, objetivoUsado, atingiu, percentualAplicado, receitaRealizada, valorComissao, status) VALUES (?,?,?,?,?,?,?,?,?,?,'Pendente')");
                            $insL->bind_param("iissddiddd", $metaId, $barbId, $mInicio, $mFim, $realizadoBase, $obj, $atingiu, $mPct, $receita, $valor);
                            $insL->execute(); $insL->close();
                        }
                        $ck->close();
                    }
                    $q->close();
                    // Marca a meta como inativa para não aparecer mais no topo
                    $upM = $conn->prepare("UPDATE Meta SET ativo=0 WHERE idMeta=? AND Unidade_idUnidade=?");
                    $upM->bind_param("ii", $metaId, $unidade_id); $upM->execute(); $upM->close();
                    $okMsg = 'Período fechado e comissões geradas.';
                } else {
                    $m->close();
                    $errMsg = 'Meta inválida.';
                }
      } elseif ($acao === 'pagar') {
                $lancId = (int)$_POST['idLanc'];
                $up = $conn->prepare("UPDATE ComissaoLancamento cl JOIN Meta m ON m.idMeta=cl.Meta_idMeta SET cl.status='Pago' WHERE cl.idLancamento=? AND m.Unidade_idUnidade=?");
                $up->bind_param("ii", $lancId, $unidade_id);
                if ($up->execute()) { $okMsg = 'Lançamento marcado como pago.'; } else { $errMsg = 'Falha ao atualizar lançamento.'; }
                $up->close();
      } elseif ($acao === 'excluir') {
        // Permite excluir apenas se já estiver Pago e pertencer à unidade
        $lancId = (int)$_POST['idLanc'];
        $del = $conn->prepare("DELETE cl FROM ComissaoLancamento cl JOIN Meta m ON m.idMeta=cl.Meta_idMeta WHERE cl.idLancamento=? AND m.Unidade_idUnidade=? AND cl.status='Pago'");
        $del->bind_param("ii", $lancId, $unidade_id);
        if ($del->execute() && $del->affected_rows>0) { $okMsg = 'Lançamento excluído do histórico.'; } else { $errMsg = 'Só é possível excluir lançamentos pagos.'; }
        $del->close();
      } elseif ($acao === 'override') {
        // Criar/atualizar/remover objetivo individual por barbeiro para uma meta
        $metaId = (int)($_POST['idMeta'] ?? 0);
        $barbId = (int)($_POST['idBarbeiro'] ?? 0);
        $valStr = trim($_POST['objetivo'] ?? '');
        // valida pertencimento
        $m = $conn->prepare("SELECT 1 FROM Meta WHERE idMeta=? AND Unidade_idUnidade=?");
        $m->bind_param("ii", $metaId, $unidade_id); $m->execute(); $mOk = $m->get_result()->num_rows>0; $m->close();
        $b = $conn->prepare("SELECT 1 FROM Barbeiro WHERE idBarbeiro=? AND Unidade_idUnidade=?");
        $b->bind_param("ii", $barbId, $unidade_id); $b->execute(); $bOk = $b->get_result()->num_rows>0; $b->close();
        if (!$mOk || !$bOk) { $errMsg = 'Dados inválidos para objetivo individual.'; }
        else if ($valStr === '') {
          // Remover objetivo individual
          $del = $conn->prepare("DELETE FROM MetaBarbeiro WHERE Meta_idMeta=? AND Barbeiro_idBarbeiro=?");
          $del->bind_param("ii", $metaId, $barbId);
          if ($del->execute()) { $okMsg = 'Objetivo individual removido.'; } else { $errMsg = 'Falha ao remover objetivo individual.'; }
          $del->close();
        } else {
          $val = (float)str_replace([','],['.'],$valStr);
          if ($val <= 0) { $errMsg = 'Objetivo inválido.'; }
          else {
            // UPSERT usando chave única (Meta, Barbeiro)
            $sql = "INSERT INTO MetaBarbeiro (Meta_idMeta, Barbeiro_idBarbeiro, objetivoOverride) VALUES (?,?,?) ON DUPLICATE KEY UPDATE objetivoOverride=VALUES(objetivoOverride)";
            $st = $conn->prepare($sql);
            $st->bind_param("iid", $metaId, $barbId, $val);
            if ($st->execute()) { $okMsg = 'Objetivo individual salvo.'; } else { $errMsg = 'Falha ao salvar objetivo individual.'; }
            $st->close();
          }
        }
            }
        }
    }
}

// Metas da unidade (somente ativas para não aparecerem após fechar)
$metas = [];
if ($tablesReady) {
  $mm = $conn->prepare("SELECT idMeta, periodicidade, inicio, fim, base, objetivoValor, percentualFlat, ativo FROM Meta WHERE Unidade_idUnidade=? AND ativo=1 ORDER BY inicio DESC");
  $mm->bind_param("i", $unidade_id);
  $mm->execute();
  $resM = $mm->get_result();
  while ($row = $resM->fetch_assoc()) { $metas[] = $row; }
  $mm->close();
}

// Meta selecionada para ver progresso
$viewId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$viewMeta = null; $progress = [];
if ($tablesReady && $viewId > 0) {
  $vm = $conn->prepare("SELECT idMeta, periodicidade, inicio, fim, base, objetivoValor, percentualFlat, ativo FROM Meta WHERE idMeta=? AND Unidade_idUnidade=?");
    $vm->bind_param("ii", $viewId, $unidade_id);
    $vm->execute();
    $viewMeta = $vm->get_result()->fetch_assoc();
    $vm->close();
    if ($viewMeta) {
        $q = $conn->prepare("SELECT b.idBarbeiro, b.nomeBarbeiro, COUNT(DISTINCT a.idAgendamento) AS atend, COALESCE(SUM(ahs.precoFinal),0) AS receita FROM Agendamento a JOIN Barbeiro b ON b.idBarbeiro=a.Barbeiro_idBarbeiro JOIN Agendamento_has_Servico ahs ON ahs.Agendamento_idAgendamento=a.idAgendamento WHERE a.Unidade_idUnidade=? AND a.data BETWEEN ? AND ? AND a.statusAgendamento='Finalizado' GROUP BY b.idBarbeiro, b.nomeBarbeiro ORDER BY b.nomeBarbeiro");
        $q->bind_param("iss", $unidade_id, $viewMeta['inicio'], $viewMeta['fim']);
        $q->execute(); $r = $q->get_result();
        while ($row = $r->fetch_assoc()) {
            // objetivo (override?)
            $obj = (float)$viewMeta['objetivoValor'];
      $ov = $conn->prepare("SELECT objetivoOverride FROM MetaBarbeiro WHERE Meta_idMeta=? AND Barbeiro_idBarbeiro=?");
            $ov->bind_param("ii", $viewId, $row['idBarbeiro']);
            $ov->execute(); $ov->bind_result($ovVal); if ($ov->fetch() && $ovVal !== null) { $obj = (float)$ovVal; } $ov->close();
            $atend = (int)$row['atend']; $receita = (float)$row['receita'];
            $real = ($viewMeta['base']==='Atendimentos') ? (float)$atend : $receita;
            $pct = (float)$viewMeta['percentualFlat'];
            $atingiu = $real >= $obj;
            $prevCom = $atingiu ? round($receita * ($pct/100.0), 2) : 0.00;
            $progress[] = [
        'id'=>$row['idBarbeiro'], 'nome'=>$row['nomeBarbeiro'],
        'atend'=>$atend, 'receita'=>$receita, 'objetivo'=>$obj,
                'realizado'=>$real, 'progresso'=>$obj>0? round(($real/$obj)*100,1) : 0,
        'atingiu'=>$atingiu, 'prevCom'=>$prevCom,
        'override'=> isset($ovVal) && $ovVal !== null ? (float)$ovVal : null
            ];
        }
        $q->close();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Metas da Unidade - Admin</title>
<link rel="stylesheet" href="dashboard_admin.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
</head>
<body class="dashboard-admin">
<div class="container py-5">
  <div class="toast-container position-fixed top-0 start-50 translate-middle-x p-3" id="toast-msg-container"></div>

  <div class="dashboard-card mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="dashboard-title mb-0"><i class="bi bi-bullseye"></i> Metas da Unidade</h2>
      <div class="d-flex gap-2">
        <a href="index_admin.php" class="dashboard-action"><i class="bi bi-arrow-left"></i> Voltar</a>
      </div>
    </div>
    <?php if (!$tablesReady): ?>
      <div class="alert alert-warning">Funcionalidade indisponível: execute a migração <code>banco/migracao_2025_10.sql</code> (phpMyAdmin → Importar) para criar as tabelas <code>Meta</code>, <code>MetaBarbeiro</code> e <code>ComissaoLancamento</code>.</div>
    <?php endif; ?>
    <form class="row g-3" method="POST">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
      <input type="hidden" name="acao" value="criar">
      <div class="col-sm-6 col-md-2">
        <label class="form-label">Periodicidade</label>
        <select class="form-select" name="periodicidade">
          <option>Mensal</option>
          <option>Semanal</option>
        </select>
      </div>
      <div class="col-sm-6 col-md-2">
        <label class="form-label">Início</label>
        <input type="date" class="form-control" name="inicio" required>
      </div>
      <div class="col-sm-6 col-md-2">
        <label class="form-label">Fim</label>
        <input type="date" class="form-control" name="fim" required>
      </div>
      <div class="col-sm-6 col-md-2">
        <label class="form-label">Base</label>
        <select class="form-select" name="base">
          <option>Receita</option>
          <option>Atendimentos</option>
        </select>
      </div>
      <div class="col-sm-6 col-md-2">
        <label class="form-label">Objetivo</label>
        <input type="number" step="0.01" class="form-control" name="objetivo" placeholder="ex.: 5000" required>
      </div>
      <div class="col-sm-6 col-md-2">
        <label class="form-label">% Comissão</label>
        <input type="number" step="0.01" class="form-control" name="percentual" placeholder="ex.: 10" required>
      </div>
      <div class="col-12">
        <button type="submit" class="dashboard-action" <?= $tablesReady ? '' : 'disabled title="Execute a migração para habilitar"' ?>><i class="bi bi-plus-circle"></i> Criar meta</button>
      </div>
    </form>
  </div>

  <div class="dashboard-card mb-4">
    <div class="dashboard-section-title mb-2"><i class="bi bi-list-task"></i> Metas cadastradas</div>
    <div class="table-responsive">
      <table class="table table-dark table-striped align-middle dashboard-table">
        <thead>
          <tr>
            <th>Período</th><th>Base</th><th>Objetivo</th><th>%</th><th>Status</th><th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php if (count($metas)>0): foreach ($metas as $m): ?>
          <tr>
            <td><?= date('d/m/Y', strtotime($m['inicio'])) ?> - <?= date('d/m/Y', strtotime($m['fim'])) ?> (<?= htmlspecialchars($m['periodicidade']) ?>)</td>
            <td><?= htmlspecialchars($m['base']) ?></td>
            <td><?= $m['base']==='Receita' ? 'R$ '.number_format($m['objetivoValor'],2,',','.') : (int)$m['objetivoValor'] ?></td>
            <td><?= number_format($m['percentualFlat'],2,',','.') ?>%</td>
            <td><?= $m['ativo'] ? 'Ativa' : 'Inativa' ?></td>
            <td>
              <a class="btn btn-sm btn-outline-warning" href="?id=<?= (int)$m['idMeta'] ?>"><i class="bi bi-bar-chart"></i> Ver</a>
              <form method="POST" style="display:inline-block;" onsubmit="return confirm('Fechar período e gerar comissões?');">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="acao" value="fechar">
                <input type="hidden" name="idMeta" value="<?= (int)$m['idMeta'] ?>">
                <button type="submit" class="btn btn-sm btn-success" <?= $tablesReady ? '' : 'disabled' ?>><i class="bi bi-cash-coin"></i> Fechar período</button>
              </form>
            </td>
          </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="6" class="text-center text-muted">Nenhuma meta cadastrada.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if ($viewMeta): ?>
  <?php if (!empty($viewMeta['ativo'])): ?>
  <div class="dashboard-card mb-4">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <div class="dashboard-section-title mb-0"><i class="bi bi-bar-chart"></i> Progresso da meta (<?= htmlspecialchars($viewMeta['base']) ?>)</div>
      <div class="text-muted">Período: <?= date('d/m/Y', strtotime($viewMeta['inicio'])) ?> - <?= date('d/m/Y', strtotime($viewMeta['fim'])) ?></div>
    </div>
    <p class="text-muted mt-0 mb-2" style="font-size:.9rem;">Objetivo individual: defina um objetivo específico para o barbeiro apenas nesta meta. Deixe o campo vazio e salve para voltar ao objetivo padrão.</p>
    <div class="table-responsive">
      <table class="table table-dark table-striped align-middle dashboard-table">
        <thead>
          <tr>
            <th>Barbeiro</th>
            <th>Atendimentos</th>
            <th>Receita</th>
            <th>Objetivo</th>
            <th>Progresso</th>
            <th>Prev. Comissão</th>
            <th>Objetivo individual</th>
          </tr>
        </thead>
        <tbody>
          <?php if (count($progress)>0): foreach ($progress as $p): ?>
          <tr>
            <td><?= htmlspecialchars($p['nome']) ?></td>
            <td><?= (int)$p['atend'] ?></td>
            <td>R$ <?= number_format($p['receita'],2,',','.') ?></td>
            <td><?= $viewMeta['base']==='Receita' ? 'R$ '.number_format($p['objetivo'],2,',','.') : (int)$p['objetivo'] ?></td>
            <td>
              <div class="progress" style="height: 10px; background: rgba(255,255,255,.12);">
                <div class="progress-bar <?= $p['atingiu'] ? 'bg-success' : 'bg-warning' ?>" role="progressbar" style="width: <?= min(100, $p['progresso']) ?>%"></div>
              </div>
              <small class="text-muted"><?= number_format($p['progresso'],1,',','.') ?>%</small>
            </td>
            <td><?= $p['atingiu'] ? ('R$ '.number_format($p['prevCom'],2,',','.')) : '—' ?></td>
            <td>
              <form method="POST" class="d-flex gap-2 align-items-center">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="acao" value="override">
                <input type="hidden" name="idMeta" value="<?= (int)$viewMeta['idMeta'] ?>">
                <input type="hidden" name="idBarbeiro" value="<?= (int)$p['id'] ?>">
                <input type="number" step="0.01" class="form-control form-control-sm" style="max-width:130px" name="objetivo" placeholder="ex.: 5000" value="<?= $p['override']!==null ? number_format($p['override'],2,'.','') : '' ?>">
                <button type="submit" class="btn btn-sm btn-outline-warning">Salvar</button>
              </form>
              <small class="text-muted">Deixe vazio e salve para remover o objetivo individual.</small>
            </td>
          </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="7" class="text-center text-muted">Sem finalizações no período.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <div class="dashboard-card">
    <div class="dashboard-section-title mb-1"><i class="bi bi-cash-stack"></i> Lançamentos de comissão desta meta (snapshot)</div>
    <p class="text-muted mt-0 mb-2" style="font-size:.9rem;">Lista apenas os lançamentos gerados ao fechar este período. Para ver todas as metas e períodos, utilize o Histórico de comissões abaixo.</p>
    <div class="table-responsive">
      <table class="table table-dark table-striped align-middle dashboard-table">
        <thead><tr><th>Barbeiro</th><th>Base real.</th><th>Objetivo</th><th>Receita</th><th>Comissão</th><th>Status</th><th>Ações</th></tr></thead>
        <tbody>
          <?php 
          $ll = $conn->prepare("SELECT cl.idLancamento, b.nomeBarbeiro, cl.baseRealizado, cl.objetivoUsado, cl.receitaRealizada, cl.valorComissao, cl.status FROM ComissaoLancamento cl JOIN Barbeiro b ON b.idBarbeiro=cl.Barbeiro_idBarbeiro WHERE cl.Meta_idMeta=? ORDER BY b.nomeBarbeiro");
          $ll->bind_param("i", $viewId);
          $ll->execute(); $rr = $ll->get_result();
          if ($rr->num_rows>0): while($L=$rr->fetch_assoc()): ?>
            <tr>
              <td><?= htmlspecialchars($L['nomeBarbeiro']) ?></td>
              <td><?= number_format($L['baseRealizado'], ($viewMeta['base']==='Receita'?2:0), ',', '.') ?></td>
              <td><?= number_format($L['objetivoUsado'], ($viewMeta['base']==='Receita'?2:0), ',', '.') ?></td>
              <td>R$ <?= number_format($L['receitaRealizada'],2,',','.') ?></td>
              <td>R$ <?= number_format($L['valorComissao'],2,',','.') ?></td>
              <td><?= htmlspecialchars($L['status']) ?></td>
              <td>
                <?php if ($L['status'] !== 'Pago'): ?>
                <form method="POST" style="display:inline-block;">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                  <input type="hidden" name="acao" value="pagar">
                  <input type="hidden" name="idLanc" value="<?= (int)$L['idLancamento'] ?>">
                  <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-check2-circle"></i> Marcar pago</button>
                </form>
                <?php else: ?>
                  <span class="badge bg-success">Pago</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; else: ?>
            <tr><td colspan="7" class="text-center text-muted">Nenhum lançamento gerado ainda.</td></tr>
          <?php endif; $ll->close(); ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <!-- Histórico de Comissões (todas as metas da unidade, inclusive fechadas) -->
  <?php if ($tablesReady): ?>
  <div class="dashboard-card mt-4">
    <div class="dashboard-section-title mb-1"><i class="bi bi-clock-history"></i> Histórico de comissões</div>
    <p class="text-muted mt-0 mb-2" style="font-size:.9rem;">Abrange todas as metas da unidade. Use os filtros por período e status. Apenas lançamentos marcados como Pago podem ser excluídos.</p>
    <form method="GET" class="row g-2 mb-3">
      <input type="hidden" name="id" value="<?= (int)$viewId ?>">
      <div class="col-sm-6 col-md-3">
        <label class="form-label">Início</label>
        <input type="date" class="form-control" name="hist_inicio" value="<?= htmlspecialchars($_GET['hist_inicio'] ?? '') ?>">
      </div>
      <div class="col-sm-6 col-md-3">
        <label class="form-label">Fim</label>
        <input type="date" class="form-control" name="hist_fim" value="<?= htmlspecialchars($_GET['hist_fim'] ?? '') ?>">
      </div>
      <div class="col-sm-6 col-md-3">
        <label class="form-label">Status</label>
        <?php $hs = $_GET['hist_status'] ?? ''; ?>
        <select name="hist_status" class="form-select">
          <option value="" <?= $hs===''?'selected':'' ?>>Todos</option>
          <option value="Pendente" <?= $hs==='Pendente'?'selected':'' ?>>Pendente</option>
          <option value="Pago" <?= $hs==='Pago'?'selected':'' ?>>Pago</option>
        </select>
      </div>
      <div class="col-sm-6 col-md-3 d-flex align-items-end">
        <button type="submit" class="btn btn-outline-warning w-100"><i class="bi bi-filter"></i> Filtrar</button>
      </div>
    </form>
    <div class="table-responsive">
      <table class="table table-dark table-striped align-middle dashboard-table">
        <thead>
          <tr>
            <th>Período</th>
            <th>Barbeiro</th>
            <th>Base</th>
            <th>Receita</th>
            <th>Comissão</th>
            <th>Status</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $w = "WHERE m.Unidade_idUnidade=?";
          $params = [$unidade_id]; $types = 'i';
          $hIni = $_GET['hist_inicio'] ?? ''; $hFim = $_GET['hist_fim'] ?? ''; $hStatus = $_GET['hist_status'] ?? '';
          if ($hIni) { $w .= " AND cl.periodoInicio >= ?"; $types .= 's'; $params[] = $hIni; }
          if ($hFim) { $w .= " AND cl.periodoFim <= ?"; $types .= 's'; $params[] = $hFim; }
          if ($hStatus === 'Pendente' || $hStatus === 'Pago') { $w .= " AND cl.status = ?"; $types .= 's'; $params[] = $hStatus; }
          $sql = "SELECT cl.idLancamento, cl.periodoInicio, cl.periodoFim, cl.receitaRealizada, cl.valorComissao, cl.status, b.nomeBarbeiro, m.base FROM ComissaoLancamento cl JOIN Meta m ON m.idMeta=cl.Meta_idMeta JOIN Barbeiro b ON b.idBarbeiro=cl.Barbeiro_idBarbeiro $w ORDER BY cl.criadoEm DESC, cl.periodoInicio DESC";
          $hist = $conn->prepare($sql);
          $hist->bind_param($types, ...$params);
          $hist->execute(); $rh = $hist->get_result();
          if ($rh->num_rows>0): while($H=$rh->fetch_assoc()): ?>
            <tr>
              <td><?= date('d/m/Y', strtotime($H['periodoInicio'])) ?> - <?= date('d/m/Y', strtotime($H['periodoFim'])) ?></td>
              <td><?= htmlspecialchars($H['nomeBarbeiro']) ?></td>
              <td><?= htmlspecialchars($H['base']) ?></td>
              <td>R$ <?= number_format($H['receitaRealizada'],2,',','.') ?></td>
              <td>R$ <?= number_format($H['valorComissao'],2,',','.') ?></td>
              <td>
                <?php if ($H['status']==='Pago'): ?>
                  <span class="badge bg-success">Pago</span>
                <?php else: ?>
                  <span class="badge bg-warning text-dark">Pendente</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($H['status']!=='Pago'): ?>
                  <form method="POST" style="display:inline-block;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="acao" value="pagar">
                    <input type="hidden" name="idLanc" value="<?= (int)$H['idLancamento'] ?>">
                    <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-check2-circle"></i> Marcar pago</button>
                  </form>
                <?php else: ?>
                  <form method="POST" style="display:inline-block;" onsubmit="return confirm('Excluir este lançamento pago do histórico?');">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="acao" value="excluir">
                    <input type="hidden" name="idLanc" value="<?= (int)$H['idLancamento'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Excluir</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; else: ?>
            <tr><td colspan="7" class="text-center text-muted">Sem lançamentos de comissão ainda.</td></tr>
          <?php endif; $hist->close(); ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
  const container = document.getElementById('toast-msg-container');
  const ok = <?= json_encode($okMsg) ?>; const err = <?= json_encode($errMsg) ?>;
  function toast(msg, ok=true){ if(!msg||!container) return; const el=document.createElement('div'); el.className = `toast align-items-center ${ok? 'text-bg-success':'text-bg-danger'} border-0`; el.innerHTML = `<div class=\"d-flex\"><div class=\"toast-body\">${msg}</div><button type=\"button\" class=\"btn-close btn-close-white me-2 m-auto\" data-bs-dismiss=\"toast\" aria-label=\"Close\"></button></div>`; container.appendChild(el); new bootstrap.Toast(el,{delay:3500}).show(); }
  if (ok) toast(ok,true); if (err) toast(err,false);
})();
</script>
<?php @include_once("../Footer/footer.html"); ?>
</body>
</html>
