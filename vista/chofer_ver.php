<?php
$lic = estado_licencia($chofer['fecha_vencimiento']);   // Estado de la licencia (color y texto)
$puedeEditar = Auth::tiene('ventanilla');   // ¿Puede modificar? (solo el rol ventanilla)
$idc = (int) $chofer['id_chofer'];   // Número del chofer
?>
<div class="row g-4">
    <!-- Columna izquierda: resumen -->
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-body text-center">
                <div class="chofer-card d-flex justify-content-center mb-2">
                    <?php if (!empty($chofer['foto'])): /* Si tiene foto... */ ?>
                        <img src="<?= e(url('choferes/foto', ['c' => $chofer['codigo_qr'], 'v' => substr(md5($chofer['foto']), 0, 8)])) ?>" alt="Foto" class="rounded border" style="width: 84px; height: 100px; object-fit: cover"><?php /* ...la muestra (se pide con su código QR) */ ?>
                    <?php else: ?><div class="foto"><?= e(mb_substr($chofer['nombres'], 0, 1)) ?></div><?php endif; /* Si no, muestra la inicial de su nombre */ ?>
                </div>
                <h2 class="h5 mb-0"><?= e($chofer['nombres'] . ' ' . $chofer['apellidos']) ?></h2><?php /* Nombre completo */ ?>
                <div class="text-muted small mb-2">C.I. <?= e($chofer['ci'] . ' ' . $chofer['ci_expedido']) ?> · <?= e($chofer['codigo_qr']) ?></div><?php /* C.I., departamento y código QR */ ?>
                <span class="badge text-bg-primary"><?= e($chofer['grupo']) ?></span><?php /* Grupo */ ?>
                <span class="badge text-bg-<?= $chofer['estado'] === 'activo' ? 'success' : 'secondary' ?> text-capitalize"><?= e($chofer['estado']) ?></span><?php /* Estado con su color */ ?>
                <?php if ($puedeEditar): /* Botones solo para ventanilla: */ ?>
                    <div class="d-flex flex-wrap gap-2 justify-content-center mt-3">
                        <a class="btn btn-sm btn-outline-primary" target="_blank" href="<?= url('choferes/hoja', ['id' => $idc]) ?>"><i class="bi bi-person-vcard"></i> Ver hoja de datos</a><?php /* ver hoja de datos personales */ ?>
                        <a class="btn btn-sm btn-primary" target="_blank" href="<?= url('choferes/credencial', ['id' => $idc]) ?>"><i class="bi bi-qr-code"></i> Credencial QR</a><?php /* imprimir credencial QR */ ?>
                        <a class="btn btn-sm btn-outline-primary" href="<?= url('choferes/editar', ['id' => $idc]) ?>"><i class="bi bi-pencil"></i> Editar</a><?php /* editar datos */ ?>
                    </div>
                <?php endif; /* Fin */ ?>
            </div>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Teléfono</span><span><?= e($chofer['telefono'] ?: '—') ?></span></li><?php /* Teléfono */ ?>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Dirección</span><span class="text-end"><?= e($chofer['direccion'] ?: '—') ?></span></li><?php /* Dirección */ ?>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Nacimiento</span><span><?= fecha($chofer['fecha_nacimiento']) ?></span></li><?php /* Fecha de nacimiento */ ?>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Afiliado desde</span><span><?= fecha($chofer['fecha_afiliacion']) ?></span></li><?php /* Fecha de afiliación */ ?>
            </ul>
        </div>

        <div class="card mb-4 kpi <?= $saldo['pendiente'] > 0 ? 'kpi-rojo' : 'kpi-verde' ?>"><?php /* Tarjeta roja si debe, verde si está al día */ ?>
            <div class="card-body">
                <div class="text-muted small">Saldo pendiente</div>
                <div class="kpi-valor <?= $saldo['pendiente'] > 0 ? 'text-danger' : 'text-success' ?>"><?= bs($saldo['pendiente']) ?></div><?php /* Saldo pendiente */ ?>
                <div class="small">En mora: <b><?= bs($saldo['vencido']) ?></b> · Pagado: <b><?= bs($saldo['pagado']) ?></b></div><?php /* Monto en mora y monto pagado */ ?>
            </div>
        </div>

        <!-- Licencia de conducir -->
        <div class="card border-<?= $lic['clase'] ?>"><?php /* Borde con el color del estado de la licencia */ ?>
            <div class="card-header d-flex justify-content-between">
                <span><i class="bi bi-person-vcard"></i> Licencia de conducir</span>
                <span class="badge text-bg-<?= $lic['clase'] ?>"><?= e($lic['texto']) ?></span><?php /* Estado de la licencia */ ?>
            </div>
            <div class="card-body">
                <?php if ($licencia): /* Si tiene licencia registrada... */ ?>
                    <p class="mb-2 small">N° <b><?= e($licencia['numero']) ?></b> · Categoría <b><?= e($licencia['categoria']) ?></b><br><?php /* ...número y categoría */ ?>
                        Vence: <b><?= fecha($licencia['fecha_vencimiento']) ?></b></p><?php /* ...fecha de vencimiento */ ?>
                <?php endif; /* Fin */ ?>
                <?php if ($puedeEditar): /* Formulario de licencia solo para ventanilla */ ?>
                    <form method="post" action="<?= url('documentos/licencia') ?>" class="row g-2 needs-validation" novalidate><?php /* Envía a documentos/licencia por POST */ ?>
                        <?= Csrf::campo() /* Token CSRF */ ?>
                        <input type="hidden" name="id_chofer" value="<?= $idc ?>"><?php /* Número del chofer (oculto) */ ?>
                        <div class="col-7"><input class="form-control form-control-sm" value="<?= e($chofer['ci']) ?>" readonly title="En Bolivia el N° de licencia es el mismo del C.I."></div><?php /* N° de licencia = C.I. (no se puede cambiar) */ ?>
                        <div class="col-5">
                            <select name="categoria" class="form-select form-select-sm">
                                <?php foreach (Licencia::CATEGORIAS as $cat): /* Una opción por cada categoría */ ?>
                                    <option <?= ($licencia['categoria'] ?? 'B') === $cat ? 'selected' : '' ?>><?= $cat ?></option><?php /* Marca la categoría actual */ ?>
                                <?php endforeach; /* Fin */ ?>
                            </select>
                        </div>
                        <div class="col-6"><label class="form-label small mb-0">Emisión</label><input type="date" name="fecha_emision" class="form-control form-control-sm" max="<?= date('Y-m-d') ?>" value="<?= e($licencia['fecha_emision'] ?? '') ?>"></div><?php /* Fecha de emisión */ ?>
                        <div class="col-6"><label class="form-label small mb-0">Vencimiento *</label><input type="date" name="fecha_vencimiento" class="form-control form-control-sm" required value="<?= e($licencia['fecha_vencimiento'] ?? '') ?>"></div><?php /* Fecha de vencimiento (obligatoria) */ ?>
                        <div class="col-12"><button class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-arrow-repeat"></i> <?= $licencia ? 'Actualizar / renovar' : 'Registrar licencia' ?></button></div><?php /* Texto del botón: Renovar o Registrar */ ?>
                    </form>
                <?php endif; /* Fin */ ?>
            </div>
        </div>
    </div>

    <!-- Columna derecha: pestañas -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" role="tablist">
                    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-cuotas"><i class="bi bi-cash-coin"></i> Cuotas</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-docs"><i class="bi bi-folder2"></i> Documentos <span class="badge text-bg-light"><?= count($documentos) ?></span></button></li><?php /* Pestaña de documentos con su cantidad */ ?>
                </ul>
            </div>
            <div class="card-body tab-content">
                <!-- Cuotas y pagos -->
                <div class="tab-pane fade show active" id="tab-cuotas">
                    <?php if ($puedeEditar && $saldo['pendiente'] > 0): /* Si puede cobrar y el chofer debe... */ ?>
                        <a href="<?= url('ventanilla') ?>" class="btn btn-success btn-sm mb-3"><i class="bi bi-qr-code-scan"></i> Cobrar en ventanilla</a><?php /* ...botón para ir a cobrar en ventanilla */ ?>
                    <?php endif; /* Fin */ ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead><tr><th>Concepto</th><th>Tipo</th><th>Límite</th><th class="text-end">Monto</th><th>Estado</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($pagos as $p): /* Una fila por cada cuota del chofer */ ?>
                                <tr>
                                    <td><?= e($p['concepto']) ?></td><?php /* Concepto */ ?>
                                    <td><span class="badge <?= $p['tipo'] === 'extraordinaria' ? 'text-bg-warning' : 'text-bg-light border' ?>"><?= e($p['tipo']) ?></span></td><?php /* Tipo (extraordinaria en amarillo) */ ?>
                                    <td><?= fecha($p['fecha_limite']) ?></td><?php /* Fecha límite */ ?>
                                    <td class="text-end"><?= bs($p['monto']) ?></td><?php /* Monto */ ?>
                                    <td>
                                        <?php if ($p['estado'] === 'pagado'): /* Si está pagada... */ ?>
                                            <span class="badge text-bg-success">Pagado</span> <small class="text-muted"><?= fecha($p['fecha_pago']) ?></small><?php /* ..."Pagado" y la fecha */ ?>
                                        <?php elseif ($p['vencida']): /* Si está vencida sin pagar... */ ?>
                                            <span class="badge text-bg-danger">En mora</span>
                                        <?php else: /* Si está pendiente dentro del plazo... */ ?>
                                            <span class="badge text-bg-warning">Pendiente</span>
                                        <?php endif; /* Fin */ ?>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <?php if ($p['estado'] === 'pagado'): /* Si está pagada... */ ?>
                                            <a class="btn btn-sm btn-light" target="_blank" href="<?= url('pagos/recibo', ['id' => $p['id_pago']]) ?>" title="Recibo <?= e($p['nro_recibo']) ?>"><i class="bi bi-printer"></i></a><?php /* ...botón para imprimir su recibo */ ?>
                                            <?php if ($puedeEditar): /* ...y si es ventanilla... */ ?>
                                                <form method="post" action="<?= url('pagos/revertir') ?>" class="d-inline" data-confirm="¿Revertir el pago <?= e($p['nro_recibo']) ?>?" data-confirm-texto="La cuota volverá a quedar pendiente. Use esta opción solo para corregir errores."><?php /* ...formulario para revertir el pago (pide confirmación) */ ?>
                                                    <?= Csrf::campo() ?><input type="hidden" name="id" value="<?= $p['id_pago'] ?>"><?php /* Token CSRF y número del pago */ ?>
                                                    <button class="btn btn-sm btn-light text-danger" title="Revertir pago"><i class="bi bi-arrow-counterclockwise"></i></button>
                                                </form>
                                            <?php endif; /* Fin */ ?>
                                        <?php endif; /* Fin */ ?>
                                    </td>
                                </tr>
                            <?php endforeach; /* Fin de la lista */ ?>
                            <?php if (!$pagos): /* Si no tiene cuotas... */ ?>
                                <tr><td colspan="6" class="text-center text-muted py-3">Sin cuotas asignadas.</td></tr>
                            <?php endif; /* Fin */ ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Documentos -->
                <div class="tab-pane fade" id="tab-docs">
                    <?php if ($puedeEditar): /* Formulario para subir documentos solo para ventanilla */ ?>
                        <form method="post" enctype="multipart/form-data" action="<?= url('documentos/subir') ?>" class="row g-2 mb-4 needs-validation" novalidate><?php /* Envía el archivo a documentos/subir */ ?>
                            <?= Csrf::campo() /* Token CSRF */ ?>
                            <input type="hidden" name="id_chofer" value="<?= $idc ?>"><?php /* Número del chofer (oculto) */ ?>
                            <input type="hidden" name="volver" value="chofer">
                            <div class="col-md-4">
                                <select name="tipo" class="form-select" required>
                                    <?php foreach (Expediente::TIPOS as $k => $t): if ($k === 'acta') continue; /* Tipos de documento (sin actas, que son del sindicato) */ ?>
                                        <option value="<?= $k ?>"><?= e($t) ?></option><?php /* Opción */ ?>
                                    <?php endforeach; /* Fin */ ?>
                                </select>
                            </div>
                            <div class="col-md-8"><input name="titulo" class="form-control" required maxlength="150" placeholder="Título del documento"></div>
                            <div class="col-md-6"><input type="file" name="archivo" class="form-control" required accept=".pdf,.jpg,.jpeg,.png"></div>
                            <div class="col-md-3"><input type="date" name="fecha_vencimiento" class="form-control" title="Vencimiento (opcional)"></div>
                            <div class="col-md-3"><button class="btn btn-primary w-100"><i class="bi bi-cloud-upload"></i> Subir</button></div>
                            <div class="form-text">PDF, JPG o PNG hasta <?= UPLOAD_MAX_BYTES / 1048576 ?> MB. La fecha de vencimiento es opcional.</div><?php /* Tamaño máximo permitido */ ?>
                        </form>
                    <?php endif; /* Fin */ ?>
                    <?php require VIEW_PATH . '/tabla_documentos.php'; /* Incluye la tabla de documentos del chofer */ ?>
                </div>
            </div>
        </div>

        <?php if ($puedeEditar): /* Botón eliminar solo para ventanilla */ ?>
            <form method="post" action="<?= url('choferes/eliminar') ?>" class="text-end mt-3" data-confirm="¿Eliminar a este chofer?" data-confirm-texto="Se eliminarán su licencia y sus documentos. Solo es posible si no tiene pagos registrados."><?php /* Pide confirmación antes de borrar */ ?>
                <?= Csrf::campo() ?><input type="hidden" name="id" value="<?= $idc ?>"><?php /* Token CSRF y número del chofer */ ?>
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Eliminar chofer</button>
            </form>
        <?php endif; /* Fin */ ?>
    </div>
</div>
