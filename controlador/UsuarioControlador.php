<?php
/**
 * CONTROLADOR de acceso y administración: inicio de sesión, usuarios y roles, bitácora y copias de seguridad.
 */
class UsuarioControlador extends Controlador   // Controlador de acceso: login, usuarios, bitácora y copias de seguridad
{
    /* ==================== Auth ==================== */

    public function login(): void   // Muestra la pantalla de inicio de sesión
    {
        if (Auth::check()) {   // Si ya inició sesión...
            $this->redirect('inicio');   // ...lo lleva directo al panel
        }
        $this->view('login', [], 'simple');   // Muestra login.php con la plantilla simple (sin menú)
    }

    public function entrar(): void   // Revisa el usuario y la contraseña escritos en el login
    {
        $this->requirePost();   // Solo acepta POST con token CSRF válido

        $usuario  = (string) $this->input('usuario', '');   // Usuario escrito
        $password = (string) ($_POST['password'] ?? '');   // Contraseña escrita (no se limpia, puede tener espacios)

        // Bloqueo temporal tras MAX_INTENTOS_LOGIN fallos seguidos del mismo usuario
        if ($usuario !== '' && (new Bitacora())->fallosRecientes($usuario, BLOQUEO_LOGIN_SEG) >= MAX_INTENTOS_LOGIN) {   // Si ese usuario falló 5 veces en los últimos 5 minutos (contado en la base de datos)...
            $_SESSION['_old'] = ['usuario' => $usuario];   // Recuerda el usuario escrito
            flash('error', 'Demasiados intentos fallidos. Espere ' . ceil(BLOQUEO_LOGIN_SEG / 60) . ' minutos e intente nuevamente.', 'Acceso bloqueado');   // Avisa que está bloqueado temporalmente
            $this->redirect('login');   // Vuelve al login sin revisar la contraseña
        }

        $u = $usuario !== '' ? (new Usuario())->porNombreUsuario($usuario) : null;   // Busca al usuario en la base de datos

        if (!$u || !$u['activo'] || !password_verify($password, $u['password_hash'])) {   // Si no existe, está desactivado o la contraseña no coincide con el hash...
            Bitacora::registrar('LOGIN_FALLIDO', 'Usuario: ' . $usuario);   // Registra el intento fallido en la bitácora (sirve para el bloqueo)
            usleep(400000);   // dificulta ataques de fuerza bruta
            $_SESSION['_old'] = ['usuario' => $usuario];   // Recuerda el usuario escrito
            flash('error', 'Verifique su usuario y contraseña.', 'Datos incorrectos');   // Tabla 19
            $this->redirect('login');   // Vuelve al login
        }

        unset($_SESSION['_old']);   // Borra lo escrito anteriormente
        Auth::login($u);   // Guarda al usuario en la sesión (con un identificador de sesión nuevo)
        Bitacora::registrar('LOGIN', 'Inicio de sesión');   // Registra el ingreso en la bitácora
        flash('success', 'Bienvenido(a), ' . $u['nombre']);   // Mensaje de bienvenida
        $this->redirect($u['rol'] === 'ventanilla' ? 'ventanilla' : 'inicio');   // Ventanilla va a la atención QR; el administrador al panel
    }

    public function salir(): void   // Cierra la sesión
    {
        $this->requirePost();   // el botón «Cerrar sesión» envía un formulario con token CSRF
        Bitacora::registrar('LOGOUT', 'Cierre de sesión');   // Registra la salida en la bitácora
        Auth::logout();   // Borra la sesión y su cookie
        Auth::iniciarSesion();   // Abre una sesión vacía para poder mostrar el mensaje
        flash('success', 'Sesión cerrada correctamente.');   // Mensaje de despedida
        $this->redirect('login');   // Vuelve al login
    }

    /* ==================== Usuario ==================== */

    private const ROLES = ['ventanilla', 'administrador'];   // Roles permitidos en el sistema (Tabla 16)

    public function usuarios(): void   // Lista de usuarios (solo administrador)
    {
        $this->view('usuarios', [   // Muestra la vista usuarios.php con:
            'titulo'   => 'Usuarios del sistema',   // el título de la pantalla
            'usuarios' => (new Usuario())->listar(),   // todos los usuarios con su último acceso
            'roles'    => self::ROLES,   // los roles para el formulario
        ]);
        limpiar_old();   // Borra los datos de un error anterior
    }

