<?php /** Tabla reutilizable de documentos. Requiere $documentos. */ ?>
<div class="table-responsive">
    <table class="table table-sm table-hover align-middle">
        <thead><tr><th>Documento</th><th>Tipo</th><th>Afiliado</th><th>Vencimiento</th><th>Cargado</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($documentos as $d): $venc = $d['fecha_vencimiento'] ? estado_licencia($d['fecha_vencimiento']) : null; /* Una fila por documento; calcula el estado de su vencimiento (si tiene) */ ?>
            <tr>
                <td>
                    <i class="bi <?= str_ends_with($d['ruta_archivo'], '.pdf') ? 'bi-file-earmark-pdf text-danger' : 'bi-file-earmark-image text-primary' ?>"></i><?php /* Ícono rojo para PDF, azul para imagen */ ?>
                    <a href="<?= url('documentos/ver', ['id' => $d['id_expediente']]) ?>" target="_blank" class="fw-semibold text-decoration-none"><?= e($d['titulo']) ?></a><?php /* Enlace para ver el archivo (se abre en otra pestaña) */ ?>
                    <?php if ($d['descripcion']): ?><div class="small text-muted"><?= e($d['descripcion']) ?></div><?php endif; /* Descripción si tiene */ ?>
                </td>
                <td><span class="badge text-bg-light border"><?= e(Expediente::TIPOS[$d['tipo']] ?? $d['tipo']) ?></span></td><?php /* Tipo de documento */ ?>
                <td class="small"><?= $d['chofer'] ? e($d['chofer']) : '<span class="text-muted">Institucional</span>' ?></td><?php /* Chofer o "Institucional" (actas) */ ?>
                <td><?= $venc ? '<span class="badge text-bg-' . $venc['clase'] . '">' . fecha($d['fecha_vencimiento']) . '</span>' : '—' ?></td><?php /* Vencimiento con su color */ ?>
                <td class="small text-muted"><?= fecha($d['created_at']) ?><br><?= e($d['registrado_por'] ?? '') ?></td><?php /* Fecha en que se subió y quién lo subió */ ?>
                <td class="text-end text-nowrap">
                    <a class="btn btn-sm btn-light" href="<?= url('documentos/ver', ['id' => $d['id_expediente'], 'descargar' => 1]) ?>" title="Descargar"><i class="bi bi-download"></i></a><?php /* Botón para descargar */ ?>
                    <?php if (Auth::tiene('ventanilla')): /* Botones solo para ventanilla: */ ?>
                        <button type="button" class="btn btn-sm btn-light btn-editar-doc" title="Modificar"
                                data-doc='<?= e(json_encode(['id' => $d['id_expediente'], 'titulo' => $d['titulo'], 'descripcion' => $d['descripcion'], 'fecha_vencimiento' => $d['fecha_vencimiento']])) ?>'><i class="bi bi-pencil"></i></button><?php /* Datos del documento para el formulario de modificar */ ?>
                        <form method="post" action="<?= url('documentos/eliminar') ?>" class="d-inline" data-confirm="¿Eliminar este documento?" data-confirm-texto="El archivo digitalizado se borrará del servidor."><?php /* Eliminar (pide confirmación) */ ?>
                            <?= Csrf::campo() ?><input type="hidden" name="id" value="<?= $d['id_expediente'] ?>"><?php /* Token CSRF y número del documento */ ?>
                            <button class="btn btn-sm btn-light text-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                        </form>
                    <?php endif; /* Fin */ ?>
                </td>
            </tr>
        <?php endforeach; /* Fin de la lista */ ?>
        <?php if (!$documentos): /* Si no hay documentos... */ ?>
            <tr><td colspan="6" class="text-center text-muted py-3">No hay documentos digitalizados.</td></tr>
        <?php endif; /* Fin */ ?>
        </tbody>
    </table>
</div>

<?php if (Auth::tiene('ventanilla')): /* Ventana para modificar documento solo para ventanilla */ ?>
<div class="modal fade" id="modal-doc" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" enctype="multipart/form-data" action="<?= url('documentos/actualizar') ?>" class="modal-content needs-validation" novalidate><?php /* Envía a documentos/actualizar */ ?>
            <div class="modal-header"><h2 class="modal-title h6"><i class="bi bi-pencil"></i> Modificar documento</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body">
                <?= Csrf::campo() /* Token CSRF */ ?>
                <input type="hidden" name="id">
                <div class="mb-3"><label class="form-label">Título *</label><input name="titulo" class="form-control" required maxlength="150"></div>
                <div class="mb-3"><label class="form-label">Descripción</label><input name="descripcion" class="form-control" maxlength="255"></div>
                <div class="mb-3"><label class="form-label">Fecha de vencimiento</label><input type="date" name="fecha_vencimiento" class="form-control"></div>
                <div class="mb-1"><label class="form-label">Reemplazar archivo (opcional)</label><input type="file" name="archivo" class="form-control" accept=".pdf,.jpg,.jpeg,.png"></div>
                <div class="form-text">Si no adjunta un archivo, se conserva el actual.</div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary"><i class="bi bi-save"></i> Guardar cambios</button></div>
        </form>
    </div>
</div>
<script>
    document.querySelectorAll('.btn-editar-doc').forEach(btn => btn.addEventListener('click', () => {
        const d = JSON.parse(btn.dataset.doc), m = document.getElementById('modal-doc'), f = m.querySelector('form');
        f.id.value = d.id; f.titulo.value = d.titulo; f.descripcion.value = d.descripcion || ''; f.fecha_vencimiento.value = d.fecha_vencimiento || '';
        f.archivo.value = '';
        bootstrap.Modal.getOrCreateInstance(m).show();
    }));
</script>
<?php endif; /* Fin */ ?>
