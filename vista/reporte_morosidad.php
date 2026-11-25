<?php /* Estado de morosidad — Figura 38 (interfaz abstracta) y Tabla 30 (prueba). */ ?>
<div class="card mb-4">
    <div class="card-header fw-semibold">Consultar estado de morosidad por grupo</div>
    <div class="card-body">
        <form class="row g-2 align-items-end" method="get">
            <input type="hidden" name="r" value="reportes/morosidad">
            <div class="col-md-5">
                <label class="form-label small" for="grupo">Grupo de transporte</label>
                <select name="grupo" id="grupo" class="form-select">
                    <option value="0">Todos los grupos</option>
                    <?php foreach ($grupos as $g): /* Una opción por grupo */ ?>
                        <option value="<?= $g['id_grupo'] ?>" <?= $grupo == $g['id_grupo'] ? 'selected' : '' ?>><?= e($g['nombre']) ?></option><?php /* Marca el grupo elegido */ ?>
                    <?php endforeach; /* Fin */ ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small" for="periodo">Periodo</label>
                <select name="periodo" id="periodo" class="form-select">
                    <option value="">Todos los periodos</option>
                    <?php foreach ($periodos as $p): /* Una opción por periodo (mes y año) */ ?>
                        <option value="<?= e($p) ?>" <?= $periodo === $p ? 'selected' : '' ?>><?= e(nombre_periodo($p)) ?></option><?php /* Marca el periodo elegido */ ?>
                    <?php endforeach; /* Fin */ ?>
                </select>
            </div>
            <div class="col-md-3"><button class="btn btn-primary w-100"><i class="bi bi-search"></i> Consultar</button></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between">
        <span><?= count($filas) ?> chofer(es) con cuotas pendientes</span><?php /* Cantidad de choferes con deuda */ ?>
        <span>Total adeudado: <b class="text-danger"><?= bs($total) ?></b></span><?php /* Total adeudado */ ?>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 tabla-seleccion">
                <thead><tr><th style="width:2.5rem"></th><th>Chofer</th><th>C.I.</th><th class="text-center">Cuotas pendientes</th><th class="text-end">Monto (Bs.)</th><th>Estado</th></tr></thead>
                <tbody>
                <?php foreach ($filas as $f): $est = estado_mora($f); /* Una fila por chofer; calcula su estado (En mora o Pendiente) */ ?>
                    <tr data-url="<?= url('choferes/ver', ['id' => $f['id_chofer']]) ?>"><?php /* Dirección de su expediente (botón Ver detalle) */ ?>
                        <td><input class="form-check-input" type="radio" name="sel" aria-label="Seleccionar <?= e($f['chofer']) ?>"></td><?php /* Botón para seleccionar la fila */ ?>
                        <td class="fw-semibold"><?= e($f['chofer']) ?> <small class="text-muted d-block"><?= e($f['grupo']) ?></small></td><?php /* Nombre y grupo */ ?>
                        <td><?= e($f['ci']) ?></td><?php /* C.I. */ ?>
                        <td class="text-center"><?= (int) $f['cuotas_pendientes'] ?></td><?php /* Cuántas cuotas debe */ ?>
                        <td class="text-end fw-semibold"><?= num($f['monto_pendiente']) ?></td><?php /* Monto que debe */ ?>
                        <td><span class="badge text-bg-<?= $est['clase'] ?>"><?= e($est['texto']) ?></span></td><?php /* Estado con color */ ?>
                    </tr>
                <?php endforeach; /* Fin */ ?>
                <?php if (!$filas): ?><tr><td colspan="6" class="text-center text-muted py-4"><i class="bi bi-info-circle"></i> <?= e(ReporteControlador::SIN_DATOS) ?></td></tr><?php endif; /* Si no hay nadie: "No existen datos para el periodo seleccionado" */ ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="d-flex gap-2 mt-3">
    <button type="button" class="btn btn-outline-primary px-4 btn-ver-seleccion" disabled><i class="bi bi-eye"></i> Ver detalle</button>
    <a class="btn btn-danger px-4 <?= $filas ? '' : 'disabled' ?>" target="_blank" href="<?= url('reportes/morosidad', ['grupo' => $grupo, 'periodo' => $periodo, 'pdf' => 1]) ?>"><i class="bi bi-file-earmark-pdf"></i> Exportar lista</a><?php /* Exportar la lista a PDF (desactivado si no hay datos) */ ?>
</div>
