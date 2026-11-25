<?php
/**
 * VISTA de los documentos PDF (FPDF 1.86): reportes y recibos.
 */
require_once ROOT_PATH . '/vista/lib/fpdf/fpdf.php';   // Carga la librería FPDF 1.86 (crea archivos PDF con PHP)

/**
 * Plantilla PDF institucional (FPDF 1.86): encabezado con logo y pie de página.
 * Todos los textos pasan por pdf_txt() para mostrar tildes y ñ (Windows-1252).
 */
class BasePdf extends FPDF   // Clase base de todos los PDF del sistema (hereda de FPDF)
{
    protected const AZUL  = [27, 58, 143];   // Color azul del sindicato (rojo, verde, azul)
    protected const ROJO  = [200, 30, 40];   // Color rojo
    protected const GRIS  = [240, 242, 247];   // Color gris para las filas

    public function __construct(protected string $titulo, protected string $subtitulo = '', string $orientacion = 'P', string|array $tamano = 'A4')   // Recibe el título, el subtítulo, la orientación (P vertical) y el tamaño (A4)
    {
        parent::__construct($orientacion, 'mm', $tamano);   // Crea el documento en milímetros
        $this->SetMargins(10, 10, 10);   // Márgenes de 10 mm
        $this->SetAutoPageBreak(true, 18);   // Salto de página automático a 18 mm del final
        $this->AliasNbPages();   // Permite escribir "Página 1 / {nb}" con el total de páginas
        $this->SetTitle(pdf_txt($titulo), false);   // Título del archivo PDF
        $this->SetAuthor(pdf_txt(APP_NAME), false);   // Autor del archivo
        $this->SetCreator('Sistema Web Villa Aroma', false);   // Programa que lo creó
    }

    public function Header(): void   // Encabezado: FPDF lo dibuja solo en cada página
    {
        $logo = ROOT_PATH . '/vista/img/logo.jpg';   // Ruta del logo
        if (is_file($logo)) {   // Si el logo existe...
            $this->Image($logo, 10, 8, 22);   // ...lo dibuja arriba a la izquierda (22 mm de ancho)
        }
        $this->SetXY(35, 9);   // Se ubica a la derecha del logo
        $this->SetFont('Helvetica', 'B', 12);   // Letra Helvetica negrita de 12 puntos
        $this->SetTextColor(...self::AZUL);   // Color azul
        $this->Cell(0, 6, pdf_txt('SINDICATO MIXTO DE TRANSPORTES "VILLA AROMA"'), 0, 2);   // Escribe el nombre del sindicato
        $this->SetFont('Helvetica', '', 8.5);   // Letra normal pequeña
        $this->SetTextColor(80, 80, 80);   // Color gris
        $this->Cell(0, 4.5, pdf_txt('Servicio Interprovincial e Interdepartamental — Lahuachaca, Prov. Aroma, La Paz'), 0, 2);   // Escribe el servicio y la ubicación
        $this->SetXY(35, $this->GetY() + 1.5);   // Baja un poco
        $this->SetFont('Helvetica', 'B', 11);   // Letra negrita de 11 puntos
        $this->SetTextColor(...self::ROJO);   // Color rojo
        $this->Cell(0, 6, pdf_txt(mb_strtoupper($this->titulo)), 0, 2);   // Escribe el título del reporte en mayúsculas
        if ($this->subtitulo !== '') {   // Si hay subtítulo (periodo o fecha de corte)...
            $this->SetFont('Helvetica', '', 9);   // ...letra normal
            $this->SetTextColor(60, 60, 60);   // ...color gris oscuro
            $this->Cell(0, 4.5, pdf_txt($this->subtitulo), 0, 2);   // ...lo escribe
        }
        $this->SetDrawColor(...self::AZUL);   // Color azul para la línea
        $this->SetLineWidth(0.6);   // Grosor de la línea
        $y = max($this->GetY() + 2, 33);   // Posición de la línea (debajo del encabezado)
        $this->Line(10, $y, $this->GetPageWidth() - 10, $y);   // Dibuja una línea horizontal de lado a lado
        $this->SetY($y + 4);   // Deja espacio debajo de la línea
        $this->SetTextColor(0, 0, 0);   // Vuelve al color negro
        $this->SetLineWidth(0.2);   // Vuelve al grosor normal
    }

