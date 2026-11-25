<?php
/**
 * MODELO de usuarios del sistema y de la bitácora (tablas usuario y registro_acceso).
 */

class Usuario extends Modelo   // Modelo de los usuarios del sistema (tabla usuario)
{
    protected string $tabla = 'usuario';   // Tabla que maneja
    protected string $clave = 'id_usuario';   // Clave primaria

    public function porNombreUsuario(string $usuario): ?array   // Busca un usuario por su nombre de usuario (login)
    {
        return $this->fetchOne('SELECT * FROM usuario WHERE usuario = ?', [$usuario]);   // Consulta preparada: el usuario escrito no se mezcla con el SQL
    }

    public function listar(): array   // Lista de usuarios con la fecha de su último ingreso
    {
        return $this->fetchAll(   // Consulta que trae cada usuario y su último LOGIN de la bitácora
            "SELECT u.id_usuario, u.nombre, u.usuario, u.rol, u.activo,
                    (SELECT MAX(r.fecha) FROM registro_acceso r WHERE r.id_usuario = u.id_usuario AND r.accion = 'LOGIN') AS ultimo_acceso
             FROM usuario u ORDER BY u.nombre"
        );
    }

    public function crear(string $nombre, string $usuario, string $password, string $rol): int   // Crea un usuario nuevo
    {
        return $this->insertar([   // Inserta:
            'nombre'        => $nombre,   // nombre completo
            'usuario'       => $usuario,   // nombre de usuario
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),   // contraseña cifrada con bcrypt (nunca se guarda en texto plano)
            'rol'           => $rol,   // rol
        ]);
    }

    public function cambiarPassword(int $id, string $password): void   // Cambia la contraseña de un usuario
    {
        $this->actualizar($id, ['password_hash' => password_hash($password, PASSWORD_BCRYPT)]);   // Guarda la nueva contraseña cifrada con bcrypt
    }


    public function existeUsuario(string $usuario, int $excepto = 0): bool   // ¿Ya existe otro usuario con ese nombre?
    {
        return (bool) $this->fetchValue('SELECT COUNT(*) FROM usuario WHERE usuario = ? AND id_usuario <> ?', [$usuario, $excepto]);   // Cuenta usuarios con ese nombre, sin contar al que se está editando
    }
}

/**
 * Registro de accesos y operaciones (tabla registro_acceso) para trazabilidad.
 */
class Bitacora extends Modelo   // Modelo de la bitácora (tabla registro_acceso)
{
    protected string $tabla = 'registro_acceso';   // Tabla que maneja
    protected string $clave = 'id_registro';   // Clave primaria

    public static function registrar(string $accion, string $detalle = ''): void   // Guarda una operación en la bitácora (se llama desde cualquier controlador)
    {
        try {   // Intenta...
            (new self())->insertar([   // ...insertar el registro con:
                'id_usuario' => Auth::id(),   // el usuario que hizo la operación
                'accion'     => mb_substr($accion, 0, 60),   // la acción (LOGIN, PAGO_REGISTRADO...), máximo 60 caracteres
                'detalle'    => mb_substr($detalle, 0, 255),   // el detalle, máximo 255 caracteres
                'ip'         => $_SERVER['REMOTE_ADDR'] ?? null,   // la dirección IP del equipo
            ]);
        } catch (Throwable $e) {   // Si falla la bitácora...
            error_log('[BITACORA] ' . $e->getMessage());   // la bitácora nunca debe detener la operación
        }
    }

    /**
     * Intentos fallidos de un usuario en la ventana de bloqueo, contados en la base de datos
     * (no en la sesión, para que borrar las cookies no reinicie el contador).
     */
    public function fallosRecientes(string $usuario, int $segundos): int   // Cuenta los intentos fallidos recientes de un usuario (para el bloqueo de 5 minutos)
    {
        return (int) $this->fetchValue(   // Devuelve el número de fallos...
            "SELECT COUNT(*) FROM registro_acceso
             WHERE accion = 'LOGIN_FALLIDO' AND detalle = ? AND fecha >= NOW() - INTERVAL ? SECOND
               AND fecha > COALESCE((SELECT MAX(r.fecha) FROM registro_acceso r JOIN usuario u ON u.id_usuario = r.id_usuario
                                     WHERE r.accion = 'LOGIN' AND u.usuario = ?), '1970-01-01')",
            ['Usuario: ' . mb_substr($usuario, 0, 200), $segundos, $usuario]   // ...de ese usuario, en esos segundos, desde su último ingreso correcto
        );
    }

    public function ultimos(int $limite = 200): array   // Últimos registros de la bitácora con el nombre del usuario
    {
        return $this->fetchAll(   // Consulta con el límite de filas
            'SELECT r.*, u.nombre FROM registro_acceso r LEFT JOIN usuario u ON u.id_usuario = r.id_usuario
             ORDER BY r.fecha DESC LIMIT ' . max(1, $limite)
        );
    }
}
