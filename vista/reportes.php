<?php /* Emisión de reportes — Figura 44 (interfaz abstracta) y Tabla 33 (prueba). */ ?>
<div class="card mb-4">
    <div class="card-header fw-semibold">Generar reporte</div>
    <div class="card-body">
        <form class="row g-2 align-items-end" method="get" id="form-reporte">
            <input type="hidden" name="r" value="reportes">
            <div class="col-md-4">
                <label class="form-label small" for="tipo">Tipo de reporte</label>
                <select name="tipo" id="tipo" class="form-select">
                    <?php foreach (ReporteControlador::TIPOS as $k => $t): /* Una opción por tipo de reporte */ ?>
                        <option value="<?= $k ?>" <?= $tipo === $k ? 'selected' : '' ?>><?= e($t) ?></option><?php /* Marca el tipo elegido */ ?>
                    <?php endforeach; /* Fin */ ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small" for="grupo">Grupo</label>
                <select name="grupo" id="grupo" class="form-select">
                    <option value="0">Todos los grupos</option>
                    <?php foreach ($grupos as $g): /* Una opción por grupo */ ?>
                        <option value="<?= $g['id_grupo'] ?>" <?= $grupo == $g['id_grupo'] ? 'selected' : '' ?>><?= e($g['nombre']) ?></option><?php /* Marca el grupo elegido */ ?>
                    <?php endforeach; /* Fin */ ?>
                </select>
            </div>
            <div class="col-6 col-md-2"><label class="form-label small" for="desde">Desde</label><input type="date" id="desde" name="desde" class="form-control" value="<?= e($desde) ?>"></div><?php /* Fecha desde */ ?>
            <div class="col-6 col-md-2"><label class="form-label small" for="hasta">Hasta</label><input type="date" id="hasta" name="hasta" class="form-control" value="<?= e($hasta) ?>"></div><?php /* Fecha hasta */ ?>
            <div class="col-12 d-flex gap-2 mt-3">
                <button class="btn btn-primary px-4"><i class="bi bi-gear"></i> Generar</button>
                <button class="btn btn-danger px-4" name="pdf" value="1" formtarget="_blank"><i class="bi bi-file-earmark-pdf"></i> Exportar PDF</button>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card kpi kpi-verde h-100"><div class="card-body">
        <div class="text-muted small">Ingresos del día</div><div class="kpi-valor text-success"><?= bs($kpi['hoy']) ?></div><?php /* Ingresos del día */ ?>
    </div></div></div>
    <div class="col-md-4"><div class="card kpi h-100"><div class="card-body">
        <div class="text-muted small">Total recaudado en el periodo</div><div class="kpi-valor"><?= bs($kpi['total']) ?></div><?php /* Total cobrado en el periodo */ ?>
    </div></div></div>
    <div class="col-md-4"><div class="card kpi kpi-rojo h-100"><div class="card-body">
        <div class="text-muted small">Afiliados en mora</div><div class="kpi-valor text-danger"><?= (int) $kpi['mora'] ?></div><?php /* Afiliados en mora */ ?>
    </div></div></div>
</div>

<div class="card">
    <div class="card-header">Consolidado por grupo de transporte <span class="text-muted small">— cuotas con fecha límite del <?= fecha($desde) ?> al <?= fecha($hasta) ?></span></div><?php /* Título de la tabla con el periodo */ ?>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Grupo</th><th class="text-center">Cuotas</th><th class="text-end">Pagado</th><th class="text-end">Pendiente</th><th class="text-end">% Mora</th></tr></thead>
                <tbody>
                <?php foreach ($filas as $r): /* Una fila por grupo */ ?>
                    <tr>
                        <td><a href="<?= url('reportes/estado-cuenta', ['grupo' => $r['id_grupo'], 'desde' => $desde, 'hasta' => $hasta]) ?>"><?= e($r['nombre']) ?></a></td><?php /* Nombre del grupo: enlace a su estado de cuenta */ ?>
                        <td class="text-center"><?= (int) $r['cuotas'] ?></td><?php /* Cantidad de cuotas */ ?>
                        <td class="text-end text-success"><?= bs($r['recaudado']) ?></td><?php /* Pagado */ ?>
                        <td class="text-end"><?= bs($r['pendiente']) ?></td><?php /* Pendiente */ ?>
                        <td class="text-end"><span class="badge text-bg-<?= $r['porc_mora'] >= 30 ? 'danger' : ($r['porc_mora'] > 0 ? 'warning' : 'success') ?>"><?= num($r['porc_mora']) ?> %</span></td><?php /* % de mora con color: rojo 30% o más, amarillo si hay mora, verde si no */ ?>
                    </tr>
                <?php endforeach; /* Fin */ ?>
                <?php if ($sinDatos): /* Si no hay datos... */ ?>
                    <tr><td colspan="5" class="text-center text-muted py-4"><i class="bi bi-info-circle"></i> <?= e(ReporteControlador::SIN_DATOS) ?></td></tr><?php /* ..."No existen datos para el periodo seleccionado" */ ?>
                <?php endif; /* Fin */ ?>
                </tbody>
                <?php if (!$sinDatos): /* Fila de totales solo si hay datos */ ?>
                <tfoot class="fw-bold">
                    <tr>
                        <td>Total</td>
                        <td class="text-center"><?= array_sum(array_column($filas, 'cuotas')) ?></td><?php /* Total de cuotas */ ?>
                        <td class="text-end"><?= bs(array_sum(array_column($filas, 'recaudado'))) ?></td><?php /* Total pagado */ ?>
                        <td class="text-end"><?= bs(array_sum(array_column($filas, 'pendiente'))) ?></td><?php /* Total pendiente */ ?>
                        <td></td>
                    </tr>
                </tfoot>
                <?php endif; /* Fin */ ?>
            </table>
        </div>
    </div>
</div>