    public function Footer(): void   // Pie de página: FPDF lo dibuja solo en cada página
    {
        $this->SetY(-14);   // Se ubica a 14 mm del final de la hoja
        $this->SetFont('Helvetica', 'I', 7.5);   // Letra cursiva pequeña
        $this->SetTextColor(120, 120, 120);   // Color gris
        $usuario = Auth::user()['nombre'] ?? '';   // Nombre del usuario que generó el reporte
        $ancho   = $this->GetPageWidth() - 20;   // Ancho útil de la hoja
        $this->Cell($ancho - 22, 4, pdf_txt('Generado el ' . date('d/m/Y H:i') . ($usuario ? " por $usuario" : '')), 0, 0, 'L');   // Escribe fecha, hora y usuario
        $this->Cell(22, 4, pdf_txt('Página ') . $this->PageNo() . ' / {nb}', 0, 1, 'R');   // Escribe "Página X / total" a la derecha
        $this->Cell($ancho, 4, pdf_txt('Documento de control interno del sindicato — no válido como factura.'), 0, 0, 'L');   // Aclara que es un documento interno (no es factura)
    }

    /** Recorta un texto para que quepa en el ancho indicado. */
    protected function ajustar(string $texto, float $ancho): string   // Recorta un texto que no entra en una celda y le pone "..."
    {
        $t = pdf_txt($texto);   // Convierte las tildes para FPDF
        if ($this->GetStringWidth($t) <= $ancho - 2) {   // Si entra en el ancho...
            return $t;   // ...lo devuelve igual
        }
        while ($t !== '' && $this->GetStringWidth($t . '...') > $ancho - 2) {   // Mientras no entre...
            $t = substr($t, 0, -1);   // ...le quita una letra al final
        }
        return $t . '...';   // Devuelve el texto recortado con "..."
    }

    public function salida(string $nombre): void   // Envía el PDF al navegador
    {
        $this->Output('I', $nombre);   // 'I' = se abre en el navegador (inline) con ese nombre
        exit;   // Termina la ejecución
    }
}

/**
 * Reporte tabular genérico (caja, estado de cuenta, morosidad) — RF09.
 *
 *   (new ReportePdf('Título', 'Subtítulo'))
 *       ->tabla(['Columna' => ancho_mm, ...], $filas, ['TOTAL', '123.00'])
 *       ->salida('archivo.pdf');
 */
