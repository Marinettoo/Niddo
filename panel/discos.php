<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
if (!in_array('Admin', $_SESSION['roles'] ?? [])) { header('Location: dashboard.php'); exit; }
require_once '../config/db.php';

$cuotas_file = __DIR__ . '/../config/cuotas.php';
$cuotas = file_exists($cuotas_file) ? (include $cuotas_file) : [];

$msg = '';
$msg_tipo = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'cuota') {
        $punto = trim($_POST['punto'] ?? '');
        $gb    = (float)($_POST['gb'] ?? 0);
        if ($punto && $gb > 0) {
            $cuotas[$punto] = (int)($gb * 1073741824);
            file_put_contents($cuotas_file, '<?php return ' . var_export($cuotas, true) . ';');
            $msg = "Cuota guardada para $punto.";
        }

    } elseif ($accion === 'montar') {
        $dev   = trim($_POST['dispositivo'] ?? '');
        $punto = trim($_POST['punto'] ?? '');
        if ($dev && $punto) {
            if (!is_dir($punto)) mkdir($punto, 0755, true);
            $out = shell_exec('sudo mount ' . escapeshellarg($dev) . ' ' . escapeshellarg($punto) . ' 2>&1');
            $msg = $out ? trim($out) : "Montado correctamente.";
            if ($out) $msg_tipo = 'error';
        }

    } elseif ($accion === 'desmontar') {
        $punto = trim($_POST['punto'] ?? '');
        if ($punto) {
            $out = shell_exec('sudo umount ' . escapeshellarg($punto) . ' 2>&1');
            $msg = $out ? trim($out) : "Desmontado correctamente.";
            if ($out) $msg_tipo = 'error';
        }
    }
}

// Leer discos del sistema
$discos = [];
$df = shell_exec('df -B1 2>/dev/null');
if ($df) {
    $lineas = explode("\n", trim($df));
    array_shift($lineas);
    foreach ($lineas as $linea) {
        $p = preg_split('/\s+/', trim($linea));
        if (count($p) < 6) continue;
        if (preg_match('/^(tmpfs|devtmpfs|udev|overlay|sysfs|proc|cgroup|none|efivarfs)/', $p[0])) continue;
        $discos[] = [
            'fs'    => $p[0],
            'total' => (int)$p[1],
            'usado' => (int)$p[2],
            'libre' => (int)$p[3],
            'punto' => $p[5],
            'cuota' => $cuotas[$p[5]] ?? null,
        ];
    }
}

function fmt($b) {
    if ($b >= 1073741824) return round($b / 1073741824, 1) . ' GB';
    if ($b >= 1048576)    return round($b / 1048576, 1) . ' MB';
    if ($b >= 1024)       return round($b / 1024, 1) . ' KB';
    return $b . ' B';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>Niddo — Discos</title>
    <?php include '_head.php'; ?>
</head>
<body>
<div class="layout">
    <?php include '_nav.php'; ?>
    <main class="main">
        <div class="page-header">
            <div class="page-title">Discos</div>
            <div class="page-sub">Espacio disponible y cuotas de almacenamiento</div>
        </div>

        <?php if ($msg): ?>
            <div class="msg-<?= $msg_tipo ?>"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <div class="seccion">
            <div class="seccion-header"><div class="seccion-title">Discos del servidor</div></div>
            <?php if (!$discos): ?>
                <p style="padding:12px; color:#888;">No se pueden leer los discos (¿shell_exec desactivado?).</p>
            <?php else: ?>
            <div class="table-wrap"><table>
                <thead>
                    <tr><th>Dispositivo</th><th>Punto de montaje</th><th>Usado</th><th>Total</th><th>Cuota Niddo</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($discos as $d):
                    $pct = $d['total'] > 0 ? round($d['usado'] / $d['total'] * 100) : 0;
                    $cuota_txt = $d['cuota'] ? fmt($d['cuota']) : '—';
                ?>
                <tr>
                    <td class="td-mono"><?= htmlspecialchars($d['fs']) ?></td>
                    <td class="td-mono"><?= htmlspecialchars($d['punto']) ?></td>
                    <td class="td-mono"><?= fmt($d['usado']) ?> (<?= $pct ?>%)</td>
                    <td class="td-mono"><?= fmt($d['total']) ?></td>
                    <td class="td-mono"><?= $cuota_txt ?></td>
                    <td style="display:flex; gap:8px; flex-wrap:wrap;">
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="accion" value="cuota">
                            <input type="hidden" name="punto" value="<?= htmlspecialchars($d['punto']) ?>">
                            <input type="number" name="gb" min="1" step="1" placeholder="GB" style="width:60px;" required>
                            <button class="btn btn-primary" type="submit">Cuota</button>
                        </form>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('¿Desmontar <?= htmlspecialchars($d['punto']) ?>?')">
                            <input type="hidden" name="accion" value="desmontar">
                            <input type="hidden" name="punto" value="<?= htmlspecialchars($d['punto']) ?>">
                            <button class="btn" type="submit">Desmontar</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <?php endif; ?>
        </div>

        <div class="seccion">
            <div class="seccion-header"><div class="seccion-title">Montar un disco</div></div>
            <form method="POST" class="form-card">
                <input type="hidden" name="accion" value="montar">
                <div class="field">
                    <label>Dispositivo</label>
                    <input type="text" name="dispositivo" required placeholder="/dev/sdb1">
                </div>
                <div class="field">
                    <label>Punto de montaje</label>
                    <input type="text" name="punto" required placeholder="/mnt/disco2">
                </div>
                <button class="btn btn-primary" type="submit">Montar</button>
            </form>
        </div>

    </main>
</div>
</body>
</html>