    /** Crea un usuario o actualiza su nombre, rol y (opcionalmente) contraseña. */
    public function guardar(): void   // Crea un usuario nuevo o modifica uno existente
    {
        $this->requirePost();   // Solo POST con token CSRF
        $modelo = new Usuario();   // Modelo de usuarios
        $id     = $this->inputInt('id_usuario');   // 0 = usuario nuevo; otro número = se está editando
        $pass   = (string) ($_POST['password'] ?? '');   // Contraseña escrita

        $_POST['nombre'] = Bolivia::normalizarNombre((string) ($_POST['nombre'] ?? ''));   // Ordena el nombre (mayúsculas y espacios)
        $v = (new Validator($_POST))   // Reglas de validación:
            ->requerido('nombre', 'El nombre')->maximo('nombre', 100, 'El nombre')->nombre('nombre', 'El nombre')   // Nombre obligatorio, máximo 100 letras y solo letras
            ->requerido('usuario', 'El usuario')->maximo('usuario', 50, 'El usuario')   // Usuario obligatorio, máximo 50 caracteres
            ->patron('usuario', '/^[a-zA-Z0-9._-]+$/', 'El usuario solo admite letras, números, punto, guion y guion bajo.')   // Usuario sin espacios ni símbolos raros
            ->requerido('rol', 'El rol')->en('rol', self::ROLES, 'El rol');   // Rol obligatorio y debe ser ventanilla o administrador
        if ($id === 0 || $pass !== '') {   // Si es usuario nuevo o se escribió una contraseña nueva...
            $v->requerido('password', 'La contraseña')->minimo('password', 8, 'La contraseña');   // ...es obligatoria y de 8 caracteres como mínimo
        }
        if ($modelo->existeUsuario((string) $this->input('usuario'), $id)) {   // Si el nombre de usuario ya lo tiene otra persona...
            $v->agregar('usuario', 'El nombre de usuario ya está en uso.');   // ...error
        }
        if ($id === Auth::id() && $this->input('rol') !== 'administrador') {   // Si el administrador intenta quitarse su propio rol...
            $v->agregar('rol', 'No puede quitarse a sí mismo el rol de administrador.');   // ...error (el sistema se quedaría sin administrador)
        }
        if ($v->falla()) {   // Si hubo errores...
            $this->conErrores($v->errores(), 'usuarios');   // ...vuelve al formulario mostrándolos
        }

        if ($id > 0) {   // Si se está editando...
            $modelo->actualizar($id, [   // ...actualiza nombre, usuario y rol
                'nombre'  => $_POST['nombre'],
                'usuario' => $this->input('usuario'),
                'rol'     => $this->input('rol'),
            ]);
            if ($pass !== '') {   // Si escribió contraseña nueva...
                $modelo->cambiarPassword($id, $pass);   // ...la cambia (se guarda cifrada con bcrypt)
            }
        } else {
            $id = $modelo->crear($_POST['nombre'], $this->input('usuario'), $pass, $this->input('rol'));   // Si es nuevo, lo crea con la contraseña cifrada
        }
        Bitacora::registrar('USUARIO_GUARDADO', "Usuario #$id " . $this->input('usuario') . ' rol ' . $this->input('rol'));   // Registra la operación en la bitácora
        flash('success', 'Usuario guardado correctamente.');   // Mensaje de éxito
        $this->redirect('usuarios');   // Vuelve a la lista
    }

    public function cambiarEstado(): void   // Activa o desactiva un usuario
    {
        $this->requirePost();   // Solo POST con token CSRF
        $id = $this->inputInt('id');   // Usuario elegido
        if ($id === Auth::id()) {   // Si es el mismo administrador conectado...
            flash('error', 'No puede desactivar su propio usuario.');   // ...no se puede desactivar a sí mismo
            $this->redirect('usuarios');   // Vuelve a la lista
        }
        $modelo = new Usuario();   // Modelo de usuarios
        $u = $modelo->buscar($id) ?? $this->notFound('Usuario no encontrado');   // Busca al usuario o muestra "no encontrado"
        $modelo->actualizar($id, ['activo' => $u['activo'] ? 0 : 1]);   // Si estaba activo lo desactiva, y al revés
        Bitacora::registrar('USUARIO_ESTADO', "Usuario #$id " . ($u['activo'] ? 'desactivado' : 'activado'));   // Registra en la bitácora
        flash('success', 'Estado del usuario actualizado.');   // Mensaje de éxito
        $this->redirect('usuarios');   // Vuelve a la lista
    }

