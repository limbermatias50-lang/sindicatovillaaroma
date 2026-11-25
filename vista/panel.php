<?php $scripts = ['vista/lib/chart.umd.min.js']; /* Esta pantalla necesita la librería Chart.js 4.5.1 para los gráficos */ ?>
<h2 class="h6 fw-bold text-muted mb-3">Resumen general del sindicato</h2>
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="card kpi h-100"><div class="card-body d-flex justify-content-between">
            <div><div class="text-muted small">Choferes afiliados</div><div class="kpi-valor"><?= (int) $totalChoferes ?></div></div><?php /* Cantidad de choferes activos */ ?>
            <i class="bi bi-people kpi-icon text-primary"></i>
        </div></div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card kpi kpi-verde h-100"><div class="card-body d-flex justify-content-between">
            <div><div class="text-muted small">Recaudado en el mes</div><div class="kpi-valor text-success"><?= bs($cajaMes) ?></div><?php /* Recaudado en el mes, con formato Bs */ ?>
                <div class="small text-muted">Hoy: <?= bs($cajaHoy) ?></div></div><?php /* Recaudado hoy */ ?>
            <i class="bi bi-cash-stack kpi-icon text-success"></i>
        </div></div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card kpi kpi-rojo h-100"><div class="card-body d-flex justify-content-between">
            <div><div class="text-muted small">Afiliados en mora</div><div class="kpi-valor text-danger"><?= (int) $afiliadosMora ?></div></div><?php /* Cantidad de afiliados con cuotas vencidas */ ?>
            <i class="bi bi-exclamation-octagon kpi-icon text-danger"></i>
        </div></div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card kpi kpi-amarillo h-100"><div class="card-body d-flex justify-content-between">
            <div><div class="text-muted small">Licencias por vencer</div><div class="kpi-valor"><?= (int) $licPorVencer ?></div><?php /* Cantidad de licencias por vencer */ ?>
                <div class="small text-muted">Vencidas o en <?= DIAS_ALERTA_LICENCIA ?> días</div></div><?php /* Días de anticipación de la alerta */ ?>
            <i class="bi bi-person-vcard kpi-icon"></i>
        </div></div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-bar-chart text-primary"></i> Ingresos por grupo</div>
            <div class="card-body"><canvas id="grafico-grupos" height="240"></canvas></div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-pie-chart text-primary"></i> Pagos al día / en mora</div>
            <div class="card-body"><canvas id="grafico-aldia" height="240"></canvas></div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><i class="bi bi-graph-up-arrow text-primary"></i> Ingresos de los últimos 6 meses</div>
    <div class="card-body"><canvas id="grafico-mensual" height="90"></canvas></div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-exclamation-triangle text-warning"></i> Licencias vencidas o por vencer (próximos <?= DIAS_ALERTA_LICENCIA ?> días)</span><?php /* Título de la tabla de licencias */ ?>
        <?php if (Auth::tiene('ventanilla')): ?><a href="<?= url('alertas') ?>" class="btn btn-sm btn-outline-primary">Ver todas</a><?php endif; /* El botón "Ver todas" solo lo ve ventanilla (alertas es de su rol) */ ?>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Chofer</th><th>Grupo</th><th>N° licencia</th><th>Vencimiento</th><th>Estado</th></tr></thead>
                <tbody>
                <?php foreach (array_slice($alertas, 0, 8) as $a): $est = estado_licencia($a['fecha_vencimiento']); /* Muestra las primeras 8 licencias y calcula su estado (color) */ ?>
                    <tr>
                        <td><?php if (Auth::tiene('ventanilla')): ?><a class="text-decoration-none" href="<?= url('choferes/ver', ['id' => $a['id_chofer']]) ?>"><?= e($a['chofer']) ?></a><?php else: ?><?= e($a['chofer']) ?><?php endif; ?></td><?php /* Nombre del chofer (con enlace solo para ventanilla) */ ?>
                        <td><?= e($a['grupo']) ?></td><?php /* Grupo */ ?>
                        <td><?= e($a['numero']) ?></td><?php /* Número de licencia */ ?>
                        <td><?= fecha($a['fecha_vencimiento']) ?></td><?php /* Fecha de vencimiento */ ?>
                        <td><span class="badge text-bg-<?= $est['clase'] ?>"><?= e($est['texto']) ?></span></td><?php /* Estado con su color */ ?>
                    </tr>
                <?php endforeach; /* Fin de la lista */ ?>
                <?php if (!$alertas): /* Si no hay licencias por vencer... */ ?>
                    <tr><td colspan="5" class="text-center text-success py-4"><i class="bi bi-check-circle"></i> Todas las licencias están vigentes.</td></tr>
                <?php endif; /* Fin */ ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script type="application/json" id="datos-graficos"><?= json_encode(['mensual' => $mensual, 'grupos' => $resumenGrupos, 'alDia' => $alDiaMora], JSON_HEX_TAG) ?></script><?php /* Datos de los gráficos en JSON: JavaScript (Chart.js) los lee de aquí */ ?>
