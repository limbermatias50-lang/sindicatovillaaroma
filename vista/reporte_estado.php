<div class="card mb-4">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="get">
            <input type="hidden" name="r" value="reportes/estado-cuenta">
            <div class="col-md-4">
                <label class="form-label small">Grupo de transporte</label>
                <select name="grupo" class="form-select">
                    <?php foreach ($grupos as $g): /* Una opción por grupo */ ?>
                        <option value="<?= $g['id_grupo'] ?>" <?= ($grupo['id_grupo'] ?? 0) == $g['id_grupo'] ? 'selected' : '' ?>><?= e($g['nombre'] . ' — ' . $g['ruta']) ?></option><?php /* Marca el grupo elegido */ ?>
                    <?php endforeach; /* Fin */ ?>
                </select>
            </div>
            <div class="col-6 col-md-2"><label class="form-label small">Desde</label><input type="date" name="desde" class="form-control" value="<?= e($desde) ?>"></div><?php /* Fecha desde (opcional) */ ?>
            <div class="col-6 col-md-2"><label class="form-label small">Hasta</label><input type="date" name="hasta" class="form-control" value="<?= e($hasta) ?>"></div><?php /* Fecha hasta (opcional) */ ?>
            <div class="col-md-4 d-flex gap-2">
                <button class="btn btn-primary flex-grow-1"><i class="bi bi-gear"></i> Generar</button>
                <?php if ($grupo): /* Si hay un grupo elegido... */ ?>
                    <a class="btn btn-danger flex-grow-1 <?= $sinDatos ? 'disabled' : '' ?>" target="_blank" href="<?= url('reportes/estado-cuenta', array_filter(['grupo' => $grupo['id_grupo'], 'desde' => $desde, 'hasta' => $hasta, 'pdf' => 1])) ?>"><i class="bi bi-file-earmark-pdf"></i> Exportar PDF</a><?php /* ...botón para exportar a PDF (desactivado si no hay datos) */ ?>
                <?php endif; /* Fin */ ?>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="card kpi"><div class="card-body"><div class="small text-muted">Asignado</div><div class="kpi-valor"><?= bs($totales['asignado']) ?></div></div></div></div><?php /* Total asignado al grupo */ ?>
    <div class="col-6 col-md-3"><div class="card kpi kpi-verde"><div class="card-body"><div class="small text-muted">Pagado</div><div class="kpi-valor text-success"><?= bs($totales['pagado']) ?></div></div></div></div><?php /* Total pagado */ ?>
    <div class="col-6 col-md-3"><div class="card kpi kpi-amarillo"><div class="card-body"><div class="small text-muted">Pendiente</div><div class="kpi-valor"><?= bs($totales['pendiente']) ?></div></div></div></div><?php /* Total pendiente */ ?>
    <div class="col-6 col-md-3"><div class="card kpi kpi-rojo"><div class="card-body"><div class="small text-muted">En mora</div><div class="kpi-valor text-danger"><?= bs($totales['vencido']) ?></div></div></div></div><?php /* Total en mora */ ?>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Chofer</th><th>C.I.</th><th class="text-end">Asignado</th><th class="text-end">Pagado</th><th class="text-end">Pendiente</th><th class="text-end">En mora</th><th>Situación</th></tr></thead>
                <tbody>
                <?php foreach ($sinDatos ? [] : $filas as $f): /* Una fila por chofer (ninguna si no hay datos) */ ?>
                    <tr>
                        <td><a href="<?= url('choferes/ver', ['id' => $f['id_chofer']]) ?>"><?= e($f['chofer']) ?></a><?php /* Nombre: enlace a su expediente */ ?>
                            <?php if ($f['estado'] !== 'activo'): ?><span class="badge text-bg-secondary"><?= e($f['estado']) ?></span><?php endif; ?></td><?php /* Si no está activo, muestra su estado */ ?>
                        <td><?= e($f['ci']) ?></td><?php /* C.I. */ ?>
                        <td class="text-end"><?= bs($f['asignado']) ?></td><?php /* Asignado */ ?>
                        <td class="text-end text-success"><?= bs($f['pagado']) ?></td><?php /* Pagado */ ?>
                        <td class="text-end"><?= bs($f['pendiente']) ?></td><?php /* Pendiente */ ?>
                        <td class="text-end text-danger"><?= bs($f['vencido']) ?></td><?php /* En mora */ ?>
                        <td>
                            <?php if ($f['vencido'] > 0): ?><span class="badge text-bg-danger">Moroso</span><?php /* Si tiene deuda vencida: Moroso */ ?>
                            <?php elseif ($f['pendiente'] > 0): ?><span class="badge text-bg-warning">Con pendientes</span><?php /* Si debe pero dentro del plazo: Con pendientes */ ?>
                            <?php else: ?><span class="badge text-bg-success">Al día</span><?php endif; /* Si no debe: Al día */ ?>
                        </td>
                    </tr>
                <?php endforeach; /* Fin */ ?>
                <?php if ($sinDatos): ?><tr><td colspan="7" class="text-center text-muted py-4"><i class="bi bi-info-circle"></i> <?= e(ReporteControlador::SIN_DATOS) ?></td></tr><?php endif; /* Si no hay datos: "No existen datos para el periodo seleccionado" */ ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