    /** Elimina un usuario; sus registros (pagos, cuotas, bitácora) se conservan sin el usuario. */
    public function eliminar(): void   // Elimina un usuario
    {
        $this->requirePost();   // Solo POST con token CSRF
        $id = $this->inputInt('id');   // Usuario elegido
        if ($id === Auth::id()) {   // No puede eliminarse a sí mismo...
            flash('error', 'No puede eliminar su propio usuario.');   // ...avisa
            $this->redirect('usuarios');   // ...y vuelve
        }
        $modelo = new Usuario();   // Modelo de usuarios
        $u = $modelo->buscar($id) ?? $this->notFound('Usuario no encontrado');   // Busca al usuario o muestra "no encontrado"
        $modelo->eliminar($id);   // Lo borra de la base de datos
        Bitacora::registrar('USUARIO_ELIMINADO', "Usuario #$id {$u['usuario']}");   // Registra en la bitácora
        flash('success', 'Usuario eliminado.');   // Mensaje de éxito
        $this->redirect('usuarios');   // Vuelve a la lista
    }

    public function bitacora(): void   // Muestra la bitácora de operaciones
    {
        $this->view('bitacora', [   // Vista bitacora.php con:
            'titulo'    => 'Bitácora de accesos',   // título
            'registros' => (new Bitacora())->ultimos(300),   // los últimos 300 registros
        ]);
    }

    /* ==================== Respaldo ==================== */

    public function respaldo(): void   // Pantalla de copias de seguridad
    {
        $pdo    = Modelo::conectar();   // Conexión a la base de datos
        $tablas = [];   // Lista de tablas
        foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {   // Pide a MySQL los nombres de todas las tablas
            $tablas[] = ['nombre' => $t, 'filas' => (int) $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn()];   // Cuenta cuántos registros tiene cada una
        }
        $this->view('respaldo', [   // Muestra la vista respaldo.php con:
            'titulo'   => 'Copias de seguridad',
            'tablas'   => $tablas,   // las tablas y sus registros
            'archivos' => $this->contarArchivos(UPLOAD_PATH),   // cuántos documentos hay guardados
            'ultimos'  => array_values(array_filter((new Bitacora())->ultimos(300), fn($r) => $r['accion'] === 'RESPALDO')),   // las copias anteriores (sacadas de la bitácora)
        ]);
    }

    /** Descarga la copia de seguridad completa en formato SQL. */
    public function respaldoBd(): void   // Descarga toda la base de datos en un archivo .sql
    {
        $pdo   = Modelo::conectar();   // Conexión a la base de datos
        $orden = ['usuario', 'registro_acceso', 'grupo_transporte', 'chofer', 'licencia', 'expediente', 'cuota', 'pago_cuota'];   // Orden de las tablas (primero las que no dependen de otras)
        $todas = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);   // Todas las tablas que existen
        $tablas = array_merge(array_values(array_intersect($orden, $todas)), array_values(array_diff($todas, $orden)));   // Ordena: primero las conocidas, luego cualquier otra

