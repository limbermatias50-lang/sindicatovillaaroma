<?php /* Alertas de vencimiento — Figura 29 (interfaz abstracta) y Tabla 26 (prueba). */ ?>
<div class="card mb-4">
    <div class="card-header fw-semibold">Licencias vencidas y por vencer (<?= $dias ?> días)</div><?php /* Días de anticipación de la alerta */ ?>
    <div class="card-body">
        <form class="row g-2 align-items-end" method="get">
            <input type="hidden" name="r" value="alertas">
            <div class="col-md-5">
                <label class="form-label small" for="grupo">Grupo de transporte</label>
                <select name="grupo" id="grupo" class="form-select">
                    <option value="0">Todos los grupos</option>
                    <?php foreach ($grupos as $g): /* Una opción por grupo */ ?>
                        <option value="<?= $g['id_grupo'] ?>" <?= $grupo == $g['id_grupo'] ? 'selected' : '' ?>><?= e($g['nombre']) ?></option><?php /* Marca el grupo elegido */ ?>
                    <?php endforeach; /* Fin */ ?>
                </select>
            </div>
            <div class="col-md-3"><button class="btn btn-primary w-100"><i class="bi bi-funnel"></i> Filtrar</button></div>
        </form>
    </div>
</div>

<?php
$vencidas = array_filter($licencias, fn($l) => $l['dias_restantes'] < 0);   // Licencias ya vencidas (días negativos)
$porVencer = array_filter($licencias, fn($l) => $l['dias_restantes'] >= 0);   // Licencias por vencer
?>
<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card kpi kpi-rojo"><div class="card-body"><div class="text-muted small">Licencias vencidas</div><div class="kpi-valor text-danger"><?= count($vencidas) ?></div></div></div></div><?php /* Cantidad de vencidas */ ?>
    <div class="col-md-4"><div class="card kpi kpi-amarillo"><div class="card-body"><div class="text-muted small">Por vencer</div><div class="kpi-valor"><?= count($porVencer) ?></div></div></div></div><?php /* Cantidad por vencer */ ?>
    <div class="col-md-4"><div class="card kpi"><div class="card-body"><div class="text-muted small">Choferes sin licencia registrada</div><div class="kpi-valor"><?= count($sinLicencia) ?></div></div></div></div><?php /* Choferes sin licencia */ ?>
</div>

<div class="card mb-3">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 tabla-seleccion">
                <thead><tr><th style="width:2.5rem"></th><th>Chofer</th><th>Nro. licencia</th><th>Vence</th><th class="text-center">Días</th><th>Estado</th></tr></thead>
                <tbody>
                <?php foreach ($licencias as $l): $est = estado_licencia($l['fecha_vencimiento']); /* Una fila por licencia, ordenadas por fecha; calcula su color */ ?>
                    <tr data-url="<?= url('choferes/ver', ['id' => $l['id_chofer']]) ?>#tab-docs"><?php /* Dirección de su expediente (pestaña documentos) */ ?>
                        <td><input class="form-check-input" type="radio" name="sel" aria-label="Seleccionar <?= e($l['chofer']) ?>"></td><?php /* Botón para seleccionar la fila */ ?>
                        <td class="fw-semibold"><?= e($l['chofer']) ?> <small class="text-muted d-block"><?= e($l['grupo']) ?> · Cel. <?= e($l['telefono']) ?></small></td><?php /* Nombre, grupo y celular */ ?>
                        <td><?= e($l['numero']) ?> <small class="text-muted">(<?= e($l['categoria']) ?>)</small></td><?php /* Número y categoría */ ?>
                        <td><?= fecha($l['fecha_vencimiento']) ?></td><?php /* Fecha de vencimiento */ ?>
                        <td class="text-center"><?= (int) $l['dias_restantes'] ?></td><?php /* Días que faltan (negativo = vencida) */ ?>
                        <td><span class="badge text-bg-<?= $est['clase'] ?>"><i class="bi bi-circle-fill small"></i> <?= e($est['texto']) ?></span></td><?php /* Estado con color: rojo vencida, amarillo por vencer */ ?>
                    </tr>
                <?php endforeach; /* Fin */ ?>
                <?php if (!$licencias): /* Si no hay alertas... */ ?>
                    <tr><td colspan="6" class="text-center text-success py-4"><i class="bi bi-check-circle"></i> No hay licencias vencidas ni por vencer en los próximos <?= $dias ?> días.</td></tr>
                <?php endif; /* Fin */ ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<div class="d-flex gap-2 mb-4">
    <button type="button" class="btn btn-outline-primary px-4 btn-ver-seleccion" disabled><i class="bi bi-folder2-open"></i> Ver expediente</button>
    <a class="btn btn-danger px-4 <?= $licencias ? '' : 'disabled' ?>" target="_blank" href="<?= url('alertas', ['grupo' => $grupo, 'pdf' => 1]) ?>"><i class="bi bi-file-earmark-pdf"></i> Exportar lista</a><?php /* Exportar la lista a PDF (desactivado si no hay datos) */ ?>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-file-earmark-text text-warning"></i> Otros documentos con vencimiento</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($documentos as $d): $est = estado_licencia($d['fecha_vencimiento']); /* Otros documentos por vencer */ ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><a href="<?= url('documentos/ver', ['id' => $d['id_expediente']]) ?>" target="_blank"><?= e($d['titulo']) ?></a><?php /* Enlace al documento */ ?>
                            <small class="text-muted d-block"><?= e($d['chofer'] ?? 'Institucional') ?></small></span><?php /* Chofer o institucional */ ?>
                        <span class="badge text-bg-<?= $est['clase'] ?>"><?= e($est['texto']) ?></span><?php /* Estado con color */ ?>
                    </li>
                <?php endforeach; /* Fin */ ?>
                <?php if (!$documentos): ?><li class="list-group-item text-muted text-center py-3">Sin documentos por vencer.</li><?php endif; /* Si no hay documentos por vencer */ ?>
            </ul>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-question-circle"></i> Choferes sin licencia registrada</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($sinLicencia as $s): /* Choferes que aún no registraron su licencia */ ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <a href="<?= url('choferes/ver', ['id' => $s['id_chofer']]) ?>"><?= e($s['chofer']) ?></a><?php /* Enlace a su expediente */ ?>
                        <small class="text-muted"><?= e($s['grupo']) ?></small><?php /* Grupo */ ?>
                    </li>
                <?php endforeach; /* Fin */ ?>
                <?php if (!$sinLicencia): ?><li class="list-group-item text-muted text-center py-3">Todos tienen licencia registrada.</li><?php endif; /* Si todos tienen licencia */ ?>
            </ul>
        </div>
    </div>
</div>
