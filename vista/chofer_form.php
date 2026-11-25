<?php
/** Formulario compartido para registrar y editar choferes. */
$editando = $chofer !== null;   // ¿Se está editando? (si $chofer tiene datos)
$val = fn(string $campo, $defecto = '') => old($campo, $chofer[$campo] ?? $defecto);   // Función: muestra el valor anterior (si hubo error) o el guardado
[$ciNumero, $ciComplemento] = Bolivia::partesCi($chofer['ci'] ?? '');   // Separa el C.I. guardado en número y complemento
$ciNumero      = $_SESSION['_old']['ci_numero'] ?? $ciNumero;   // Si hubo error, usa el número escrito
$ciComplemento = $_SESSION['_old']['ci_complemento'] ?? $ciComplemento;   // Si hubo error, usa el complemento escrito
$hoy = date('Y-m-d');   // Fecha de hoy
?>
<div class="card" style="max-width: 900px">
    <div class="card-header"><i class="bi bi-person-vcard me-1"></i> <?= $editando ? 'Datos de ' . e($chofer['nombres']) : 'Nuevo chofer afiliado' ?></div><?php /* Título: "Datos de ..." o "Nuevo chofer" */ ?>
    <div class="card-body">
        <form method="post" action="<?= url($editando ? 'choferes/actualizar' : 'choferes/guardar') ?>" class="needs-validation" novalidate enctype="multipart/form-data"><?php /* Envía a actualizar o a guardar; enctype permite subir la foto */ ?>
            <?= Csrf::campo() /* Token CSRF */ ?>
            <?php if ($editando): /* Si se edita... */ ?>
                <input type="hidden" name="id_chofer" value="<?= $chofer['id_chofer'] ?>"><?php /* ...envía el número del chofer en un campo oculto */ ?>
            <?php endif; /* Fin */ ?>

            <div class="row g-3">
                <!-- C.I. boliviano: número (4 a 8 dígitos) + complemento opcional + expedido -->
                <div class="col-md-5">
                    <label class="form-label">C.I. *</label>
                    <div class="input-group has-validation">
                        <input name="ci_numero" class="form-control" required inputmode="numeric" maxlength="8"
                               pattern="<?= Bolivia::html('CI_NUMERO') ?>" data-solo="digitos" placeholder="4876512"<?php /* pattern: el navegador revisa la regla del C.I. (4 a 8 dígitos) */ ?>
                               value="<?= e($ciNumero) ?>" style="flex: 2"><?php /* Valor anterior del C.I. */ ?>
                        <input name="ci_complemento" class="form-control text-uppercase" maxlength="2"
                               pattern="<?= Bolivia::html('CI_COMPLEMENTO') ?>" data-solo="alfanumerico" placeholder="Compl."<?php /* Regla del complemento (número + letra) */ ?>
                               title="Solo si su carnet lo tiene (ej. 1A)" value="<?= e($ciComplemento) ?>" style="flex: 1"><?php /* Valor anterior del complemento */ ?>
                        <select name="ci_expedido" class="form-select" required style="flex: 1.2">
                            <?php foreach (Bolivia::DEPARTAMENTOS as $cod => $dep): /* Una opción por cada departamento de Bolivia */ ?>
                                <option value="<?= $cod ?>" title="<?= e($dep) ?>" <?= $val('ci_expedido', 'LP') === $cod ? 'selected' : '' ?>><?= $cod ?></option><?php /* Marca el departamento elegido (La Paz por defecto) */ ?>
                            <?php endforeach; /* Fin */ ?>
                        </select>
                        <div class="invalid-feedback">De 4 a 8 dígitos sin cero inicial. Complemento opcional: un número y una letra (1A).</div>
                    </div>
                    <div class="form-text">Número · complemento (si tiene) · expedido en</div>
                </div>
                <div class="col-md-7">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label">Nombres *</label>
                            <input name="nombres" class="form-control" required maxlength="80" minlength="2"
                                   pattern="<?= Bolivia::html('NOMBRE') ?>" data-solo="letras" value="<?= $val('nombres') ?>"><?php /* Regla de nombres (solo letras) y valor anterior */ ?>
                            <div class="invalid-feedback">Escriba solo letras (sin números ni símbolos).</div>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">Apellidos *</label>
                            <input name="apellidos" class="form-control" required maxlength="80" minlength="2"
                                   pattern="<?= Bolivia::html('NOMBRE') ?>" data-solo="letras" value="<?= $val('apellidos') ?>"><?php /* Regla de apellidos y valor anterior */ ?>
                            <div class="invalid-feedback">Escriba solo letras (sin números ni símbolos).</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Celular</label>
                    <input name="telefono" class="form-control" inputmode="numeric" maxlength="8"
                           pattern="<?= Bolivia::html('CELULAR') ?>" data-solo="digitos" placeholder="71234567" value="<?= $val('telefono') ?>"><?php /* Regla del celular (8 dígitos) y valor anterior */ ?>
                    <div class="invalid-feedback">El celular debe tener exactamente 8 dígitos.</div>
                </div>
                <div class="col-md-8">
                    <label class="form-label">Dirección</label>
                    <input name="direccion" class="form-control" maxlength="150" value="<?= $val('direccion') ?>"><?php /* Dirección anterior */ ?>
                </div>
                <div class="col-md-8">
                    <label class="form-label" for="foto">Foto del chofer <small class="text-muted">(JPG o PNG, tipo carnet)</small></label>
                    <input type="file" name="foto" id="foto" class="form-control" accept=".jpg,.jpeg,.png">
                    <div class="form-text">Aparece en la hoja de DATOS PERSONALES que se ve al escanear el QR.</div>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <img id="foto-previa" alt="Vista previa de la foto" class="border rounded <?= !empty($chofer['foto']) ? '' : 'd-none' ?>"<?php /* Muestra la foto actual si tiene */ ?>
                         style="width: 90px; height: 110px; object-fit: cover"
                         src="<?= !empty($chofer['foto']) ? e(url('choferes/foto', ['c' => $chofer['codigo_qr'], 'v' => md5($chofer['foto'])])) : '' ?>"><?php /* Dirección de la foto (se pide con el código QR) */ ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Fecha de nacimiento</label>
                    <input type="date" name="fecha_nacimiento" class="form-control" value="<?= $val('fecha_nacimiento') ?>"<?php /* Fecha de nacimiento anterior */ ?>
                           min="<?= date('Y-m-d', strtotime('-' . Bolivia::EDAD_MAXIMA . ' years')) ?>" max="<?= date('Y-m-d', strtotime('-' . Bolivia::EDAD_MINIMA . ' years')) ?>"><?php /* Límites: entre 90 y 18 años atrás */ ?>
                    <div class="invalid-feedback">Debe tener entre <?= Bolivia::EDAD_MINIMA ?> y <?= Bolivia::EDAD_MAXIMA ?> años.</div><?php /* Mensaje si no cumple la edad */ ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Fecha de afiliación *</label>
                    <input type="date" name="fecha_afiliacion" class="form-control" required value="<?= $val('fecha_afiliacion', $hoy) ?>"<?php /* Fecha de afiliación (hoy por defecto) */ ?>
                           min="<?= Bolivia::FUNDACION ?>" max="<?= $hoy ?>"><?php /* Entre la fundación del sindicato y hoy */ ?>
                    <div class="invalid-feedback">Entre el 01/12/2012 (fundación) y hoy.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Grupo de transporte *</label>
                    <select name="id_grupo" class="form-select" required>
                        <option value="">Seleccione…</option>
                        <?php foreach ($grupos as $g): /* Una opción por cada grupo */ ?>
                            <option value="<?= $g['id_grupo'] ?>" <?= $val('id_grupo') == $g['id_grupo'] ? 'selected' : '' ?>><?php /* Marca el grupo elegido */ ?>
                                <?= e($g['nombre'] . ($g['ruta'] ? ' — ' . $g['ruta'] : '')) /* Nombre y ruta del grupo */ ?>
                            </option>
                        <?php endforeach; /* Fin */ ?>
                    </select>
                    <div class="invalid-feedback">Seleccione el grupo.</div>
                </div>
                <?php if ($editando): /* Si se edita, se puede cambiar el estado */ ?>
                    <div class="col-md-4">
                        <label class="form-label">Estado</label>
                        <select name="estado" class="form-select">
                            <?php foreach (['activo', 'suspendido', 'inactivo'] as $es): /* Una opción por cada estado */ ?>
                                <option value="<?= $es ?>" <?= $val('estado') === $es ? 'selected' : '' ?>><?= ucfirst($es) ?></option><?php /* Marca el estado actual */ ?>
                            <?php endforeach; /* Fin */ ?>
                        </select>
                    </div>
                <?php else: /* Si es nuevo, siempre empieza activo */ ?>
                    <div class="col-12">
                        <div class="alert alert-info small mb-0">
                            <i class="bi bi-info-circle"></i> Al guardar se generará automáticamente el <b>código QR único</b> de la credencial
                            y se asignarán las cuotas vigentes de su grupo.
                        </div>
                    </div>
                <?php endif; /* Fin */ ?>
            </div>

            <div class="d-flex gap-2 justify-content-end mt-4">
                <a href="<?= $editando ? url('choferes/ver', ['id' => $chofer['id_chofer']]) : url('choferes') ?>" class="btn btn-light">Cancelar</a><?php /* Cancelar: vuelve al expediente o a la lista */ ?>
                <button class="btn btn-primary"><i class="bi bi-save"></i> Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Vista previa inmediata de la foto elegida
    document.getElementById('foto')?.addEventListener('change', (e) => {
        const img = document.getElementById('foto-previa'), f = e.target.files[0];
        if (!f) return;
        img.src = URL.createObjectURL(f); img.classList.remove('d-none');
    });
</script>
