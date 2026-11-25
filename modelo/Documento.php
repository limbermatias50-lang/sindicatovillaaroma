<?php
/**
 * MODELO del expediente digital (tabla expediente).
 */

/**
 * Documentos digitalizados: expedientes, antecedentes, actas y licencias (RF05).
 */
class Expediente extends Modelo   // Modelo del expediente digital (tabla expediente)
{
    protected string $tabla = 'expediente';   // Tabla que maneja
    protected string $clave = 'id_expediente';   // Clave primaria

    public const TIPOS = [   // Tipos de documento permitidos (clave => nombre que se muestra)
        'expediente'  => 'Expediente del afiliado',   // documentos del expediente del afiliado
        'antecedente' => 'Antecedente',   // antecedentes
        'licencia'    => 'Licencia de conducir (copia)',   // copia de la licencia
        'acta'        => 'Acta de reunión / asamblea',   // actas de reuniones y asambleas del sindicato
        'otro'        => 'Otro documento',   // cualquier otro
    ];

    public function listar(string $tipo = '', string $buscar = '', int $idChofer = 0): array   // Lista de documentos con filtros opcionales
    {
        $where  = [];   // Condiciones del WHERE
        $params = [];   // Datos de la consulta preparada
        if ($tipo !== '' && isset(self::TIPOS[$tipo])) {   // Si se eligió un tipo válido...
            $where[] = 'e.tipo = :t';   // ...filtra por tipo
            $params['t'] = $tipo;   // Valor del tipo
        }
        if ($buscar !== '') {   // Si se escribió algo en el buscador...
            $where[] = "(e.titulo LIKE :b OR e.descripcion LIKE :b OR CONCAT(c.nombres, ' ', c.apellidos) LIKE :b OR c.ci LIKE :b)";   // ...busca en título, descripción, nombre del chofer o su C.I.
            $params['b'] = "%$buscar%";   // Texto con % para buscar "que contenga"
        }
        if ($idChofer > 0) {   // Si se pide solo de un chofer...
            $where[] = 'e.id_chofer = :c';   // ...filtra por chofer
            $params['c'] = $idChofer;   // Valor del chofer
        }
        return $this->fetchAll(   // Consulta con el chofer y quién lo subió
            "SELECT e.*, CONCAT(c.nombres, ' ', c.apellidos) AS chofer, c.ci, u.nombre AS registrado_por
             FROM expediente e
             LEFT JOIN chofer c ON c.id_chofer = e.id_chofer
             LEFT JOIN usuario u ON u.id_usuario = e.id_usuario"   // (LEFT JOIN: las actas no tienen chofer)
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '') .   // Agrega los filtros si hay
            ' ORDER BY e.created_at DESC',   // Los más recientes primero
            $params   // Datos de la consulta preparada
        );
    }

    /** Documentos (no licencias) con fecha de vencimiento próxima o vencida. */
    public function porVencer(int $dias = DIAS_ALERTA_LICENCIA): array   // Documentos con fecha de vencimiento próxima o pasada
    {
        return $this->fetchAll(   // Consulta ordenada por fecha de vencimiento
            "SELECT e.*, CONCAT(c.nombres, ' ', c.apellidos) AS chofer,
                    DATEDIFF(e.fecha_vencimiento, CURDATE()) AS dias_restantes
             FROM expediente e LEFT JOIN chofer c ON c.id_chofer = e.id_chofer
             WHERE e.fecha_vencimiento IS NOT NULL
               AND e.fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
             ORDER BY e.fecha_vencimiento",
            [$dias]   // Días de anticipación
        );
    }

    public function total(): int   // Cuántos documentos hay en total
    {
        return (int) $this->fetchValue('SELECT COUNT(*) FROM expediente');   // Cuenta los registros de la tabla
    }
}