        $sql  = "-- Copia de seguridad de la base de datos " . DB_NAME . "\n";   // Encabezado del archivo .sql
        $sql .= "-- Sindicato Mixto de Transporte \"Villa Aroma\" — generada el " . date('d/m/Y H:i:s') . "\n\n";   // Fecha en que se generó la copia
        $sql .= "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";   // Desactiva las claves foráneas mientras se restaura
        foreach ($tablas as $t) {   // Para cada tabla...
            $crear = $pdo->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM)[1];   // ...pide a MySQL la instrucción que crea esa tabla
            $sql  .= "DROP TABLE IF EXISTS `$t`;\n$crear;\n\n";   // Borra la tabla si existe y la vuelve a crear
            $filas = $pdo->query("SELECT * FROM `$t`")->fetchAll(PDO::FETCH_ASSOC);   // Lee todos sus registros
            foreach (array_chunk($filas, 100) as $bloque) {   // Los agrupa de 100 en 100
                $cols   = '`' . implode('`, `', array_keys($bloque[0])) . '`';   // Nombres de las columnas
                $values = array_map(fn($f) => '(' . implode(', ', array_map(   // Arma cada fila con sus valores...
                    fn($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), array_values($f))) . ')', $bloque);   // ...protegidos con quote() (comillas escapadas); NULL si está vacío
                $sql .= "INSERT INTO `$t` ($cols) VALUES\n" . implode(",\n", $values) . ";\n";   // Instrucción INSERT con esas filas
            }
            $sql .= "\n";   // Línea en blanco entre tablas
        }
        $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";   // Vuelve a activar las claves foráneas

        Bitacora::registrar('RESPALDO', 'Copia de seguridad descargada (' . count($tablas) . ' tablas)');   // Registra la copia en la bitácora
        $nombre = 'respaldo_' . DB_NAME . '_' . date('Y-m-d_His') . '.sql';   // Nombre del archivo con fecha y hora
        header('Content-Type: application/sql; charset=utf-8');   // Indica que se envía un archivo SQL
        header('Content-Disposition: attachment; filename="' . $nombre . '"');   // attachment = el navegador lo descarga en vez de mostrarlo
        header('Content-Length: ' . strlen($sql));   // Tamaño del archivo
        echo $sql;   // Envía el contenido
        exit;   // Termina
    }

    /** Descarga en un .zip la carpeta archivos con todos los documentos digitalizados. */
    public function respaldoDocumentos(): void   // Descarga todos los documentos y fotos en un .zip
    {
        if (!class_exists('ZipArchive')) {   // Si PHP no tiene la extensión zip...
            flash('error', 'Active la extensión «zip» de PHP (php.ini: extension=zip) o copie manualmente la carpeta archivos.', 'No disponible');   // ...avisa cómo activarla
            $this->redirect('respaldo');   // ...y vuelve a la pantalla
        }
        $tmp = tempnam(sys_get_temp_dir(), 'docs');   // Crea un archivo temporal
        $zip = new ZipArchive();   // Objeto para crear archivos ZIP
        $zip->open($tmp, ZipArchive::OVERWRITE);   // Abre el ZIP vacío
        $n = 0;   // Contador de archivos
        if (is_dir(UPLOAD_PATH)) {   // Si existe la carpeta archivos/...
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(UPLOAD_PATH, FilesystemIterator::SKIP_DOTS)) as $f) {   // ...recorre todas sus subcarpetas
                if ($f->isFile()) {   // Si es un archivo (no carpeta)...
                    $zip->addFile($f->getPathname(), 'archivos/' . ltrim(str_replace('\\', '/', substr($f->getPathname(), strlen(UPLOAD_PATH))), '/'));   // ...lo agrega al ZIP con su misma ruta
                    $n++;   // ...y lo cuenta
                }
            }
        }
        if ($n === 0) {   // Si no hay ningún documento...
            $zip->addFromString('archivos/LEEME.txt', 'Aún no hay documentos digitalizados.');   // ...agrega una nota para que el ZIP no quede vacío
        }
        $zip->close();   // Cierra y termina de escribir el ZIP

        Bitacora::registrar('RESPALDO', "Copia de documentos digitalizados ($n archivos)");   // Registra la copia en la bitácora
        header('Content-Type: application/zip');   // Indica que se envía un ZIP
        header('Content-Disposition: attachment; filename="respaldo_documentos_' . date('Y-m-d_His') . '.zip"');   // Nombre del archivo con fecha y hora; se descarga
        header('Content-Length: ' . filesize($tmp));   // Tamaño del archivo
        readfile($tmp);   // Envía el ZIP
        unlink($tmp);   // Borra el archivo temporal
        exit;   // Termina
    }

    private function contarArchivos(string $ruta): int   // Cuenta los archivos de una carpeta y sus subcarpetas
    {
        if (!is_dir($ruta)) {   // Si la carpeta no existe...
            return 0;   // ...hay 0 archivos
        }
        $n = 0;   // Contador
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ruta, FilesystemIterator::SKIP_DOTS)) as $f) {   // Recorre todo el contenido
            if ($f->isFile() && $f->getFilename() !== '.htaccess') {   // Solo cuenta archivos reales (no las protecciones .htaccess)
                $n++;   // Suma uno
            }
        }
        return $n;   // Devuelve el total
    }
}