class ReportePdf extends BasePdf   // PDF de reportes: una tabla con columnas, filas y total
{
    /**
     * @param array $columnas encabezado => ancho en mm
     * @param array $filas    arreglo de filas (valores en el mismo orden de columnas)
     * @param array $total    fila final: [etiqueta, valores...] alineados a la derecha
     */
    public function tabla(array $columnas, array $filas, array $total = []): self   // Dibuja la tabla del reporte y las firmas
    {
        $this->AddPage();   // Agrega una página
        $anchos = array_values($columnas);   // Anchos de las columnas en mm

        $encabezado = function () use ($columnas) {   // Función que dibuja la fila de títulos de la tabla
            $this->SetFont('Helvetica', 'B', 8.5);   // Letra negrita
            $this->SetFillColor(...self::AZUL);   // Fondo azul
            $this->SetTextColor(255, 255, 255);   // Letra blanca
            foreach ($columnas as $titulo => $ancho) {   // Para cada columna...
                $this->Cell($ancho, 7, pdf_txt($titulo), 0, 0, 'C', true);   // ...escribe su título centrado con fondo
            }
            $this->Ln();   // Salta a la siguiente línea
            $this->SetTextColor(0, 0, 0);   // Vuelve a letra negra
            $this->SetFont('Helvetica', '', 8.5);   // Vuelve a letra normal
        };
        $encabezado();   // Dibuja los títulos

        if (!$filas) {   // Si no hay filas...
            $this->SetFont('Helvetica', 'I', 9);   // ...letra cursiva
            $this->Cell(array_sum($anchos), 10, pdf_txt('Sin registros para los criterios seleccionados.'), 'B', 1, 'C');   // ...escribe "Sin registros"
        }

        foreach ($filas as $i => $fila) {   // Para cada fila de datos...
            if ($this->GetY() > $this->GetPageHeight() - 25) {   // Si se llega al final de la hoja...
                $this->AddPage();   // ...agrega otra página
                $encabezado();   // ...y repite los títulos
            }
            $this->SetFillColor(...self::GRIS);   // Color de fondo para las filas pares
            foreach (array_values($fila) as $j => $valor) {   // Para cada valor de la fila...
                $numerico = is_numeric(str_replace([',', '.'], '', (string) $valor)) && $j > 0;   // ¿Es un número? (se alinea a la derecha)
                $this->Cell($anchos[$j], 6, $this->ajustar((string) $valor, $anchos[$j]), 'B', 0, $numerico ? 'R' : 'L', $i % 2 === 1);   // Escribe el valor (recortado si no entra), con fondo intercalado
            }
            $this->Ln();   // Salta a la siguiente fila
        }

        if ($total) {   // Si hay fila de total...
            $etiqueta = array_shift($total);   // Primer valor: el texto (ej. TOTAL RECAUDADO)
            $nValores = count($total);   // Cuántos valores numéricos tiene el total
            $anchoEtiqueta = array_sum(array_slice($anchos, 0, count($anchos) - $nValores));   // Ancho que ocupa el texto del total
            $this->SetFont('Helvetica', 'B', 9);   // Letra negrita
            $this->SetFillColor(255, 243, 205);   // Fondo amarillo claro
            $this->Cell($anchoEtiqueta, 7, pdf_txt($etiqueta), 'TB', 0, 'R', true);   // Escribe el texto del total
            foreach ($total as $k => $valor) {   // Escribe cada valor del total...
                $this->Cell($anchos[count($anchos) - $nValores + $k], 7, pdf_txt((string) $valor), 'TB', 0, 'R', true);   // ...alineado a la derecha
            }
            $this->Ln(12);   // Deja espacio
        }

        // Firmas
        if ($this->GetY() > $this->GetPageHeight() - 45) {   // Si no queda espacio para las firmas...
            $this->AddPage();   // ...agrega otra página
        }
        $this->Ln(16);   // Espacio antes de las firmas
        $this->SetFont('Helvetica', '', 8.5);   // Letra normal
        $y = $this->GetY();   // Posición vertical de las líneas de firma
        $this->Line(25, $y, 85, $y);   // Línea de firma izquierda
        $this->Line(125, $y, 185, $y);   // Línea de firma derecha
        $this->SetXY(25, $y + 1);   // Se ubica debajo de la línea izquierda
        $this->Cell(60, 5, pdf_txt('Elaborado por'), 0, 0, 'C');   // Escribe "Elaborado por"
        $this->SetXY(125, $y + 1);   // Se ubica debajo de la línea derecha
        $this->Cell(60, 5, pdf_txt('Secretario de Hacienda'), 0, 0, 'C');   // Escribe "Secretario de Hacienda"
        return $this;   // Devuelve el PDF para llamar a salida()
    }
}

/**
 * Recibo digital de pago para control interno (límite: no es factura fiscal).
 */
class ReciboPdf extends BasePdf   // PDF del recibo de pago (tamaño A5)
{
    public function __construct()   // Se ejecuta al crear el recibo
    {
        parent::__construct('Recibo de pago', '', 'P', 'A5');   // Título "Recibo de pago", hoja A5 vertical
    }

