-- -------------------------------------------------------------
-- BASE DE DATOS: AUTOMATIZACIÓN DE COBRANZA Y CONTROL DE DESPACHOS
-- DISTRIBUIDORA DE AGUA MINERAL
-- Engine: InnoDB | Charset: utf8mb4
-- Compatible con MySQL / MariaDB via phpMyAdmin
-- -------------------------------------------------------------

CREATE DATABASE IF NOT EXISTS `distribuidora_agua` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `distribuidora_agua`;

-- 1. TABLA DE USUARIOS (SISTEMA DE AUTENTICACIÓN)
CREATE TABLE IF NOT EXISTS `usuarios` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `usuario` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `nombre` VARCHAR(100) NOT NULL,
    `fecha_registro` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. TABLA DE MARCAS DE AGUA Y PRECIOS DINÁMICOS
CREATE TABLE IF NOT EXISTS `marcas_agua` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre_marca` VARCHAR(100) NOT NULL UNIQUE,
    `codigo_identificador` VARCHAR(50) NOT NULL UNIQUE,
    `precio_usd` DECIMAL(10, 2) NOT NULL,
    `activo` BOOLEAN DEFAULT TRUE,
    `fecha_registro` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `fecha_actualizacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. TABLA DE FORMAS Y MÉTODOS DE PAGO
CREATE TABLE IF NOT EXISTS `formas_pago` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre_forma` VARCHAR(100) NOT NULL UNIQUE,
    `codigo_identificador` VARCHAR(50) NOT NULL UNIQUE,
    `requiere_referencia` BOOLEAN DEFAULT FALSE,
    `moneda_defecto` VARCHAR(10) DEFAULT 'USD',
    `activo` BOOLEAN DEFAULT TRUE,
    `fecha_registro` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. TABLA DE CLIENTES
CREATE TABLE IF NOT EXISTS `clientes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre_oficial` VARCHAR(150) NOT NULL,
    `nombre_despacho_alias` VARCHAR(150) NOT NULL UNIQUE,
    `telefono_whatsapp` VARCHAR(20) NOT NULL,
    `categoria` ENUM('domicilio', 'local', 'facturacion_legal') NOT NULL,
    `activo` BOOLEAN DEFAULT TRUE,
    `fecha_registro` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. TABLA DE HISTORIAL DE DESPACHOS
-- cliente_id es opcional: la ingesta guarda el despacho aunque no exista catálogo de clientes.
CREATE TABLE IF NOT EXISTS `despachos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `fecha` DATE NOT NULL,
    `cliente_id` INT NULL,
    `nombre_cliente_raw` VARCHAR(150) NULL,
    `alias_despacho_consolidado` VARCHAR(150) NULL,
    `despachador` VARCHAR(100) NOT NULL,
    `botellas_zenda` INT DEFAULT 0,
    `botellas_alpes` INT DEFAULT 0,
    `monto_despacho_usd` DECIMAL(10, 2) NOT NULL,
    `estado_pago` ENUM('pendiente', 'notificado', 'pagado_parcial', 'pagado') DEFAULT 'pendiente',
    `forma_pago_id` INT NULL,
    `referencia_pago` VARCHAR(100) NULL,
    `observaciones` TEXT NULL,
    `fecha_creacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_despachos_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_despachos_forma_pago` FOREIGN KEY (`forma_pago_id`) REFERENCES `formas_pago` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX `idx_despachos_fecha_despachador` (`fecha`, `despachador`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. TABLA DE SALDOS PENDIENTES (CARTERA Y ARRASTRE DE DEUDAS)
CREATE TABLE IF NOT EXISTS `saldos_pendientes` (
    `cliente_id` INT PRIMARY KEY,
    `botellas_pendientes_zenda` INT DEFAULT 0,
    `botellas_pendientes_alpes` INT DEFAULT 0,
    `monto_deuda_total_usd` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `ultimo_despacho_fecha` DATE NULL,
    `fecha_actualizacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_saldos_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. TABLA DE AUDITORÍA Y ALERTAS DE REVISIÓN HUMANA
CREATE TABLE IF NOT EXISTS `alertas_revision` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `fecha` DATE NOT NULL,
    `nombre_raw` VARCHAR(150) NOT NULL,
    `motivo` VARCHAR(100) NOT NULL,
    `datos_item` JSON NOT NULL,
    `resuelto` BOOLEAN DEFAULT FALSE,
    `fecha_creacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- INSERCIÓN DE DATOS SEMILLA (MOCK DATA)
-- -------------------------------------------------------------

-- Semilla para Usuarios (Usuario: admin / Contraseña: admin)
INSERT INTO `usuarios` (`id`, `usuario`, `password`, `nombre`) VALUES
(1, 'admin', '$2y$10$IaGnnEmPDqYfudh7Bt0X6eXtfviNZmAXXU2RC0I2gCUe1xqQCXTg6', 'Administrador General');

-- Semilla para Marcas de Agua
INSERT INTO `marcas_agua` (`id`, `nombre_marca`, `codigo_identificador`, `precio_usd`) VALUES
(1, 'La Zenda', 'zenda', 7.00),
(2, 'Los Alpes', 'alpes', 3.00);

-- Semilla para Formas de Pago
INSERT INTO `formas_pago` (`id`, `nombre_forma`, `codigo_identificador`, `requiere_referencia`, `moneda_defecto`) VALUES
(1, 'Pago Móvil', 'pago_movil', 1, 'BS'),
(2, 'Referencia / Transferencia Bancaria', 'transferencia', 1, 'BS'),
(3, 'Efectivo Dólares ($)', 'efectivo_usd', 0, 'USD'),
(4, 'Efectivo Bolívares Soberanos', 'efeditvo_bs', 0, 'BS');

-- Semilla para Clientes
INSERT INTO `clientes` (`id`, `nombre_oficial`, `nombre_despacho_alias`, `telefono_whatsapp`, `categoria`) VALUES
(1, 'Pastelería Chacao C.A.', 'PASTELERIA CHACAO', '+584121234567', 'local'),
(2, 'Residencias Tucurabua Apt 3-05', 'EDF TUCURABUA PISO 3 APT 3-05', '+584149876543', 'domicilio'),
(3, 'FISA C.A.', 'FISA', '+584121111111', 'facturacion_legal'),
(4, 'Abasto Las Joyas', 'ABASTO LAS JOYAS', '+584165554433', 'local'),
(5, 'Residencias Cosmo Apt 5-A', 'EDF COSMO PISO 5 APT 5-A', '+584127778899', 'domicilio'),
(6, 'Sayeco Repuestos', 'SAYECO REPUESTOS', '+584142223344', 'local');

-- Semilla para Saldos Pendientes iniciales (Simula deudas previas)
INSERT INTO `saldos_pendientes` (`cliente_id`, `botellas_pendientes_zenda`, `botellas_pendientes_alpes`, `monto_deuda_total_usd`, `ultimo_despacho_fecha`) VALUES
(1, 2, 1, 17.00, '2026-07-20'),
(2, 0, 4, 12.00, '2026-07-22'),
(3, 0, 0, 0.00, NULL),
(4, 1, 2, 13.00, '2026-07-24'),
(5, 0, 0, 0.00, NULL),
(6, 0, 0, 0.00, NULL);
