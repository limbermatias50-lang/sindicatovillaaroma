<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Grupo</th><th>Ruta</th><th>Delegado</th><th class="text-center">Choferes</th><th>Estado</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($grupos as $g): /* Una fila por grupo */ ?>
                            <tr>
                                <td class="fw-semibold"><?= e($g['nombre']) ?></td><?php /* Nombre */ ?>
                                <td><?= e($g['ruta']) ?></td><?php /* Ruta */ ?>
                                <td><?= e($g['delegado']) ?></td><?php /* Delegado */ ?>
                                <td class="text-center"><?= $g['choferes'] ?></td><?php /* Cantidad de choferes activos */ ?>
                                <td><span class="badge text-bg-<?= $g['activo'] ? 'success' : 'secondary' ?>"><?= $g['activo'] ? 'Activo' : 'Inactivo' ?></span></td><?php /* Activo o inactivo */ ?>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-light btn-editar" data-grupo='<?= e(json_encode($g)) ?>'><i class="bi bi-pencil"></i></button><?php /* Botón editar: lleva los datos del grupo en JSON */ ?>
                                </td>
                            </tr>
                        <?php endforeach; /* Fin */ ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header" id="form-titulo"><i class="bi bi-plus-circle"></i> Nuevo grupo</div>
            <div class="card-body">
                <form method="post" action="<?= url('grupos/guardar') ?>" id="form-grupo" class="needs-validation" novalidate><?php /* Envía a grupos/guardar */ ?>
                    <?= Csrf::campo() /* Token CSRF */ ?>
                    <input type="hidden" name="id_grupo" value="0">
                    <div class="mb-3"><label class="form-label">Nombre *</label><input name="nombre" class="form-control" required maxlength="80" value="<?= old('nombre') ?>"></div><?php /* Nombre del grupo */ ?>
                    <div class="mb-3"><label class="form-label">Ruta</label><input name="ruta" class="form-control" maxlength="120" placeholder="Lahuachaca – La Paz" value="<?= old('ruta') ?>"></div><?php /* Ruta */ ?>
                    <div class="mb-3"><label class="form-label">Delegado</label><input name="delegado" class="form-control" maxlength="100" pattern="<?= Bolivia::html('NOMBRE') ?>" data-solo="letras" value="<?= old('delegado') ?>"><div class="invalid-feedback">Solo letras (nombre y apellidos).</div></div><?php /* Delegado */ ?>
                    <div class="form-check mb-3 d-none" id="chk-activo">
                        <input class="form-check-input" type="checkbox" name="activo" id="activo" checked>
                        <label class="form-check-label" for="activo">Grupo activo</label>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="reset" class="btn btn-light" id="btn-nuevo">Limpiar</button>
                        <button class="btn btn-primary flex-grow-1"><i class="bi bi-save"></i> Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php limpiar_old(); /* Borra los datos de un error anterior */ ?>
<script>
    document.querySelectorAll('.btn-editar').forEach(btn => btn.addEventListener('click', () => {
        const g = JSON.parse(btn.dataset.grupo), f = document.getElementById('form-grupo');
        f.id_grupo.value = g.id_grupo; f.nombre.value = g.nombre; f.ruta.value = g.ruta || ''; f.delegado.value = g.delegado || '';
        f.activo.checked = g.activo == 1;
        document.getElementById('chk-activo').classList.remove('d-none');
        document.getElementById('form-titulo').innerHTML = '<i class="bi bi-pencil"></i> Editar ' + App.escapar(g.nombre);
        f.nombre.focus();
    }));
    document.getElementById('btn-nuevo').addEventListener('click', () => {
        document.querySelector('#form-grupo [name=id_grupo]').value = 0;
        document.getElementById('chk-activo').classList.add('d-none');
        document.getElementById('form-titulo').innerHTML = '<i class="bi bi-plus-circle"></i> Nuevo grupo';
    });
</script>