    public function generar(array $pagos): void   // Dibuja el recibo con uno o varios pagos del mismo chofer
    {
        $p = $pagos[0];   // Datos del primer pago (el chofer es el mismo en todos)
        $this->subtitulo = 'N° ' . implode(', ', array_column($pagos, 'nro_recibo'));   // Subtítulo con los números de recibo
        $this->AddPage();   // Agrega la página
        $ancho = $this->GetPageWidth() - 20;   // Ancho útil de la hoja

        $this->SetFont('Helvetica', '', 9.5);   // Letra normal
        $datos = [   // Datos del encabezado del recibo:
            'Chofer'      => $p['chofer'],   // nombre del chofer
            'C.I.'        => $p['ci'] . ' ' . $p['ci_expedido'],   // C.I. con su departamento
            'Grupo'       => $p['grupo'],   // grupo
            'Credencial'  => $p['codigo_qr'],   // código de su credencial
            'Fecha pago'  => fecha($p['fecha_pago'], true),   // fecha y hora del pago
            'Atendido por'=> $p['cajero'] ?? '',   // cajero que lo atendió
        ];
        foreach ($datos as $k => $v) {   // Para cada dato...
            $this->SetFont('Helvetica', 'B', 9.5);   // ...etiqueta en negrita
            $this->Cell(30, 6, pdf_txt($k . ':'), 0, 0);   // ...escribe la etiqueta
            $this->SetFont('Helvetica', '', 9.5);   // ...valor en letra normal
            $this->Cell(0, 6, pdf_txt($v), 0, 1);   // ...escribe el valor
        }
        $this->Ln(3);   // Espacio

        $this->SetFont('Helvetica', 'B', 9);   // Letra negrita
        $this->SetFillColor(...self::AZUL);   // Fondo azul
        $this->SetTextColor(255, 255, 255);   // Letra blanca
        $this->Cell(22, 7, pdf_txt('Recibo'), 0, 0, 'C', true);   // Título de columna "Recibo"
        $this->Cell($ancho - 22 - 30, 7, pdf_txt('Concepto'), 0, 0, 'L', true);   // Título de columna "Concepto"
        $this->Cell(30, 7, pdf_txt('Monto (Bs)'), 0, 1, 'R', true);   // Título de columna "Monto"
        $this->SetTextColor(0, 0, 0);   // Vuelve a letra negra
        $this->SetFont('Helvetica', '', 9);   // Letra normal

        $total = 0;   // Total en cero
        foreach ($pagos as $pg) {   // Para cada pago...
            $total += (float) $pg['monto'];   // ...suma su monto
            $concepto = $pg['concepto'] . ' (' . $pg['tipo'] . ')';   // ...concepto y tipo de cuota
            $this->Cell(22, 6.5, pdf_txt($pg['nro_recibo']), 'B', 0, 'C');   // ...número de recibo
            $this->Cell($ancho - 22 - 30, 6.5, $this->ajustar($concepto, $ancho - 52), 'B', 0);   // ...concepto (recortado si no entra)
            $this->Cell(30, 6.5, num($pg['monto']), 'B', 1, 'R');   // ...monto
        }
        $this->SetFont('Helvetica', 'B', 10);   // Letra negrita
        $this->SetFillColor(255, 243, 205);   // Fondo amarillo claro
        $this->Cell($ancho - 30, 8, pdf_txt('TOTAL PAGADO'), 0, 0, 'R', true);   // Texto "TOTAL PAGADO"
        $this->Cell(30, 8, 'Bs ' . num($total), 0, 1, 'R', true);   // Total en bolivianos

        $this->Ln(18);   // Espacio para las firmas
        $y = $this->GetY();   // Posición de las líneas
        $this->Line(20, $y, 64, $y);   // Línea de firma izquierda
        $this->Line($this->GetPageWidth() - 64, $y, $this->GetPageWidth() - 20, $y);   // Línea de firma derecha
        $this->SetFont('Helvetica', '', 8);   // Letra pequeña
        $this->SetXY(20, $y + 1);   // Debajo de la línea izquierda
        $this->Cell(44, 4, pdf_txt('Recibí conforme'), 0, 0, 'C');   // "Recibí conforme" (cajero)
        $this->SetXY($this->GetPageWidth() - 64, $y + 1);   // Debajo de la línea derecha
        $this->Cell(44, 4, pdf_txt('Entregué conforme'), 0, 0, 'C');   // "Entregué conforme" (chofer)

        $this->salida('recibo_' . $p['nro_recibo'] . '.pdf');   // Envía el recibo al navegador
    }
}
