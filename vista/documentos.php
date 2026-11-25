<div class="row g-4">
    <?php if (Auth::tiene('ventanilla')): /* El formulario para subir solo lo ve ventanilla */ ?>
    <div class="col-xl-4">
        <div class="card">
            <div class="card-header"><i class="bi bi-cloud-upload text-primary"></i> Digitalizar documento</div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data" action="<?= url('documentos/subir') ?>" class="needs-validation" novalidate id="form-doc"><?php /* Envía el archivo a documentos/subir (multipart = permite archivos) */ ?>
                    <?= Csrf::campo() /* Token CSRF */ ?>
                    <div class="mb-3">
                        <label class="form-label">Tipo *</label>
                        <select name="tipo" class="form-select" required id="tipo-doc">
                            <?php foreach (Expediente::TIPOS as $k => $t): /* Una opción por tipo de documento */ ?>
                                <option value="<?= $k ?>" <?= old('tipo') === $k ? 'selected' : '' ?>><?= e($t) ?></option><?php /* Marca el tipo elegido antes */ ?>
                            <?php endforeach; /* Fin */ ?>
                        </select>
                    </div>
                    <div class="mb-3" id="bloque-chofer">
                        <label class="form-label" for="buscar-chofer-doc">Buscar chofer por C.I. o código QR</label>
                        <div class="input-group mb-2">
                            <input type="text" id="buscar-chofer-doc" class="form-control text-uppercase" placeholder="4876512 o VA-XXXXXXXXXX" autocomplete="off">
                            <button class="btn btn-outline-primary" type="button" id="btn-buscar-chofer-doc"><i class="bi bi-search"></i></button>
                        </div>
                        <label class="form-label small text-muted">Chofer afiliado</label>
                        <select name="id_chofer" class="form-select" id="sel-chofer-doc">
                            <option value="0">— Documento institucional —</option>
                            <?php foreach ($choferes as $c): /* Una opción por chofer activo */ ?>
                                <option value="<?= $c['id_chofer'] ?>" data-ci="<?= e($c['ci']) ?>" <?= (int) old('id_chofer', $idChofer) === (int) $c['id_chofer'] ? 'selected' : '' ?>><?php /* data-ci guarda su C.I.; marca el chofer elegido */ ?>
                                    <?= e($c['apellidos'] . ' ' . $c['nombres'] . ' — C.I. ' . $c['ci']) /* Apellidos, nombres y C.I. */ ?>
                                </option>
                            <?php endforeach; /* Fin */ ?>
                        </select>
                    </div>
                    <div class="mb-3"><label class="form-label">Título *</label><input name="titulo" class="form-control" required maxlength="150" value="<?= old('titulo') ?>" placeholder="Acta de asamblea ordinaria 05/2026"></div><?php /* Título del documento (vuelve a mostrar lo escrito si hubo error) */ ?>
                    <div class="mb-3"><label class="form-label">Descripción</label><textarea name="descripcion" class="form-control" rows="2" maxlength="255"><?= old('descripcion') ?></textarea></div><?php /* Descripción */ ?>
                    <div class="row g-2 mb-3">
                        <div class="col-6"><label class="form-label small" id="lbl-fecha-doc">Fecha del documento</label><input type="date" name="fecha_documento" class="form-control" max="<?= date('Y-m-d') ?>" value="<?= old('fecha_documento') ?>"></div><?php /* Fecha del documento (o de emisión si es licencia) */ ?>
                        <div class="col-6"><label class="form-label small" id="lbl-vence-doc">Vence (opcional)</label><input type="date" name="fecha_vencimiento" id="vence-doc" class="form-control" value="<?= old('fecha_vencimiento') ?>"></div><?php /* Fecha de vencimiento */ ?>
                    </div>
                    <div class="row g-2 mb-3 d-none" id="bloque-licencia">
                        <div class="col-6"><label class="form-label small">Nro. licencia</label><input type="text" id="nro-licencia" class="form-control" readonly placeholder="= C.I. del chofer"></div>
                        <div class="col-6">
                            <label class="form-label small">Categoría *</label>
                            <select name="categoria" class="form-select">
                                <option value="">—</option>
                                <?php foreach (Licencia::CATEGORIAS as $cat): ?><option <?= old('categoria') === $cat ? 'selected' : '' ?>><?= $cat ?></option><?php endforeach; /* Categorías de licencia */ ?>
                            </select>
                        </div>
                        <div class="col-12 form-text">En Bolivia el número de licencia es el mismo C.I. del titular.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Archivo escaneado *</label>
                        <input type="file" name="archivo" class="form-control" required accept=".pdf,.jpg,.jpeg,.png">
                        <div class="form-text">PDF, JPG o PNG — máximo <?= UPLOAD_MAX_BYTES / 1048576 ?> MB.</div><?php /* Tamaño máximo permitido */ ?>
                    </div>
                    <button class="btn btn-primary w-100"><i class="bi bi-cloud-upload"></i> Guardar en el repositorio</button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; /* Fin del formulario */ ?>

    <div class="<?= Auth::tiene('ventanilla') ? 'col-xl-8' : 'col-12' ?>"><?php /* Ancho de la tabla según si hay formulario */ ?>
        <div class="card">
            <div class="card-body">
                <form class="row g-2 mb-3" method="get">
                    <input type="hidden" name="r" value="documentos">
                    <div class="col-md-5"><input type="search" name="buscar" class="form-control" placeholder="Título, chofer o C.I." value="<?= e($filtro['buscar']) ?>"></div><?php /* Buscador de documentos */ ?>
                    <div class="col-md-4">
                        <select name="tipo" class="form-select">
                            <option value="">Todos los tipos</option>
                            <?php foreach (Expediente::TIPOS as $k => $t): /* Filtro por tipo */ ?>
                                <option value="<?= $k ?>" <?= $filtro['tipo'] === $k ? 'selected' : '' ?>><?= e($t) ?></option><?php /* Marca el tipo elegido */ ?>
                            <?php endforeach; /* Fin */ ?>
                        </select>
                    </div>
                    <div class="col-md-3"><button class="btn btn-outline-primary w-100"><i class="bi bi-search"></i> Buscar</button></div>
                </form>
                <?php require VIEW_PATH . '/tabla_documentos.php'; /* Incluye la tabla de documentos */ ?>
            </div>
        </div>
    </div>
</div>


