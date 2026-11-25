-- =====================================================================
--  Sistema Web para el Control de Choferes, Aportes y Documentación
--  mediante Tecnología QR — Sindicato Mixto de Transporte "Villa Aroma"
--  Base de datos MySQL / MariaDB (compatible con XAMPP 8.2 — MariaDB 10.4)
-- =====================================================================
--  Importar en phpMyAdmin: pestaña "Importar" > seleccionar este archivo.
-- =====================================================================

SET NAMES utf8mb4;

DROP DATABASE IF EXISTS sindicato_villa_aroma;
CREATE DATABASE sindicato_villa_aroma CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sindicato_villa_aroma;

-- ---------------------------------------------------------------------
-- Seguridad: usuarios del sistema (RF08)
-- ---------------------------------------------------------------------
CREATE TABLE usuario (
    id_usuario     INT AUTO_INCREMENT PRIMARY KEY,
    nombre         VARCHAR(100) NOT NULL,
    usuario        VARCHAR(50)  NOT NULL UNIQUE,
    password_hash  VARCHAR(255) NOT NULL,
    rol            ENUM('ventanilla','administrador') NOT NULL DEFAULT 'ventanilla',
    activo         TINYINT(1)   NOT NULL DEFAULT 1    -- el último acceso se obtiene de registro_acceso
) ENGINE=InnoDB;

