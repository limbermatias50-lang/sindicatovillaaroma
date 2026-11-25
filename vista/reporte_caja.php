<?php $verTodo = true; /* Muestra el rango de fechas (desde y hasta) */ ?>
<div class="card mb-4">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="get">
            <input type="hidden" name="r" value="reportes/caja">
            <div class="col-6 col-md-3"><label class="form-label small"><?= $verTodo ? 'Desde' : 'Fecha' ?></label><input type="date" name="desde" class="form-control" value="<?= e($desde) ?>"></div><?php /* Fecha desde */ ?>
            <?php if ($verTodo): /* Si se usa rango... */ ?>
                <div class="col-6 col-md-3"><label class="form-label small">Hasta</label><input type="date" name="hasta" class="form-control" value="<?= e($hasta) ?>"></div><?php /* ...fecha hasta */ ?>
            <?php endif; /* Fin */ ?>
            <div class="col-6 col-md-3">
                <label class="form-label small">Grupo</label>
                <select name="grupo" class="form-select">
                    <option value="0">Todos</option>
                    <?php foreach ($grupos as $g): /* Una opción por grupo */ ?>
                        <option value="<?= $g['id_grupo'] ?>" <?= $grupo == $g['id_grupo'] ? 'selected' : '' ?>><?= e($g['nombre']) ?></option><?php /* Marca el grupo elegido */ ?>
                    <?php endforeach; /* Fin */ ?>
                </select>
            </div>
            <div class="col-6 col-md-3 d-flex gap-2">
                <button class="btn btn-primary flex-grow-1"><i class="bi bi-gear"></i> Generar</button>
                <a class="btn btn-danger flex-grow-1" target="_blank" href="<?= url('reportes/caja', ['desde' => $desde, 'hasta' => $hasta, 'grupo' => $grupo, 'pdf' => 1]) ?>"><i class="bi bi-file-earmark-pdf"></i> PDF</a><?php /* Botón para exportar a PDF con los mismos filtros */ ?>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between">
        <span><?= $desde === $hasta ? 'Caja del ' . fecha($desde) : 'Del ' . fecha($desde) . ' al ' . fecha($hasta) ?></span><?php /* Fecha o rango del reporte */ ?>
        <span>Total: <b class="text-success fs-5"><?= bs($total) ?></b> · <?= count($filas) ?> pago(s)</span><?php /* Total cobrado y cantidad de pagos */ ?>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Recibo</th><th>Fecha y hora</th><th>Chofer</th><th>Grupo</th><th>Concepto</th><th>Cajero</th><th class="text-end">Monto</th></tr></thead>
                <tbody>
                <?php foreach ($filas as $f): /* Una fila por pago */ ?>
                    <tr>
                        <td class="text-nowrap"><a target="_blank" href="<?= url('pagos/recibo', ['id' => $f['id_pago']]) ?>"><?= e($f['nro_recibo']) ?></a></td><?php /* N° de recibo: enlace al recibo PDF */ ?>
                        <td class="text-nowrap"><?= fecha($f['fecha_pago'], true) ?></td><?php /* Fecha y hora */ ?>
                        <td><?= e($f['chofer']) ?></td><?php /* Chofer */ ?>
                        <td class="text-nowrap"><?= e($f['grupo']) ?></td><?php /* Grupo */ ?>
                        <td><?= e($f['concepto']) ?></td><?php /* Concepto */ ?>
                        <td class="small"><?= e($f['cajero']) ?></td><?php /* Cajero que cobró */ ?>
                        <td class="text-end"><?= bs($f['monto']) ?></td><?php /* Monto */ ?>
                    </tr>
                <?php endforeach; /* Fin */ ?>
                <?php if (!$filas): /* Si no hay pagos... */ ?>
                    <tr><td colspan="7" class="text-center text-muted py-4"><i class="bi bi-info-circle"></i> <?= e(ReporteControlador::SIN_DATOS) ?></td></tr><?php /* ..."No existen datos para el periodo seleccionado" */ ?>
                <?php endif; /* Fin */ ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
