<?php /** Panel configurable de cuotas por grupo (Figura 12 — wireframe de gestión de cuotas). */ ?>
<!-- Resumen por grupo -->
<div class="row g-3 mb-4">
    <?php foreach ($resumen as $r): $total = $r['recaudado'] + $r['pendiente']; $pct = $total > 0 ? round($r['recaudado'] * 100 / $total) : 0; /* Una tarjeta por grupo: calcula el % cobrado */ ?>
        <div class="col-6 col-lg-3">
            <div class="card kpi h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between"><b><?= e($r['nombre']) ?></b><small class="text-muted"><?= $pct ?>%</small></div><?php /* Nombre del grupo y % cobrado */ ?>
                    <div class="progress my-2" style="height: 6px"><div class="progress-bar bg-success" style="width: <?= $pct ?>%"></div></div><?php /* Barra de avance */ ?>
                    <div class="small">Recaudado <b class="text-success"><?= bs($r['recaudado']) ?></b></div><?php /* Recaudado */ ?>
                    <div class="small">Pendiente <b class="text-danger"><?= bs($r['pendiente']) ?></b></div><?php /* Pendiente */ ?>
                </div>
            </div>
        </div>
    <?php endforeach; /* Fin */ ?>
</div>

<div class="card">
    <div class="card-body">
        <form class="row g-2 align-items-end mb-3" method="get">
            <input type="hidden" name="r" value="cuotas">
            <div class="col-6 col-md-3">
                <label class="form-label small">Grupo</label>
                <select name="grupo" class="form-select">
                    <option value="0">Todos</option>
                    <?php foreach ($grupos as $g): /* Una opción por grupo */ ?>
                        <option value="<?= $g['id_grupo'] ?>" <?= $filtro['grupo'] == $g['id_grupo'] ? 'selected' : '' ?>><?= e($g['nombre']) ?></option><?php /* Marca el grupo elegido */ ?>
                    <?php endforeach; /* Fin */ ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small">Tipo</label>
                <select name="tipo" class="form-select">
                    <option value="">Todas</option>
                    <option value="ordinaria" <?= $filtro['tipo'] === 'ordinaria' ? 'selected' : '' ?>>Ordinarias</option><?php /* Filtro: ordinarias */ ?>
                    <option value="extraordinaria" <?= $filtro['tipo'] === 'extraordinaria' ? 'selected' : '' ?>>Extraordinarias</option><?php /* Filtro: extraordinarias */ ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small">Estado</label>
                <select name="estado" class="form-select">
                    <option value="vigente" <?= $filtro['estado'] === 'vigente' ? 'selected' : '' ?>>Vigentes</option><?php /* Filtro: vigentes */ ?>
                    <option value="anulada" <?= $filtro['estado'] === 'anulada' ? 'selected' : '' ?>>Anuladas</option><?php /* Filtro: anuladas */ ?>
                </select>
            </div>
            <div class="col-6 col-md-4 d-flex gap-2">
                <button class="btn btn-outline-primary flex-grow-1"><i class="bi bi-funnel"></i> Filtrar</button>
                <?php if ($puedeParametrizar): /* El botón "Nueva cuota" solo lo ve ventanilla */ ?>
                    <button type="button" class="btn btn-primary flex-grow-1" data-bs-toggle="modal" data-bs-target="#modalCuota" id="btn-nueva">
                        <i class="bi bi-plus-lg"></i> Nueva cuota
                    </button>
                <?php endif; /* Fin */ ?>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr><th>Concepto</th><th>Grupo</th><th>Tipo</th><th class="text-end">Monto</th><th>Periodo</th><th>Fecha límite</th><th>Avance</th><th class="text-end">Por cobrar</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($cuotas as $c): $pct = $c['obligados'] ? round($c['pagados'] * 100 / $c['obligados']) : 0; /* Una fila por cuota: calcula el % de choferes que ya pagaron */ ?>
                    <tr>
                        <td>
                            <span class="fw-semibold"><?= e($c['concepto']) ?></span><?php /* Concepto */ ?>
                            <?php if ($c['acta_referencia']): ?><div class="small text-muted"><i class="bi bi-journal"></i> <?= e($c['acta_referencia']) ?></div><?php endif; /* Acta de asamblea que la aprobó */ ?>
                        </td>
                        <td><?= e($c['grupo']) ?></td><?php /* Grupo */ ?>
                        <td><span class="badge <?= $c['tipo'] === 'extraordinaria' ? 'text-bg-warning' : 'text-bg-light border' ?>"><?= e($c['tipo']) ?></span></td><?php /* Tipo (extraordinaria en amarillo) */ ?>
                        <td class="text-end"><?= bs($c['monto']) ?></td><?php /* Monto */ ?>
                        <td class="text-nowrap"><?= e(nombre_periodo($c['fecha_limite'])) ?></td><?php /* Periodo (mes y año de la fecha límite) */ ?>
                        <td class="<?= $c['fecha_limite'] < date('Y-m-d') ? 'text-danger' : '' ?>"><?= fecha($c['fecha_limite']) ?></td><?php /* Fecha límite (roja si ya pasó) */ ?>
                        <td style="min-width: 120px">
                            <div class="progress" style="height: 6px"><div class="progress-bar bg-success" style="width: <?= $pct ?>%"></div></div><?php /* Barra de avance de cobro */ ?>
                            <small class="text-muted"><?= (int) $c['pagados'] ?>/<?= (int) $c['obligados'] ?> pagaron</small><?php /* Cuántos pagaron de cuántos deben */ ?>
                        </td>
                        <td class="text-end <?= $c['por_cobrar'] > 0 ? 'text-danger' : 'text-success' ?>"><?= bs($c['por_cobrar']) ?></td><?php /* Lo que falta cobrar */ ?>
                        <td class="text-end text-nowrap">
                            <?php if ($puedeParametrizar && $c['estado'] === 'vigente'): /* Botones solo para cuotas vigentes y para ventanilla: */ ?>
                                <button class="btn btn-sm btn-light btn-editar" data-cuota='<?= e(json_encode($c)) ?>' title="Modificar"><i class="bi bi-pencil"></i></button><?php /* Modificar: lleva los datos de la cuota en JSON */ ?>
                                <form method="post" action="<?= url('cuotas/eliminar') ?>" class="d-inline" data-confirm="¿Eliminar la cuota «<?= e($c['concepto']) ?>»?" data-confirm-texto="<?= $c['pagados'] > 0 ? 'Ya tiene pagos cobrados: se anulará y se quitarán solo las deudas pendientes.' : 'Se borrará la cuota y las deudas asignadas a los choferes.' ?>"><?php /* Eliminar (pide confirmación) */ ?>
                                    <?= Csrf::campo() ?><input type="hidden" name="id" value="<?= $c['id_cuota'] ?>"><?php /* Token CSRF y número de la cuota */ ?>
                                    <button class="btn btn-sm btn-light text-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                                </form>
                            <?php endif; /* Fin */ ?>
                        </td>
                    </tr>
                <?php endforeach; /* Fin de la lista */ ?>
                <?php if (!$cuotas): /* Si no hay cuotas... */ ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">No hay cuotas para los filtros seleccionados.</td></tr>
                <?php endif; /* Fin */ ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($puedeParametrizar): /* Ventana para registrar cuotas solo para ventanilla */ ?>