CREATE TABLE registro_acceso (
    id_registro  INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario   INT          NULL,
    accion       VARCHAR(60)  NOT NULL,
    detalle      VARCHAR(255) NULL,
    ip           VARCHAR(45)  NULL,
    fecha        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_reg_fecha (fecha),
    CONSTRAINT fk_reg_usuario FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Grupos de transporte
-- ---------------------------------------------------------------------
CREATE TABLE grupo_transporte (
    id_grupo     INT AUTO_INCREMENT PRIMARY KEY,
    nombre       VARCHAR(80)  NOT NULL UNIQUE,
    ruta         VARCHAR(120) NULL,
    delegado     VARCHAR(100) NULL,
    activo       TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Choferes afiliados (RF01)
-- ---------------------------------------------------------------------
CREATE TABLE chofer (
    id_chofer         INT AUTO_INCREMENT PRIMARY KEY,
    codigo_qr         VARCHAR(50)  NOT NULL UNIQUE,
    ci                VARCHAR(11)  NOT NULL UNIQUE,      -- 4 a 8 dígitos + complemento opcional: 4876512 o 4876512-1A
    ci_expedido       ENUM('LP','CB','SC','OR','PT','CH','TJ','BE','PD') NOT NULL DEFAULT 'LP',
    nombres           VARCHAR(80)  NOT NULL,
    apellidos         VARCHAR(80)  NOT NULL,
    telefono          CHAR(8)      NULL,                  -- celular: 8 dígitos
    direccion         VARCHAR(150) NULL,
    foto              VARCHAR(255) NULL,                  -- ruta de la foto (storage/uploads/fotos), hoja de datos personales
    fecha_nacimiento  DATE         NULL,
    fecha_afiliacion  DATE         NOT NULL,
    id_grupo          INT          NOT NULL,
    estado            ENUM('activo','suspendido','inactivo') NOT NULL DEFAULT 'activo',
    INDEX idx_chofer_nombre (apellidos, nombres),
    CONSTRAINT fk_chofer_grupo FOREIGN KEY (id_grupo) REFERENCES grupo_transporte(id_grupo)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Licencias de conducir y expediente digital (RF05, RF06)
-- ---------------------------------------------------------------------
CREATE TABLE licencia (
    id_licencia        INT AUTO_INCREMENT PRIMARY KEY,
    id_chofer          INT          NOT NULL UNIQUE,   -- una licencia vigente por chofer
    numero             VARCHAR(11)  NOT NULL,             -- en Bolivia es el mismo número del C.I.
    categoria          ENUM('A','B','C','P','M','T') NOT NULL DEFAULT 'B',
    fecha_emision      DATE         NULL,
    fecha_vencimiento  DATE         NOT NULL,
    INDEX idx_lic_venc (fecha_vencimiento),
    CONSTRAINT fk_lic_chofer FOREIGN KEY (id_chofer) REFERENCES chofer(id_chofer) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE expediente (
    id_expediente      INT AUTO_INCREMENT PRIMARY KEY,
    id_chofer          INT          NULL,           -- NULL para actas institucionales
    tipo               ENUM('expediente','antecedente','acta','licencia','otro') NOT NULL,
    titulo             VARCHAR(150) NOT NULL,
    descripcion        VARCHAR(255) NULL,
    fecha_documento    DATE         NULL,
    fecha_vencimiento  DATE         NULL,
    ruta_archivo       VARCHAR(255) NOT NULL,
    nombre_original    VARCHAR(150) NOT NULL,      -- tipo y tamaño se obtienen del propio archivo
    id_usuario         INT          NULL,
    created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_exp_tipo (tipo),
    CONSTRAINT fk_exp_chofer  FOREIGN KEY (id_chofer)  REFERENCES chofer(id_chofer) ON DELETE CASCADE,
    CONSTRAINT fk_exp_usuario FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Cuotas por grupo y pagos (RF03, RF04)
-- ---------------------------------------------------------------------
CREATE TABLE cuota (
    id_cuota        INT AUTO_INCREMENT PRIMARY KEY,
    id_grupo        INT            NOT NULL,
    tipo            ENUM('ordinaria','extraordinaria') NOT NULL,
    concepto        VARCHAR(120)   NOT NULL,
    monto           DECIMAL(10,2)  NOT NULL,
    fecha_limite    DATE           NOT NULL,
    acta_referencia VARCHAR(120)   NULL,          -- asamblea que aprobó la cuota
    estado          ENUM('vigente','anulada') NOT NULL DEFAULT 'vigente',
    id_usuario      INT            NULL,
    INDEX idx_cuota_grupo (id_grupo, estado),
    CONSTRAINT fk_cuota_grupo   FOREIGN KEY (id_grupo)   REFERENCES grupo_transporte(id_grupo),
    CONSTRAINT fk_cuota_usuario FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Cada cuota genera una fila "pendiente" por chofer del grupo (el monto a pagar es el de la cuota).
-- El cobro cambia la fila a "pagado" y guarda el monto cobrado: la clave única impide pagos duplicados.
-- El N° de recibo no se guarda: es el id del pago con formato R-000123.
CREATE TABLE pago_cuota (
    id_pago      INT AUTO_INCREMENT PRIMARY KEY,
    id_cuota     INT            NOT NULL,
    id_chofer    INT            NOT NULL,
    estado       ENUM('pendiente','pagado') NOT NULL DEFAULT 'pendiente',
    monto_pagado DECIMAL(10,2)  NULL,          -- se llena al cobrar (conserva el monto aunque la cuota cambie)
    fecha_pago   DATETIME       NULL,
    id_usuario   INT            NULL,
    UNIQUE KEY uq_pago_cuota_chofer (id_cuota, id_chofer),
    INDEX idx_pago_estado (estado, fecha_pago),
    CONSTRAINT fk_pago_cuota   FOREIGN KEY (id_cuota)   REFERENCES cuota(id_cuota) ON DELETE CASCADE,
    CONSTRAINT fk_pago_chofer  FOREIGN KEY (id_chofer)  REFERENCES chofer(id_chofer) ON DELETE CASCADE,
    CONSTRAINT fk_pago_usuario FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================================
--  BASE DE DATOS VACÍA: solo los dos usuarios para ingresar al sistema
-- =====================================================================
-- Contraseñas:  admin / Admin2026*   ventanilla / Ventanilla2026*
-- (cámbielas desde Administración → Usuarios después del primer ingreso)
INSERT INTO usuario (nombre, usuario, password_hash, rol) VALUES
('Administrador del sistema', 'admin',      '$2y$12$0RIl5KD7q.3nZ4B8gohsquPiKrokmVC1zv/ARU3xc7xoqaCWC.owW', 'administrador'),
('Secretaria de Ventanilla',  'ventanilla', '$2y$12$ujq2NHpS/wmiGw6JSwFZDuICpxg2Drvv07Eg0zs35yUmxQIyCZeRa', 'ventanilla');

-- Primeros pasos: 1) Grupos → registrar los grupos de transporte  2) Choferes → Nuevo
