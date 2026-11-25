<div class="card">
    <div class="card-body">
        <form class="row g-2 align-items-end mb-3" method="get">
            <input type="hidden" name="r" value="choferes">
            <div class="col-md-5">
                <label class="form-label small">Buscar</label>
                <input type="search" name="buscar" class="form-control" value="<?= e($filtro['buscar']) ?>" placeholder="C.I., nombre o código QR"><?php /* Muestra lo que se buscó (escapado con e() contra XSS) */ ?>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small">Grupo</label>
                <select name="grupo" class="form-select">
                    <option value="0">Todos</option>
                    <?php foreach ($grupos as $g): /* Una opción por cada grupo */ ?>
                        <option value="<?= $g['id_grupo'] ?>" <?= $filtro['grupo'] == $g['id_grupo'] ? 'selected' : '' ?>><?= e($g['nombre']) ?></option><?php /* Marca el grupo elegido en el filtro */ ?>
                    <?php endforeach; /* Fin de los grupos */ ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small">Estado</label>
                <select name="estado" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach (['activo', 'suspendido', 'inactivo'] as $es): /* Una opción por cada estado */ ?>
                        <option value="<?= $es ?>" <?= $filtro['estado'] === $es ? 'selected' : '' ?>><?= ucfirst($es) ?></option><?php /* Marca el estado elegido */ ?>
                    <?php endforeach; /* Fin de los estados */ ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-outline-primary flex-grow-1"><i class="bi bi-search"></i> Filtrar</button>
                <?php if (Auth::tiene('ventanilla')): /* El botón "Nuevo" solo lo ve ventanilla */ ?>
                    <a href="<?= url('choferes/nuevo') ?>" class="btn btn-primary flex-grow-1"><i class="bi bi-person-plus"></i> Nuevo</a><?php /* Lleva al formulario de chofer nuevo */ ?>
                <?php endif; /* Fin */ ?>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                <tr>
                    <th>Chofer</th><th>C.I.</th><th>Grupo</th><th>Teléfono</th><th>Licencia</th><th class="text-end">Deuda</th><th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($choferes as $c): $lic = estado_licencia($c['fecha_vencimiento']); /* Una fila por chofer; calcula el estado de su licencia */ ?>
                    <tr>
                        <td>
                            <a href="<?= url('choferes/ver', ['id' => $c['id_chofer']]) ?>" class="fw-semibold text-decoration-none"><?php /* Enlace a su expediente */ ?>
                                <?= e($c['apellidos'] . ' ' . $c['nombres']) /* Apellidos y nombres */ ?>
                            </a>
                            <?php if ($c['estado'] !== 'activo'): /* Si no está activo... */ ?>
                                <span class="badge text-bg-secondary text-capitalize"><?= e($c['estado']) ?></span><?php /* ...muestra su estado en gris */ ?>
                            <?php endif; /* Fin */ ?>
                            <div class="small text-muted"><?= e($c['codigo_qr']) ?></div><?php /* Código de su credencial QR */ ?>
                        </td>
                        <td class="text-nowrap"><?= e($c['ci']) ?> <small class="text-muted"><?= e($c['ci_expedido']) ?></small></td><?php /* C.I. y departamento */ ?>
                        <td><?= e($c['grupo']) ?></td><?php /* Grupo */ ?>
                        <td><?= $c['telefono'] ? e($c['telefono']) : '<span class="text-muted">—</span>' ?></td><?php /* Celular (o raya si no tiene) */ ?>
                        <td><span class="badge text-bg-<?= $lic['clase'] ?>"><?= e($lic['texto']) ?></span></td><?php /* Estado de la licencia con su color */ ?>
                        <td class="text-end <?= $c['deuda'] > 0 ? 'text-danger fw-semibold' : 'text-success' ?>"><?= bs($c['deuda']) ?></td><?php /* Deuda: roja si debe, verde si está al día */ ?>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-light" href="<?= url('choferes/ver', ['id' => $c['id_chofer']]) ?>" title="Ver expediente"><i class="bi bi-eye"></i></a><?php /* Botón: ver expediente */ ?>
                            <?php if (Auth::tiene('ventanilla')): /* Botones solo para ventanilla: */ ?>
                                <a class="btn btn-sm btn-light" href="<?= url('choferes/credencial', ['id' => $c['id_chofer']]) ?>" target="_blank" title="Imprimir credencial QR"><i class="bi bi-qr-code"></i></a><?php /* imprimir credencial QR */ ?>
                                <a class="btn btn-sm btn-light" href="<?= url('choferes/editar', ['id' => $c['id_chofer']]) ?>" title="Editar"><i class="bi bi-pencil"></i></a><?php /* editar */ ?>
                            <?php endif; /* Fin */ ?>
                        </td>
                    </tr>
                <?php endforeach; /* Fin de la lista */ ?>
                <?php if (!$choferes): /* Si no hay choferes... */ ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No se encontraron choferes.</td></tr>
                <?php endif; /* Fin */ ?>
                </tbody>
            </table>
        </div>
        <div class="small text-muted"><?= count($choferes) ?> chofer(es)</div><?php /* Cantidad de choferes mostrados */ ?>
    </div>
</div>