<!-- Modal: registrar / modificar cuota -->
<div class="modal fade" id="modalCuota" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content needs-validation" method="post" action="<?= url('cuotas/guardar') ?>" id="form-cuota" novalidate><?php /* Envía a cuotas/guardar */ ?>
            <?= Csrf::campo() /* Token CSRF */ ?>
            <input type="hidden" name="id_cuota" value="">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-titulo"><i class="bi bi-cash-coin"></i> Nueva cuota</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Tipo *</label>
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="tipo" id="t-ord" value="ordinaria" checked>
                        <label class="btn btn-outline-primary" for="t-ord">Ordinaria</label>
                        <input type="radio" class="btn-check" name="tipo" id="t-ext" value="extraordinaria">
                        <label class="btn btn-outline-primary" for="t-ext">Extraordinaria</label>
                    </div>
                </div>
                <div class="mb-3"><label class="form-label">Concepto *</label><input name="concepto" class="form-control" required maxlength="120" placeholder="Cuota mensual 10/2026"></div>
                <div class="row g-3 mb-3">
                    <div class="col-6"><label class="form-label">Monto (Bs) *</label><input name="monto" type="number" step="0.01" min="1" max="10000" class="form-control" required><div class="invalid-feedback">Entre Bs 1 y Bs 10.000.</div></div>
                    <div class="col-6"><label class="form-label">Periodo (fecha límite) *</label><input name="fecha_limite" type="date" class="form-control" required min="<?= Bolivia::FUNDACION ?>" max="<?= date('Y-m-d', strtotime('+2 years')) ?>"></div><?php /* Periodo = fecha límite de pago */ ?>
                </div>
                <div class="mb-3"><label class="form-label">Aprobada en (acta / asamblea)</label><input name="acta_referencia" class="form-control" maxlength="120" placeholder="Asamblea extraordinaria N° 4"></div>
                <div id="bloque-grupos">
                    <label class="form-label">Aplicar a los grupos *</label>
                    <div class="border rounded p-2">
                        <div class="form-check"><input class="form-check-input" type="checkbox" id="todos-grupos"><label class="form-check-label fw-semibold" for="todos-grupos">Todos los grupos</label></div>
                        <?php foreach ($grupos as $g): /* Una casilla por grupo */ ?>
                            <div class="form-check">
                                <input class="form-check-input chk-grupo" type="checkbox" name="grupos[]" value="<?= $g['id_grupo'] ?>" id="g<?= $g['id_grupo'] ?>"><?php /* Casilla del grupo */ ?>
                                <label class="form-check-label" for="g<?= $g['id_grupo'] ?>"><?= e($g['nombre']) ?> <small class="text-muted"><?= e($g['ruta']) ?></small></label><?php /* Nombre del grupo */ ?>
                            </div>
                        <?php endforeach; /* Fin */ ?>
                    </div>
                    <div class="form-text">Se generará la deuda para cada chofer activo de los grupos seleccionados.</div>
                </div>
                <div class="alert alert-info small d-none mt-2" id="aviso-edicion">El nuevo monto se aplicará solo a los choferes que aún no pagaron.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary"><i class="bi bi-save"></i> Guardar</button>
            </div>
        </form>
    </div>
</div>
<script>
    (() => {
        const f = document.getElementById('form-cuota');
        const rutaGuardar = f.action;
        const rutaActualizar = rutaGuardar.replace('cuotas%2Fguardar', 'cuotas%2Factualizar').replace('cuotas/guardar', 'cuotas/actualizar');
        document.getElementById('todos-grupos').addEventListener('change', e =>
            document.querySelectorAll('.chk-grupo').forEach(c => c.checked = e.target.checked));

        document.getElementById('btn-nueva').addEventListener('click', () => {
            f.reset(); f.action = rutaGuardar; f.id_cuota.value = '';
            document.getElementById('modal-titulo').innerHTML = '<i class="bi bi-cash-coin"></i> Nueva cuota';
            document.getElementById('bloque-grupos').classList.remove('d-none');
            document.getElementById('aviso-edicion').classList.add('d-none');
        });

        document.querySelectorAll('.btn-editar').forEach(btn => btn.addEventListener('click', () => {
            const c = JSON.parse(btn.dataset.cuota);
            f.reset(); f.action = rutaActualizar;
            f.id_cuota.value = c.id_cuota; f.concepto.value = c.concepto; f.monto.value = c.monto;
            f.fecha_limite.value = c.fecha_limite; f.acta_referencia.value = c.acta_referencia || '';
            f.querySelector(`[name=tipo][value=${c.tipo}]`).checked = true;
            document.getElementById('modal-titulo').innerHTML = '<i class="bi bi-pencil"></i> Modificar cuota — ' + App.escapar(c.grupo);
            document.getElementById('bloque-grupos').classList.add('d-none');
            document.getElementById('aviso-edicion').classList.remove('d-none');
            bootstrap.Modal.getOrCreateInstance('#modalCuota').show();
        }));
    })();
</script>
<?php endif; /* Fin */ ?>
